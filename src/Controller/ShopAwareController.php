<?php

namespace App\Controller;

use App\Entity\Shop;
use App\Entity\User;
use App\Service\ShopContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

abstract class ShopAwareController extends AbstractController
{
    protected function getShopUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    protected function requireShop(ShopContext $shopContext): Shop
    {
        return $shopContext->requireAccessibleShop($this->getShopUser());
    }

    protected function assertShopData(ShopContext $shopContext, ?Shop $dataShop): void
    {
        $shopContext->assertOwnsShopData($this->getShopUser(), $dataShop);
    }
}
