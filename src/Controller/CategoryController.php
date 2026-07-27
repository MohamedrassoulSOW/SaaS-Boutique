<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\User;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use App\Service\ShopContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/categories')]
#[IsGranted('ROLE_USER')]
class CategoryController extends AbstractController
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

    #[Route('', name: 'app_category_index')]
    public function index(CategoryRepository $repo, ShopContext $shopContext): Response
    {
        $shop = $this->requireShop($shopContext);

        return $this->render('category/index.html.twig', [
            'categories' => $repo->findBy(['shop' => $shop], ['sortOrder' => 'ASC', 'name' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'app_category_new')]
    public function new(Request $request, EntityManagerInterface $em, ShopContext $shopContext): Response
    {
        $shop = $this->requireShop($shopContext);
        $category = new Category();
        $category->setShop($shop);
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($category);
            $em->flush();
            $this->addFlash('success', 'Catégorie créée.');

            return $this->redirectToRoute('app_category_index');
        }

        return $this->render('category/form.html.twig', ['form' => $form, 'title' => 'Nouvelle catégorie']);
    }

    #[Route('/{id}/edit', name: 'app_category_edit')]
    public function edit(Category $category, Request $request, EntityManagerInterface $em, ShopContext $shopContext): Response
    {
        $shop = $this->requireShop($shopContext);
        if ($category->getShop()?->getId() !== $shop->getId()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Catégorie mise à jour.');

            return $this->redirectToRoute('app_category_index');
        }

        return $this->render('category/form.html.twig', ['form' => $form, 'title' => 'Modifier la catégorie']);
    }

    #[Route('/{id}/delete', name: 'app_category_delete', methods: ['POST'])]
    public function delete(Category $category, Request $request, EntityManagerInterface $em, ShopContext $shopContext): Response
    {
        $shop = $this->requireShop($shopContext);
        if ($category->getShop()?->getId() !== $shop->getId()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete'.$category->getId(), $request->request->get('_token'))) {
            $em->remove($category);
            $em->flush();
            $this->addFlash('success', 'Catégorie supprimée.');
        }

        return $this->redirectToRoute('app_category_index');
    }
}
