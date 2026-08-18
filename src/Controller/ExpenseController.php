<?php

namespace App\Controller;

use App\Entity\Expense;
use App\Repository\ExpenseRepository;
use App\Service\ActivityLogger;
use App\Service\ShopContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/depenses')]
#[IsGranted('MODULE_EXPENSES')]
class ExpenseController extends ShopAwareController
{
    #[Route('', name: 'app_expense_index')]
    public function index(Request $request, ShopContext $shopContext, ExpenseRepository $expenses): Response
    {
        $shop = $this->requireShop($shopContext);
        $monthStart = new \DateTimeImmutable('first day of this month midnight');
        $tomorrow = new \DateTimeImmutable('tomorrow');
        $fromRaw = (string) $request->query->get('from', $monthStart->format('Y-m-d'));
        $toRaw = (string) $request->query->get('to', (new \DateTimeImmutable('today'))->format('Y-m-d'));

        try {
            $from = new \DateTimeImmutable($fromRaw.' 00:00:00');
        } catch (\Exception) {
            $from = $monthStart;
        }
        try {
            $toInclusive = new \DateTimeImmutable($toRaw.' 00:00:00');
        } catch (\Exception) {
            $toInclusive = new \DateTimeImmutable('today');
        }
        $to = $toInclusive->modify('+1 day');
        if ($to > $tomorrow->modify('+1 year')) {
            $to = $tomorrow;
            $toInclusive = new \DateTimeImmutable('today');
        }

        $rows = $expenses->findForShop($shop, $from, $to);
        $total = $expenses->sumForShop($shop, $from, $to);

        return $this->render('expense/index.html.twig', [
            'shop' => $shop,
            'expenses' => $rows,
            'total' => $total,
            'from' => $from,
            'to' => $toInclusive,
            'categories' => Expense::CATEGORIES,
        ]);
    }

    #[Route('/nouveau', name: 'app_expense_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        ShopContext $shopContext,
        EntityManagerInterface $em,
        ActivityLogger $logger,
    ): Response {
        $shop = $this->requireShop($shopContext);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('expense_new', (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Session expirée.');

                return $this->redirectToRoute('app_expense_new');
            }

            $amount = (float) $request->request->get('amount', 0);
            $label = strip_tags(trim((string) $request->request->get('label', '')));
            $category = (string) $request->request->get('category', 'autres');
            if ($amount <= 0 || $label === '') {
                $this->addFlash('warning', 'Libellé et montant obligatoires.');

                return $this->redirectToRoute('app_expense_new');
            }
            if (!isset(Expense::CATEGORIES[$category])) {
                $category = 'autres';
            }

            $spentRaw = (string) $request->request->get('spent_at', date('Y-m-d'));
            try {
                $spentAt = new \DateTimeImmutable($spentRaw.' 12:00:00');
            } catch (\Exception) {
                $spentAt = new \DateTimeImmutable();
            }

            $expense = new Expense();
            $expense->setShop($shop);
            $expense->setRecordedBy($this->getShopUser());
            $expense->setLabel($label);
            $expense->setCategory($category);
            $expense->setAmount(number_format($amount, 2, '.', ''));
            $expense->setSpentAt($spentAt);
            $expense->setNote(strip_tags(trim((string) $request->request->get('note', ''))) ?: null);

            $recentDup = $em->getRepository(Expense::class)->createQueryBuilder('e')
                ->andWhere('e.shop = :shop')
                ->andWhere('e.label = :label')
                ->andWhere('e.amount = :amount')
                ->andWhere('e.spentAt >= :recent')
                ->setParameter('shop', $shop)
                ->setParameter('label', $label)
                ->setParameter('amount', number_format($amount, 2, '.', ''))
                ->setParameter('recent', (new \DateTimeImmutable('-1 minute')))
                ->getQuery()
                ->getOneOrNullResult();
            if ($recentDup) {
                $this->addFlash('warning', 'Dépense similaire enregistrée il y a moins d\'une minute.');
                return $this->redirectToRoute('app_expense_index');
            }

            $em->persist($expense);
            $em->flush();
            $logger->log('expense.create', 'Dépense : '.$label, $this->getShopUser(), $shop);
            $this->addFlash('success', 'Dépense enregistrée.');

            return $this->redirectToRoute('app_expense_index');
        }

        return $this->render('expense/new.html.twig', [
            'categories' => Expense::CATEGORIES,
        ]);
    }
}
