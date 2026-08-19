<?php

namespace App\Controller;

use App\Entity\Category;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use App\Service\ActivityLogger;
use App\Service\ShopContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/categories')]
#[IsGranted('MODULE_CATEGORIES')]
class CategoryController extends ShopAwareController
{
    #[Route('', name: 'app_category_index')]
    public function index(CategoryRepository $repo, ShopContext $shopContext): Response
    {
        $shop = $this->requireShop($shopContext);

        return $this->render('category/index.html.twig', [
            'categories' => $repo->findBy(['shop' => $shop], ['sortOrder' => 'ASC', 'name' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'app_category_new')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        ShopContext $shopContext,
        ActivityLogger $logger,
    ): Response {
        $shop = $this->requireShop($shopContext);
        $category = new Category();
        $category->setShop($shop);
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($category);
            $em->flush();
            $logger->log('category.create', 'Catégorie créée : '.$category->getName(), $this->getShopUser(), $shop);
            $this->addFlash('success', 'Catégorie créée.');

            return $this->redirectToRoute('app_category_index');
        }

        return $this->render('category/form.html.twig', ['form' => $form, 'title' => 'Nouvelle catégorie']);
    }

    #[Route('/{id}/edit', name: 'app_category_edit')]
    public function edit(
        Category $category,
        Request $request,
        EntityManagerInterface $em,
        ShopContext $shopContext,
        ActivityLogger $logger,
    ): Response {
        $shop = $this->requireShop($shopContext);
        $this->assertShopData($shopContext, $category->getShop());

        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $logger->log('category.update', 'Catégorie modifiée : '.$category->getName(), $this->getShopUser(), $shop);
            $this->addFlash('success', 'Catégorie mise à jour.');

            return $this->redirectToRoute('app_category_index');
        }

        return $this->render('category/form.html.twig', ['form' => $form, 'title' => 'Modifier la catégorie']);
    }

    #[Route('/{id}/delete', name: 'app_category_delete', methods: ['POST'])]
    public function delete(
        Category $category,
        Request $request,
        EntityManagerInterface $em,
        ShopContext $shopContext,
        ActivityLogger $logger,
        #[Autowire(service: 'limiter.admin_operations')]
        RateLimiterFactory $rateLimiter,
    ): Response {
        $shop = $this->requireShop($shopContext);
        $this->assertShopData($shopContext, $category->getShop());
        $limiterKey = 'delete_' . ($this->getShopUser()?->getId() ?? 'anon');
        if (!$rateLimiter->create($limiterKey)->consume(1)->isAccepted()) {
            $this->addFlash('danger', 'Trop de requêtes. Réessayez dans une minute.');

            return $this->redirectToRoute('app_category_index');
        }

        if ($this->isCsrfTokenValid('delete'.$category->getId(), $request->request->get('_token'))) {
            $name = (string) $category->getName();
            $em->remove($category);
            $em->flush();
            $logger->log('category.delete', 'Catégorie supprimée : '.$name, $this->getShopUser(), $shop);
            $this->addFlash('success', 'Catégorie supprimée.');
        }

        return $this->redirectToRoute('app_category_index');
    }
}
