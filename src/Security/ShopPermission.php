<?php

namespace App\Security;

use App\Entity\Shop;
use App\Entity\ShopMember;
use App\Entity\User;
use App\Service\ShopContext;

/**
 * Droits métier par rôle entreprise.
 *
 * - entrepreneur : tout
 * - manager : opérations entreprise (sauf vendeurs / contrats / réglages entreprise)
 * - cashier : caisse, clients, consultation catalogue
 * - stock : stock, catalogue, achats, inventaires
 */
class ShopPermission
{
    public const DASHBOARD = 'dashboard';
    public const SALES = 'sales';
    public const PRODUCTS_VIEW = 'products_view';
    public const PRODUCTS_MANAGE = 'products_manage';
    public const CATEGORIES = 'categories';
    public const SUPPLIERS = 'suppliers';
    public const CUSTOMERS = 'customers';
    public const STOCK = 'stock';
    public const PURCHASES = 'purchases';
    public const INVENTORIES = 'inventories';
    public const REPORTS = 'reports';
    public const FISCAL = 'fiscal';
    public const CASH = 'cash';
    public const SALE_CANCEL = 'sale_cancel';
    public const VIEW_MARGIN = 'view_margin';
    public const SHOPS = 'shops';
    public const STAFF = 'staff';
    public const CONTRACTS = 'contracts';

    /** @var array<string, list<string>> */
    private const ROLE_MODULES = [
        ShopMember::ROLE_MANAGER => [
            self::DASHBOARD,
            self::SALES,
            self::PRODUCTS_VIEW,
            self::PRODUCTS_MANAGE,
            self::CATEGORIES,
            self::SUPPLIERS,
            self::CUSTOMERS,
            self::STOCK,
            self::PURCHASES,
            self::INVENTORIES,
            self::REPORTS,
            self::FISCAL,
            self::CASH,
            self::SALE_CANCEL,
            self::VIEW_MARGIN,
            self::SHOPS,
        ],
        ShopMember::ROLE_CASHIER => [
            self::DASHBOARD,
            self::SALES,
            self::PRODUCTS_VIEW,
            self::CUSTOMERS,
            self::CASH,
        ],
        ShopMember::ROLE_STOCK => [
            self::DASHBOARD,
            self::PRODUCTS_VIEW,
            self::PRODUCTS_MANAGE,
            self::CATEGORIES,
            self::SUPPLIERS,
            self::STOCK,
            self::PURCHASES,
            self::INVENTORIES,
        ],
    ];

    public function __construct(private ShopContext $shopContext)
    {
    }

    public function can(User $user, string $module, ?Shop $shop = null): bool
    {
        if ($user->isAdmin() || $user->isSuspended() || !$user->isActive()) {
            return false;
        }

        if ($user->isMerchant()) {
            return true;
        }

        if (!$user->isEmployee()) {
            return false;
        }

        $shop ??= $this->shopContext->getCurrentShop($user);
        if (!$shop || !$this->shopContext->userCanAccess($user, $shop)) {
            return false;
        }

        $role = $this->shopContext->getMemberRole($user, $shop);
        if ($role === null) {
            return false;
        }

        $allowed = self::ROLE_MODULES[$role] ?? [];

        return \in_array($module, $allowed, true);
    }

    public function roleLabel(?string $role): string
    {
        return match ($role) {
            ShopMember::ROLE_MANAGER => 'Responsable',
            ShopMember::ROLE_STOCK => 'Magasinier',
            ShopMember::ROLE_CASHIER => 'Vendeur / Caissier',
            default => 'Employé',
        };
    }
}
