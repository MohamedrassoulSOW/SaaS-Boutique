<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\ShopContract;
use App\Entity\Subscription;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;

class SubscriptionEnforcementService
{
    public function __construct(
        private SubscriptionRepository $subscriptions,
        private EntityManagerInterface $em,
        private NotificationService $notifications,
        private ActivityLogger $activityLogger,
        private SubscriptionBillingService $billing,
        private AppMailer $appMailer,
    ) {
    }

    /**
     * @return array{notified: int, suspended: int, terminated: int, skipped: int}
     */
    public function enforce(bool $dryRun = false, bool $notifyOnly = false): array
    {
        $stats = ['notified' => 0, 'suspended' => 0, 'terminated' => 0, 'skipped' => 0];
        $today = new \DateTimeImmutable('today');

        /** @var Subscription[] $list */
        $list = $this->subscriptions->createQueryBuilder('s')
            ->andWhere('s.status IN (:statuses)')
            ->setParameter('statuses', [Subscription::STATUS_ACTIVE, Subscription::STATUS_EXPIRED])
            ->getQuery()
            ->getResult();

        foreach ($list as $subscription) {
            if (!$subscription->isBillable()) {
                ++$stats['skipped'];
                continue;
            }

            $this->billing->ensureNextDueAt($subscription);
            $daysOverdue = $subscription->getDaysOverdue($today);
            if ($daysOverdue <= 0) {
                ++$stats['skipped'];
                continue;
            }

            $grace = $subscription->getGraceDays();

            if ($daysOverdue > $grace) {
                if ($notifyOnly) {
                    $this->notify($subscription, $daysOverdue, $grace, true);
                    ++$stats['notified'];
                    continue;
                }

                if (!$dryRun) {
                    $this->terminate($subscription, $daysOverdue, $grace);
                }
                ++$stats['terminated'];
                continue;
            }

            // Retard dans le délai de grâce : avertir + suspendre l'accès
            if (!$dryRun) {
                $this->notify($subscription, $daysOverdue, $grace, false);
                ++$stats['notified'];

                if (!$notifyOnly) {
                    $this->suspend($subscription, $daysOverdue, $grace);
                    ++$stats['suspended'];
                }
            } else {
                ++$stats['notified'];
                if (!$notifyOnly) {
                    ++$stats['suspended'];
                }
            }
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        return $stats;
    }

    private function notify(Subscription $subscription, int $daysOverdue, int $grace, bool $terminal): void
    {
        $user = $subscription->getMerchant()?->getUser();
        if (!$user) {
            return;
        }

        $lastAt = $subscription->getLastEnforcementAt();
        $alreadyToday = $lastAt && $lastAt->format('Y-m-d') === (new \DateTimeImmutable())->format('Y-m-d')
            && \in_array($subscription->getLastEnforcementAction(), ['notify', 'suspend', 'terminate'], true);
        if ($alreadyToday && !$terminal) {
            return;
        }

        $due = $subscription->getNextDueAt()?->format('d/m/Y') ?? '—';
        $title = $terminal
            ? 'Abonnement résilié pour impayé'
            : 'Retard de paiement abonnement';
        $message = $terminal
            ? sprintf(
                'Votre abonnement a été résilié après %d jour(s) de retard (délai max %d jours). Échéance : %s. Contactez le support pour régulariser.',
                $daysOverdue,
                $grace,
                $due
            )
            : sprintf(
                'Paiement en retard de %d jour(s) (délai max avant résiliation : %d jours). Échéance : %s. Régularisez rapidement pour éviter la rupture.',
                $daysOverdue,
                $grace,
                $due
            );

        $this->notifications->notify(
            $user,
            Notification::TYPE_SUBSCRIPTION,
            $title,
            $message,
            $subscription->getMerchant()?->getShops()->first() ?: null
        );

        $this->appMailer->sendSubscriptionAlert($user, $title, $message, $terminal);

        $subscription->setLastEnforcementAction($terminal ? 'terminate' : 'notify');
        $subscription->setLastEnforcementAt(new \DateTimeImmutable());
    }

    private function suspend(Subscription $subscription, int $daysOverdue, int $grace): void
    {
        $merchant = $subscription->getMerchant();
        if (!$merchant) {
            return;
        }

        $alreadySuspended = $subscription->getLastEnforcementAction() === 'suspend'
            || $subscription->getLastEnforcementAction() === 'terminate';

        $user = $merchant->getUser();
        if ($user && !$user->isSuspended()) {
            $user->setIsSuspended(true);
            $user->setIsActive(false);
        }

        foreach ($merchant->getShops() as $shop) {
            if ($shop->isActive()) {
                $shop->setIsActive(false);
            }
        }

        $subscription->setLastEnforcementAction('suspend');
        $subscription->setLastEnforcementAt(new \DateTimeImmutable());

        if ($alreadySuspended) {
            return;
        }

        $this->activityLogger->log(
            'subscription.suspend',
            sprintf(
                'Suspension auto pour impayé — %s (%d j / grâce %d j)',
                $merchant->getCompanyName(),
                $daysOverdue,
                $grace
            ),
            null,
            $merchant->getShops()->first() ?: null
        );
    }

    private function terminate(Subscription $subscription, int $daysOverdue, int $grace): void
    {
        $this->notify($subscription, $daysOverdue, $grace, true);
        $this->suspend($subscription, $daysOverdue, $grace);

        $subscription->setStatus(Subscription::STATUS_CANCELLED);
        $subscription->setLastEnforcementAction('terminate');
        $subscription->setLastEnforcementAt(new \DateTimeImmutable());

        $merchant = $subscription->getMerchant();
        if ($merchant) {
            foreach ($merchant->getShops() as $shop) {
                $contract = $shop->getContract();
                if ($contract && $contract->getStatus() !== ShopContract::STATUS_TERMINATED
                    && $contract->getStatus() !== ShopContract::STATUS_DRAFT) {
                    $contract->setStatus(ShopContract::STATUS_TERMINATED);
                }
            }
        }

        $this->activityLogger->log(
            'subscription.terminate',
            sprintf(
                'Résiliation auto pour impayé — %s (%d j > grâce %d j)',
                $merchant?->getCompanyName() ?? '—',
                $daysOverdue,
                $grace
            ),
            null,
            $merchant?->getShops()->first() ?: null
        );
    }
}
