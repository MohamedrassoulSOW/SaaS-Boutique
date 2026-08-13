<?php

namespace App\Repository;

use App\Entity\Expense;
use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Expense>
 */
class ExpenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Expense::class);
    }

    /**
     * @return list<Expense>
     */
    public function findForShop(Shop $shop, ?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.shop = :shop')
            ->setParameter('shop', $shop)
            ->orderBy('e.spentAt', 'DESC');

        if ($from) {
            $qb->andWhere('e.spentAt >= :from')->setParameter('from', $from);
        }
        if ($to) {
            $qb->andWhere('e.spentAt < :to')->setParameter('to', $to);
        }

        return $qb->getQuery()->getResult();
    }

    public function sumForShop(Shop $shop, \DateTimeImmutable $from, \DateTimeImmutable $to): float
    {
        return (float) $this->createQueryBuilder('e')
            ->select('COALESCE(SUM(e.amount), 0)')
            ->andWhere('e.shop = :shop')
            ->andWhere('e.spentAt >= :from')
            ->andWhere('e.spentAt < :to')
            ->setParameter('shop', $shop)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
