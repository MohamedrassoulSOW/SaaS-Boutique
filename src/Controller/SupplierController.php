<?php

namespace App\Controller;

use App\Entity\Supplier;
use App\Form\SupplierType;
use App\Repository\SupplierRepository;
use App\Service\ActivityLogger;
use App\Service\ShopContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/suppliers')]
#[IsGranted('MODULE_SUPPLIERS')]
class SupplierController extends ShopAwareController
{
    #[Route('', name: 'app_supplier_index')]
    public function index(SupplierRepository $repo, ShopContext $shopContext): Response
    {
        $shop = $this->requireShop($shopContext);

        return $this->render('supplier/index.html.twig', [
            'suppliers' => $repo->findBy(['shop' => $shop], ['name' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'app_supplier_new')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        ShopContext $shopContext,
        ActivityLogger $logger,
    ): Response {
        $shop = $this->requireShop($shopContext);
        $supplier = new Supplier();
        $supplier->setShop($shop);
        $form = $this->createForm(SupplierType::class, $supplier);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($supplier);
            $em->flush();
            $logger->log('supplier.create', 'Fournisseur créé : '.$supplier->getName(), $this->getShopUser(), $shop);
            $this->addFlash('success', 'Fournisseur ajouté.');

            return $this->redirectToRoute('app_supplier_index');
        }

        return $this->render('supplier/form.html.twig', ['form' => $form, 'title' => 'Nouveau fournisseur']);
    }

    #[Route('/{id}', name: 'app_supplier_show')]
    public function show(Supplier $supplier, ShopContext $shopContext): Response
    {
        $this->requireShop($shopContext);
        $this->assertShopData($shopContext, $supplier->getShop());

        return $this->render('supplier/show.html.twig', ['supplier' => $supplier]);
    }

    #[Route('/{id}/edit', name: 'app_supplier_edit')]
    public function edit(
        Supplier $supplier,
        Request $request,
        EntityManagerInterface $em,
        ShopContext $shopContext,
        ActivityLogger $logger,
    ): Response {
        $shop = $this->requireShop($shopContext);
        $this->assertShopData($shopContext, $supplier->getShop());

        $form = $this->createForm(SupplierType::class, $supplier);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $logger->log('supplier.update', 'Fournisseur modifié : '.$supplier->getName(), $this->getShopUser(), $shop);
            $this->addFlash('success', 'Fournisseur mis à jour.');

            return $this->redirectToRoute('app_supplier_index');
        }

        return $this->render('supplier/form.html.twig', ['form' => $form, 'title' => 'Modifier le fournisseur']);
    }

    #[Route('/{id}/delete', name: 'app_supplier_delete', methods: ['POST'])]
    public function delete(
        Supplier $supplier,
        Request $request,
        EntityManagerInterface $em,
        ShopContext $shopContext,
        ActivityLogger $logger,
        #[Autowire(service: 'limiter.admin_operations')]
        RateLimiterFactory $rateLimiter,
    ): Response {
        $shop = $this->requireShop($shopContext);
        $this->assertShopData($shopContext, $supplier->getShop());
        $limiterKey = 'delete_' . ($this->getShopUser()?->getId() ?? 'anon');
        if (!$rateLimiter->create($limiterKey)->consume(1)->isAccepted()) {
            $this->addFlash('danger', 'Trop de requêtes. Réessayez dans une minute.');

            return $this->redirectToRoute('app_supplier_index');
        }
        if ($this->isCsrfTokenValid('delete'.$supplier->getId(), $request->request->get('_token'))) {
            $name = (string) $supplier->getName();
            $em->remove($supplier);
            $em->flush();
            $logger->log('supplier.delete', 'Fournisseur supprimé : '.$name, $this->getShopUser(), $shop);
            $this->addFlash('success', 'Fournisseur supprimé.');
        }

        return $this->redirectToRoute('app_supplier_index');
    }
}
