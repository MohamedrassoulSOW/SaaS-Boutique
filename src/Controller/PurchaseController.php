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
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/purchases')]
#[IsGranted('MODULE_PURCHASES')]
class PurchaseController extends ShopAwareController
{
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
        #[Autowire(service: 'limiter.financial_operations')]
        RateLimiterFactory $financialLimiter,
    ): Response {
        $shop = $this->requireShop($shopContext);
        $supplierList = $suppliers->findBy(['shop' => $shop], ['name' => 'ASC']);
        $productList = $products->findBy(['shop' => $shop, 'isActive' => true], ['name' => 'ASC']);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('purchase_new', (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Session expirée. Réessayez.');

                return $this->redirectToRoute('app_purchase_new');
            }

            $limiter = $financialLimiter->create((string) $this->getUser()->getId());
            if (!$limiter->consume(1)->isAccepted()) {
                $this->addFlash('danger', 'Trop d\'opérations. Veuillez patienter quelques instants.');

                return $this->redirectToRoute('app_purchase_new');
            }

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
                    if (!$product || $qty < 1 || $product->getShop()?->getId() !== $shop->getId()) {
                        continue;
                    }
                    $unitPrice = max(0, (float) ($prices[$i] ?? $product->getPurchasePrice()));
                    $item = new PurchaseOrderItem();
                    $item->setProduct($product);
                    $item->setQuantity($qty);
                    $item->setUnitPrice(number_format($unitPrice, 2, '.', ''));
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
        $this->assertShopData($shopContext, $order->getShop());

        return $this->render('purchase/show.html.twig', ['order' => $order]);
    }

    #[Route('/{id}/receive', name: 'app_purchase_receive', methods: ['POST'])]
    #[IsGranted('MODULE_PURCHASES_MANAGE')]
    public function receive(
        PurchaseOrder $order,
        Request $request,
        ShopContext $shopContext,
        EntityManagerInterface $em,
        StockService $stockService,
        ActivityLogger $logger,
    ): Response {
        $shop = $this->requireShop($shopContext);
        $this->assertShopData($shopContext, $order->getShop());

        if (!$this->isCsrfTokenValid('receive'.$order->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($order->getStatus() === PurchaseOrder::STATUS_RECEIVED) {
            $this->addFlash('warning', 'Commande déjà entièrement reçue.');

            return $this->redirectToRoute('app_purchase_show', ['id' => $order->getId()]);
        }

        /** @var User $user */
        $user = $this->getUser();
        $qtyMap = $request->request->all('receive_qty') ?: [];
        $receivedAny = false;
        $fullyReceived = true;

        try {
            $em->wrapInTransaction(function () use ($em, $order, $qtyMap, $user, $stockService, &$receivedAny, &$fullyReceived) {
                foreach ($order->getItems() as $item) {
                    $remaining = $item->getRemainingQuantity();
                    $raw = $qtyMap[(string) $item->getId()] ?? $qtyMap[$item->getId()] ?? null;
                    // Sans saisie par ligne = réception du reste (comportement classique)
                    $qty = $raw === null || $raw === '' ? $remaining : max(0, min($remaining, (int) $raw));
                    if ($qty < 1) {
                        if ($remaining > 0) {
                            $fullyReceived = false;
                        }
                        continue;
                    }

                    $stockService->adjust(
                        $item->getProduct(),
                        $qty,
                        StockMovement::TYPE_PURCHASE,
                        $user,
                        'Réception '.$order->getReference(),
                        false
                    );
                    $item->setReceivedQuantity($item->getReceivedQuantity() + $qty);
                    $receivedAny = true;
                    if ($item->getRemainingQuantity() > 0) {
                        $fullyReceived = false;
                    }
                }

                if ($fullyReceived) {
                    $order->setStatus(PurchaseOrder::STATUS_RECEIVED);
                    $order->setReceivedAt(new \DateTimeImmutable());
                } elseif ($receivedAny) {
                    $order->setStatus(PurchaseOrder::STATUS_PARTIAL);
                }
            });
        } catch (OptimisticLockException) {
            $this->addFlash('danger', 'Conflit détecté — un autre utilisateur a modifié cette commande. Rafraîchissez et réessayez.');

            return $this->redirectToRoute('app_purchase_show', ['id' => $order->getId()]);
        }

        if (!$receivedAny) {
            $this->addFlash('warning', 'Aucune quantité à réceptionner.');

            return $this->redirectToRoute('app_purchase_show', ['id' => $order->getId()]);
        }

        if ($fullyReceived) {
            $this->addFlash('success', 'Commande entièrement reçue, stock mis à jour.');
        } else {
            $this->addFlash('success', 'Réception partielle enregistrée, stock mis à jour.');
        }

        $logger->log('purchase.receive', 'Réception '.$order->getReference(), $user, $shop);

        return $this->redirectToRoute('app_purchase_show', ['id' => $order->getId()]);
    }
}
