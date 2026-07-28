<?php

namespace App\Controller;

use App\Entity\Merchant;
use App\Entity\Notification;
use App\Entity\Shop;
use App\Entity\ShopContract;
use App\Entity\Subscription;
use App\Entity\User;
use App\Form\AdminContractDraftType;
use App\Form\AdminMerchantType;
use App\Form\AdminShopType;
use App\Form\ContractSignType;
use App\Repository\ActivityLogRepository;
use App\Repository\MerchantRepository;
use App\Repository\PaymentRepository;
use App\Repository\ShopContractRepository;
use App\Repository\ShopRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\Service\ActivityLogger;
use App\Service\BinaryUploadService;
use App\Service\ContractService;
use App\Service\NotificationService;
use App\Service\SubscriptionBillingService;
use App\Service\SubscriptionEnforcementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('', name: 'admin_dashboard')]
    public function dashboard(
        UserRepository $users,
        MerchantRepository $merchants,
        ShopRepository $shops,
        SubscriptionRepository $subscriptions,
        PaymentRepository $payments,
    ): Response {
        return $this->render('admin/dashboard.html.twig', [
            'userCount' => $users->count([]),
            'merchantCount' => $merchants->count([]),
            'shopCount' => $shops->count([]),
            'activeSubscriptions' => $subscriptions->count(['status' => Subscription::STATUS_ACTIVE]),
            'payments' => $payments->findBy([], ['createdAt' => 'DESC'], 10),
        ]);
    }

    #[Route('/users', name: 'admin_users')]
    public function users(UserRepository $users): Response
    {
        return $this->render('admin/users.html.twig', [
            'users' => $users->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/merchants', name: 'admin_merchants')]
    public function merchants(MerchantRepository $merchants): Response
    {
        return $this->render('admin/merchants.html.twig', [
            'merchants' => $merchants->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/merchants/new', name: 'admin_merchant_new')]
    public function createMerchant(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        UserRepository $users,
        ActivityLogger $logger,
        NotificationService $notifications,
    ): Response {
        $form = $this->createForm(AdminMerchantType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = strtolower(trim((string) $form->get('email')->getData()));
            if ($users->findOneBy(['email' => $email])) {
                $this->addFlash('danger', 'Cet email est déjà utilisé.');
            } else {
                $user = new User();
                $user->setEmail($email);
                $user->setFirstName((string) $form->get('firstName')->getData());
                $user->setLastName((string) $form->get('lastName')->getData());
                $user->setPhone($form->get('phone')->getData());
                $user->setRoles([User::ROLE_MERCHANT]);
                $user->setPassword($passwordHasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));

                $merchant = new Merchant();
                $merchant->setCompanyName((string) $form->get('companyName')->getData());
                $merchant->setLegalForm($form->get('legalForm')->getData());
                $merchant->setTaxId($form->get('taxId')->getData());
                $merchant->setRegistrationNumber($form->get('registrationNumber')->getData());
                $merchant->setRepresentativeTitle($form->get('representativeTitle')->getData());
                $merchant->setAddress($form->get('address')->getData());
                $merchant->setCity($form->get('city')->getData());
                $merchant->setCountry($form->get('country')->getData() ?: 'Sénégal');
                $merchant->setUser($user);
                $user->setMerchant($merchant);

                $plan = (string) $form->get('plan')->getData();
                $subscription = new Subscription();
                $subscription->setMerchant($merchant);
                $subscription->setPlan($plan);
                $subscription->setStatus(Subscription::STATUS_ACTIVE);
                $subscription->setPrice(match ($plan) {
                    Subscription::PLAN_BASIC => '15000',
                    Subscription::PLAN_PRO => '25000',
                    default => '0',
                });
                $merchant->setSubscription($subscription);

                $em->persist($user);
                $em->flush();

                /** @var User $admin */
                $admin = $this->getUser();
                $logger->log('admin.merchant_create', 'Commerçant créé : '.$user->getEmail(), $admin);

                $notifications->notify(
                    $user,
                    Notification::TYPE_INFO,
                    'Compte créé',
                    'Votre compte commerçant a été créé par l\'administration. Connectez-vous avec l\'email fourni.',
                );

                $this->addFlash('success', 'Compte commerçant créé. Il peut se connecter avec son email.');

                return $this->redirectToRoute('admin_merchants');
            }
        }

        return $this->render('admin/merchant_form.html.twig', [
            'form' => $form,
            'title' => 'Créer un compte commerçant',
        ]);
    }

    #[Route('/shops', name: 'admin_shops')]
    public function shops(ShopRepository $shops): Response
    {
        return $this->render('admin/shops.html.twig', [
            'shops' => $shops->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/shops/new', name: 'admin_shop_new')]
    public function createShop(
        Request $request,
        EntityManagerInterface $em,
        ActivityLogger $logger,
        NotificationService $notifications,
        BinaryUploadService $uploader,
        ContractService $contracts,
    ): Response {
        $shop = new Shop();

        $merchantId = $request->query->getInt('merchant');
        if ($merchantId > 0) {
            $merchant = $em->getRepository(Merchant::class)->find($merchantId);
            if ($merchant) {
                $shop->setMerchant($merchant);
            }
        }

        $form = $this->createForm(AdminShopType::class, $shop);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $merchant = $shop->getMerchant();
            if (!$merchant || !$merchant->getUser()) {
                $this->addFlash('danger', 'Commerçant invalide.');

                return $this->redirectToRoute('admin_shop_new');
            }

            $user = $merchant->getUser();
            $user->setFirstName((string) $form->get('personFirstName')->getData());
            $user->setLastName((string) $form->get('personLastName')->getData());
            $user->setPhone($form->get('personPhone')->getData());
            $user->setUpdatedAt(new \DateTimeImmutable());

            $merchant->setCompanyName((string) $form->get('companyName')->getData());
            $merchant->setLegalForm($form->get('legalForm')->getData());
            $merchant->setTaxId($form->get('taxId')->getData());
            $merchant->setRegistrationNumber($form->get('registrationNumber')->getData());
            $merchant->setRepresentativeTitle($form->get('representativeTitle')->getData());
            $merchant->setAddress((string) $form->get('companyAddress')->getData());
            $merchant->setPostalCode($form->get('postalCode')->getData());
            $merchant->setCity((string) $form->get('city')->getData());
            $merchant->setCountry((string) $form->get('country')->getData());

            $file = $form->get('logoFile')->getData();
            if ($file) {
                try {
                    $payload = $uploader->readImage($file);
                    $shop->setLogoData($payload['data']);
                    $shop->setLogoMime($payload['mime']);
                    $shop->setLogoName($payload['name']);
                } catch (\InvalidArgumentException $e) {
                    $this->addFlash('danger', $e->getMessage());
                }
            }

            $em->persist($shop);
            $em->flush();

            /** @var User $admin */
            $admin = $this->getUser();
            $contract = $contracts->createForShop(
                $shop,
                $admin,
                (string) $form->get('plan')->getData(),
                (string) $form->get('price')->getData(),
                (int) $form->get('durationMonths')->getData(),
                (string) $form->get('billingPeriod')->getData(),
            );

            $logger->log(
                'admin.shop_create',
                sprintf('Boutique "%s" créée avec contrat %s', $shop->getName(), $contract->getNumber()),
                $admin,
                $shop
            );

            $notifications->notify(
                $user,
                Notification::TYPE_INFO,
                'Boutique et contrat créés',
                sprintf(
                    'Votre boutique "%s" a été créée. Un contrat (%s) est prêt à être signé.',
                    $shop->getName(),
                    $contract->getNumber()
                ),
                $shop
            );

            $this->addFlash('success', 'Boutique créée. Contrat généré — imprimez-le et faites-le signer.');

            return $this->redirectToRoute('admin_contract_show', ['id' => $contract->getId()]);
        }

        return $this->render('admin/shop_form.html.twig', [
            'form' => $form,
            'title' => 'Créer une boutique + contrat',
        ]);
    }

    #[Route('/contracts', name: 'admin_contracts')]
    public function contracts(ShopContractRepository $contracts): Response
    {
        return $this->render('admin/contracts.html.twig', [
            'contracts' => $contracts->findAllRecent(),
        ]);
    }

    #[Route('/contracts/new', name: 'admin_contract_new')]
    public function newContract(
        Request $request,
        ContractService $contracts,
        NotificationService $notifications,
    ): Response {
        $contract = new ShopContract();
        $contract->setPlan(Subscription::PLAN_BASIC);
        $contract->setBillingPeriod(ShopContract::BILLING_MONTHLY);
        $contract->setPrice('15000');
        $contract->setDurationMonths(12);
        $contract->setSharedWithMerchant(true);

        $form = $this->createForm(AdminContractDraftType::class, $contract);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $admin */
            $admin = $this->getUser();
            try {
                $contracts->saveDraft($contract, $admin, $contract->isSharedWithMerchant());
                if ($contract->isSharedWithMerchant() && $contract->getMerchant()?->getUser()) {
                    $notifications->notify(
                        $contract->getMerchant()->getUser(),
                        Notification::TYPE_INFO,
                        'Nouveau contrat en discussion',
                        sprintf('Un contrat (%s) vous a été transmis pour discussion. Consultez votre tableau de bord.', $contract->getNumber()),
                        $contract->getShop()
                    );
                }
                $this->addFlash('success', 'Contrat préparé. Vous pouvez l\'imprimer et le présenter au commerçant.');

                return $this->redirectToRoute('admin_contract_show', ['id' => $contract->getId()]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('admin/contract_form.html.twig', [
            'form' => $form,
            'title' => 'Préparer un contrat (discussion)',
            'contract' => null,
        ]);
    }

    #[Route('/contracts/{id}/edit', name: 'admin_contract_edit')]
    public function editContract(
        ShopContract $contract,
        Request $request,
        ContractService $contracts,
    ): Response {
        if ($contract->getStatus() === ShopContract::STATUS_SIGNED) {
            $this->addFlash('danger', 'Un contrat signé ne peut plus être modifié.');

            return $this->redirectToRoute('admin_contract_show', ['id' => $contract->getId()]);
        }

        $form = $this->createForm(AdminContractDraftType::class, $contract);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $admin */
            $admin = $this->getUser();
            try {
                $contracts->saveDraft($contract, $admin);
                $this->addFlash('success', 'Contrat mis à jour (PDF régénéré).');

                return $this->redirectToRoute('admin_contract_show', ['id' => $contract->getId()]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('admin/contract_form.html.twig', [
            'form' => $form,
            'title' => 'Modifier le contrat '.$contract->getNumber(),
            'contract' => $contract,
        ]);
    }

    #[Route('/contracts/{id}/share', name: 'admin_contract_share', methods: ['POST'])]
    public function shareContract(
        ShopContract $contract,
        Request $request,
        ContractService $contracts,
        NotificationService $notifications,
    ): Response {
        if ($this->isCsrfTokenValid('share_contract'.$contract->getId(), $request->request->get('_token'))) {
            /** @var User $admin */
            $admin = $this->getUser();
            $contracts->shareWithMerchant($contract, $admin);
            if ($contract->getMerchant()?->getUser()) {
                $notifications->notify(
                    $contract->getMerchant()->getUser(),
                    Notification::TYPE_INFO,
                    'Contrat disponible',
                    sprintf('Le contrat %s est disponible sur votre tableau de bord.', $contract->getNumber()),
                    $contract->getShop()
                );
            }
            $this->addFlash('success', 'Contrat visible sur le dashboard du commerçant.');
        }

        return $this->redirectToRoute('admin_contract_show', ['id' => $contract->getId()]);
    }

    #[Route('/contracts/{id}/send-signature', name: 'admin_contract_send_signature', methods: ['POST'])]
    public function sendContractForSignature(
        ShopContract $contract,
        Request $request,
        ContractService $contracts,
        NotificationService $notifications,
    ): Response {
        if ($this->isCsrfTokenValid('send_contract'.$contract->getId(), $request->request->get('_token'))) {
            /** @var User $admin */
            $admin = $this->getUser();
            $contracts->sendForSignature($contract, $admin);
            if ($contract->getMerchant()?->getUser()) {
                $notifications->notify(
                    $contract->getMerchant()->getUser(),
                    Notification::TYPE_INFO,
                    'Contrat à signer',
                    sprintf('Le contrat %s est prêt pour signature.', $contract->getNumber()),
                    $contract->getShop()
                );
            }
            $this->addFlash('success', 'Contrat envoyé pour signature.');
        }

        return $this->redirectToRoute('admin_contract_show', ['id' => $contract->getId()]);
    }

    #[Route('/contracts/{id}', name: 'admin_contract_show')]
    public function showContract(
        ShopContract $contract,
        Request $request,
        ContractService $contracts,
    ): Response {
        $canSign = !$contract->isDraft()
            && $contract->getStatus() !== ShopContract::STATUS_TERMINATED;
        $signPlatform = $canSign && $contract->getPlatformSignedAt() === null;
        $signMerchant = $canSign && $contract->getMerchantSignedAt() === null;
        $platform = $contracts->getPlatform();

        $form = null;
        if ($signPlatform || $signMerchant) {
            $form = $this->createForm(ContractSignType::class, null, [
                'sign_platform' => $signPlatform,
                'sign_merchant' => $signMerchant,
                'default_platform_signer' => $platform['representative'],
                'default_merchant_signer' => $contract->getMerchant()?->getUser()?->getFullName() ?? '',
                'default_merchant_title' => $contract->getMerchant()?->getRepresentativeTitle() ?: 'Gérant',
            ]);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                /** @var User $admin */
                $admin = $this->getUser();
                if ($signPlatform && $form->has('platformSignedBy')) {
                    $contracts->signPlatform($contract, (string) $form->get('platformSignedBy')->getData(), $admin);
                }
                if ($signMerchant && $form->has('merchantSignedBy')) {
                    $contracts->signMerchant(
                        $contract,
                        (string) $form->get('merchantSignedBy')->getData(),
                        (string) $form->get('merchantSignedTitle')->getData(),
                        $admin
                    );
                }
                $this->addFlash('success', $contract->isFullySigned()
                    ? 'Contrat entièrement signé. PDF mis à jour.'
                    : 'Signature enregistrée.');

                return $this->redirectToRoute('admin_contract_show', ['id' => $contract->getId()]);
            }
        }

        return $this->render('admin/contract_show.html.twig', [
            'contract' => $contract,
            'platform' => $platform,
            'form' => $form,
        ]);
    }

    #[Route('/contracts/{id}/print', name: 'admin_contract_print')]
    public function printContract(ShopContract $contract, ContractService $contracts): Response
    {
        return $this->render('contract/print.html.twig', [
            'contract' => $contract,
            'shop' => $contract->getShop(),
            'merchant' => $contract->getMerchant(),
            'user' => $contract->getMerchant()?->getUser(),
            'platform' => $contracts->getPlatform(),
        ]);
    }

    #[Route('/contracts/{id}/pdf', name: 'admin_contract_pdf')]
    public function contractPdf(ShopContract $contract, ContractService $contracts): Response
    {
        $pdf = $contracts->generatePdf($contract);

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$contract->getNumber().'.pdf"',
        ]);
    }

    #[Route('/shops/{id}/toggle', name: 'admin_shop_toggle', methods: ['POST'])]
    public function toggleShop(Shop $shop, Request $request, EntityManagerInterface $em, ActivityLogger $logger): Response
    {
        if ($this->isCsrfTokenValid('shop_toggle'.$shop->getId(), $request->request->get('_token'))) {
            $shop->setIsActive(!$shop->isActive());
            $em->flush();
            /** @var User $admin */
            $admin = $this->getUser();
            $logger->log('admin.shop_toggle', 'Boutique '.$shop->getName().' '.($shop->isActive() ? 'activée' : 'désactivée'), $admin, $shop);
            $this->addFlash('success', 'Statut boutique mis à jour.');
        }

        return $this->redirectToRoute('admin_shops');
    }

    #[Route('/subscriptions', name: 'admin_subscriptions')]
    public function subscriptions(SubscriptionRepository $subscriptions): Response
    {
        return $this->render('admin/subscriptions.html.twig', [
            'subscriptions' => $subscriptions->findBy([], ['startsAt' => 'DESC']),
        ]);
    }

    #[Route('/subscriptions/enforce', name: 'admin_subscriptions_enforce', methods: ['POST'])]
    public function enforceSubscriptions(
        Request $request,
        SubscriptionEnforcementService $enforcement,
    ): Response {
        if ($this->isCsrfTokenValid('enforce_subs', $request->request->get('_token'))) {
            $stats = $enforcement->enforce(false, false);
            $this->addFlash(
                'success',
                sprintf(
                    'Enforcement : %d notifié(s), %d suspendu(s), %d résilié(s).',
                    $stats['notified'],
                    $stats['suspended'],
                    $stats['terminated']
                )
            );
        }

        return $this->redirectToRoute('admin_subscriptions');
    }

    #[Route('/subscriptions/{id}/payment', name: 'admin_subscription_payment', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function recordSubscriptionPayment(
        Subscription $subscription,
        Request $request,
        SubscriptionBillingService $billing,
    ): Response {
        if ($this->isCsrfTokenValid('pay'.$subscription->getId(), $request->request->get('_token'))) {
            /** @var User $admin */
            $admin = $this->getUser();
            $billing->recordPayment(
                $subscription,
                $admin,
                (string) $request->request->get('method', 'manuel'),
                $request->request->getString('reference') ?: null,
            );
            $this->addFlash('success', 'Paiement enregistré. Accès rétabli si nécessaire.');
        }

        return $this->redirectToRoute('admin_subscriptions');
    }

    #[Route('/activity', name: 'admin_activity')]
    public function activity(ActivityLogRepository $logs): Response
    {
        return $this->render('admin/activity.html.twig', [
            'logs' => $logs->findBy([], ['createdAt' => 'DESC'], 200),
        ]);
    }

    #[Route('/users/{id}/toggle-suspend', name: 'admin_user_toggle_suspend', methods: ['POST'])]
    public function toggleSuspend(User $user, Request $request, EntityManagerInterface $em, ActivityLogger $logger): Response
    {
        if ($this->isCsrfTokenValid('suspend'.$user->getId(), $request->request->get('_token'))) {
            $user->setIsSuspended(!$user->isSuspended());
            $user->setIsActive(!$user->isSuspended());
            $em->flush();
            /** @var User $admin */
            $admin = $this->getUser();
            $logger->log('admin.suspend', ($user->isSuspended() ? 'Suspension' : 'Réactivation').' de '.$user->getEmail(), $admin);
            $this->addFlash('success', 'Statut utilisateur mis à jour.');
        }

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/subscriptions/{id}/plan', name: 'admin_subscription_plan', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function updatePlan(
        Subscription $subscription,
        Request $request,
        EntityManagerInterface $em,
        SubscriptionBillingService $billing,
    ): Response {
        if ($this->isCsrfTokenValid('plan'.$subscription->getId(), $request->request->get('_token'))) {
            $plan = (string) $request->request->get('plan', Subscription::PLAN_FREE);
            $subscription->setPlan($plan);
            $subscription->setPrice(match ($plan) {
                Subscription::PLAN_BASIC => '10000',
                Subscription::PLAN_PRO => '25000',
                default => '0',
            });
            $subscription->setEndsAt((new \DateTimeImmutable())->modify('+30 days'));
            $subscription->setStatus(Subscription::STATUS_ACTIVE);
            $billing->ensureNextDueAt($subscription);
            $em->flush();
            $this->addFlash('success', 'Abonnement mis à jour.');
        }

        return $this->redirectToRoute('admin_subscriptions');
    }
}
