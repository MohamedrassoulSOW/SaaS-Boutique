<?php

namespace App\Controller;

use App\Entity\CashSession;
use App\Entity\Sale;
use App\Repository\CashSessionRepository;
use App\Repository\SaleRepository;
use App\Service\ActivityLogger;
use App\Service\ShopContext;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/caisse')]
#[IsGranted('MODULE_CASH')]
class CashSessionController extends ShopAwareController
{
    #[Route('', name: 'app_cash_index')]
    public function index(
        ShopContext $shopContext,
        CashSessionRepository $sessions,
        SaleRepository $sales,
    ): Response {
        $shop = $this->requireShop($shopContext);
        $open = $sessions->findOpenForShop($shop);
        $cashToday = 0.0;
        if ($open) {
            $cashToday = (float) $sales->createQueryBuilder('s')
                ->select('COALESCE(SUM(s.amountPaid), 0)')
                ->andWhere('s.shop = :shop')
                ->andWhere('s.status = :status')
                ->andWhere('s.paymentMethod = :cash')
                ->andWhere('s.soldAt >= :from')
                ->setParameter('shop', $shop)
                ->setParameter('status', Sale::STATUS_COMPLETED)
                ->setParameter('cash', Sale::PAYMENT_CASH)
                ->setParameter('from', $open->getOpenedAt())
                ->getQuery()->getSingleScalarResult();
        }

        return $this->render('cash/index.html.twig', [
            'shop' => $shop,
            'openSession' => $open,
            'sessions' => $sessions->findRecentForShop($shop),
            'cashSinceOpen' => $cashToday,
            'expectedCash' => $open ? (float) $open->getOpeningFloat() + $cashToday : 0.0,
        ]);
    }

    #[Route('/ouvrir', name: 'app_cash_open', methods: ['POST'])]
    public function open(
        Request $request,
        ShopContext $shopContext,
        CashSessionRepository $sessions,
        EntityManagerInterface $em,
        ActivityLogger $logger,
        #[Autowire(service: 'limiter.financial_operations')]
        RateLimiterFactory $rateLimiter,
    ): Response {
        $shop = $this->requireShop($shopContext);
        if (!$this->isCsrfTokenValid('cash_open', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée.');

            return $this->redirectToRoute('app_cash_index');
        }
        $limiterKey = 'cash_open_' . ($this->getShopUser()?->getId() ?? 'anon');
        if (!$rateLimiter->create($limiterKey)->consume(1)->isAccepted()) {
            $this->addFlash('danger', 'Trop de requêtes. Réessayez dans une minute.');

            return $this->redirectToRoute('app_cash_index');
        }
        if ($sessions->findOpenForShop($shop)) {
            $this->addFlash('warning', 'Une session de caisse est déjà ouverte.');

            return $this->redirectToRoute('app_cash_index');
        }

        $float = max(0, (float) $request->request->get('opening_float', 0));
        $session = new CashSession();
        $session->setShop($shop);
        $session->setOpenedBy($this->getShopUser());
        $session->setOpeningFloat(number_format($float, 2, '.', ''));
        $session->setStatus(CashSession::STATUS_OPEN);
        $em->persist($session);
        try {
            $em->flush();
        } catch (UniqueConstraintViolationException) {
            $this->addFlash('warning', 'Une session de caisse est déjà ouverte.');

            return $this->redirectToRoute('app_cash_index');
        }
        $logger->log('cash.open', 'Ouverture caisse fond ' . $session->getOpeningFloat(), $this->getShopUser(), $shop);
        $this->addFlash('success', 'Caisse ouverte.');

        return $this->redirectToRoute('app_cash_index');
    }

    #[Route('/{id}/fermer', name: 'app_cash_close', methods: ['POST'])]
    public function close(
        CashSession $session,
        Request $request,
        ShopContext $shopContext,
        SaleRepository $sales,
        EntityManagerInterface $em,
        ActivityLogger $logger,
        #[Autowire(service: 'limiter.financial_operations')]
        RateLimiterFactory $rateLimiter,
    ): Response {
        $shop = $this->requireShop($shopContext);
        $this->assertShopData($shopContext, $session->getShop());
        if (!$this->isCsrfTokenValid('cash_close'.$session->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée.');

            return $this->redirectToRoute('app_cash_index');
        }
        $limiterKey = 'cash_close_' . ($this->getShopUser()?->getId() ?? 'anon');
        if (!$rateLimiter->create($limiterKey)->consume(1)->isAccepted()) {
            $this->addFlash('danger', 'Trop de requêtes. Réessayez dans une minute.');

            return $this->redirectToRoute('app_cash_index');
        }
        if (!$session->isOpen()) {
            $this->addFlash('warning', 'Cette session est déjà fermée.');

            return $this->redirectToRoute('app_cash_index');
        }

        $cashSales = (float) $sales->createQueryBuilder('s')
            ->select('COALESCE(SUM(s.amountPaid), 0)')
            ->andWhere('s.shop = :shop')
            ->andWhere('s.status = :status')
            ->andWhere('s.paymentMethod = :cash')
            ->andWhere('s.soldAt >= :from')
            ->setParameter('shop', $shop)
            ->setParameter('status', Sale::STATUS_COMPLETED)
            ->setParameter('cash', Sale::PAYMENT_CASH)
            ->setParameter('from', $session->getOpenedAt())
            ->getQuery()->getSingleScalarResult();

        $expected = (float) $session->getOpeningFloat() + $cashSales;
        $counted = max(0, (float) $request->request->get('closing_counted', 0));
        $diff = $counted - $expected;

        $session->setExpectedCash(number_format($expected, 2, '.', ''));
        $session->setClosingCounted(number_format($counted, 2, '.', ''));
        $session->setDifference(number_format($diff, 2, '.', ''));
        $session->setNotes(strip_tags($request->request->getString('notes') ?: ''));
        $session->setClosedBy($this->getShopUser());
        $session->setClosedAt(new \DateTimeImmutable());
        $session->setStatus(CashSession::STATUS_CLOSED);
        $em->flush();

        $logger->log(
            'cash.close',
            sprintf('Fermeture caisse écart %s', $session->getDifference()),
            $this->getShopUser(),
            $shop
        );
        $this->addFlash(
            abs($diff) < 0.01 ? 'success' : 'warning',
            abs($diff) < 0.01
                ? 'Caisse fermée — aucun écart.'
                : sprintf('Caisse fermée — écart : %s FCFA', number_format($diff, 0, ',', ' '))
        );

        return $this->redirectToRoute('app_cash_index');
    }
}
