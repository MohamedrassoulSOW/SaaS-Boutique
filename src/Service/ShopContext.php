<?php

namespace App\Service;

use App\Entity\Shop;
use App\Entity\User;
use App\Repository\ShopRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Contexte entreprise persisté en base (preferredShopId), sans dépendance fichier.
 */
class ShopContext
{
    public function __construct(
        private ShopRepository $shopRepository,
        private EntityManagerInterface $em,
    ) {
    }

    public function getCurrentShop(?User $user = null): ?Shop
    {
        if (!$user || $user->isAdmin()) {
            return null;
        }

        if ($user->getPreferredShopId()) {
            $shop = $this->shopRepository->find($user->getPreferredShopId());
            if ($shop && $this->userCanAccess($user, $shop)) {
                return $shop;
            }
            $user->setPreferredShopId(null);
            $this->em->flush();
        }

        $shops = $this->getAccessibleShops($user);
        if ($shops === []) {
            return null;
        }

        // Auto-sélection sans lever d'exception (Twig globals appellent getCurrentShop)
        $first = $shops[0];
        if ($this->userCanAccess($user, $first)) {
            $user->setPreferredShopId($first->getId());
            $this->em->flush();

            return $first;
        }

        return null;
    }

    public function setCurrentShop(Shop $shop, ?User $user = null): void
    {
        if (!$user) {
            throw new AccessDeniedHttpException('Utilisateur requis.');
        }

        if (!$this->userCanAccess($user, $shop)) {
            throw new AccessDeniedHttpException('Vous n\'avez pas accès à cette entreprise.');
        }

        $user->setPreferredShopId($shop->getId());
        $this->em->flush();
    }

    public function clearCurrentShop(?User $user = null): void
    {
        if ($user) {
            $user->setPreferredShopId(null);
            $this->em->flush();
        }
    }

    /** @return Shop[] */
    public function getAccessibleShops(User $user): array
    {
        if ($user->isAdmin()) {
            return [];
        }

        if ($user->isMerchant() && $user->getMerchant()) {
            $shops = [];
            foreach ($user->getMerchant()->getShops() as $shop) {
                if ($shop->isActive()) {
                    $shops[] = $shop;
                }
            }

            usort($shops, static fn (Shop $a, Shop $b) => strcmp((string) $a->getName(), (string) $b->getName()));

            return $shops;
        }

        $shops = [];
        foreach ($user->getShopMemberships() as $membership) {
            $shop = $membership->getShop();
            if ($membership->isActive() && $shop && $shop->isActive()) {
                $shops[] = $shop;
            }
        }

        return $shops;
    }

    public function userCanAccess(User $user, Shop $shop): bool
    {
        if ($user->isSuspended() || !$user->isActive()) {
            return false;
        }

        if (!$shop->isActive()) {
            return false;
        }

        if ($user->isAdmin()) {
            return false;
        }

        if ($user->isMerchant()) {
            $merchant = $user->getMerchant();
            if (!$merchant || !$shop->getMerchant()) {
                return false;
            }

            return $merchant->getId() === $shop->getMerchant()->getId();
        }

        foreach ($user->getShopMemberships() as $membership) {
            if ($membership->isActive()
                && $membership->getShop()?->getId() === $shop->getId()) {
                return true;
            }
        }

        return false;
    }

    public function getMemberRole(User $user, Shop $shop): ?string
    {
        if ($user->isMerchant() && $this->userCanAccess($user, $shop)) {
            return 'merchant';
        }

        foreach ($user->getShopMemberships() as $membership) {
            if ($membership->isActive()
                && $membership->getShop()?->getId() === $shop->getId()) {
                return $membership->getRole();
            }
        }

        return null;
    }

    public function requireAccessibleShop(User $user): Shop
    {
        if ($user->isAdmin()) {
            throw new AccessDeniedHttpException('L\'administrateur n\'a pas accès aux entreprises.');
        }

        $shop = $this->getCurrentShop($user);
        if (!$shop) {
            throw new NotFoundHttpException('Aucune entreprise accessible. Contactez l\'administrateur pour en créer une.');
        }

        if (!$this->userCanAccess($user, $shop)) {
            $this->clearCurrentShop($user);
            throw new AccessDeniedHttpException('Accès refusé à cette entreprise.');
        }

        return $shop;
    }

    public function assertOwnsShopData(User $user, ?Shop $dataShop): void
    {
        if ($user->isAdmin() || !$dataShop || !$this->userCanAccess($user, $dataShop)) {
            throw new AccessDeniedHttpException('Ces données n\'appartiennent pas à votre entreprise.');
        }
    }
}
