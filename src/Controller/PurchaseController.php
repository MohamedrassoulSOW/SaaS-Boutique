<?php

namespace App\Controller;

use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderItem;
use App\Entity\StockMovement;
use App\Entity\User;
use App\Repository\ProductRepository;
use App\Repository\PurchaseOrderRepository;
use App\Repository\SupplierRepository;
use App\Service\ActivityLogger;
use App\Service\ShopContext;
use App\Service\StockService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/purchases')]
#[IsGranted('ROLE_USER')]
class PurchaseController extends AbstractController
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

    #[Route('', name: 'app_purchase_index')]
    public function index(PurchaseOrderRepository $repo, ShopContext $shopContext): Response
    {
        $shop = $this->requireShop($shopContext);

        return $this->render('purchase/index.html.twig', [
            'orders' => $repo->findBy(['shop' => $shop], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'app_purchase_new')]
    public function new(
        Request $request,
        ShopContext $shopContext,
        SupplierRepository $suppliers,
        ProductRepository $products,
        EntityManagerInterface $em,
        ActivityLogger $logger,
    ): Response {
        $shop = $this->requireShop($shopContext);
        $supplierList = $suppliers->findBy(['shop' => $shop], ['name' => 'ASC']);
        $productList = $products->findBy(['shop' => $shop, 'isActive' => true], ['name' => 'ASC']);

        if ($request->isMethod('POST')) {
            /** @var User $user */
            $user = $this->getUser();
            $supplier = $suppliers->find($request->request->getInt('supplier_id'));
            if (!$supplier || $supplier->getShop()?->getId() !== $shop->getId()) {
                $this->addFlash('danger', 'Fournisseur invalide.');
            } else {
                $order = new PurchaseOrder();
                $order->setShop($shop);
                $order->setSupplier($supplier);
                $order->setCreatedBy($user);
                $order->setStatus(PurchaseOrder::STATUS_ORDERED);

                $productIds = $request->request->all('product_id') ?: [];
                $quantities = $request->request->all('quantity') ?: [];
                $prices = $request->request->all('unit_price') ?: [];

                foreach ($productIds as $i => $pid) {
                    $product = $products->find((int) $pid);
                    $qty = (int) ($quantities[$i] ?? 0);
                    if (!$product || $qty < 1) {
                        continue;
                    }
                    $item = new PurchaseOrderItem();
                    $item->setProduct($product);
                    $item->setQuantity($qty);
                    $item->setUnitPrice(number_format((float) ($prices[$i] ?? $product->getPurchasePrice()), 2, '.', ''));
                    $order->addItem($item);
                }

                $order->recalculateTotal();
                $em->persist($order);
                $em->flush();
                $logger->log('purchase.create', 'Commande '.$order->getReference(), $user, $shop);
                $this->addFlash('success', 'Commande créée.');

                return $this->redirectToRoute('app_purchase_show', ['id' => $order->getId()]);
            }
        }

        return $this->render('purchase/new.html.twig', [
            'suppliers' => $supplierList,
            'products' => $productList,
        ]);
    }

    #[Route('/{id}', name: 'app_purchase_show')]
    public function show(PurchaseOrder $order, ShopContext $shopContext): Response
    {
        $shop = $this->requireShop($shopContext);
        if ($order->getShop()?->getId() !== $shop->getId()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('purchase/show.html.twig', ['order' => $order]);
    }

    #[Route('/{id}/receive', name: 'app_purchase_receive', methods: ['POST'])]
    public function receive(
        PurchaseOrder $order,
        Request $request,
        ShopContext $shopContext,
        EntityManagerInterface $em,
        StockService $stockService,
        ActivityLogger $logger,
    ): Response {
        $shop = $this->requireShop($shopContext);
        if ($order->getShop()?->getId() !== $shop->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('receive'.$order->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($order->getStatus() === PurchaseOrder::STATUS_RECEIVED) {
            $this->addFlash('warning', 'Commande déjà reçue.');

            return $this->redirectToRoute('app_purchase_show', ['id' => $order->getId()]);
        }

        /** @var User $user */
        $user = $this->getUser();
        foreach ($order->getItems() as $item) {
            $stockService->adjust(
                $item->getProduct(),
                $item->getQuantity(),
                StockMovement::TYPE_PURCHASE,
                $user,
                'Réception '.$order->getReference()
            );
        }

        $order->setStatus(PurchaseOrder::STATUS_RECEIVED);
        $order->setReceivedAt(new \DateTimeImmutable());
        $em->flush();
        $logger->log('purchase.receive', 'Réception '.$order->getReference(), $user, $shop);
        $this->addFlash('success', 'Marchandises reçues, stock mis à jour.');

        return $this->redirectToRoute('app_purchase_show', ['id' => $order->getId()]);
    }
}
