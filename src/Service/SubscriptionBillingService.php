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
        private FiscalService $fiscalService,
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
        $baseAmount = (float) ($amount ?? $subscription->getPrice());

        $settings = $this->fiscalService->getPlatformSettings();
        $taxRate = 0.0;
        $taxAmount = 0.0;
        $totalAmount = $baseAmount;
        if ($settings->isTaxOnSubscriptions() && $baseAmount > 0) {
            $taxRate = (float) $settings->getDefaultVatRate();
            $split = $this->fiscalService->splitAmount($baseAmount, $taxRate, false);
            $taxAmount = $split['tax'];
            $totalAmount = $split['gross'];
        }

        $payment = new Payment();
        $payment->setSubscription($subscription);
        $payment->setAmount(number_format($totalAmount, 2, '.', ''));
        $payment->setTaxRate(number_format($taxRate, 2, '.', ''));
        $payment->setTaxAmount(number_format($taxAmount, 2, '.', ''));
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

    /**
     * Marque l'abonnement comme non payé (échéance en retard + paiement en attente).
     */
    public function markUnpaid(Subscription $subscription, ?User $actor = null, ?string $reference = null): Payment
    {
        if (!$subscription->isBillable()) {
            throw new \InvalidArgumentException('Un abonnement gratuit ne peut pas être marqué impayé.');
        }
        if ($subscription->getStatus() === Subscription::STATUS_CANCELLED) {
            throw new \InvalidArgumentException('Abonnement résilié.');
        }

        $this->ensureNextDueAt($subscription);

        $today = new \DateTimeImmutable('today');
        // Met l'échéance à hier pour refléter un impayé immédiat
        if ($subscription->getNextDueAt() === null || $subscription->getNextDueAt() >= $today) {
            $subscription->setNextDueAt($today->modify('-1 day'));
        }

        $settings = $this->fiscalService->getPlatformSettings();
        $baseAmount = (float) $subscription->getPrice();
        $taxRate = 0.0;
        $taxAmount = 0.0;
        $totalAmount = $baseAmount;
        if ($settings->isTaxOnSubscriptions() && $baseAmount > 0) {
            $taxRate = (float) $settings->getDefaultVatRate();
            $split = $this->fiscalService->splitAmount($baseAmount, $taxRate, false);
            $taxAmount = $split['tax'];
            $totalAmount = $split['gross'];
        }

        $payment = new Payment();
        $payment->setSubscription($subscription);
        $payment->setAmount(number_format($totalAmount, 2, '.', ''));
        $payment->setTaxRate(number_format($taxRate, 2, '.', ''));
        $payment->setTaxAmount(number_format($taxAmount, 2, '.', ''));
        $payment->setStatus(Payment::STATUS_PENDING);
        $payment->setMethod('manuel');
        $payment->setReference($reference ?: 'Impayé');
        $payment->setDueAt($subscription->getNextDueAt());
        $payment->setPaidAt(null);

        $this->em->persist($payment);
        $this->em->flush();

        $merchant = $subscription->getMerchant();
        $this->activityLogger->log(
            'subscription.unpaid',
            sprintf(
                'Abonnement marqué non payé : %s (%s FCFA)',
                $merchant?->getCompanyName() ?? '—',
                number_format($totalAmount, 0, ',', ' ')
            ),
            $actor,
            $merchant?->getShops()->first() ?: null
        );

        return $payment;
    }

    /**
     * Résilie manuellement un abonnement (suspend l'accès + contrats).
     */
    public function markCancelled(Subscription $subscription, ?User $actor = null, ?string $reference = null): void
    {
        if ($subscription->getStatus() === Subscription::STATUS_CANCELLED) {
            return;
        }

        $subscription->setStatus(Subscription::STATUS_CANCELLED);
        $subscription->setLastEnforcementAction('terminate');
        $subscription->setLastEnforcementAt(new \DateTimeImmutable());
        $subscription->setEndsAt(new \DateTimeImmutable());

        $merchant = $subscription->getMerchant();
        if ($merchant) {
            $user = $merchant->getUser();
            if ($user) {
                $user->setIsSuspended(true);
                $user->setIsActive(false);
            }
            foreach ($merchant->getShops() as $shop) {
                $shop->setIsActive(false);
                $contract = $shop->getContract();
                if ($contract && $contract->getStatus() !== ShopContract::STATUS_TERMINATED
                    && $contract->getStatus() !== ShopContract::STATUS_DRAFT) {
                    $contract->setStatus(ShopContract::STATUS_TERMINATED);
                }
            }
        }

        $this->em->flush();

        $this->activityLogger->log(
            'subscription.cancel',
            sprintf(
                'Abonnement résilié manuellement : %s%s',
                $merchant?->getCompanyName() ?? '—',
                $reference ? ' ('.$reference.')' : ''
            ),
            $actor,
            $merchant?->getShops()->first() ?: null
        );
    }

    public function restoreAccess(Subscription $subscription): void
    {
        if ($subscription->getStatus() !== Subscription::STATUS_ACTIVE) {
            return;
        }

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
