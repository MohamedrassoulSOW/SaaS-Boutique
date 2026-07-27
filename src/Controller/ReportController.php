<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\ProductRepository;
use App\Repository\PurchaseOrderRepository;
use App\Repository\SaleRepository;
use App\Service\ShopContext;
use App\Service\StockService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reports')]
#[IsGranted('ROLE_USER')]
class ReportController extends AbstractController
{
    #[Route('', name: 'app_report_index')]
    public function index(
        ShopContext $shopContext,
        SaleRepository $sales,
        PurchaseOrderRepository $purchases,
        ProductRepository $products,
        StockService $stockService,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $shopContext->getCurrentShop($user);
        if (!$shop) {
            return $this->redirectToRoute('app_shop_index');
        }

        $today = new \DateTimeImmutable('today');
        $monthStart = new \DateTimeImmutable('first day of this month midnight');

        $daily = (float) $sales->createQueryBuilder('s')
            ->select('COALESCE(SUM(s.total), 0)')
            ->andWhere('s.shop = :shop')->andWhere('s.soldAt >= :from')
            ->setParameter('shop', $shop)->setParameter('from', $today)
            ->getQuery()->getSingleScalarResult();

        $monthly = (float) $sales->createQueryBuilder('s')
            ->select('COALESCE(SUM(s.total), 0)')
            ->andWhere('s.shop = :shop')->andWhere('s.soldAt >= :from')
            ->setParameter('shop', $shop)->setParameter('from', $monthStart)
            ->getQuery()->getSingleScalarResult();

        $purchaseTotal = (float) $purchases->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.total), 0)')
            ->andWhere('p.shop = :shop')->andWhere('p.createdAt >= :from')
            ->setParameter('shop', $shop)->setParameter('from', $monthStart)
            ->getQuery()->getSingleScalarResult();

        $soldProducts = $sales->createQueryBuilder('s')
            ->select('p.name AS name, SUM(si.quantity) AS qty, SUM(si.quantity * si.unitPrice) AS amount')
            ->join('s.items', 'si')->join('si.product', 'p')
            ->andWhere('s.shop = :shop')->andWhere('s.soldAt >= :from')
            ->setParameter('shop', $shop)->setParameter('from', $monthStart)
            ->groupBy('p.id')->orderBy('qty', 'DESC')->setMaxResults(20)
            ->getQuery()->getResult();

        $cogs = 0.0;
        foreach ($soldProducts as $row) {
            // Approximate COGS from current purchase prices is imperfect; use sale amount for profit display with purchase month total
        }

        $profit = $monthly - $purchaseTotal;

        return $this->render('report/index.html.twig', [
            'shop' => $shop,
            'daily' => $daily,
            'monthly' => $monthly,
            'purchaseTotal' => $purchaseTotal,
            'profit' => $profit,
            'soldProducts' => $soldProducts,
            'lowStock' => $stockService->getLowStockProducts($shop),
            'productCount' => $products->count(['shop' => $shop]),
        ]);
    }
}
