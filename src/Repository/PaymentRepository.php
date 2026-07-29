<?php

namespace App\Repository;

use App\Entity\Payment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    /** @return list<Payment> */
    public function findPaidSince(\DateTimeImmutable $from): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->andWhere('p.paidAt IS NOT NULL')
            ->andWhere('p.paidAt >= :from')
            ->setParameter('status', Payment::STATUS_PAID)
            ->setParameter('from', $from)
            ->orderBy('p.paidAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function sumPaidSince(\DateTimeImmutable $from): float
    {
        $value = $this->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.amount), 0)')
            ->andWhere('p.status = :status')
            ->andWhere('p.paidAt IS NOT NULL')
            ->andWhere('p.paidAt >= :from')
            ->setParameter('status', Payment::STATUS_PAID)
            ->setParameter('from', $from)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) $value;
    }

    public function sumPaidBetween(\DateTimeImmutable $from, \DateTimeImmutable $toExclusive): float
    {
        $value = $this->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.amount), 0)')
            ->andWhere('p.status = :status')
            ->andWhere('p.paidAt IS NOT NULL')
            ->andWhere('p.paidAt >= :from')
            ->andWhere('p.paidAt < :to')
            ->setParameter('status', Payment::STATUS_PAID)
            ->setParameter('from', $from)
            ->setParameter('to', $toExclusive)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) $value;
    }

    public function save(Payment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Payment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
