<?php

namespace App\Controller;

use App\Entity\Inventory;
use App\Entity\InventoryItem;
use App\Entity\Notification;
use App\Entity\StockMovement;
use App\Entity\User;
use App\Repository\InventoryRepository;
use App\Repository\ProductRepository;
use App\Service\ActivityLogger;
use App\Service\NotificationService;
use App\Service\ShopContext;
use App\Service\StockService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/inventories')]
#[IsGranted('MODULE_INVENTORIES')]
class InventoryController extends ShopAwareController
{
    #[Route('', name: 'app_inventory_index')]
    public function index(InventoryRepository $repo, ShopContext $shopContext): Response
    {
        $shop = $this->requireShop($shopContext);

        return $this->render('inventory/index.html.twig', [
            'inventories' => $repo->findBy(['shop' => $shop], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'app_inventory_new')]
    public function new(
        Request $request,
        ShopContext $shopContext,
        ProductRepository $products,
        EntityManagerInterface $em,
        NotificationService $notifications,
        ActivityLogger $logger,
    ): Response {
        $shop = $this->requireShop($shopContext);
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('inventory_new', (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Session expirée. Réessayez.');

                return $this->redirectToRoute('app_inventory_new');
            }

            $type = $request->request->get('type', Inventory::TYPE_FULL);
            $allowedTypes = [Inventory::TYPE_FULL, Inventory::TYPE_PARTIAL];
            if (!in_array($type, $allowedTypes, true)) {
                $type = Inventory::TYPE_FULL;
            }
            $inventory = new Inventory();
            $inventory->setShop($shop);
            $inventory->setType($type);
            $inventory->setCreatedBy($user);

            $selected = $request->request->all('product_ids') ?: [];
            $allProducts = $products->findBy(['shop' => $shop, 'isActive' => true]);

            foreach ($allProducts as $product) {
                if ($type === Inventory::TYPE_PARTIAL && !in_array((string) $product->getId(), $selected, true)) {
                    continue;
                }
                $item = new InventoryItem();
                $item->setProduct($product);
                $item->setTheoreticalQty($product->getQuantity());
                $item->setRealQty($product->getQuantity());
                $inventory->addItem($item);
            }

            $em->persist($inventory);
            $em->flush();

            $notifications->notify(
                $user,
                Notification::TYPE_INVENTORY,
                'Nouvel inventaire',
                'Inventaire '.$inventory->getReference().' créé.',
                $shop
            );
            $logger->log('inventory.create', 'Inventaire '.$inventory->getReference(), $user, $shop);

            return $this->redirectToRoute('app_inventory_show', ['id' => $inventory->getId()]);
        }

        return $this->render('inventory/new.html.twig', [
            'products' => $products->findBy(['shop' => $shop, 'isActive' => true], ['name' => 'ASC']),
        ]);
    }

    #[Route('/{id}', name: 'app_inventory_show')]
    public function show(Inventory $inventory, ShopContext $shopContext): Response
    {
        $shop = $this->requireShop($shopContext);
        $this->assertShopData($shopContext, $inventory->getShop());

        return $this->render('inventory/show.html.twig', ['inventory' => $inventory]);
    }

    #[Route('/{id}/complete', name: 'app_inventory_complete', methods: ['POST'])]
    #[IsGranted('MODULE_INVENTORIES_MANAGE')]
    public function complete(
        Inventory $inventory,
        Request $request,
        ShopContext $shopContext,
        EntityManagerInterface $em,
        StockService $stockService,
        ActivityLogger $logger,
    ): Response {
        $shop = $this->requireShop($shopContext);
        $this->assertShopData($shopContext, $inventory->getShop());

        if (!$this->isCsrfTokenValid('inventory_complete'.$inventory->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée. Réessayez.');

            return $this->redirectToRoute('app_inventory_show', ['id' => $inventory->getId()]);
        }

        /** @var User $user */
        $user = $this->getUser();
        $realQtys = $request->request->all('real_qty') ?: [];
        if (!\is_array($realQtys)) {
            $realQtys = [];
        }

        try {
            $em->wrapInTransaction(function () use ($inventory, $realQtys, $stockService, $em, $user) {
                foreach ($inventory->getItems() as $item) {
                    $id = (string) $item->getId();
                    if (isset($realQtys[$id])) {
                        $item->setRealQty((int) $realQtys[$id]);
                    }
                    if ($item->getDifference() !== 0) {
                        $stockService->setQuantity(
                            $item->getProduct(),
                            $item->getRealQty(),
                            $user,
                            'Inventaire '.$inventory->getReference()
                        );
                    }
                }

                $inventory->setStatus(Inventory::STATUS_COMPLETED);
                $inventory->setCompletedAt(new \DateTimeImmutable());
            });
        } catch (\Throwable) {
            $this->addFlash('danger', 'Conflit de modification. Réessayez.');

            return $this->redirectToRoute('app_inventory_show', ['id' => $inventory->getId()]);
        }

        $logger->log('inventory.complete', 'Inventaire '.$inventory->getReference().' terminé', $user, $shop);
        $this->addFlash('success', 'Inventaire terminé et stocks ajustés.');

        return $this->redirectToRoute('app_inventory_show', ['id' => $inventory->getId()]);
    }
}
