<?php

namespace App\Controller;

use App\Entity\Sale;
use App\Entity\User;
use App\Repository\CustomerRepository;
use App\Repository\ProductRepository;
use App\Repository\PurchaseOrderRepository;
use App\Repository\SaleRepository;
use App\Service\ShopContext;
use App\Service\StockService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reports')]
#[IsGranted('MODULE_REPORTS')]
class ReportController extends ShopAwareController
{
    #[Route('', name: 'app_report_index')]
    public function index(
        ShopContext $shopContext,
        SaleRepository $sales,
        PurchaseOrderRepository $purchases,
        ProductRepository $products,
        StockService $stockService,
        CustomerRepository $customers,
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
            ->andWhere('s.status = :status')
            ->setParameter('shop', $shop)->setParameter('from', $today)
            ->setParameter('status', Sale::STATUS_COMPLETED)
            ->getQuery()->getSingleScalarResult();

        $monthly = (float) $sales->createQueryBuilder('s')
            ->select('COALESCE(SUM(s.total), 0)')
            ->andWhere('s.shop = :shop')->andWhere('s.soldAt >= :from')
            ->andWhere('s.status = :status')
            ->setParameter('shop', $shop)->setParameter('from', $monthStart)
            ->setParameter('status', Sale::STATUS_COMPLETED)
            ->getQuery()->getSingleScalarResult();

        $monthTax = (float) $sales->createQueryBuilder('s')
            ->select('COALESCE(SUM(s.taxAmount), 0)')
            ->andWhere('s.shop = :shop')->andWhere('s.soldAt >= :from')
            ->andWhere('s.status = :status')
            ->setParameter('shop', $shop)->setParameter('from', $monthStart)
            ->setParameter('status', Sale::STATUS_COMPLETED)
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
            ->andWhere('s.status = :status')
            ->setParameter('shop', $shop)->setParameter('from', $monthStart)
            ->setParameter('status', Sale::STATUS_COMPLETED)
            ->groupBy('p.id')->orderBy('qty', 'DESC')->setMaxResults(20)
            ->getQuery()->getResult();

        $profit = $monthly - $purchaseTotal;
        $debtors = $customers->findWithDebt($shop);
        $debtTotal = array_sum(array_map(static fn ($c) => (float) $c->getBalance(), $debtors));

        return $this->render('report/index.html.twig', [
            'shop' => $shop,
            'daily' => $daily,
            'monthly' => $monthly,
            'monthTax' => $monthTax,
            'purchaseTotal' => $purchaseTotal,
            'profit' => $profit,
            'soldProducts' => $soldProducts,
            'lowStock' => $stockService->getLowStockProducts($shop),
            'productCount' => $products->count(['shop' => $shop]),
            'debtTotal' => $debtTotal,
            'debtorsCount' => \count($debtors),
            'from' => $monthStart,
            'to' => new \DateTimeImmutable('today'),
        ]);
    }

    #[Route('/export/ventes.csv', name: 'app_report_export_sales')]
    public function exportSales(Request $request, ShopContext $shopContext, SaleRepository $sales): StreamedResponse
    {
        $shop = $this->requireShop($shopContext);
        [$from, $to] = $this->resolvePeriod($request);

        $rows = $sales->createQueryBuilder('s')
            ->leftJoin('s.customer', 'c')->addSelect('c')
            ->andWhere('s.shop = :shop')
            ->andWhere('s.soldAt >= :from AND s.soldAt < :to')
            ->setParameter('shop', $shop)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('s.soldAt', 'ASC')
            ->getQuery()->getResult();

        return $this->csvResponse(
            sprintf('ventes_%s_%s.csv', $from->format('Ymd'), $to->modify('-1 day')->format('Ymd')),
            ['Reference', 'Date', 'Statut', 'Client', 'Paiement', 'HT', 'TVA', 'TTC', 'Paye'],
            static function () use ($rows) {
                foreach ($rows as $sale) {
                    /** @var Sale $sale */
                    $net = (float) $sale->getTotal() - (float) $sale->getTaxAmount();
                    yield [
                        $sale->getReference(),
                        $sale->getSoldAt()->format('Y-m-d H:i'),
                        $sale->getStatus(),
                        $sale->getCustomer()?->getFullName() ?: 'Comptoir',
                        $sale->getPaymentMethod(),
                        number_format($net, 2, '.', ''),
                        $sale->getTaxAmount(),
                        $sale->getTotal(),
                        $sale->getAmountPaid(),
                    ];
                }
            }
        );
    }

