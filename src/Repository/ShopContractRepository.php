<?php

namespace App\Repository;

use App\Entity\Merchant;
use App\Entity\ShopContract;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShopContract>
 */
class ShopContractRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShopContract::class);
    }

    /** @return list<ShopContract> */
    public function findAllRecent(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.merchant', 'm')->addSelect('m')
            ->leftJoin('c.shop', 's')->addSelect('s')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** Contrats visibles par l'entrepreneur(se) (partagés). */
    /** @return list<ShopContract> */
    public function findVisibleForMerchant(Merchant $merchant): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.merchant = :merchant')
            ->andWhere('c.sharedWithMerchant = true')
            ->setParameter('merchant', $merchant)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
