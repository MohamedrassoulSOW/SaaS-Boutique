<?php

namespace App\Entity;

use App\Repository\StockMovementRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockMovementRepository::class)]
#[ORM\Table(name: 'stock_movements', indexes: [
    new ORM\Index(columns: ['shop_id', 'product_id', 'created_at']),
    new ORM\Index(columns: ['created_by_id']),
])]
class StockMovement
{
    public const TYPE_IN = 'in';
    public const TYPE_OUT = 'out';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_SALE = 'sale';
    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_INVENTORY = 'inventory';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Shop $shop = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\Column(length: 30)]
    private string $type = self::TYPE_ADJUSTMENT;

    #[ORM\Column]
    private int $quantity = 0;

    #[ORM\Column]
    private int $quantityBefore = 0;

    #[ORM\Column]
    private int $quantityAfter = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne]
    private ?User $createdBy = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getQuantityBefore(): int
    {
        return $this->quantityBefore;
    }

    public function setQuantityBefore(int $quantityBefore): static
    {
        $this->quantityBefore = $quantityBefore;

        return $this;
    }

    public function getQuantityAfter(): int
    {
        return $this->quantityAfter;
    }

    public function setQuantityAfter(int $quantityAfter): static
    {
        $this->quantityAfter = $quantityAfter;

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;

        return $this;
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
}
