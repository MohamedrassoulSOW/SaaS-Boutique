<?php

namespace App\Controller;

use App\Entity\Supplier;
use App\Entity\User;
use App\Form\SupplierType;
use App\Repository\SupplierRepository;
use App\Service\ShopContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/suppliers')]
#[IsGranted('ROLE_USER')]
class SupplierController extends AbstractController
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

    #[Route('', name: 'app_supplier_index')]
    public function index(SupplierRepository $repo, ShopContext $shopContext): Response
    {
        $shop = $this->requireShop($shopContext);

        return $this->render('supplier/index.html.twig', [
            'suppliers' => $repo->findBy(['shop' => $shop], ['name' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'app_supplier_new')]
    public function new(Request $request, EntityManagerInterface $em, ShopContext $shopContext): Response
    {
        $shop = $this->requireShop($shopContext);
        $supplier = new Supplier();
        $supplier->setShop($shop);
        $form = $this->createForm(SupplierType::class, $supplier);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($supplier);
            $em->flush();
            $this->addFlash('success', 'Fournisseur ajouté.');

            return $this->redirectToRoute('app_supplier_index');
        }

        return $this->render('supplier/form.html.twig', ['form' => $form, 'title' => 'Nouveau fournisseur']);
    }

    #[Route('/{id}', name: 'app_supplier_show')]
    public function show(Supplier $supplier, ShopContext $shopContext): Response
    {
        $shop = $this->requireShop($shopContext);
        if ($supplier->getShop()?->getId() !== $shop->getId()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('supplier/show.html.twig', ['supplier' => $supplier]);
    }

    #[Route('/{id}/edit', name: 'app_supplier_edit')]
    public function edit(Supplier $supplier, Request $request, EntityManagerInterface $em, ShopContext $shopContext): Response
    {
        $shop = $this->requireShop($shopContext);
        if ($supplier->getShop()?->getId() !== $shop->getId()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(SupplierType::class, $supplier);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Fournisseur mis à jour.');

            return $this->redirectToRoute('app_supplier_index');
        }

        return $this->render('supplier/form.html.twig', ['form' => $form, 'title' => 'Modifier le fournisseur']);
    }

    #[Route('/{id}/delete', name: 'app_supplier_delete', methods: ['POST'])]
    public function delete(Supplier $supplier, Request $request, EntityManagerInterface $em, ShopContext $shopContext): Response
    {
        $shop = $this->requireShop($shopContext);
        if ($supplier->getShop()?->getId() !== $shop->getId()) {
            throw $this->createAccessDeniedException();
        }
        if ($this->isCsrfTokenValid('delete'.$supplier->getId(), $request->request->get('_token'))) {
            $em->remove($supplier);
            $em->flush();
            $this->addFlash('success', 'Fournisseur supprimé.');
        }

        return $this->redirectToRoute('app_supplier_index');
    }
}
