<?php

namespace App\Controller;

use App\Entity\Shop;
use App\Entity\User;
use App\Form\ShopType;
use App\Service\ActivityLogger;
use App\Service\ShopContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/shops')]
#[IsGranted('ROLE_USER')]
class ShopController extends AbstractController
{
    #[Route('', name: 'app_shop_index')]
    public function index(ShopContext $shopContext): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('shop/index.html.twig', [
            'shops' => $shopContext->getAccessibleShops($user),
            'current' => $shopContext->getCurrentShop($user),
        ]);
    }

    #[Route('/new', name: 'app_shop_new')]
    #[IsGranted('ROLE_MERCHANT')]
    public function new(Request $request, EntityManagerInterface $em, ShopContext $shopContext, ActivityLogger $logger): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $merchant = $user->getMerchant();
        if (!$merchant) {
            throw $this->createAccessDeniedException();
        }

        $shop = new Shop();
        $shop->setMerchant($merchant);
        $form = $this->createForm(ShopType::class, $shop);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($shop);
            $em->flush();
            $shopContext->setCurrentShop($shop);
            $logger->log('shop.create', 'Création boutique '.$shop->getName(), $user, $shop);
            $this->addFlash('success', 'Boutique créée.');

            return $this->redirectToRoute('app_shop_index');
        }

        return $this->render('shop/form.html.twig', ['form' => $form, 'title' => 'Nouvelle boutique']);
    }

    #[Route('/{id}/edit', name: 'app_shop_edit')]
    public function edit(Shop $shop, Request $request, EntityManagerInterface $em, ShopContext $shopContext, ActivityLogger $logger): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$shopContext->userCanAccess($user, $shop) || (!$user->isMerchant() && !$user->isAdmin())) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(ShopType::class, $shop);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $logger->log('shop.update', 'Modification boutique '.$shop->getName(), $user, $shop);
            $this->addFlash('success', 'Boutique mise à jour.');

            return $this->redirectToRoute('app_shop_index');
        }

        return $this->render('shop/form.html.twig', ['form' => $form, 'title' => 'Modifier la boutique']);
    }
}
