<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\Sale;
use App\Entity\Shop;
use App\Repository\ExpenseRepository;
use Doctrine\ORM\EntityManagerInterface;

class ProfitService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ExpenseRepository $expenses,
    ) {
    }

    /**
     * @return array{
     *   revenue: float,
     *   cost: float,
     *   profit: float,
     *   margin: float,
     *   salesCount: int,
     *   itemsSold: int,
     *   productCount: int,
     *   byDay: list<array{label: string, revenue: float, cost: float, profit: float}>,
     *   byProduct: list<array{
     *     id: int,
     *     name: string,
     *     reference: ?string,
     *     qty: float,
     *     revenue: float,
     *     cost: float,
     *     profit: float,
     *     margin: float,
     *     unitProfit: float
     *   }>,
     *   lowMargin: list<array{
     *     id: int,
     *     name: string,
     *     reference: ?string,
     *     qty: float,
     *     revenue: float,
     *     cost: float,
     *     profit: float,
     *     margin: float,
     *     unitProfit: float
     *   }>,
     *   selectedProduct: ?array{
     *     id: int,
     *     name: string,
     *     reference: ?string,
     *     qty: float,
     *     revenue: float,
     *     cost: float,
     *     profit: float,
     *     margin: float,
     *     unitProfit: float
     *   }
     * }
     */
    public function summarize(
        Shop $shop,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        ?int $productId = null,
        string $sort = 'profit',
        string $q = '',
    ): array {
        $qb = $this->baseQb($shop, $from, $to, $productId);

        $totals = (clone $qb)
            ->select(
                'COALESCE(SUM(si.quantity * si.unitPrice), 0) AS revenue',
                'COALESCE(SUM(si.quantity * COALESCE(si.unitCost, p.purchasePrice)), 0) AS cost',
                'COUNT(DISTINCT s.id) AS salesCount',
                'COALESCE(SUM(si.quantity), 0) AS itemsSold',
                'COUNT(DISTINCT p.id) AS productCount'
            )
            ->getQuery()
            ->getSingleResult();

        $revenue = (float) $totals['revenue'];
        $cost = (float) $totals['cost'];
        $profit = $revenue - $cost;
        $expensesTotal = $this->expenses->sumForShop($shop, $from, $to);
        $netProfit = $profit - $expensesTotal;
        $margin = $revenue > 0 ? ($profit / $revenue) * 100 : 0.0;

        $productQb = $this->baseQb($shop, $from, $to, $productId)
            ->select(
                'p.id AS id',
                'p.name AS name',
                'p.reference AS reference',
                'SUM(si.quantity) AS qty',
                'SUM(si.quantity * si.unitPrice) AS revenue',
                'SUM(si.quantity * COALESCE(si.unitCost, p.purchasePrice)) AS cost'
            )
            ->groupBy('p.id')
            ->addGroupBy('p.name')
            ->addGroupBy('p.reference');

        $q = trim($q);
        if ($q !== '') {
            $productQb
                ->andWhere('p.name LIKE :q OR p.reference LIKE :q OR p.barcode LIKE :q')
                ->setParameter('q', '%'.$q.'%');
        }

        $orderMap = [
            'profit' => 'profit',
            'margin' => 'margin',
            'revenue' => 'revenue',
            'qty' => 'qty',
            'name' => 'name',
            'cost' => 'cost',
        ];
        $sortKey = $orderMap[$sort] ?? 'profit';

        $rows = $productQb->getQuery()->getResult();
        $mappedProducts = [];
        foreach ($rows as $row) {
            $mappedProducts[] = $this->mapProductRow($row);
        }

        usort($mappedProducts, static function (array $a, array $b) use ($sortKey): int {
            if ($sortKey === 'name') {
                return strcasecmp($a['name'], $b['name']);
            }
            // Numeric desc by default (best first)
            return $b[$sortKey] <=> $a[$sortKey];
        });

        $lowMargin = array_values(array_filter(
            $mappedProducts,
            static fn (array $r) => $r['revenue'] > 0 && $r['margin'] < 15
        ));
        usort($lowMargin, static fn (array $a, array $b) => $a['margin'] <=> $b['margin']);
        $lowMargin = \array_slice($lowMargin, 0, 10);

        $selectedProduct = null;
        if ($productId !== null) {
            foreach ($mappedProducts as $row) {
                if ($row['id'] === $productId) {
                    $selectedProduct = $row;
                    break;
                }
            }
            if ($selectedProduct === null) {
                $product = $this->em->getRepository(Product::class)->findOneBy([
                    'id' => $productId,
                    'shop' => $shop,
                ]);
                if ($product) {
                    $selectedProduct = [
                        'id' => (int) $product->getId(),
                        'name' => (string) $product->getName(),
                        'reference' => $product->getReference(),
                        'qty' => 0.0,
                        'revenue' => 0.0,
                        'cost' => 0.0,
                        'profit' => 0.0,
                        'margin' => 0.0,
                        'unitProfit' => 0.0,
                    ];
                }
            }
        }

        return [
            'revenue' => $revenue,
            'cost' => $cost,
            'profit' => $profit,
            'expenses' => $expensesTotal,
            'netProfit' => $netProfit,
            'margin' => $margin,
            'salesCount' => (int) $totals['salesCount'],
            'itemsSold' => (int) $totals['itemsSold'],
            'productCount' => (int) $totals['productCount'],
            'byDay' => $this->buildSeries($shop, $from, $to, $productId),
            'byProduct' => $mappedProducts,
            'lowMargin' => $lowMargin,
            'selectedProduct' => $selectedProduct,
        ];
    }

    /**
     * @return list<Product>
     */
    public function listProducts(Shop $shop): array
    {
        return $this->em->getRepository(Product::class)->createQueryBuilder('p')
            ->andWhere('p.shop = :shop')
            ->andWhere('p.isActive = true')
            ->setParameter('shop', $shop)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    private function baseQb(Shop $shop, \DateTimeImmutable $from, \DateTimeImmutable $to, ?int $productId)
    {
        $qb = $this->em->createQueryBuilder()
            ->from(\App\Entity\SaleItem::class, 'si')
            ->join('si.sale', 's')
            ->join('si.product', 'p')
            ->andWhere('s.shop = :shop')
            ->andWhere('s.status = :status')
            ->andWhere('s.soldAt >= :from')
            ->andWhere('s.soldAt < :to')
            ->setParameter('shop', $shop)
            ->setParameter('status', Sale::STATUS_COMPLETED)
            ->setParameter('from', $from)
            ->setParameter('to', $to);

        if ($productId !== null) {
            $qb->andWhere('p.id = :productId')->setParameter('productId', $productId);
        }

        return $qb;
    }

    /**
     * @return list<array{label: string, revenue: float, cost: float, profit: float}>
     */
    private function buildSeries(Shop $shop, \DateTimeImmutable $from, \DateTimeImmutable $to, ?int $productId): array
    {
        $days = max(1, (int) $from->diff($to)->days);
        // Aggregate by week if longer than ~2 months, by month if > 1 year
        if ($days > 366) {
            $step = 'month';
            $format = 'm/Y';
        } elseif ($days > 62) {
            $step = 'week';
            $format = 'd/m';
        } else {
            $step = 'day';
            $format = 'd/m';
        }

        $series = [];
        $cursor = $from;
        while ($cursor < $to) {
            $next = match ($step) {
                'month' => $cursor->modify('first day of next month midnight'),
                'week' => $cursor->modify('+7 days'),
                default => $cursor->modify('+1 day'),
            };
            if ($next > $to) {
                $next = $to;
            }

            $bucket = $this->baseQb($shop, $cursor, $next, $productId)
                ->select(
                    'COALESCE(SUM(si.quantity * si.unitPrice), 0) AS revenue',
                    'COALESCE(SUM(si.quantity * COALESCE(si.unitCost, p.purchasePrice)), 0) AS cost'
                )
                ->getQuery()
                ->getSingleResult();

            $dayRevenue = (float) $bucket['revenue'];
            $dayCost = (float) $bucket['cost'];
            $label = $step === 'week'
                ? $cursor->format('d/m').'–'.$next->modify('-1 day')->format('d/m')
                : $cursor->format($format);

            $series[] = [
                'label' => $label,
                'revenue' => $dayRevenue,
                'cost' => $dayCost,
                'profit' => $dayRevenue - $dayCost,
            ];
            $cursor = $next;
        }

        return $series;
    }

    /**
     * @param array{id: int|string, name: string, reference?: ?string, qty: float|string, revenue: float|string, cost: float|string} $row
     *
     * @return array{id: int, name: string, reference: ?string, qty: float, revenue: float, cost: float, profit: float, margin: float, unitProfit: float}
     */
    private function mapProductRow(array $row): array
    {
        $qty = (float) $row['qty'];
        $revenue = (float) $row['revenue'];
        $cost = (float) $row['cost'];
        $profit = $revenue - $cost;

        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'reference' => isset($row['reference']) && $row['reference'] !== '' ? (string) $row['reference'] : null,
            'qty' => $qty,
            'revenue' => $revenue,
            'cost' => $cost,
            'profit' => $profit,
            'margin' => $revenue > 0 ? ($profit / $revenue) * 100 : 0.0,
            'unitProfit' => $qty > 0 ? $profit / $qty : 0.0,
        ];
    }
}
