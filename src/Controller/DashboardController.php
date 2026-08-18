<?php

namespace App\Controller;

use App\Entity\Sale;
use App\Entity\User;
use App\Repository\CashSessionRepository;
use App\Repository\CustomerRepository;
use App\Repository\ProductRepository;
use App\Repository\SaleRepository;
use App\Repository\ShopContractRepository;
use App\Repository\ShopMemberRepository;
use App\Security\ShopPermission;
use App\Service\FiscalService;
use App\Service\ShopContext;
use App\Service\StockService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(
        ShopContext $shopContext,
        SaleRepository $sales,
        ProductRepository $products,
        CustomerRepository $customers,
        StockService $stockService,
        ShopContractRepository $contractRepo,
        FiscalService $fiscalService,
        ShopPermission $shopPermission,
        CashSessionRepository $cashSessions,
        ShopMemberRepository $members,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->isAdmin()) {
            return $this->redirectToRoute('admin_dashboard');
        }

        $shop = $shopContext->getCurrentShop($user);
        if (!$shop) {
            return $this->render('dashboard/no_shop.html.twig', [
                'user' => $user,
            ]);
        }

        $todayStart = new \DateTimeImmutable('today');
        $monthStart = new \DateTimeImmutable('first day of this month midnight');
        $completed = Sale::STATUS_COMPLETED;

        $todaySales = $sales->createQueryBuilder('s')
            ->select('COALESCE(SUM(s.total), 0)')
            ->andWhere('s.shop = :shop')
            ->andWhere('s.status = :status')
            ->andWhere('s.soldAt >= :from')
            ->setParameter('shop', $shop)
            ->setParameter('status', $completed)
            ->setParameter('from', $todayStart)
            ->getQuery()->getSingleScalarResult();

        $monthSales = $sales->createQueryBuilder('s')
            ->select('COALESCE(SUM(s.total), 0)')
            ->andWhere('s.shop = :shop')
            ->andWhere('s.status = :status')
            ->andWhere('s.soldAt >= :from')
            ->setParameter('shop', $shop)
            ->setParameter('status', $completed)
            ->setParameter('from', $monthStart)
            ->getQuery()->getSingleScalarResult();

        $salesCount = $sales->count(['shop' => $shop, 'status' => $completed]);
        $productCount = $products->count(['shop' => $shop, 'isActive' => true]);
        $customerCount = $customers->count(['shop' => $shop]);
        $canStock = $shopPermission->can($user, ShopPermission::STOCK, $shop);
        $lowStock = $canStock ? $stockService->getLowStockProducts($shop) : [];

        $topProducts = $sales->createQueryBuilder('s')
            ->select('p.name AS name, SUM(si.quantity) AS qty')
            ->join('s.items', 'si')
            ->join('si.product', 'p')
            ->andWhere('s.shop = :shop')
            ->andWhere('s.status = :status')
            ->setParameter('shop', $shop)
            ->setParameter('status', $completed)
            ->groupBy('p.id')
            ->orderBy('qty', 'DESC')
            ->setMaxResults(5)
            ->getQuery()->getResult();

        $weekStart = (new \DateTimeImmutable('today'))->modify('-6 days');
        $weekEnd = (new \DateTimeImmutable('today'))->modify('+1 day');
        $rawWeek = $sales->createQueryBuilder('s')
            ->select('SUBSTRING(s.soldAt, 1, 10) AS dayKey, COALESCE(SUM(s.total), 0) AS amount')
            ->andWhere('s.shop = :shop')
            ->andWhere('s.status = :status')
            ->andWhere('s.soldAt >= :from AND s.soldAt < :to')
            ->setParameter('shop', $shop)
            ->setParameter('status', $completed)
            ->setParameter('from', $weekStart)
            ->setParameter('to', $weekEnd)
            ->groupBy('dayKey')
            ->getQuery()->getResult();
        $weekMap = [];
        foreach ($rawWeek as $row) {
            $weekMap[(string) $row['dayKey']] = (float) $row['amount'];
        }
        $salesByDay = [];
        for ($i = 6; $i >= 0; --$i) {
            $day = (new \DateTimeImmutable('today'))->modify("-{$i} days");
            $key = $day->format('Y-m-d');
            $salesByDay[] = ['label' => $day->format('d/m'), 'value' => $weekMap[$key] ?? 0.0];
        }

        $merchantContracts = [];
        if ($user->isMerchant() && $user->getMerchant()) {
            $merchantContracts = $contractRepo->findVisibleForMerchant($user->getMerchant());
        }

        $fiscalSummary = null;
        if ($shopPermission->can($user, ShopPermission::FISCAL, $shop)) {
            $monthEnd = $monthStart->modify('+1 month');
            $taxMonth = $sales->summarizeTaxForShop($shop, $monthStart, $monthEnd);
            $fiscalSummary = [
                'taxConfig' => $fiscalService->resolveShopTax($shop),
                'monthTax' => $taxMonth['tax'],
                'monthNet' => $taxMonth['net'],
                'monthGross' => $taxMonth['gross'],
                'taxId' => $shop->getMerchant()?->getTaxId(),
            ];
        }

        $alerts = [];
        if ($canStock && \count($lowStock) > 0) {
            $alerts[] = [
                'type' => 'warning',
                'text' => sprintf('%d produit(s) en stock faible', \count($lowStock)),
                'href' => 'app_stock_index',
            ];
        }
        if ($shopPermission->can($user, ShopPermission::CUSTOMERS, $shop)) {
            $debtors = $customers->findWithDebt($shop);
            if ($debtors !== []) {
                $alerts[] = [
                    'type' => 'warning',
                    'text' => sprintf('%d client(s) avec solde dû', \count($debtors)),
                    'href' => 'app_customer_debts',
                ];
            }
        }
        if ($shopPermission->can($user, ShopPermission::CASH, $shop) && !$cashSessions->findOpenForShop($shop)) {
            $alerts[] = [
                'type' => 'info',
                'text' => 'Session de caisse non ouverte',
                'href' => 'app_cash_index',
            ];
        }
        if ($user->isMerchant()) {
            $sub = $user->getMerchant()?->getSubscription();
            if ($sub && !$sub->isActive()) {
                $alerts[] = [
                    'type' => 'danger',
                    'text' => 'Abonnement inactif ou expiré',
                    'href' => 'app_contact',
                ];
            }
            $unsigned = array_filter(
                $merchantContracts,
                static fn ($c) => method_exists($c, 'getMerchantSignedAt') && $c->getMerchantSignedAt() === null
            );
            if ($unsigned !== []) {
                $alerts[] = [
                    'type' => 'info',
                    'text' => 'Contrat(s) en attente de signature',
                    'href' => 'app_dashboard',
                    'anchor' => 'contrats',
                ];
            }
        }
        if ($fiscalSummary && !$fiscalSummary['taxConfig']['enabled']) {
            $alerts[] = [
                'type' => 'info',
                'text' => 'TVA non activée sur les ventes',
                'href' => 'app_fiscal_index',
            ];
        }

        $onboarding = null;
        if ($user->isMerchant() && !$user->hasCompletedOnboarding()) {
            $staffCount = \count($members->findByShop($shop));
            $onboarding = [
                'hasShop' => true,
                'hasProducts' => $productCount > 0,
                'hasSale' => $salesCount > 0,
                'taxReady' => (bool) ($fiscalSummary['taxConfig']['enabled'] ?? false) || (bool) $shop->getMerchant()?->getTaxId(),
                'hasStaff' => $staffCount > 0,
            ];
        }

        return $this->render('dashboard/index.html.twig', [
            'shop' => $shop,
            'contract' => $shop->getContract(),
            'merchantContracts' => $merchantContracts,
            'todaySales' => $todaySales,
            'monthSales' => $monthSales,
            'salesCount' => $salesCount,
            'productCount' => $productCount,
            'customerCount' => $customerCount,
            'lowStock' => $lowStock,
            'canStock' => $canStock,
            'topProducts' => $topProducts,
            'salesByDay' => $salesByDay,
            'fiscalSummary' => $fiscalSummary,
            'alerts' => $alerts,
            'onboarding' => $onboarding,
        ]);
    }

    #[Route('/onboarding/terminer', name: 'app_onboarding_complete', methods: ['POST'])]
    public function completeOnboarding(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$this->isCsrfTokenValid('onboarding_complete', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée.');

            return $this->redirectToRoute('app_dashboard');
        }
        $user->setOnboardingCompletedAt(new \DateTimeImmutable());
        $em->flush();
        $this->addFlash('success', 'Checklist masquée. Vous pourrez tout retrouver dans le menu.');

        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/shop/switch/{id}', name: 'app_shop_switch', methods: ['POST'])]
    public function switchShop(int $id, Request $request, ShopContext $shopContext): Response
    {
        if (!$this->isCsrfTokenValid('shop_switch', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_dashboard');
        }

        /** @var User $user */
        $user = $this->getUser();
        $target = null;
        foreach ($shopContext->getAccessibleShops($user) as $shop) {
            if ($shop->getId() === $id) {
                $target = $shop;
                break;
            }
        }

        if (!$target) {
            $this->addFlash('danger', 'Entreprise inaccessible.');

            return $this->redirectToRoute('app_dashboard');
        }

        $shopContext->setCurrentShop($target, $user);
        $this->addFlash('success', 'Entreprise active : '.$target->getName());

        return $this->redirectToRoute('app_dashboard');
    }
}
