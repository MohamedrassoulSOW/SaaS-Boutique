<?php

namespace App\Entity;

use App\Repository\ShopContractRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShopContractRepository::class)]
#[ORM\Table(name: 'shop_contracts')]
class ShopContract
{
    use BinaryPayloadTrait;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending_signature';
    public const STATUS_SIGNED = 'signed';
    public const STATUS_TERMINATED = 'terminated';

    public const BILLING_MONTHLY = 'monthly';
    public const BILLING_ANNUAL = 'annual';

    /** Délai max de retard (jours) — abonnement mensuel — avant rupture immédiate */
    public const MONTHLY_UNPAID_DAYS_BEFORE_TERMINATION = 15;

    /** Délai max de retard (jours) — abonnement annuel — avant rupture immédiate (1 mois) */
    public const ANNUAL_UNPAID_DAYS_BEFORE_TERMINATION = 30;

    /** @deprecated Utiliser MONTHLY_UNPAID_DAYS_BEFORE_TERMINATION */
    public const UNPAID_DAYS_BEFORE_TERMINATION = 15;

    /** @deprecated */
    public const UNPAID_MONTHS_BEFORE_TERMINATION = 2;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 40)]
    private string $number;

    #[ORM\OneToOne(inversedBy: 'contract')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Shop $shop = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Merchant $merchant = null;

    /** Boutique proposée (discussions, avant création) */
    #[ORM\Column(length: 150, nullable: true)]
    private ?string $proposedShopName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $proposedShopAddress = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $proposedShopPhone = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $proposedShopEmail = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $discussionNotes = null;

    /** Visible par le commerçant sur son dashboard (discussions) */
    #[ORM\Column]
    private bool $sharedWithMerchant = false;

    #[ORM\Column(length: 30)]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(length: 30)]
    private string $plan = Subscription::PLAN_BASIC;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $price = '0.00';

    /** Périodicité de facturation : monthly | annual */
    #[ORM\Column(length: 20)]
    private string $billingPeriod = self::BILLING_MONTHLY;

    #[ORM\Column]
    private int $durationMonths = 12;

    #[ORM\Column]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column]
    private \DateTimeImmutable $endsAt;

    #[ORM\Column(length: 20)]
    private string $termsVersion = '1.4';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne]
    private ?User $createdBy = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $platformSignedBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $platformSignedAt = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $merchantSignedBy = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $merchantSignedTitle = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $merchantSignedAt = null;

    #[ORM\Column(type: Types::BLOB, nullable: true)]
    private mixed $pdfData = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $pdfMime = 'application/pdf';

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->startsAt = $now;
        $this->endsAt = $now->modify('+12 months');
        $this->number = 'CTR-'.$now->format('Ymd').'-'.strtoupper(substr(uniqid(), -5));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getShop(): ?Shop
    {
        return $this->shop;
    }

    public function setShop(?Shop $shop): static
    {
        $this->shop = $shop;

        return $this;
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_SIGNED => 'Signé',
            self::STATUS_TERMINATED => 'Résilié',
            self::STATUS_DRAFT => $this->sharedWithMerchant ? 'En discussion' : 'Brouillon',
            default => 'En attente de signature',
        };
    }

    public function getProposedShopName(): ?string
    {
        return $this->proposedShopName;
    }

    public function setProposedShopName(?string $proposedShopName): static
    {
        $this->proposedShopName = $proposedShopName;

        return $this;
    }

    public function getProposedShopAddress(): ?string
    {
        return $this->proposedShopAddress;
    }

    public function setProposedShopAddress(?string $proposedShopAddress): static
    {
        $this->proposedShopAddress = $proposedShopAddress;

        return $this;
    }

    public function getProposedShopPhone(): ?string
    {
        return $this->proposedShopPhone;
    }

    public function setProposedShopPhone(?string $proposedShopPhone): static
    {
        $this->proposedShopPhone = $proposedShopPhone;

        return $this;
    }

    public function getProposedShopEmail(): ?string
    {
        return $this->proposedShopEmail;
    }

    public function setProposedShopEmail(?string $proposedShopEmail): static
    {
        $this->proposedShopEmail = $proposedShopEmail;

        return $this;
    }

    public function getDiscussionNotes(): ?string
    {
        return $this->discussionNotes;
    }

    public function setDiscussionNotes(?string $discussionNotes): static
    {
        $this->discussionNotes = $discussionNotes;

        return $this;
    }

    public function isSharedWithMerchant(): bool
    {
        return $this->sharedWithMerchant;
    }

    public function setSharedWithMerchant(bool $sharedWithMerchant): static
    {
        $this->sharedWithMerchant = $sharedWithMerchant;

        return $this;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function getDisplayShopName(): string
    {
        return (string) ($this->shop?->getName() ?: $this->proposedShopName ?: 'Boutique à définir');
    }

    public function getDisplayShopAddress(): ?string
    {
        return $this->shop?->getAddress() ?: $this->proposedShopAddress;
    }

    public function getDisplayShopPhone(): ?string
    {
        return $this->shop?->getPhone() ?: $this->proposedShopPhone;
    }

    public function getDisplayShopEmail(): ?string
    {
        return $this->shop?->getEmail() ?: $this->proposedShopEmail;
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

    public function getPlanLabel(): string
    {
        return match ($this->plan) {
            Subscription::PLAN_PRO => 'Pro',
            Subscription::PLAN_FREE => 'Gratuit',
            default => 'Basique',
        };
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

    public function getBillingPeriodLabel(): string
    {
        return $this->billingPeriod === self::BILLING_ANNUAL ? 'Annuel' : 'Mensuel';
    }

    public function getPricePeriodLabel(): string
    {
        return $this->billingPeriod === self::BILLING_ANNUAL ? '/ an' : '/ mois';
    }

    public function getUnpaidMonthsBeforeTermination(): int
    {
        return self::UNPAID_MONTHS_BEFORE_TERMINATION;
    }

    public function getAnnualUnpaidDaysBeforeTermination(): int
    {
        return self::ANNUAL_UNPAID_DAYS_BEFORE_TERMINATION;
    }

    public function getUnpaidDaysBeforeTermination(): int
    {
        return self::MONTHLY_UNPAID_DAYS_BEFORE_TERMINATION;
    }

    public function getMonthlyUnpaidDaysBeforeTermination(): int
    {
        return self::MONTHLY_UNPAID_DAYS_BEFORE_TERMINATION;
    }

    public static function unpaidDaysForPeriod(string $billingPeriod): int
    {
        return $billingPeriod === self::BILLING_ANNUAL
            ? self::ANNUAL_UNPAID_DAYS_BEFORE_TERMINATION
            : self::MONTHLY_UNPAID_DAYS_BEFORE_TERMINATION;
    }

    public function getGraceDaysForBilling(): int
    {
        return self::unpaidDaysForPeriod($this->billingPeriod);
    }

    public function getDurationMonths(): int
    {
        return $this->durationMonths;
    }

    public function setDurationMonths(int $durationMonths): static
    {
        $this->durationMonths = $durationMonths;

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

    public function getEndsAt(): \DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(\DateTimeImmutable $endsAt): static
    {
        $this->endsAt = $endsAt;

        return $this;
    }

    public function getTermsVersion(): string
    {
        return $this->termsVersion;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getPlatformSignedBy(): ?string
    {
        return $this->platformSignedBy;
    }

    public function setPlatformSignedBy(?string $platformSignedBy): static
    {
        $this->platformSignedBy = $platformSignedBy;

        return $this;
    }

    public function getPlatformSignedAt(): ?\DateTimeImmutable
    {
        return $this->platformSignedAt;
    }

    public function setPlatformSignedAt(?\DateTimeImmutable $platformSignedAt): static
    {
        $this->platformSignedAt = $platformSignedAt;

        return $this;
    }

    public function getMerchantSignedBy(): ?string
    {
        return $this->merchantSignedBy;
    }

    public function setMerchantSignedBy(?string $merchantSignedBy): static
    {
        $this->merchantSignedBy = $merchantSignedBy;

        return $this;
    }

    public function getMerchantSignedTitle(): ?string
    {
        return $this->merchantSignedTitle;
    }

    public function setMerchantSignedTitle(?string $merchantSignedTitle): static
    {
        $this->merchantSignedTitle = $merchantSignedTitle;

        return $this;
    }

    public function getMerchantSignedAt(): ?\DateTimeImmutable
    {
        return $this->merchantSignedAt;
    }

    public function setMerchantSignedAt(?\DateTimeImmutable $merchantSignedAt): static
    {
        $this->merchantSignedAt = $merchantSignedAt;

        return $this;
    }

    public function getPdfData(): ?string
    {
        return $this->readBinary($this->pdfData);
    }

    public function setPdfData(?string $pdfData): static
    {
        $this->pdfData = $this->writeBinary($pdfData);
        $this->pdfMime = 'application/pdf';

        return $this;
    }

    public function getPdfMime(): ?string
    {
        return $this->pdfMime;
    }

    public function hasPdf(): bool
    {
        return $this->getPdfData() !== null && $this->getPdfData() !== '';
    }

    public function isFullySigned(): bool
    {
        return $this->platformSignedAt !== null && $this->merchantSignedAt !== null;
    }

    public function refreshSignedStatus(): void
    {
        if ($this->isFullySigned()) {
            $this->status = self::STATUS_SIGNED;
        } else {
            $this->status = self::STATUS_PENDING;
        }
    }
}
