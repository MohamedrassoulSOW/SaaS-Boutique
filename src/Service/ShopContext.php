<?php

namespace App\Service;

use App\Entity\Shop;
use App\Entity\ShopMember;
use App\Entity\User;
use App\Repository\ShopRepository;
use Symfony\Component\HttpFoundation\RequestStack;

class ShopContext
{
    private const SESSION_KEY = 'current_shop_id';

    public function __construct(
        private RequestStack $requestStack,
        private ShopRepository $shopRepository,
    ) {
    }

    public function getCurrentShop(?User $user = null): ?Shop
    {
        $session = $this->requestStack->getSession();
        $shopId = $session->get(self::SESSION_KEY);

        if ($shopId) {
            $shop = $this->shopRepository->find($shopId);
            if ($shop && $user && $this->userCanAccess($user, $shop)) {
                return $shop;
            }
        }

        if (!$user) {
            return null;
        }

        $shops = $this->getAccessibleShops($user);
        if ($shops === []) {
            return null;
        }

        $this->setCurrentShop($shops[0]);

        return $shops[0];
    }

    public function setCurrentShop(Shop $shop): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, $shop->getId());
    }

    /** @return Shop[] */
    public function getAccessibleShops(User $user): array
    {
        if ($user->isAdmin()) {
            return $this->shopRepository->findBy(['isActive' => true], ['name' => 'ASC']);
        }

        if ($user->isMerchant() && $user->getMerchant()) {
            return $user->getMerchant()->getShops()->toArray();
        }

        $shops = [];
        foreach ($user->getShopMemberships() as $membership) {
            if ($membership->isActive() && $membership->getShop()) {
                $shops[] = $membership->getShop();
            }
        }

        return $shops;
    }

    public function userCanAccess(User $user, Shop $shop): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isMerchant() && $user->getMerchant()?->getId() === $shop->getMerchant()?->getId()) {
            return true;
        }

        foreach ($user->getShopMemberships() as $membership) {
            if ($membership->isActive() && $membership->getShop()?->getId() === $shop->getId()) {
                return true;
            }
        }

        return false;
    }
}
