<?php

namespace App\Entity;

use App\Repository\PurchaseOrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PurchaseOrderRepository::class)]
#[ORM\Table(name: 'purchase_orders')]
#[ORM\Index(name: 'IDX_PO_SHOP', columns: ['shop_id'])]
#[ORM\Index(name: 'IDX_PO_SUPPLIER', columns: ['supplier_id'])]
#[ORM\Index(name: 'IDX_PO_CREATED_BY', columns: ['created_by_id'])]
class PurchaseOrder
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ORDERED = 'ordered';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Version]
    private int $version;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Shop $shop = null;

    #[ORM\ManyToOne(inversedBy: 'purchaseOrders')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Supplier $supplier = null;

    #[ORM\Column(length: 40)]
    private ?string $reference = null;

    #[ORM\Column(length: 30)]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $total = '0.00';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $receivedAt = null;

    #[ORM\ManyToOne]
    private ?User $createdBy = null;

    /** @var Collection<int, PurchaseOrderItem> */
    #[ORM\OneToMany(mappedBy: 'purchaseOrder', targetEntity: PurchaseOrderItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->items = new ArrayCollection();
        $this->reference = 'PO-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(4)));
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

    public function getSupplier(): ?Supplier
    {
        return $this->supplier;
    }

    public function setSupplier(?Supplier $supplier): static
    {
        $this->supplier = $supplier;

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;

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

    public function getTotal(): string
    {
        return $this->total;
    }

    public function setTotal(string $total): static
    {
        $this->total = $total;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getReceivedAt(): ?\DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function setReceivedAt(?\DateTimeImmutable $receivedAt): static
    {
        $this->receivedAt = $receivedAt;

        return $this;
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

    /** @return Collection<int, PurchaseOrderItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(PurchaseOrderItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setPurchaseOrder($this);
        }

        return $this;
    }

    public function recalculateTotal(): void
    {
        $total = 0.0;
        foreach ($this->items as $item) {
            $total += (float) $item->getUnitPrice() * $item->getQuantity();
        }
        $this->total = number_format($total, 2, '.', '');
    }
}
