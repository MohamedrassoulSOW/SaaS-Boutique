<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\Shop;
use App\Entity\User;
use App\Service\ShopContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class MediaController extends AbstractController
{
    #[Route('/media/product/{id}/photo', name: 'app_media_product_photo')]
    public function productPhoto(Product $product, ShopContext $shopContext): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $shopContext->assertOwnsShopData($user, $product->getShop());

        $data = $product->getPhotoData();
        if ($data === null) {
            throw $this->createNotFoundException();
        }

        return new Response($data, 200, [
            'Content-Type' => $product->getPhotoMime() ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.($product->getPhotoName() ?: 'photo').'"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    #[Route('/media/shop/{id}/logo', name: 'app_media_shop_logo')]
    public function shopLogo(Shop $shop, ShopContext $shopContext): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user->isAdmin() && !$shopContext->userCanAccess($user, $shop)) {
            throw $this->createAccessDeniedException();
        }

        $data = $shop->getLogoData();
        if ($data === null) {
            throw $this->createNotFoundException();
        }

        return new Response($data, 200, [
            'Content-Type' => $shop->getLogoMime() ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.($shop->getLogoName() ?: 'logo').'"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
