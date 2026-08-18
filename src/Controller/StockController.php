<?php

namespace App\Controller;

use App\Entity\StockMovement;
use App\Entity\User;
use App\Repository\ProductRepository;
use App\Repository\StockMovementRepository;
use App\Service\ActivityLogger;
use App\Service\ShopContext;
use App\Service\StockService;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
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
        #[Autowire(service: 'limiter.financial_operations')]
        RateLimiterFactory $financialLimiter,
    ): Response {
        $shop = $this->requireShop($shopContext);
        $productList = $products->findBy(['shop' => $shop, 'isActive' => true], ['name' => 'ASC']);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('stock_adjust', (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Session expirée. Réessayez.');

                return $this->redirectToRoute('app_stock_adjust');
            }

            $limiter = $financialLimiter->create((string) $this->getUser()->getId());
            if (!$limiter->consume(1)->isAccepted()) {
                $this->addFlash('danger', 'Trop d\'opérations. Veuillez patienter quelques instants.');

                return $this->redirectToRoute('app_stock_adjust');
            }

            /** @var User $user */
            $user = $this->getUser();
            $product = $products->find($request->request->getInt('product_id'));
            if (!$product || !$shopContext->userCanAccess($user, $product->getShop()) || $product->getShop()?->getId() !== $shop->getId()) {
                $this->addFlash('danger', 'Produit invalide ou hors de votre entreprise.');
            } else {
                $type = (string) $request->request->get('type', StockMovement::TYPE_ADJUSTMENT);
                $allowedTypes = [StockMovement::TYPE_ADJUSTMENT, StockMovement::TYPE_IN, StockMovement::TYPE_OUT];
                if (!in_array($type, $allowedTypes, true)) {
                    $type = StockMovement::TYPE_ADJUSTMENT;
                }
                $qty = abs($request->request->getInt('quantity'));
                $reason = strip_tags(trim((string) $request->request->get('reason', '')));

                if ($type === StockMovement::TYPE_ADJUSTMENT) {
                    $targetQty = $request->request->getInt('quantity');
                    if ($targetQty < 0) {
                        $this->addFlash('danger', 'La quantité cible ne peut pas être négative.');

                        return $this->redirectToRoute('app_stock_adjust');
                    }
                    try {
                        $stockService->setQuantity($product, $targetQty, $user, $reason ?: 'Ajustement manuel');
                    } catch (OptimisticLockException) {
                        $this->addFlash('danger', 'Conflit détecté — un autre utilisateur a modifié ce produit. Rafraîchissez et réessayez.');

                        return $this->redirectToRoute('app_stock_adjust');
                    }
                } else {
                    $delta = match ($type) {
                        StockMovement::TYPE_IN => $qty,
                        StockMovement::TYPE_OUT => -$qty,
                        default => 0,
                    };
                    try {
                        $stockService->adjust($product, $delta, $type, $user, $reason ?: null);
                    } catch (OptimisticLockException) {
                        $this->addFlash('danger', 'Conflit détecté — un autre utilisateur a modifié ce produit. Rafraîchissez et réessayez.');

                        return $this->redirectToRoute('app_stock_adjust');
                    }
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
