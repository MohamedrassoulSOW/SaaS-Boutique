<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\CustomerRepository;
use App\Repository\ProductRepository;
use App\Repository\SaleRepository;
use App\Repository\ShopContractRepository;
use App\Security\ShopPermission;
use App\Service\FiscalService;
use App\Service\ShopContext;
use App\Service\StockService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->isAdmin()) {
            return $this->redirectToRoute('admin_dashboard');
        }

        $shop = $shopContext->getCurrentShop($user);
        if (!$shop) {
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
        ]);
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