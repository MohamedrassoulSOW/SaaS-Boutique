<?php

namespace App\Entity;

use App\Repository\SubscriptionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
#[ORM\Table(name: 'subscriptions')]
class Subscription
{
    public const PLAN_FREE = 'free';
    public const PLAN_BASIC = 'basic';
    public const PLAN_PRO = 'pro';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    /** Tarifs catalogue mensuels (FCFA) — source unique. */
    public static function catalogPrice(string $plan): string
    {
        return match ($plan) {
            self::PLAN_BASIC => '15000.00',
            self::PLAN_PRO => '25000.00',
            default => '0.00',
        };
    }

    public static function planLabel(string $plan): string
    {
        return match ($plan) {
            self::PLAN_BASIC => 'Basique',
            self::PLAN_PRO => 'Pro',
            default => 'Gratuit',
        };
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'subscription')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Merchant $merchant = null;

    #[ORM\Column(length: 30)]
    private string $plan = self::PLAN_FREE;

    #[ORM\Column(length: 30)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $price = '0.00';

    #[ORM\Column(length: 20)]
    private string $billingPeriod = 'monthly';

    #[ORM\Column]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    /** Prochaine échéance de paiement (facturation récurrente). */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $nextDueAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastPaidAt = null;

    /** Dernière action d’enforcement (notify / suspend / terminate). */
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $lastEnforcementAction = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastEnforcementAt = null;

    /** @var Collection<int, Payment> */
    #[ORM\OneToMany(mappedBy: 'subscription', targetEntity: Payment::class, cascade: ['persist'])]
    private Collection $payments;

    public function __construct()
    {
        $this->startsAt = new \DateTimeImmutable();
        $this->endsAt = (new \DateTimeImmutable())->modify('+30 days');
        $this->nextDueAt = (new \DateTimeImmutable())->modify('+1 month');
        $this->payments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMerchant(): ?Merchant
    {
        return $this->merchant;
    }

    public function setMerchant(?Merchant $merchant): static
    {
        $this->merchant = $merchant;

        return $this;
    }

    public function getPlan(): string
    {
        return $this->plan;
    }

    public function setPlan(string $plan): static
    {
        $this->plan = $plan;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getBillingPeriod(): string
    {
        return $this->billingPeriod;
    }

    public function setBillingPeriod(string $billingPeriod): static
    {
        $this->billingPeriod = $billingPeriod;

        return $this;
    }

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(\DateTimeImmutable $startsAt): static
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(?\DateTimeImmutable $endsAt): static
    {
        $this->endsAt = $endsAt;

        return $this;
    }

    public function getNextDueAt(): ?\DateTimeImmutable
    {
        return $this->nextDueAt;
    }

    public function setNextDueAt(?\DateTimeImmutable $nextDueAt): static
    {
        $this->nextDueAt = $nextDueAt;

        return $this;
    }

    public function getLastPaidAt(): ?\DateTimeImmutable
    {
        return $this->lastPaidAt;
    }

    public function setLastPaidAt(?\DateTimeImmutable $lastPaidAt): static
    {
        $this->lastPaidAt = $lastPaidAt;

        return $this;
    }

    public function getLastEnforcementAction(): ?string
    {
        return $this->lastEnforcementAction;
    }

    public function setLastEnforcementAction(?string $lastEnforcementAction): static
    {
        $this->lastEnforcementAction = $lastEnforcementAction;

        return $this;
    }

    public function getLastEnforcementAt(): ?\DateTimeImmutable
    {
        return $this->lastEnforcementAt;
    }

    public function setLastEnforcementAt(?\DateTimeImmutable $lastEnforcementAt): static
    {
        $this->lastEnforcementAt = $lastEnforcementAt;

        return $this;
    }

    /** @return Collection<int, Payment> */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && ($this->endsAt === null || $this->endsAt >= new \DateTimeImmutable());
    }

    public function isBillable(): bool
    {
        return (float) $this->price > 0;
    }

    public function getGraceDays(): int
    {
        return ShopContract::unpaidDaysForPeriod($this->billingPeriod);
    }

    public function getDaysOverdue(?\DateTimeImmutable $today = null): int
    {
        if (!$this->nextDueAt) {
            return 0;
        }

        $today ??= new \DateTimeImmutable('today');
        $due = $this->nextDueAt->setTime(0, 0);
        if ($due > $today) {
            return 0;
        }

        return (int) $due->diff($today)->days;
    }
}
