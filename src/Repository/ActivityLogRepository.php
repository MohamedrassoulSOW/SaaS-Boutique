<?php

namespace App\Repository;

use App\Entity\ActivityLog;
use App\Entity\Shop;
use App\Entity\User;
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

    /**
     * @return array{logs: list<ActivityLog>, total: int}
     */
    public function searchForShop(
        Shop $shop,
        ?string $search = null,
        ?string $action = null,
        ?int $userId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        int $page = 1,
        int $perPage = 50,
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.shop = :shop')
            ->setParameter('shop', $shop);

        if ($search !== null && $search !== '') {
            $qb->andWhere('a.description LIKE :search')
                ->setParameter('search', '%' . addcslashes($search, '%_') . '%');
        }

        if ($action !== null && $action !== '') {
            $qb->andWhere('a.action = :action')
                ->setParameter('action', $action);
        }

        if ($userId !== null) {
            $qb->andWhere('a.user = :userId')
                ->setParameter('userId', $userId);
        }

        if ($dateFrom !== null && $dateFrom !== '') {
            $qb->andWhere('a.createdAt >= :dateFrom')
                ->setParameter('dateFrom', new \DateTimeImmutable($dateFrom));
        }

        if ($dateTo !== null && $dateTo !== '') {
            $qb->andWhere('a.createdAt <= :dateTo')
                ->setParameter('dateTo', (new \DateTimeImmutable($dateTo))->modify('+1 day'));
        }

        $totalQb = clone $qb;
        $total = (int) $totalQb
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $logs = $qb
            ->orderBy('a.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return ['logs' => $logs, 'total' => $total];
    }

    /** @return list<array{action: string, count: int}> */
    public function findDistinctActionsForShop(Shop $shop): array
    {
        return $this->createQueryBuilder('a')
            ->select('a.action')
            ->andWhere('a.shop = :shop')
            ->setParameter('shop', $shop)
            ->groupBy('a.action')
            ->orderBy('a.action', 'ASC')
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
