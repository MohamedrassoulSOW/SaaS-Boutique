<?php

namespace App\Controller;

use App\Entity\StockMovement;
use App\Entity\User;
use App\Repository\ProductRepository;
use App\Repository\StockMovementRepository;
use App\Service\ShopContext;
use App\Service\StockService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/stock')]
#[IsGranted('ROLE_USER')]
class StockController extends AbstractController
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

    #[Route('', name: 'app_stock_index')]
    public function index(ShopContext $shopContext, StockService $stockService, StockMovementRepository $movements): Response
    {
        $shop = $this->requireShop($shopContext);

        return $this->render('stock/index.html.twig', [
            'lowStock' => $stockService->getLowStockProducts($shop),
            'movements' => $movements->findBy(['shop' => $shop], ['createdAt' => 'DESC'], 50),
        ]);
    }

    #[Route('/adjust', name: 'app_stock_adjust')]
    public function adjust(
        Request $request,
        ShopContext $shopContext,
        ProductRepository $products,
        StockService $stockService,
    ): Response {
        $shop = $this->requireShop($shopContext);
        $productList = $products->findBy(['shop' => $shop, 'isActive' => true], ['name' => 'ASC']);

        if ($request->isMethod('POST')) {
            /** @var User $user */
            $user = $this->getUser();
            $product = $products->find($request->request->getInt('product_id'));
            if (!$product || $product->getShop()?->getId() !== $shop->getId()) {
                $this->addFlash('danger', 'Produit invalide.');
            } else {
                $type = (string) $request->request->get('type', StockMovement::TYPE_ADJUSTMENT);
                $qty = abs($request->request->getInt('quantity'));
                $reason = (string) $request->request->get('reason', '');

                $delta = match ($type) {
                    StockMovement::TYPE_IN => $qty,
                    StockMovement::TYPE_OUT => -$qty,
                    default => $request->request->getInt('quantity') - $product->getQuantity(),
                };

                if ($type === StockMovement::TYPE_ADJUSTMENT) {
                    $stockService->setQuantity($product, $request->request->getInt('quantity'), $user, $reason ?: 'Ajustement manuel');
                } else {
                    $stockService->adjust($product, $delta, $type, $user, $reason ?: null);
                }
                $this->addFlash('success', 'Stock mis à jour.');

                return $this->redirectToRoute('app_stock_index');
            }
        }

        return $this->render('stock/adjust.html.twig', ['products' => $productList]);
    }
}
