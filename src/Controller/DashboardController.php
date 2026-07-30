<?php

namespace App\Controller;

use App\Entity\Sale;
use App\Entity\User;
use App\Repository\CashSessionRepository;
use App\Repository\CustomerRepository;
use App\Repository\ProductRepository;
use App\Repository\SaleRepository;
use App\Repository\ShopContractRepository;
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
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->isAdmin()) {
            return $this->redirectToRoute('admin_dashboard');
        }

        $shop = $shopContext->getCurrentShop($user);
        if (!$shop) {
            $accessible = $shopContext->getAccessibleShops($user);
            if ($accessible === [] || !$shopPermission->can($user, ShopPermission::SHOPS)) {
                return $this->render('dashboard/no_shop.html.twig', [
                    'user' => $user,
                ]);
            }

            return $this->redirectToRoute('app_shop_index');
        }

        $todayStart = new \DateTimeImmutable('today');
        $monthStart = new \DateTimeImmutable('first day of this month midnight');

        $todaySales = $sales->createQueryBuilder('s')
            ->select('COALESCE(SUM(s.total), 0)')
            ->andWhere('s.shop = :shop')->andWhere('s.soldAt >= :from')
            ->setParameter('shop', $shop)->setParameter('from', $todayStart)
            ->getQuery()->getSingleScalarResult();

        $monthSales = $sales->createQueryBuilder('s')
            ->select('COALESCE(SUM(s.total), 0)')
            ->andWhere('s.shop = :shop')->andWhere('s.soldAt >= :from')
            ->setParameter('shop', $shop)->setParameter('from', $monthStart)
            ->getQuery()->getSingleScalarResult();

        $salesCount = $sales->count(['shop' => $shop]);
        $productCount = $products->count(['shop' => $shop, 'isActive' => true]);
        $customerCount = $customers->count(['shop' => $shop]);
        $lowStock = $stockService->getLowStockProducts($shop);

        $topProducts = $sales->createQueryBuilder('s')
            ->select('p.name AS name, SUM(si.quantity) AS qty')
            ->join('s.items', 'si')
            ->join('si.product', 'p')
            ->andWhere('s.shop = :shop')
            ->setParameter('shop', $shop)
            ->groupBy('p.id')
            ->orderBy('qty', 'DESC')
            ->setMaxResults(5)
            ->getQuery()->getResult();

        $salesByDay = [];
        for ($i = 6; $i >= 0; --$i) {
            $day = (new \DateTimeImmutable('today'))->modify("-{$i} days");
            $next = $day->modify('+1 day');
            $amount = $sales->createQueryBuilder('s')
                ->select('COALESCE(SUM(s.total), 0)')
                ->andWhere('s.shop = :shop')
                ->andWhere('s.soldAt >= :from AND s.soldAt < :to')
                ->setParameter('shop', $shop)
                ->setParameter('from', $day)
                ->setParameter('to', $next)
                ->getQuery()->getSingleScalarResult();
            $salesByDay[] = ['label' => $day->format('d/m'), 'value' => (float) $amount];
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
        if (\count($lowStock) > 0) {
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
                'text' => 'Aucune de caisse non ouverte',
                'href' => 'app_cash_index',
            ];
        }
        if ($user->isMerchant()) {
            $sub = $user->getMerchant()?->getSubscription();
            if ($sub && !$sub->isActive()) {
                $alerts[] = [
                    'type' => 'danger',
                    'text' => 'Abonnement inactif ou expiré',
                    'href' => 'app_dashboard',
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
            $onboarding = [
                'hasShop' => true,
                'hasProducts' => $productCount > 0,
                'hasSale' => $sales->count(['shop' => $shop, 'status' => Sale::STATUS_COMPLETED]) > 0,
                'taxReady' => (bool) ($fiscalSummary['taxConfig']['enabled'] ?? false) || (bool) $shop->getMerchant()?->getTaxId(),
                'hasStaff' => false,
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

    #[Route('/shop/switch/{id}', name: 'app_shop_switch')]
    public function switchShop(int $id, ShopContext $shopContext): Response
    {
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