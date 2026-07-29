<?php

namespace App\Repository;

use App\Entity\ActivityLog;
use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityLog>
 */
class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    /** @return list<ActivityLog> */
    public function findRecentForShop(Shop $shop, int $limit = 300): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.shop = :shop')
            ->setParameter('shop', $shop)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function save(ActivityLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ActivityLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
