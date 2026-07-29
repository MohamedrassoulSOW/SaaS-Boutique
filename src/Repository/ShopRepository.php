<?php

namespace App\Repository;

use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Shop>
 */
class ShopRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Shop::class);
    }

    /** @return list<Shop> */
    public function findCreatedSince(\DateTimeImmutable $from): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.createdAt >= :from')
            ->setParameter('from', $from)
            ->orderBy('s.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.isActive = true')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function save(Shop $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Shop $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
