<?php

namespace App\Twig;

use App\Entity\Shop;
use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Security\ShopPermission;
use App\Service\ShopContext;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension implements GlobalsInterface
{
    private ?array $cachedGlobals = null;

    public function __construct(
        private Security $security,
        private ShopContext $shopContext,
        private NotificationRepository $notifications,
        private ShopPermission $shopPermission,
        private RequestStack $requestStack,
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
            new TwigFunction('role_badge', [$this, 'roleBadge']),
            new TwigFunction('role_badge_label', [$this, 'roleBadgeLabel']),
        ];
    }

    /** Returns a Bootstrap color class for a given role key. */
    public function roleBadge(?string $role): string
    {
        return match ($role) {
            'ROLE_ADMIN' => 'bg-danger',
            'ROLE_MERCHANT' => 'bg-primary',
            'ROLE_EMPLOYEE' => 'bg-warning text-dark',
            'ROLE_USER' => 'bg-secondary',
            'manager' => 'bg-warning text-dark',
            'cashier' => 'bg-success',
            'stock' => 'bg-info text-dark',
            default => 'bg-secondary',
        };
    }

    /** Returns the human label for a role key. */
    public function roleBadgeLabel(?string $role): string
    {
        return match ($role) {
            'ROLE_ADMIN' => 'Admin',
            'ROLE_MERCHANT' => 'Entrepreneur(se)',
            'ROLE_EMPLOYEE' => 'Responsable',
            'ROLE_USER' => 'Utilisateur',
            'manager' => 'Responsable',
            'cashier' => 'Agent',
            'stock' => 'Magasinier',
            default => $role ?? '—',
        };
    }

    public function formatMoney(float|int|string|null $amount, ?string $currency = null): string
    {
        $value = (float) ($amount ?? 0);

        if ($currency === null) {
            $currency = $this->getCurrentCurrency();
        }

        $decimals = Shop::currencyDecimals($currency);
        $symbol = Shop::currencySymbol($currency);

        return number_format(round($value, $decimals), $decimals, ',', ' ').' '.$symbol;
    }

    private function getCurrentCurrency(): string
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return 'XOF';
        }

        $shop = $this->shopContext->getCurrentShop($user);

        return $shop?->getCurrency() ?? 'XOF';
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
        if ($this->cachedGlobals !== null) {
            return $this->cachedGlobals;
        }

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

        $userRole = null;
        if ($user instanceof User) {
            $userRole = $user->getRoles()[0] ?? null;
        }

        $this->cachedGlobals = [
            'current_shop' => $currentShop,
            'accessible_shops' => $shops,
            'unread_notifications' => $unread,
            'member_role' => $memberRole,
            'member_role_label' => $this->shopPermission->roleLabel($memberRole),
            'current_user_role' => $userRole,
            'currency' => $currentShop?->getCurrency() ?? 'XOF',
            'currency_symbol' => $currentShop ? $currentShop->getCurrencySymbol() : 'FCFA',
            'csp_nonce' => $this->requestStack->getCurrentRequest()?->attributes->get('csp_nonce', '') ?? '',
        ];

        return $this->cachedGlobals;
    }
}
