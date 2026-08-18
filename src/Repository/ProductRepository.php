<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * Catalogue POS sans charger les BLOB photos (perf / mémoire).
     *
     * @return list<Product>
     */
    public function findActiveForPos(Shop $shop): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.id, p.name, p.reference, p.barcode, p.salePrice, p.purchasePrice, p.quantity, p.minStock, p.photoMime, p.brand')
            ->leftJoin('p.category', 'c', 'WITH', 'c.id = p.category')
            ->addSelect('c.id AS categoryId, c.name AS categoryName')
            ->andWhere('p.shop = :shop')
            ->andWhere('p.isActive = true')
            ->setParameter('shop', $shop)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(Product $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Product $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
