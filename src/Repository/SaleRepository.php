<?php

namespace App\Repository;

use App\Entity\Sale;
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
