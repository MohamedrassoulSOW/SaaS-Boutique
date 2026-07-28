<?php

namespace App\Service;

use App\Entity\Payment;
use App\Entity\ShopContract;
use App\Entity\Subscription;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class SubscriptionBillingService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ActivityLogger $activityLogger,
    ) {
    }

    public function ensureNextDueAt(Subscription $subscription): void
    {
        if ($subscription->getNextDueAt() !== null) {
            return;
        }

        $from = $subscription->getLastPaidAt() ?? $subscription->getStartsAt();
        $subscription->setNextDueAt($this->addBillingPeriod($from, $subscription->getBillingPeriod()));
    }

    public function syncFromContract(Subscription $subscription, ShopContract $contract): void
    {
        $subscription->setPlan($contract->getPlan());
        $subscription->setPrice($contract->getPrice());
        $subscription->setBillingPeriod($contract->getBillingPeriod());
        $subscription->setStartsAt($contract->getStartsAt());
        $subscription->setEndsAt($contract->getEndsAt());
        $subscription->setStatus(Subscription::STATUS_ACTIVE);

        if ($subscription->getNextDueAt() === null) {
            $subscription->setNextDueAt(
                $this->addBillingPeriod($contract->getStartsAt(), $contract->getBillingPeriod())
            );
        }
    }

    public function recordPayment(
        Subscription $subscription,
        ?User $actor = null,
        ?string $method = 'manuel',
        ?string $reference = null,
        ?string $amount = null,
    ): Payment {
        $this->ensureNextDueAt($subscription);

        $dueAt = $subscription->getNextDueAt() ?? new \DateTimeImmutable('today');
        $paidAt = new \DateTimeImmutable();
        $payment = new Payment();
        $payment->setSubscription($subscription);
        $payment->setAmount($amount ?? $subscription->getPrice());
        $payment->setStatus(Payment::STATUS_PAID);
        $payment->setMethod($method);
        $payment->setReference($reference);
        $payment->setDueAt($dueAt);
        $payment->setPaidAt($paidAt);

        $subscription->setLastPaidAt($paidAt);
        $subscription->setNextDueAt($this->addBillingPeriod($dueAt, $subscription->getBillingPeriod()));
        $subscription->setStatus(Subscription::STATUS_ACTIVE);
        $subscription->setLastEnforcementAction(null);
        $subscription->setLastEnforcementAt(null);

        $this->restoreAccess($subscription);

        $this->em->persist($payment);
        $this->em->flush();

        $merchant = $subscription->getMerchant();
        $this->activityLogger->log(
            'subscription.payment',
            sprintf(
                'Paiement enregistré pour %s (%s FCFA) — prochaine échéance %s',
                $merchant?->getCompanyName() ?? '—',
                number_format((float) $payment->getAmount(), 0, ',', ' '),
                $subscription->getNextDueAt()?->format('d/m/Y') ?? '—'
            ),
            $actor,
            $merchant?->getShops()->first() ?: null
        );

        return $payment;
    }

    public function restoreAccess(Subscription $subscription): void
    {
        $merchant = $subscription->getMerchant();
        if (!$merchant) {
            return;
        }

        $user = $merchant->getUser();
        if ($user) {
            $user->setIsSuspended(false);
            $user->setIsActive(true);
        }

        foreach ($merchant->getShops() as $shop) {
            $shop->setIsActive(true);
        }
    }

    public function addBillingPeriod(\DateTimeImmutable $from, string $billingPeriod): \DateTimeImmutable
    {
        if ($billingPeriod === ShopContract::BILLING_ANNUAL) {
            return $from->modify('+1 year');
        }

        return $from->modify('+1 month');
    }
}
