<?php

namespace App\Repository;

use App\Entity\Customer;
use App\Entity\CustomerPayment;
use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CustomerPayment>
 */
class CustomerPaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerPayment::class);
    }

    /**
     * @return list<CustomerPayment>
     */
    public function findForCustomer(Customer $customer, int $limit = 50): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.customer = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<CustomerPayment>
     */
    public function findForShop(Shop $shop, int $limit = 100): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.shop = :shop')
            ->setParameter('shop', $shop)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
