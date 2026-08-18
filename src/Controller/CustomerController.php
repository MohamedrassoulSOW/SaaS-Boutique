<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\CustomerPayment;
use App\Form\CustomerType;
use App\Repository\CustomerPaymentRepository;
use App\Repository\CustomerRepository;
use App\Service\ActivityLogger;
use App\Service\ShopContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
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

    #[Route('/credits', name: 'app_customer_debts')]
    public function debts(CustomerRepository $repo, ShopContext $shopContext): Response
    {
        $shop = $this->requireShop($shopContext);

        return $this->render('customer/debts.html.twig', [
            'shop' => $shop,
            'customers' => $repo->findWithDebt($shop),
        ]);
    }

    #[Route('/{id}/paiement', name: 'app_customer_pay', methods: ['POST'])]
    public function pay(
        Customer $customer,
        Request $request,
        EntityManagerInterface $em,
        ShopContext $shopContext,
        ActivityLogger $logger,
        #[Autowire(service: 'limiter.financial_operations')]
        RateLimiterFactory $rateLimiter,
    ): Response {
        $shop = $this->requireShop($shopContext);
        $this->assertShopData($shopContext, $customer->getShop());
        if (!$this->isCsrfTokenValid('customer_pay'.$customer->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée.');

            return $this->redirectToRoute('app_customer_show', ['id' => $customer->getId()]);
        }
        $limiterKey = 'customer_pay_' . ($this->getShopUser()?->getId() ?? 'anon');
        if (!$rateLimiter->create($limiterKey)->consume(1)->isAccepted()) {
            $this->addFlash('danger', 'Trop de requêtes. Réessayez dans une minute.');

            return $this->redirectToRoute('app_customer_show', ['id' => $customer->getId()]);
        }

        $amount = max(0, (float) $request->request->get('amount', 0));
        if ($amount <= 0) {
            $this->addFlash('warning', 'Indiquez un montant valide.');

            return $this->redirectToRoute('app_customer_show', ['id' => $customer->getId()]);
        }

        $method = (string) $request->request->get('method', CustomerPayment::METHOD_CASH);
        if (!\in_array($method, [
            CustomerPayment::METHOD_CASH,
            CustomerPayment::METHOD_MOBILE,
            CustomerPayment::METHOD_CARD,
            CustomerPayment::METHOD_OTHER,
        ], true)) {
            $method = CustomerPayment::METHOD_CASH;
        }

        try {
            $em->wrapInTransaction(function () use ($customer, $amount, $method, $shop, $em, $request) {
                $balance = (float) $customer->getBalance();
                $paid = min($amount, $balance);
                if ($paid <= 0) {
                    return;
                }

                $payment = new CustomerPayment();
                $payment->setCustomer($customer);
                $payment->setShop($shop);
                $payment->setRecordedBy($this->getShopUser());
                $payment->setAmount(number_format($paid, 2, '.', ''));
                $payment->setMethod($method);
                $payment->setNote(trim((string) $request->request->get('note', '')) ?: null);

                $customer->setBalance(number_format(max(0, $balance - $paid), 2, '.', ''));
                $em->persist($payment);
            });
        } catch (\Throwable) {
            $this->addFlash('danger', 'Erreur lors du paiement. Réessayez.');

            return $this->redirectToRoute('app_customer_show', ['id' => $customer->getId()]);
        }

        $paid = (float) $request->request->get('amount', 0);
        $logger->log(
            'customer.payment',
            sprintf('Paiement crédit %s FCFA — %s', number_format($paid, 0, ',', ' '), $customer->getFullName()),
            $this->getShopUser(),
            $shop
        );
        $this->addFlash('success', sprintf('Paiement de %s FCFA enregistré.', number_format($paid, 0, ',', ' ')));

        return $this->redirectToRoute('app_customer_show', ['id' => $customer->getId()]);
    }

    #[Route('/new', name: 'app_customer_new')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        ShopContext $shopContext,
        ActivityLogger $logger,
    ): Response {
        $shop = $this->requireShop($shopContext);
        $customer = new Customer();
        $customer->setShop($shop);
        $form = $this->createForm(CustomerType::class, $customer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($customer);
            $em->flush();
            $logger->log('customer.create', 'Client créé : '.$customer->getFullName(), $this->getShopUser(), $shop);
            $this->addFlash('success', 'Client ajouté.');

            return $this->redirectToRoute('app_customer_index');
        }

        return $this->render('customer/form.html.twig', ['form' => $form, 'title' => 'Nouveau client']);
    }

    #[Route('/{id}', name: 'app_customer_show')]
    public function show(Customer $customer, ShopContext $shopContext, CustomerPaymentRepository $payments): Response
    {
        $this->requireShop($shopContext);
        $this->assertShopData($shopContext, $customer->getShop());

        return $this->render('customer/show.html.twig', [
            'customer' => $customer,
            'payments' => $payments->findForCustomer($customer),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_customer_edit')]
    public function edit(
        Customer $customer,
        Request $request,
        EntityManagerInterface $em,
        ShopContext $shopContext,
        ActivityLogger $logger,
    ): Response {
        $shop = $this->requireShop($shopContext);
        $this->assertShopData($shopContext, $customer->getShop());

        $form = $this->createForm(CustomerType::class, $customer);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $logger->log('customer.update', 'Client modifié : '.$customer->getFullName(), $this->getShopUser(), $shop);
            $this->addFlash('success', 'Client mis à jour.');

            return $this->redirectToRoute('app_customer_index');
        }

        return $this->render('customer/form.html.twig', ['form' => $form, 'title' => 'Modifier le client']);
    }

    #[Route('/{id}/delete', name: 'app_customer_delete', methods: ['POST'])]
    public function delete(
        Customer $customer,
        Request $request,
        EntityManagerInterface $em,
        ShopContext $shopContext,
        ActivityLogger $logger,
    ): Response {
        $shop = $this->requireShop($shopContext);
        $this->assertShopData($shopContext, $customer->getShop());
        if ($this->isCsrfTokenValid('delete'.$customer->getId(), $request->request->get('_token'))) {
            $label = $customer->getFullName();
            $em->remove($customer);
            $em->flush();
            $logger->log('customer.delete', 'Client supprimé : '.$label, $this->getShopUser(), $shop);
            $this->addFlash('success', 'Client supprimé.');
        }

        return $this->redirectToRoute('app_customer_index');
    }
}
