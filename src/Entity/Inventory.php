<?php

namespace App\Entity;

use App\Repository\InventoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventoryRepository::class)]
#[ORM\Table(name: 'inventories')]
#[ORM\Index(name: 'IDX_INVENTORY_SHOP', columns: ['shop_id'])]
#[ORM\Index(name: 'IDX_INVENTORY_CREATED_BY', columns: ['created_by_id'])]
class Inventory
{
    public const TYPE_FULL = 'full';
    public const TYPE_PARTIAL = 'partial';
    public const STATUS_DRAFT = 'draft';
    public const STATUS_COMPLETED = 'completed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Version]
    private int $version;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Shop $shop = null;

    #[ORM\Column(length: 40)]
    private ?string $reference = null;

    #[ORM\Column(length: 30)]
    private string $type = self::TYPE_FULL;

    #[ORM\Column(length: 30)]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\ManyToOne]
    private ?User $createdBy = null;

    /** @var Collection<int, InventoryItem> */
    #[ORM\OneToMany(mappedBy: 'inventory', targetEntity: InventoryItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->items = new ArrayCollection();
        $this->reference = 'INV-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(4)));
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

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;

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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;

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

    /** @return Collection<int, InventoryItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(InventoryItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setInventory($this);
        }

        return $this;
    }
}
