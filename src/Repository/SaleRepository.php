<?php

namespace App\Repository;

use App\Entity\Sale;
use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Sale>
 */
class SaleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sale::class);
    }

    /** @return list<Sale> */
    public function findCompletedSince(\DateTimeImmutable $from): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.status = :status')
            ->andWhere('s.soldAt >= :from')
            ->setParameter('status', Sale::STATUS_COMPLETED)
            ->setParameter('from', $from)
            ->orderBy('s.soldAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function sumCompletedSince(\DateTimeImmutable $from): float
    {
        $value = $this->createQueryBuilder('s')
            ->select('COALESCE(SUM(s.total), 0)')
            ->andWhere('s.status = :status')
            ->andWhere('s.soldAt >= :from')
            ->setParameter('status', Sale::STATUS_COMPLETED)
            ->setParameter('from', $from)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) $value;
    }

    /**
     * Agrégats fiscaux pour une entreprise sur une période.
     *
     * @return array{gross: float, tax: float, net: float, salesCount: int}
     */
    public function summarizeTaxForShop(Shop $shop, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $row = $this->createQueryBuilder('s')
            ->select(
                'COALESCE(SUM(s.total), 0) AS gross',
                'COALESCE(SUM(s.taxAmount), 0) AS tax',
                'COUNT(s.id) AS salesCount'
            )
            ->andWhere('s.shop = :shop')
            ->andWhere('s.status = :status')
            ->andWhere('s.soldAt >= :from')
            ->andWhere('s.soldAt < :to')
            ->setParameter('shop', $shop)
            ->setParameter('status', Sale::STATUS_COMPLETED)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleResult();

        $gross = (float) $row['gross'];
        $tax = (float) $row['tax'];

        return [
            'gross' => $gross,
            'tax' => $tax,
            'net' => round($gross - $tax, 2),
            'salesCount' => (int) $row['salesCount'],
        ];
    }

    /**
     * TVA collectée par mois (12 derniers mois max).
     *
     * @return list<array{label: string, tax: float, gross: float}>
     */
    public function taxCollectedByMonth(Shop $shop, int $months = 6): array
    {
        $months = max(1, min(12, $months));
        $result = [];
        $cursor = new \DateTimeImmutable('first day of this month midnight');

        for ($i = $months - 1; $i >= 0; --$i) {
            $from = $cursor->modify("-{$i} months");
            $to = $from->modify('+1 month');
            $summary = $this->summarizeTaxForShop($shop, $from, $to);
            $result[] = [
                'label' => $from->format('m/Y'),
                'tax' => $summary['tax'],
                'gross' => $summary['gross'],
            ];
        }

        return $result;
    }

    public function save(Sale $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Sale $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
