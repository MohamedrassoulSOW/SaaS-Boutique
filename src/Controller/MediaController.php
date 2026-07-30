<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\Shop;
use App\Entity\User;
use App\Service\ShopContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
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

        $response = new Response($data, 200, [
            'Content-Type' => $product->getPhotoMime() ?: 'application/octet-stream',
            'Cache-Control' => 'private, max-age=3600',
        ]);
        $response->headers->set(
            'Content-Disposition',
            HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_INLINE,
                $product->getPhotoName() ?: 'photo.jpg'
            )
        );

        return $response;
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

        $response = new Response($data, 200, [
            'Content-Type' => $shop->getLogoMime() ?: 'application/octet-stream',
            'Cache-Control' => 'private, max-age=3600',
        ]);
        $response->headers->set(
            'Content-Disposition',
            HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_INLINE,
                $shop->getLogoName() ?: 'logo.png'
            )
        );

        return $response;
    }
}
