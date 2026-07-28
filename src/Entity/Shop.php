<?php

namespace App\Entity;

use App\Repository\ShopRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ShopRepository::class)]
#[ORM\Table(name: 'shops')]
class Shop
{
    use BinaryPayloadTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'shops')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Merchant $merchant = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: Types::BLOB, nullable: true)]
    private mixed $logoData = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $logoMime = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $logoName = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, ShopMember> */
    #[ORM\OneToMany(mappedBy: 'shop', targetEntity: ShopMember::class, orphanRemoval: true)]
    private Collection $members;

    /** @var Collection<int, Category> */
    #[ORM\OneToMany(mappedBy: 'shop', targetEntity: Category::class, orphanRemoval: true)]
    private Collection $categories;

    /** @var Collection<int, Product> */
    #[ORM\OneToMany(mappedBy: 'shop', targetEntity: Product::class, orphanRemoval: true)]
    private Collection $products;

    /** @var Collection<int, Supplier> */
    #[ORM\OneToMany(mappedBy: 'shop', targetEntity: Supplier::class, orphanRemoval: true)]
    private Collection $suppliers;

    /** @var Collection<int, Customer> */
    #[ORM\OneToMany(mappedBy: 'shop', targetEntity: Customer::class, orphanRemoval: true)]
    private Collection $customers;

    #[ORM\OneToOne(mappedBy: 'shop', cascade: ['persist', 'remove'])]
    private ?ShopContract $contract = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->members = new ArrayCollection();
        $this->categories = new ArrayCollection();
        $this->products = new ArrayCollection();
        $this->suppliers = new ArrayCollection();
        $this->customers = new ArrayCollection();
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getLogoData(): ?string
    {
        return $this->readBinary($this->logoData);
    }

    public function setLogoData(?string $logoData): static
    {
        $this->logoData = $this->writeBinary($logoData);

        return $this;
    }

    public function getLogoMime(): ?string
    {
        return $this->logoMime;
    }

    public function setLogoMime(?string $logoMime): static
    {
        $this->logoMime = $logoMime;

        return $this;
    }

    public function getLogoName(): ?string
    {
        return $this->logoName;
    }

    public function setLogoName(?string $logoName): static
    {
        $this->logoName = $logoName;

        return $this;
    }

    public function hasLogo(): bool
    {
        return $this->getLogoData() !== null && $this->getLogoData() !== '';
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

    /** @return Collection<int, ShopMember> */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    /** @return Collection<int, Category> */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    /** @return Collection<int, Product> */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    /** @return Collection<int, Supplier> */
    public function getSuppliers(): Collection
    {
        return $this->suppliers;
    }

    /** @return Collection<int, Customer> */
    public function getCustomers(): Collection
    {
        return $this->customers;
    }

    public function getContract(): ?ShopContract
    {
        return $this->contract;
    }

    public function setContract(?ShopContract $contract): static
    {
        if ($contract === null && $this->contract !== null) {
            $this->contract->setShop(null);
        }

        if ($contract !== null && $contract->getShop() !== $this) {
            $contract->setShop($this);
        }

        $this->contract = $contract;

        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->name;
    }
}
