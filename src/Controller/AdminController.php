<?php

namespace App\Controller;

use App\Entity\Merchant;
use App\Entity\Subscription;
use App\Entity\User;
use App\Repository\ActivityLogRepository;
use App\Repository\MerchantRepository;
use App\Repository\PaymentRepository;
use App\Repository\ShopRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

    #[Route('/subscriptions', name: 'admin_subscriptions')]
    public function subscriptions(SubscriptionRepository $subscriptions): Response
    {
        return $this->render('admin/subscriptions.html.twig', [
            'subscriptions' => $subscriptions->findBy([], ['startsAt' => 'DESC']),
        ]);
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

    #[Route('/subscriptions/{id}/plan', name: 'admin_subscription_plan', methods: ['POST'])]
    public function updatePlan(Subscription $subscription, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('plan'.$subscription->getId(), $request->request->get('_token'))) {
            $plan = (string) $request->request->get('plan', Subscription::PLAN_FREE);
            $subscription->setPlan($plan);
            $subscription->setPrice(match ($plan) {
                Subscription::PLAN_BASIC => '19.99',
                Subscription::PLAN_PRO => '49.99',
                default => '0.00',
            });
            $subscription->setEndsAt((new \DateTimeImmutable())->modify('+30 days'));
            $subscription->setStatus(Subscription::STATUS_ACTIVE);
            $em->flush();
            $this->addFlash('success', 'Abonnement mis à jour.');
        }

        return $this->redirectToRoute('admin_subscriptions');
    }
}
