<?php

namespace App\Controller;

use App\Entity\Inventory;
use App\Entity\Merchant;
use App\Entity\Notification;
use App\Entity\Payment;
use App\Entity\PurchaseOrder;
use App\Entity\Sale;
use App\Entity\Shop;
use App\Entity\ShopContract;
use App\Entity\StockMovement;
use App\Entity\Subscription;
use App\Entity\User;
use App\Form\AdminContractDraftType;
use App\Form\AdminMerchantType;
use App\Form\AdminShopType;
use App\Form\AdminUserType;
use App\Form\ContractSignType;
use App\Form\PlatformFiscalSettingsType;
use App\Repository\ActivityLogRepository;
use App\Repository\MerchantRepository;
use App\Repository\PaymentRepository;
use App\Repository\ShopContractRepository;
use App\Repository\ShopRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\Service\ActivityLogger;
use App\Service\AppMailer;
use App\Service\BinaryUploadService;
use App\Service\ContractService;
use App\Service\FiscalService;
use App\Service\NotificationService;
use App\Service\SubscriptionBillingService;
use App\Service\SubscriptionEnforcementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
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
        SubscriptionBillingService $billing,
        EntityManagerInterface $em,
    ): Response {
        $allSubs = $subscriptions->findAll();
        $today = new \DateTimeImmutable('today');

        $paidCount = 0;
        $unpaidCount = 0;
        $cancelledCount = 0;
        $freeCount = 0;
        $activeCount = 0;

        foreach ($allSubs as $sub) {
            if ($sub->getStatus() === Subscription::STATUS_CANCELLED) {
                ++$cancelledCount;
                continue;
            }
            if ($sub->isActive()) {
                ++$activeCount;
            }
            if (!$sub->isBillable()) {
                ++$freeCount;
                continue;
            }
            if ($sub->getDaysOverdue($today) > 0) {
                ++$unpaidCount;
            } else {
                ++$paidCount;
            }
        }

        $monthStart = $today->modify('first day of this month')->setTime(0, 0);
        $prevMonthStart = $monthStart->modify('-1 month');

        $revenueThisMonth = $payments->sumPaidBetween($monthStart, $today->modify('+1 day'));
        $revenuePrevMonth = $payments->sumPaidBetween($prevMonthStart, $monthStart);

        $from12 = $today->modify('first day of this month')->modify('-11 months');
        $paymentsPaid = $payments->findPaidSince($from12);
        $revenueByMonth = $this->seriesByMonth($from12, 12, $paymentsPaid, static fn (Payment $p) => $p->getPaidAt() ?? $p->getCreatedAt(), static fn (Payment $p) => (float) $p->getAmount());

        $cancelledByMonth = $this->seriesByMonth(
            $from12,
            12,
            array_values(array_filter(
                $allSubs,
                static fn (Subscription $s) => $s->getStatus() === Subscription::STATUS_CANCELLED
            )),
            static fn (Subscription $s) => $s->getLastEnforcementAt() ?? $s->getEndsAt(),
            static fn () => 1.0
        );

        $planLabels = [
            Subscription::PLAN_FREE => 'Gratuit',
            Subscription::PLAN_BASIC => 'Basique',
            Subscription::PLAN_PRO => 'Pro',
        ];
        $byPlan = $subscriptions->countByPlan();
        $byStatus = $subscriptions->countByStatus();

        $pendingPayments = $payments->count(['status' => Payment::STATUS_PENDING]);

        return $this->render('admin/dashboard.html.twig', [
            'userCount' => $users->count([]),
            'merchantCount' => $merchants->count([]),
            'shopCount' => $shops->count([]),
            'activeSubscriptions' => $activeCount,
            'paidSubscribers' => $paidCount,
            'unpaidSubscribers' => $unpaidCount,
            'cancelledSubscribers' => $cancelledCount,
            'freeSubscribers' => $freeCount,
            'pendingPayments' => $pendingPayments,
            'revenueThisMonth' => $revenueThisMonth,
            'revenuePrevMonth' => $revenuePrevMonth,
            'payments' => $payments->findBy([], ['createdAt' => 'DESC'], 10),
            'paymentStatusLabels' => [
                Payment::STATUS_PAID => 'Payé',
                Payment::STATUS_PENDING => 'En attente',
                Payment::STATUS_FAILED => 'Échoué',
            ],
            'chartPayload' => [
                'revenueByMonth' => $revenueByMonth,
                'cancelledByMonth' => $cancelledByMonth,
                'paymentHealth' => [
                    'labels' => ['Payés', 'Gratuits', 'Impayés', 'Résiliés'],
                    'values' => [$paidCount, $freeCount, $unpaidCount, $cancelledCount],
                ],
                'plans' => [
                    'labels' => array_values(array_map(static fn (string $k) => $planLabels[$k] ?? $k, array_keys($byPlan))),
                    'values' => array_values($byPlan),
                ],
                'statuses' => [
                    'labels' => ['Actif', 'Expiré', 'Résilié'],
                    'values' => [
                        $byStatus[Subscription::STATUS_ACTIVE] ?? 0,
                        $byStatus[Subscription::STATUS_EXPIRED] ?? 0,
                        $byStatus[Subscription::STATUS_CANCELLED] ?? 0,
                    ],
                ],
            ],
        ]);
    }

    /**
     * @template T
     * @param list<T> $items
     * @param callable(T): (?\DateTimeInterface) $dateGetter
     * @param callable(T): float $valueGetter
     * @return list<array{date: string, label: string, value: float}>
     */
    private function seriesByMonth(\DateTimeImmutable $from, int $months, array $items, callable $dateGetter, callable $valueGetter): array
    {
        $bucket = [];
        $cursor = $from->modify('first day of this month')->setTime(0, 0);
        for ($i = 0; $i < $months; ++$i) {
            $key = $cursor->format('Y-m');
            $bucket[$key] = [
                'date' => $key,
                'label' => $cursor->format('m/Y'),
                'value' => 0.0,
            ];
            $cursor = $cursor->modify('+1 month');
        }

        foreach ($items as $item) {
            $date = $dateGetter($item);
            if (!$date instanceof \DateTimeInterface) {
                continue;
            }
            $key = $date->format('Y-m');
            if (!isset($bucket[$key])) {
                continue;
            }
            $bucket[$key]['value'] += $valueGetter($item);
        }

        return array_values($bucket);
    }

    #[Route('/users', name: 'admin_users')]
    public function users(Request $request, UserRepository $users): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;
        $total = $users->count([]);
        $pages = (int) ceil($total / $perPage);

        return $this->render('admin/users.html.twig', [
            'users' => $users->findBy([], ['createdAt' => 'DESC'], $perPage, $offset),
            'page' => $page,
            'pages' => $pages,
        ]);
    }

    #[Route('/admins', name: 'admin_admins')]
    public function adminList(Request $request, UserRepository $users): Response
    {
        $admins = $users->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_ADMIN%')
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/admins.html.twig', [
            'admins' => $admins,
        ]);
    }

    #[Route('/admins/new', name: 'admin_admin_new')]
    public function createAdmin(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        UserRepository $users,
        ActivityLogger $logger,
        AppMailer $mailer,
        #[Autowire(service: 'limiter.admin_operations')]
        RateLimiterFactory $rateLimiter,
    ): Response {
        $form = $this->createForm(AdminUserType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = strtolower(trim((string) $form->get('email')->getData()));
            if ($users->findOneBy(['email' => $email])) {
                $this->addFlash('danger', 'Cet email est déjà utilisé.');
            } else {
                $plainPassword = (string) ($form->get('plainPassword')->getData() ?? '');
                if ($plainPassword === '') {
                    $plainPassword = bin2hex(random_bytes(16));
                }
                $user = new User();
                $user->setEmail($email);
                $user->setFirstName((string) $form->get('firstName')->getData());
                $user->setLastName((string) $form->get('lastName')->getData());
                $user->setPhone($form->get('phone')->getData());
                $user->setRoles([User::ROLE_ADMIN]);
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));

                $inviteToken = $mailer->issueInviteToken($user);

                $em->persist($user);
                $em->flush();

                /** @var User $admin */
                $admin = $this->getUser();
                $logger->log('admin.admin_create', 'Administrateur créé : '.$user->getEmail(), $admin);

                $mailer->sendWelcomeAdmin($user, $inviteToken);

                $this->addFlash('success', 'Compte administrateur créé. Un email d\'invitation a été envoyé.');

                return $this->redirectToRoute('admin_admins');
            }
        }

        return $this->render('admin/admin_form.html.twig', [
            'form' => $form,
            'title' => 'Créer un compte administrateur',
        ]);
    }

    #[Route('/admins/{id}/delete', name: 'admin_admin_delete', methods: ['POST'])]
    public function deleteAdmin(
        User $user,
        Request $request,
        EntityManagerInterface $em,
        ActivityLogger $logger,
        #[Autowire(service: 'limiter.admin_operations')]
        RateLimiterFactory $rateLimiter,
    ): Response {
        if (!$user->isAdmin()) {
            $this->addFlash('danger', 'Ce compte n\'est pas un administrateur.');

            return $this->redirectToRoute('admin_admins');
        }
        if ($user->getId() === $this->getUser()?->getId()) {
            $this->addFlash('danger', 'Vous ne pouvez pas supprimer votre propre compte.');

            return $this->redirectToRoute('admin_admins');
        }
        if (!$this->isCsrfTokenValid('delete_admin'.$user->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('admin_admins');
        }
        if (!$rateLimiter->create('admin_delete')->consume(1)->isAccepted()) {
            $this->addFlash('danger', 'Trop de requêtes. Réessayez dans une minute.');

            return $this->redirectToRoute('admin_admins');
        }

        $email = $user->getEmail();

        try {
            $em->createQuery('DELETE FROM App\Entity\Notification n WHERE n.user = :user')
                ->setParameter('user', $user)
                ->execute();
            $em->createQuery('UPDATE App\Entity\ActivityLog a SET a.user = NULL WHERE a.user = :user')
                ->setParameter('user', $user)
                ->execute();
            $em->remove($user);
            $em->flush();

            /** @var User $admin */
            $admin = $this->getUser();
            $logger->log('admin.admin_delete', 'Administrateur supprimé : '.$email, $admin);

            $this->addFlash('success', 'Compte administrateur supprimé.');
        } catch (\Throwable) {
            $this->addFlash('danger', 'Une erreur est survenue lors de la suppression.');
        }

        return $this->redirectToRoute('admin_admins');
    }

    #[Route('/admins/{id}/toggle-suspend', name: 'admin_admin_toggle_suspend', methods: ['POST'])]
    public function toggleSuspendAdmin(
        User $user,
        Request $request,
        EntityManagerInterface $em,
        ActivityLogger $logger,
        AppMailer $mailer,
    ): Response {
        if (!$user->isAdmin()) {
            $this->addFlash('danger', 'Ce compte n\'est pas un administrateur.');

            return $this->redirectToRoute('admin_admins');
        }
        if ($user->getId() === $this->getUser()?->getId()) {
            $this->addFlash('danger', 'Vous ne pouvez pas suspendre votre propre compte.');

            return $this->redirectToRoute('admin_admins');
        }
        if ($this->isCsrfTokenValid('suspend_admin'.$user->getId(), $request->request->get('_token'))) {
            $user->setIsSuspended(!$user->isSuspended());
            $user->setIsActive(!$user->isSuspended());
            $em->flush();
            /** @var User $admin */
            $admin = $this->getUser();
            $logger->log('admin.suspend', ($user->isSuspended() ? 'Suspension' : 'Réactivation').' de l\'admin '.$user->getEmail(), $admin);
            $mailer->sendAccountStatus($user, $user->isSuspended());
            $this->addFlash('success', 'Statut administrateur mis à jour. Un email a été envoyé.');
        }

        return $this->redirectToRoute('admin_admins');
    }

    #[Route('/merchants', name: 'admin_merchants')]
    public function merchants(Request $request, MerchantRepository $merchants): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;
        $total = $merchants->count([]);
        $pages = (int) ceil($total / $perPage);

        return $this->render('admin/merchants.html.twig', [
            'merchants' => $merchants->findBy([], ['createdAt' => 'DESC'], $perPage, $offset),
            'page' => $page,
            'pages' => $pages,
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
        AppMailer $mailer,
    ): Response {
        $form = $this->createForm(AdminMerchantType::class, null, [
            'is_edit' => false,
            'merchant' => null,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = strtolower(trim((string) $form->get('email')->getData()));
            if ($users->findOneBy(['email' => $email])) {
                $this->addFlash('danger', 'Cet email est déjà utilisé.');
            } else {
                $plainPassword = (string) ($form->get('plainPassword')->getData() ?? '');
                if ($plainPassword === '') {
                    $plainPassword = bin2hex(random_bytes(16));
                }
                $user = new User();
                $user->setEmail($email);
                $user->setFirstName((string) $form->get('firstName')->getData());
                $user->setLastName((string) $form->get('lastName')->getData());
                $user->setPhone($form->get('phone')->getData());
                $user->setRoles([User::ROLE_MERCHANT]);
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));

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
                $subscription->setPrice(Subscription::catalogPrice($plan));
                $merchant->setSubscription($subscription);

                $inviteToken = $mailer->issueInviteToken($user);

                $em->persist($user);
                $em->flush();

                /** @var User $admin */
                $admin = $this->getUser();
                $logger->log('admin.merchant_create', 'Entrepreneur créé : '.$user->getEmail(), $admin);

                $notifications->notify(
                    $user,
                    Notification::TYPE_INFO,
                    'Compte créé',
                    'Votre compte entrepreneur a été créé. Définissez votre mot de passe via l\'email d\'invitation.',
                );

                $mailer->sendWelcomeMerchant($user, $inviteToken);

                $this->addFlash('success', 'Compte entrepreneur créé. Un email d\'invitation (sans mot de passe en clair) a été envoyé.');

                return $this->redirectToRoute('admin_merchants');
            }
        }

        return $this->render('admin/merchant_form.html.twig', [
            'form' => $form,
            'title' => 'Créer un compte entrepreneur',
            'merchant' => null,
            'is_edit' => false,
        ]);
    }

    #[Route('/merchants/{id}/edit', name: 'admin_merchant_edit')]
    public function editMerchant(
        Merchant $merchant,
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        UserRepository $users,
        ActivityLogger $logger,
        AppMailer $mailer,
    ): Response {
        $user = $merchant->getUser();
        if (!$user) {
            throw $this->createNotFoundException('Compte utilisateur introuvable.');
        }

        $form = $this->createForm(AdminMerchantType::class, null, [
            'is_edit' => true,
            'merchant' => $merchant,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = strtolower(trim((string) $form->get('email')->getData()));
            $existing = $users->findOneBy(['email' => $email]);
            if ($existing && $existing->getId() !== $user->getId()) {
                $this->addFlash('danger', 'Cet email est déjà utilisé.');
            } else {
                $user->setEmail($email);
                $user->setFirstName((string) $form->get('firstName')->getData());
                $user->setLastName((string) $form->get('lastName')->getData());
                $user->setPhone($form->get('phone')->getData());
                $user->setIsActive((bool) $form->get('isActive')->getData());
                $user->setUpdatedAt(new \DateTimeImmutable());

                $plainPassword = $form->get('plainPassword')->getData();
                if (\is_string($plainPassword) && $plainPassword !== '') {
                    $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
                }

                $merchant->setCompanyName((string) $form->get('companyName')->getData());
                $merchant->setLegalForm($form->get('legalForm')->getData());
                $merchant->setTaxId($form->get('taxId')->getData());
                $merchant->setRegistrationNumber($form->get('registrationNumber')->getData());
                $merchant->setRepresentativeTitle($form->get('representativeTitle')->getData());
                $merchant->setAddress($form->get('address')->getData());
                $merchant->setCity($form->get('city')->getData());
                $merchant->setCountry($form->get('country')->getData() ?: 'Sénégal');

                $plan = (string) $form->get('plan')->getData();
                $subscription = $merchant->getSubscription();
                if (!$subscription) {
                    $subscription = new Subscription();
                    $subscription->setMerchant($merchant);
                    $merchant->setSubscription($subscription);
                    $em->persist($subscription);
                }
                $subscription->setPlan($plan);
                $subscription->setPrice(Subscription::catalogPrice($plan));

                $em->flush();

                /** @var User $admin */
                $admin = $this->getUser();
                $logger->log('admin.merchant_update', 'Entrepreneur modifié : '.$user->getEmail(), $admin);

                if (\is_string($plainPassword) && $plainPassword !== '') {
                    $mailer->sendPasswordChanged($user);
                }

                $this->addFlash('success', 'Entrepreneur enregistré.');

                return $this->redirectToRoute('admin_merchants');
            }
        }

        return $this->render('admin/merchant_form.html.twig', [
            'form' => $form,
            'title' => 'Modifier l\'entrepreneur',
            'merchant' => $merchant,
            'is_edit' => true,
        ]);
    }

    #[Route('/merchants/{id}/delete', name: 'admin_merchant_delete', methods: ['POST'])]
    public function deleteMerchant(
        Merchant $merchant,
        Request $request,
        EntityManagerInterface $em,
        ActivityLogger $logger,
        #[Autowire(service: 'limiter.admin_operations')]
        RateLimiterFactory $rateLimiter,
    ): Response {
        if (!$this->isCsrfTokenValid('delete_merchant'.$merchant->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('admin_merchants');
        }
        if (!$rateLimiter->create('admin_delete')->consume(1)->isAccepted()) {
            $this->addFlash('danger', 'Trop de requêtes. Réessayez dans une minute.');

            return $this->redirectToRoute('admin_merchants');
        }

        $user = $merchant->getUser();
        $label = $user?->getEmail() ?? $merchant->getCompanyName() ?? 'inconnu';

        try {
            $em->wrapInTransaction(function () use ($em, $merchant) {
                $this->purgeMerchant($em, $merchant);
            });

            /** @var User $admin */
            $admin = $this->getUser();
            $logger->log('admin.merchant_delete', 'Entrepreneur supprimé : '.$label, $admin);

            $this->addFlash('success', 'Entrepreneur supprimé.');
        } catch (\Throwable) {
            $this->addFlash('danger', 'Une erreur est survenue lors de la suppression. L\'entité est peut-être liée à des données existantes.');
        }

        return $this->redirectToRoute('admin_merchants');
    }

    /**
     * Supprime un entrepreneur et ses données liées (entreprises, ventes, abonnement…).
     */
    private function purgeMerchant(EntityManagerInterface $em, Merchant $merchant): void
    {
        $user = $merchant->getUser();
        $shops = $merchant->getShops()->toArray();

        foreach ($shops as $shop) {
            $this->purgeShop($em, $shop);
        }

        $subscription = $merchant->getSubscription();
        if ($subscription) {
            foreach ($subscription->getPayments()->toArray() as $payment) {
                $em->remove($payment);
            }
            $em->remove($subscription);
            $merchant->setSubscription(null);
        }

        if ($user) {
            $em->createQuery('DELETE FROM App\Entity\Notification n WHERE n.user = :user')
                ->setParameter('user', $user)
                ->execute();
            $em->createQuery('UPDATE App\Entity\ActivityLog a SET a.user = NULL WHERE a.user = :user')
                ->setParameter('user', $user)
                ->execute();

            $em->remove($user);
        } else {
            $em->remove($merchant);
        }
    }

    private function purgeShop(EntityManagerInterface $em, Shop $shop): void
    {
        if ($shop->getId() === null) {
            return;
        }

        $em->createQuery('DELETE FROM App\Entity\Notification n WHERE n.shop = :shop')
            ->setParameter('shop', $shop)
            ->execute();
        $em->createQuery('UPDATE App\Entity\ActivityLog a SET a.shop = NULL WHERE a.shop = :shop')
            ->setParameter('shop', $shop)
            ->execute();

        foreach ($em->getRepository(Sale::class)->findBy(['shop' => $shop]) as $sale) {
            $em->remove($sale);
        }

        foreach ($em->getRepository(StockMovement::class)->findBy(['shop' => $shop]) as $movement) {
            $em->remove($movement);
        }

        foreach ($em->getRepository(Inventory::class)->findBy(['shop' => $shop]) as $inventory) {
            $em->remove($inventory);
        }

        foreach ($em->getRepository(PurchaseOrder::class)->findBy(['shop' => $shop]) as $order) {
            $em->remove($order);
        }

        $memberUsers = [];
        foreach ($shop->getMembers()->toArray() as $member) {
            $memberUser = $member->getUser();
            if ($memberUser && $memberUser->isEmployee()) {
                $memberUsers[$memberUser->getId()] = $memberUser;
            }
            $em->remove($member);
        }

        $contract = $shop->getContract();
        if ($contract) {
            $em->remove($contract);
        }

        foreach ($shop->getProducts()->toArray() as $product) {
            $em->remove($product);
        }
        foreach ($shop->getCategories()->toArray() as $category) {
            $em->remove($category);
        }
        foreach ($shop->getSuppliers()->toArray() as $supplier) {
            $em->remove($supplier);
        }
        foreach ($shop->getCustomers()->toArray() as $customer) {
            $em->remove($customer);
        }

        $em->remove($shop);

        foreach ($memberUsers as $memberUser) {
            $remaining = 0;
            foreach ($memberUser->getShopMemberships() as $membership) {
                if ($membership->getShop()?->getId() !== $shop->getId()) {
                    ++$remaining;
                }
            }
            if ($remaining === 0) {
                $em->createQuery('DELETE FROM App\Entity\Notification n WHERE n.user = :user')
                    ->setParameter('user', $memberUser)
                    ->execute();
                $em->createQuery('UPDATE App\Entity\ActivityLog a SET a.user = NULL WHERE a.user = :user')
                    ->setParameter('user', $memberUser)
                    ->execute();
                $em->remove($memberUser);
            }
        }
    }

    #[Route('/shops', name: 'admin_shops')]
    public function shops(Request $request, ShopRepository $shops): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;
        $total = $shops->count([]);
        $pages = (int) ceil($total / $perPage);

        return $this->render('admin/shops.html.twig', [
            'shops' => $shops->findBy([], ['createdAt' => 'DESC'], $perPage, $offset),
            'page' => $page,
            'pages' => $pages,
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
        AppMailer $mailer,
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
                $this->addFlash('danger', 'Entrepreneur invalide.');

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
                    $this->addFlash('danger', 'Erreur lors du téléchargement du logo. Vérifiez le format et la taille.');
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
                sprintf('Entreprise "%s" créée avec contrat %s', $shop->getName(), $contract->getNumber()),
                $admin,
                $shop
            );

            $notifications->notify(
                $user,
                Notification::TYPE_INFO,
                'Entreprise et contrat créés',
                sprintf(
                    'Votre entreprise "%s" a été créée. Un contrat (%s) est prêt à être signé.',
                    $shop->getName(),
                    $contract->getNumber()
                ),
                $shop
            );

            $mailer->sendShopCreated($user, $shop, $contract);

            $this->addFlash('success', 'Entreprise créée. Contrat généré — un email a été envoyé à l\'entrepreneur.');

            return $this->redirectToRoute('admin_contract_show', ['id' => $contract->getId()]);
        }

        return $this->render('admin/shop_form.html.twig', [
            'form' => $form,
            'title' => 'Créer une entreprise + contrat',
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
        AppMailer $mailer,
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
                    $merchantUser = $contract->getMerchant()->getUser();
                    $notifications->notify(
                        $merchantUser,
                        Notification::TYPE_INFO,
                        'Nouveau contrat en discussion',
                        sprintf('Un contrat (%s) vous a été transmis pour discussion. Consultez votre tableau de bord.', $contract->getNumber()),
                        $contract->getShop()
                    );
                    $mailer->sendContractNotice($merchantUser, $contract, 'discussion');
                }
                $this->addFlash('success', 'Contrat préparé. Vous pouvez l\'imprimer et le présenter à l\'entrepreneur.');

                return $this->redirectToRoute('admin_contract_show', ['id' => $contract->getId()]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('danger', 'Erreur lors de la sauvegarde du contrat. Vérifiez les données saisies.');
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
                $this->addFlash('danger', 'Erreur lors de la mise à jour du contrat. Vérifiez les données saisies.');
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
        AppMailer $mailer,
    ): Response {
        if ($this->isCsrfTokenValid('share_contract'.$contract->getId(), $request->request->get('_token'))) {
            /** @var User $admin */
            $admin = $this->getUser();
            $contracts->shareWithMerchant($contract, $admin);
            if ($contract->getMerchant()?->getUser()) {
                $merchantUser = $contract->getMerchant()->getUser();
                $notifications->notify(
                    $merchantUser,
                    Notification::TYPE_INFO,
                    'Contrat disponible',
                    sprintf('Le contrat %s est disponible sur votre tableau de bord.', $contract->getNumber()),
                    $contract->getShop()
                );
                $mailer->sendContractNotice($merchantUser, $contract, 'shared');
            }
            $this->addFlash('success', 'Contrat visible sur le dashboard de l\'entrepreneur.');
        }

        return $this->redirectToRoute('admin_contract_show', ['id' => $contract->getId()]);
    }

    #[Route('/contracts/{id}/send-signature', name: 'admin_contract_send_signature', methods: ['POST'])]
    public function sendContractForSignature(
        ShopContract $contract,
        Request $request,
        ContractService $contracts,
        NotificationService $notifications,
        AppMailer $mailer,
    ): Response {
        if ($this->isCsrfTokenValid('send_contract'.$contract->getId(), $request->request->get('_token'))) {
            /** @var User $admin */
            $admin = $this->getUser();
            $contracts->sendForSignature($contract, $admin);
            if ($contract->getMerchant()?->getUser()) {
                $merchantUser = $contract->getMerchant()->getUser();
                $notifications->notify(
                    $merchantUser,
                    Notification::TYPE_INFO,
                    'Contrat à signer',
                    sprintf('Le contrat %s est prêt pour signature.', $contract->getNumber()),
                    $contract->getShop()
                );
                $mailer->sendContractNotice($merchantUser, $contract, 'signature');
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
            'Content-Disposition' => \Symfony\Component\HttpFoundation\HeaderUtils::makeDisposition('inline', $contract->getNumber().'.pdf'),
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
            $logger->log('admin.shop_toggle', 'Entreprise '.$shop->getName().' '.($shop->isActive() ? 'activée' : 'désactivée'), $admin, $shop);
            $this->addFlash('success', 'Statut entreprise mis à jour.');
        }

        return $this->redirectToRoute('admin_shops');
    }

    #[Route('/subscriptions', name: 'admin_subscriptions')]
    public function subscriptions(Request $request, SubscriptionRepository $subscriptions): Response
    {
        $filter = (string) $request->query->get('filter', 'all');
        $all = $subscriptions->findBy([], ['startsAt' => 'DESC']);
        $today = new \DateTimeImmutable('today');

        $filtered = array_values(array_filter($all, static function (Subscription $s) use ($filter, $today): bool {
            if ($filter === 'all') {
                return true;
            }
            if ($filter === 'cancelled') {
                return $s->getStatus() === Subscription::STATUS_CANCELLED;
            }

            $isUnpaid = $s->isBillable()
                && $s->getStatus() !== Subscription::STATUS_CANCELLED
                && $s->getDaysOverdue($today) > 0;
            $isPaid = $s->getStatus() !== Subscription::STATUS_CANCELLED && !$isUnpaid;

            return match ($filter) {
                'paid' => $isPaid,
                'unpaid' => $isUnpaid,
                default => true,
            };
        }));

        $counts = [
            'all' => \count($all),
            'paid' => 0,
            'unpaid' => 0,
            'cancelled' => 0,
        ];
        foreach ($all as $s) {
            if ($s->getStatus() === Subscription::STATUS_CANCELLED) {
                ++$counts['cancelled'];
            } elseif (!$s->isBillable() || $s->getDaysOverdue($today) <= 0) {
                ++$counts['paid'];
            } else {
                ++$counts['unpaid'];
            }
        }

        return $this->render('admin/subscriptions.html.twig', [
            'subscriptions' => $filtered,
            'filter' => $filter,
            'counts' => $counts,
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
            $status = (string) $request->request->get('payment_status', 'paid');
            $allowedStatuses = ['paid', 'unpaid', 'cancelled'];
            if (!in_array($status, $allowedStatuses, true)) {
                $status = 'paid';
            }
            $reference = $request->request->getString('reference') ?: null;

            try {
                if ($status === 'unpaid') {
                    if ($subscription->getStatus() === Subscription::STATUS_CANCELLED) {
                        $subscription->setStatus(Subscription::STATUS_ACTIVE);
                    }
                    $billing->markUnpaid($subscription, $admin, $reference);
                    $this->addFlash('warning', 'Abonnement marqué comme impayé.');
                } elseif ($status === 'cancelled') {
                    $billing->markCancelled($subscription, $admin, $reference);
                    $this->addFlash('danger', 'Abonnement résilié.');
                } else {
                    if ($subscription->getStatus() === Subscription::STATUS_CANCELLED) {
                        $subscription->setStatus(Subscription::STATUS_ACTIVE);
                    }
                    $method = (string) $request->request->get('method', 'manuel');
                    $allowedMethods = ['cash', 'card', 'mobile', 'transfer', 'other', 'manuel'];
                    if (!in_array($method, $allowedMethods, true)) {
                        $method = 'cash';
                    }
                    $billing->recordPayment(
                        $subscription,
                        $admin,
                        $method,
                        $reference,
                    );
                    $this->addFlash('success', 'Abonnement marqué comme payé. Accès rétabli si nécessaire.');
                }
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('danger', 'Opération refusée. Vérifiez les données et réessayez.');
            }
        }

        $filter = (string) $request->request->get('filter', 'all');

        return $this->redirectToRoute('admin_subscriptions', $filter !== 'all' ? ['filter' => $filter] : []);
    }

    #[Route('/activity', name: 'admin_activity')]
    public function activity(ActivityLogRepository $logs): Response
    {
        return $this->render('admin/activity.html.twig', [
            'logs' => $logs->findBy([], ['createdAt' => 'DESC'], 200),
        ]);
    }

    #[Route('/users/{id}/toggle-suspend', name: 'admin_user_toggle_suspend', methods: ['POST'])]
    public function toggleSuspend(User $user, Request $request, EntityManagerInterface $em, ActivityLogger $logger, AppMailer $mailer): Response
    {
        if ($this->isCsrfTokenValid('suspend'.$user->getId(), $request->request->get('_token'))) {
            $user->setIsSuspended(!$user->isSuspended());
            $user->setIsActive(!$user->isSuspended());
            $em->flush();
            /** @var User $admin */
            $admin = $this->getUser();
            $logger->log('admin.suspend', ($user->isSuspended() ? 'Suspension' : 'Réactivation').' de '.$user->getEmail(), $admin);
            $mailer->sendAccountStatus($user, $user->isSuspended());
            $this->addFlash('success', 'Statut utilisateur mis à jour. Un email a été envoyé.');
        }

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/subscriptions/{id}/plan', name: 'admin_subscription_plan', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function updatePlan(
        Subscription $subscription,
        Request $request,
        EntityManagerInterface $em,
        SubscriptionBillingService $billing,
        ActivityLogger $logger,
    ): Response {
        if ($this->isCsrfTokenValid('plan'.$subscription->getId(), $request->request->get('_token'))) {
            $plan = (string) $request->request->get('plan', Subscription::PLAN_FREE);
            $allowedPlans = [Subscription::PLAN_FREE, Subscription::PLAN_BASIC, Subscription::PLAN_PRO];
            if (!in_array($plan, $allowedPlans, true)) {
                $plan = Subscription::PLAN_FREE;
            }
            $subscription->setPlan($plan);
            $subscription->setPrice(Subscription::catalogPrice($plan));
            $subscription->setEndsAt((new \DateTimeImmutable())->modify('+30 days'));
            $subscription->setStatus(Subscription::STATUS_ACTIVE);
            $billing->ensureNextDueAt($subscription);
            $em->flush();

            /** @var User $admin */
            $admin = $this->getUser();
            $merchantEmail = $subscription->getMerchant()?->getUser()?->getEmail() ?? 'n/a';
            $logger->log('admin.plan_update', 'Formule '.$plan.' pour '.$merchantEmail, $admin);

            $this->addFlash('success', 'Abonnement mis à jour.');
        }

        return $this->redirectToRoute('admin_subscriptions');
    }

    #[Route('/fiscalite', name: 'admin_fiscal')]
    public function fiscalSettings(
        Request $request,
        EntityManagerInterface $em,
        FiscalService $fiscal,
        ActivityLogger $logger,
    ): Response {
        $settings = $fiscal->getPlatformSettings();
        $form = $this->createForm(PlatformFiscalSettingsType::class, $settings);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $settings->setDefaultVatRate(number_format((float) $settings->getDefaultVatRate(), 2, '.', ''));
            $settings->touch();
            $em->flush();

            /** @var User $admin */
            $admin = $this->getUser();
            $logger->log('admin.fiscal_update', 'Paramètres fiscaux plateforme mis à jour', $admin);
            $this->addFlash('success', 'Fiscalité plateforme enregistrée.');

            return $this->redirectToRoute('admin_fiscal');
        }

        return $this->render('admin/fiscal.html.twig', [
            'form' => $form,
            'settings' => $settings,
        ]);
    }
}
