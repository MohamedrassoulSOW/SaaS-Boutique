<?php

namespace App\Repository;

use App\Entity\Subscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Subscription>
 */
class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    /** @return array<string, int> */
    public function countByPlan(): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('s.plan AS plan')
            ->addSelect('COUNT(s.id) AS total')
            ->groupBy('s.plan')
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['plan']] = (int) $row['total'];
        }

        return $out;
    }

    /** @return array<string, int> */
    public function countByStatus(): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('s.status AS status')
            ->addSelect('COUNT(s.id) AS total')
            ->groupBy('s.status')
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['status']] = (int) $row['total'];
        }

        return $out;
    }

    public function save(Subscription $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Subscription $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
