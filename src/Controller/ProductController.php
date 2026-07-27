<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\User;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use App\Service\ActivityLogger;
use App\Service\ShopContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/products')]
#[IsGranted('ROLE_USER')]
class ProductController extends AbstractController
{
    private function requireShop(ShopContext $shopContext): \App\Entity\Shop
    {
        /** @var User $user */
        $user = $this->getUser();
        $shop = $shopContext->getCurrentShop($user);
        if (!$shop) {
            throw $this->createNotFoundException('Aucune boutique active.');
        }

        return $shop;
    }

    #[Route('', name: 'app_product_index')]
    public function index(Request $request, ProductRepository $repo, ShopContext $shopContext): Response
    {
        $shop = $this->requireShop($shopContext);
        $q = trim((string) $request->query->get('q', ''));
        $lowOnly = $request->query->getBoolean('low');

        $qb = $repo->createQueryBuilder('p')
            ->andWhere('p.shop = :shop')
            ->setParameter('shop', $shop)
            ->orderBy('p.name', 'ASC');

        if ($q !== '') {
            $qb->andWhere('p.name LIKE :q OR p.reference LIKE :q OR p.barcode LIKE :q')
                ->setParameter('q', '%'.$q.'%');
        }
        if ($lowOnly) {
            $qb->andWhere('p.quantity <= p.minStock');
        }

        return $this->render('product/index.html.twig', [
            'products' => $qb->getQuery()->getResult(),
            'q' => $q,
            'lowOnly' => $lowOnly,
        ]);
    }

    #[Route('/new', name: 'app_product_new')]
    public function new(Request $request, EntityManagerInterface $em, ShopContext $shopContext, ActivityLogger $logger): Response
    {
        $shop = $this->requireShop($shopContext);
        $product = new Product();
        $product->setShop($shop);
        $form = $this->createForm(ProductType::class, $product, ['shop' => $shop]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($product);
            $em->flush();
            /** @var User $user */
            $user = $this->getUser();
            $logger->log('product.create', 'Produit '.$product->getName(), $user, $shop);
            $this->addFlash('success', 'Produit ajouté.');

            return $this->redirectToRoute('app_product_index');
        }

        return $this->render('product/form.html.twig', ['form' => $form, 'title' => 'Nouveau produit']);
    }

    #[Route('/{id}/edit', name: 'app_product_edit')]
    public function edit(Product $product, Request $request, EntityManagerInterface $em, ShopContext $shopContext): Response
    {
        $shop = $this->requireShop($shopContext);
        if ($product->getShop()?->getId() !== $shop->getId()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(ProductType::class, $product, ['shop' => $shop]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $product->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();
            $this->addFlash('success', 'Produit mis à jour.');

            return $this->redirectToRoute('app_product_index');
        }

        return $this->render('product/form.html.twig', ['form' => $form, 'title' => 'Modifier le produit']);
    }

    #[Route('/{id}/delete', name: 'app_product_delete', methods: ['POST'])]
    public function delete(Product $product, Request $request, EntityManagerInterface $em, ShopContext $shopContext): Response
    {
        $shop = $this->requireShop($shopContext);
        if ($product->getShop()?->getId() !== $shop->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete'.$product->getId(), $request->request->get('_token'))) {
            $em->remove($product);
            $em->flush();
            $this->addFlash('success', 'Produit supprimé.');
        }

        return $this->redirectToRoute('app_product_index');
    }
}
