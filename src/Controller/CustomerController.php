<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\User;
use App\Form\CustomerType;
use App\Repository\CustomerRepository;
use App\Service\ShopContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/customers')]
#[IsGranted('MODULE_CUSTOMERS')]
class CustomerController extends ShopAwareController
{
    #[Route('', name: 'app_customer_index')]
    public function index(CustomerRepository $repo, ShopContext $shopContext): Response
    {
        $shop = $this->requireShop($shopContext);

        return $this->render('customer/index.html.twig', [
            'customers' => $repo->findBy(['shop' => $shop], ['lastName' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'app_customer_new')]
    public function new(Request $request, EntityManagerInterface $em, ShopContext $shopContext): Response
    {
        $shop = $this->requireShop($shopContext);
        $customer = new Customer();
        $customer->setShop($shop);
        $form = $this->createForm(CustomerType::class, $customer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($customer);
            $em->flush();
            $this->addFlash('success', 'Client ajouté.');

            return $this->redirectToRoute('app_customer_index');
        }

        return $this->render('customer/form.html.twig', ['form' => $form, 'title' => 'Nouveau client']);
    }

    #[Route('/{id}', name: 'app_customer_show')]
    public function show(Customer $customer, ShopContext $shopContext): Response
    {
        $shop = $this->requireShop($shopContext);
        $this->assertShopData($shopContext, $customer->getShop());

        return $this->render('customer/show.html.twig', ['customer' => $customer]);
    }

    #[Route('/{id}/edit', name: 'app_customer_edit')]
    public function edit(Customer $customer, Request $request, EntityManagerInterface $em, ShopContext $shopContext): Response
    {
        $shop = $this->requireShop($shopContext);
        $this->assertShopData($shopContext, $customer->getShop());

        $form = $this->createForm(CustomerType::class, $customer);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Client mis à jour.');

            return $this->redirectToRoute('app_customer_index');
        }

        return $this->render('customer/form.html.twig', ['form' => $form, 'title' => 'Modifier le client']);
    }

    #[Route('/{id}/delete', name: 'app_customer_delete', methods: ['POST'])]
    public function delete(Customer $customer, Request $request, EntityManagerInterface $em, ShopContext $shopContext): Response
    {
        $shop = $this->requireShop($shopContext);
        $this->assertShopData($shopContext, $customer->getShop());
        if ($this->isCsrfTokenValid('delete'.$customer->getId(), $request->request->get('_token'))) {
            $em->remove($customer);
            $em->flush();
            $this->addFlash('success', 'Client supprimé.');
        }

        return $this->redirectToRoute('app_customer_index');
    }
}
