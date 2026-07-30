<?php

namespace App\Repository;

use App\Entity\CashSession;
use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CashSession>
 */
class CashSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CashSession::class);
    }

    public function findOpenForShop(Shop $shop): ?CashSession
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.shop = :shop')
            ->andWhere('c.status = :status')
            ->setParameter('shop', $shop)
            ->setParameter('status', CashSession::STATUS_OPEN)
            ->orderBy('c.openedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<CashSession> */
    public function findRecentForShop(Shop $shop, int $limit = 20): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.shop = :shop')
            ->setParameter('shop', $shop)
            ->orderBy('c.openedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
