<?php

namespace App\Controller;

use App\Entity\StockMovement;
use App\Entity\User;
use App\Repository\ProductRepository;
use App\Repository\StockMovementRepository;
use App\Service\ActivityLogger;
use App\Service\ShopContext;
use App\Service\StockService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/stock')]
#[IsGranted('MODULE_STOCK')]
class StockController extends ShopAwareController
{
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
        ActivityLogger $logger,
    ): Response {
        $shop = $this->requireShop($shopContext);
        $productList = $products->findBy(['shop' => $shop, 'isActive' => true], ['name' => 'ASC']);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('stock_adjust', (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Session expirée. Réessayez.');

                return $this->redirectToRoute('app_stock_adjust');
            }

            /** @var User $user */
            $user = $this->getUser();
            $product = $products->find($request->request->getInt('product_id'));
            if (!$product || !$shopContext->userCanAccess($user, $product->getShop()) || $product->getShop()?->getId() !== $shop->getId()) {
                $this->addFlash('danger', 'Produit invalide ou hors de votre entreprise.');
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

                $logger->log(
                    'stock.adjust',
                    sprintf('%s — %s (%s %d)', $product->getName(), $type, $type === StockMovement::TYPE_ADJUSTMENT ? 'cible' : 'Δ', $type === StockMovement::TYPE_ADJUSTMENT ? $request->request->getInt('quantity') : $qty),
                    $user,
                    $shop
                );
                $this->addFlash('success', 'Stock mis à jour.');

                return $this->redirectToRoute('app_stock_index');
            }
        }

        return $this->render('stock/adjust.html.twig', ['products' => $productList]);
    }
}
