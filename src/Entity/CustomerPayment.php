<?php

namespace App\Entity;

use App\Repository\CustomerPaymentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CustomerPaymentRepository::class)]
#[ORM\Table(name: 'customer_payments')]
#[ORM\Index(name: 'IDX_CUSTPAY_CUSTOMER', columns: ['customer_id'])]
#[ORM\Index(name: 'IDX_CUSTPAY_SHOP', columns: ['shop_id'])]
#[ORM\Index(name: 'IDX_CUSTPAY_RECORDED_BY', columns: ['recorded_by_id'])]
class CustomerPayment
{
    public const METHOD_CASH = 'cash';
    public const METHOD_MOBILE = 'mobile';
    public const METHOD_CARD = 'card';
    public const METHOD_OTHER = 'other';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\Version]
    private int $version = 0;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Customer $customer = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Shop $shop = null;

    #[ORM\ManyToOne]
    private ?User $recordedBy = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $amount = '0.00';

    #[ORM\Column(length: 30)]
    private string $method = self::METHOD_CASH;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $note = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): static
    {
        $this->customer = $customer;

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

    public function getRecordedBy(): ?User
    {
        return $this->recordedBy;
    }

    public function setRecordedBy(?User $recordedBy): static
    {
        $this->recordedBy = $recordedBy;

        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function setMethod(string $method): static
    {
        $this->method = $method;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getMethodLabel(): string
    {
        return match ($this->method) {
            self::METHOD_MOBILE => 'Mobile money',
            self::METHOD_CARD => 'Carte',
            self::METHOD_OTHER => 'Autre',
            default => 'Espèces',
        };
    }
}
