<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Product;
use App\Entity\Shop;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function notify(User $user, string $type, string $title, string $message, ?Shop $shop = null): Notification
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType($type);
        $notification->setTitle($title);
        $notification->setMessage($message);
        $notification->setShop($shop);

        $this->em->persist($notification);

        return $notification;
    }

    public function notifyLowStock(Product $product, User $user): void
    {
        if (!$product->isLowStock()) {
            return;
        }

        $this->notify(
            $user,
            Notification::TYPE_LOW_STOCK,
            'Stock faible',
            sprintf('Le produit "%s" est en stock faible (%d restant).', $product->getName(), $product->getQuantity()),
            $product->getShop()
        );
    }
}
