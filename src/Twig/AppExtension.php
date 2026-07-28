<?php

namespace App\Twig;

use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Security\ShopPermission;
use App\Service\ShopContext;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private Security $security,
        private ShopContext $shopContext,
        private NotificationRepository $notifications,
        private ShopPermission $shopPermission,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('money', [$this, 'formatMoney']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('can_module', [$this, 'canModule']),
        ];
    }

    public function formatMoney(float|int|string|null $amount): string
    {
        $value = (float) ($amount ?? 0);

        return number_format(round($value), 0, ',', ' ').' FCFA';
    }

    public function canModule(string $module): bool
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $this->shopPermission->can($user, $module);
    }

    public function getGlobals(): array
    {
        $user = $this->security->getUser();
        $currentShop = null;
        $shops = [];
        $unread = 0;
        $memberRole = null;

        if ($user instanceof User) {
            $currentShop = $this->shopContext->getCurrentShop($user);
            $shops = $this->shopContext->getAccessibleShops($user);
            $unread = $this->notifications->count(['user' => $user, 'isRead' => false]);
            if ($currentShop) {
                $memberRole = $this->shopContext->getMemberRole($user, $currentShop);
            }
        }

        return [
            'current_shop' => $currentShop,
            'accessible_shops' => $shops,
            'unread_notifications' => $unread,
            'member_role' => $memberRole,
            'member_role_label' => $this->shopPermission->roleLabel($memberRole),
            'currency' => 'FCFA',
            'currency_code' => 'XOF',
        ];
    }
}
