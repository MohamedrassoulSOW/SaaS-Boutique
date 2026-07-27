<?php

namespace App\Twig;

use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Service\ShopContext;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class AppExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private Security $security,
        private ShopContext $shopContext,
        private NotificationRepository $notifications,
    ) {
    }

    public function getGlobals(): array
    {
        $user = $this->security->getUser();
        $currentShop = null;
        $shops = [];
        $unread = 0;

        if ($user instanceof User) {
            $currentShop = $this->shopContext->getCurrentShop($user);
            $shops = $this->shopContext->getAccessibleShops($user);
            $unread = $this->notifications->count(['user' => $user, 'isRead' => false]);
        }

        return [
            'current_shop' => $currentShop,
            'accessible_shops' => $shops,
            'unread_notifications' => $unread,
        ];
    }
}
