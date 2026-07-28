<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'products')]
class Product
{
    use BinaryPayloadTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Shop $shop = null;

    #[ORM\ManyToOne(inversedBy: 'products')]
    private ?Category $category = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $barcode = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $brand = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    #[Assert\NotBlank]
    private string $purchasePrice = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    #[Assert\NotBlank]
    private string $salePrice = '0.00';

    #[ORM\Column]
    private int $quantity = 0;

    #[ORM\Column]
    private int $minStock = 5;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** Contenu image en base (BLOB), jamais sur le disque */
    #[ORM\Column(type: Types::BLOB, nullable: true)]
    private mixed $photoData = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $photoMime = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $photoName = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

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

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getBarcode(): ?string
    {
        return $this->barcode;
    }

    public function setBarcode(?string $barcode): static
    {
        $this->barcode = $barcode;

        return $this;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function setBrand(?string $brand): static
    {
        $this->brand = $brand;

        return $this;
    }

    public function getPurchasePrice(): string
    {
        return $this->purchasePrice;
    }

    public function setPurchasePrice(string $purchasePrice): static
    {
        $this->purchasePrice = $purchasePrice;

        return $this;
    }

    public function getSalePrice(): string
    {
        return $this->salePrice;
    }

    public function setSalePrice(string $salePrice): static
    {
        $this->salePrice = $salePrice;

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

    public function getMinStock(): int
    {
        return $this->minStock;
    }

    public function setMinStock(int $minStock): static
    {
        $this->minStock = $minStock;

        return $this;
    }

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->minStock;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getPhotoData(): ?string
    {
        return $this->readBinary($this->photoData);
    }

    public function setPhotoData(?string $photoData): static
    {
        $this->photoData = $this->writeBinary($photoData);

        return $this;
    }

    public function getPhotoMime(): ?string
    {
        return $this->photoMime;
    }

    public function setPhotoMime(?string $photoMime): static
    {
        $this->photoMime = $photoMime;

        return $this;
    }

    public function getPhotoName(): ?string
    {
        return $this->photoName;
    }

    public function setPhotoName(?string $photoName): static
    {
        $this->photoName = $photoName;

        return $this;
    }

    public function hasPhoto(): bool
    {
        return $this->getPhotoData() !== null && $this->getPhotoData() !== '';
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->name;
    }
}
