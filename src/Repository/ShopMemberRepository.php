<?php

namespace App\Repository;

use App\Entity\Shop;
use App\Entity\ShopMember;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShopMember>
 */
class ShopMemberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShopMember::class);
    }

    public function save(ShopMember $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ShopMember $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /** @return list<ShopMember> */
    public function findByShop(Shop $shop): array
    {
        return $this->createQueryBuilder('m')
            ->innerJoin('m.user', 'u')->addSelect('u')
            ->andWhere('m.shop = :shop')
            ->setParameter('shop', $shop)
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
