<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use App\Service\ActivityLogger;
use App\Service\BinaryUploadService;
use App\Service\ShopContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/products')]
#[IsGranted('MODULE_PRODUCTS_VIEW')]
class ProductController extends ShopAwareController
{
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
    #[IsGranted('MODULE_PRODUCTS_MANAGE')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        ShopContext $shopContext,
        ActivityLogger $logger,
        BinaryUploadService $uploader,
    ): Response {
        $shop = $this->requireShop($shopContext);
        $product = new Product();
        $product->setShop($shop);
        $form = $this->createForm(ProductType::class, $product, [
            'shop' => $shop,
            'show_margin' => $this->isGranted('MODULE_VIEW_MARGIN'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyPhoto($form->get('photoFile')->getData(), $product, $uploader);
            $em->persist($product);
            $em->flush();
            $logger->log('product.create', 'Produit '.$product->getName(), $this->getShopUser(), $shop);
            $this->addFlash('success', 'Produit ajouté (données en base).');

            return $this->redirectToRoute('app_product_index');
        }

        return $this->render('product/form.html.twig', [
            'form' => $form,
            'title' => 'Nouveau produit',
            'product' => $product,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_product_edit')]
    #[IsGranted('MODULE_PRODUCTS_MANAGE')]
    public function edit(
        Product $product,
        Request $request,
        EntityManagerInterface $em,
        ShopContext $shopContext,
        ActivityLogger $logger,
        BinaryUploadService $uploader,
    ): Response {
        $shop = $this->requireShop($shopContext);
        $this->assertShopData($shopContext, $product->getShop());

        $form = $this->createForm(ProductType::class, $product, [
            'shop' => $shop,
            'show_margin' => $this->isGranted('MODULE_VIEW_MARGIN'),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyPhoto($form->get('photoFile')->getData(), $product, $uploader);
            $product->setShop($shop);
            $product->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();
            $logger->log('product.update', 'Produit modifié : '.$product->getName(), $this->getShopUser(), $shop);
            $this->addFlash('success', 'Produit mis à jour.');

            return $this->redirectToRoute('app_product_index');
        }

        return $this->render('product/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier le produit',
            'product' => $product,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_product_delete', methods: ['POST'])]
    #[IsGranted('MODULE_PRODUCTS_MANAGE')]
    public function delete(
        Product $product,
        Request $request,
        EntityManagerInterface $em,
        ShopContext $shopContext,
        ActivityLogger $logger,
    ): Response {
        $shop = $this->requireShop($shopContext);
        $this->assertShopData($shopContext, $product->getShop());

        if ($this->isCsrfTokenValid('delete'.$product->getId(), $request->request->get('_token'))) {
            $name = (string) $product->getName();

            $hasHistory = (int) $em->createQueryBuilder()
                ->select('COUNT(si.id)')
                ->from(\App\Entity\SaleItem::class, 'si')
                ->andWhere('si.product = :p')
                ->setParameter('p', $product)
                ->getQuery()->getSingleScalarResult() > 0
                || (int) $em->createQueryBuilder()
                    ->select('COUNT(m.id)')
                    ->from(\App\Entity\StockMovement::class, 'm')
                    ->andWhere('m.product = :p')
                    ->setParameter('p', $product)
                    ->getQuery()->getSingleScalarResult() > 0;

            if ($hasHistory) {
                $product->setIsActive(false);
                $em->flush();
                $logger->log('product.deactivate', 'Produit désactivé (historique conservé) : '.$name, $this->getShopUser(), $shop);
                $this->addFlash('warning', 'Produit désactivé : il a un historique et ne peut pas être effacé définitivement.');
            } else {
                $em->remove($product);
                $em->flush();
                $logger->log('product.delete', 'Produit supprimé : '.$name, $this->getShopUser(), $shop);
                $this->addFlash('success', 'Produit supprimé.');
            }
        }

        return $this->redirectToRoute('app_product_index');
    }

    private function applyPhoto(mixed $file, Product $product, BinaryUploadService $uploader): void
    {
        if (!$file) {
            return;
        }

        try {
            $payload = $uploader->readImage($file);
            $product->setPhotoData($payload['data']);
            $product->setPhotoMime($payload['mime']);
            $product->setPhotoName($payload['name']);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('danger', 'Erreur lors du téléchargement de la photo. Vérifiez le format et la taille.');
        }
    }
}