    #[Route('/export/tva.csv', name: 'app_report_export_tax')]
    public function exportTax(Request $request, ShopContext $shopContext, SaleRepository $sales): StreamedResponse
    {
        $shop = $this->requireShop($shopContext);
        [$from, $to] = $this->resolvePeriod($request);

        $rows = $sales->createQueryBuilder('s')
            ->andWhere('s.shop = :shop')
            ->andWhere('s.status = :status')
            ->andWhere('s.soldAt >= :from AND s.soldAt < :to')
            ->setParameter('shop', $shop)
            ->setParameter('status', Sale::STATUS_COMPLETED)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('s.soldAt', 'ASC')
            ->getQuery()->getResult();

        return $this->csvResponse(
            sprintf('tva_%s_%s.csv', $from->format('Ymd'), $to->modify('-1 day')->format('Ymd')),
            ['Reference', 'Date', 'Taux', 'Base_HT', 'TVA', 'TTC'],
            static function () use ($rows) {
                foreach ($rows as $sale) {
                    /** @var Sale $sale */
                    $net = (float) $sale->getTotal() - (float) $sale->getTaxAmount();
                    yield [
                        $sale->getReference(),
                        $sale->getSoldAt()->format('Y-m-d'),
                        $sale->getTaxRate(),
                        number_format($net, 2, '.', ''),
                        $sale->getTaxAmount(),
                        $sale->getTotal(),
                    ];
                }
            }
        );
    }

    #[Route('/export/credits.csv', name: 'app_report_export_credits')]
    public function exportCredits(ShopContext $shopContext, CustomerRepository $customers): StreamedResponse
    {
        $shop = $this->requireShop($shopContext);
        $rows = $customers->findWithDebt($shop);

        return $this->csvResponse(
            'clients_credit_'.date('Ymd').'.csv',
            ['Client', 'Telephone', 'Email', 'Solde_du'],
            static function () use ($rows) {
                foreach ($rows as $customer) {
                    yield [
                        $customer->getFullName(),
                        $customer->getPhone() ?: '',
                        $customer->getEmail() ?: '',
                        $customer->getBalance(),
                    ];
                }
            }
        );
    }

    /**
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    private function resolvePeriod(Request $request): array
    {
        $fromInput = $request->query->getString('from');
        $toInput = $request->query->getString('to');
        $from = $fromInput !== ''
            ? new \DateTimeImmutable($fromInput.' 00:00:00')
            : new \DateTimeImmutable('first day of this month midnight');
        $toExclusive = $toInput !== ''
            ? (new \DateTimeImmutable($toInput.' 00:00:00'))->modify('+1 day')
            : (new \DateTimeImmutable('today'))->modify('+1 day');

        if ($from >= $toExclusive) {
            $from = new \DateTimeImmutable('first day of this month midnight');
            $toExclusive = (new \DateTimeImmutable('today'))->modify('+1 day');
        }

        return [$from, $toExclusive];
    }

    /**
     * @param callable(): \Generator<int, list<string|int|float|null>> $rows
     */
    private function csvResponse(string $filename, array $headers, callable $rows): StreamedResponse
    {
        $response = new StreamedResponse(static function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fprintf($out, \chr(0xEF).\chr(0xBB).\chr(0xBF));
            fputcsv($out, $headers, ';');
            foreach ($rows() as $row) {
                fputcsv($out, $row, ';');
            }
            fclose($out);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');

        return $response;
    }
}
