<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\Shop;
use App\Entity\StockMovement;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class StockService
{
    public function __construct(
        private EntityManagerInterface $em,
        private NotificationService $notificationService,
    ) {
    }

    public function adjust(
        Product $product,
        int $delta,
        string $type,
        ?User $user = null,
        ?string $reason = null,
    ): StockMovement {
        $before = $product->getQuantity();
        $after = max(0, $before + $delta);
        $product->setQuantity($after);
        $product->setUpdatedAt(new \DateTimeImmutable());

        $movement = new StockMovement();
        $movement->setShop($product->getShop());
        $movement->setProduct($product);
        $movement->setType($type);
        $movement->setQuantity($delta);
        $movement->setQuantityBefore($before);
        $movement->setQuantityAfter($after);
        $movement->setReason($reason);
        $movement->setCreatedBy($user);

        $this->em->persist($movement);
        $this->em->flush();

        if ($user && $product->isLowStock()) {
            $this->notificationService->notifyLowStock($product, $user);
        }

        return $movement;
    }

    public function setQuantity(Product $product, int $quantity, ?User $user = null, ?string $reason = null): StockMovement
    {
        return $this->adjust(
            $product,
            $quantity - $product->getQuantity(),
            StockMovement::TYPE_ADJUSTMENT,
            $user,
            $reason
        );
    }

    /** @return Product[] */
    public function getLowStockProducts(Shop $shop): array
    {
        return $this->em->getRepository(Product::class)->createQueryBuilder('p')
            ->andWhere('p.shop = :shop')
            ->andWhere('p.quantity <= p.minStock')
            ->andWhere('p.isActive = true')
            ->setParameter('shop', $shop)
            ->orderBy('p.quantity', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
