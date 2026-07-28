<?php

namespace App\Entity;

use App\Repository\ShopMemberRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShopMemberRepository::class)]
#[ORM\Table(name: 'shop_members')]
#[ORM\UniqueConstraint(name: 'UNIQ_SHOP_USER', fields: ['shop', 'user'])]
class ShopMember
{
    public const ROLE_MANAGER = 'manager';
    public const ROLE_CASHIER = 'cashier';
    public const ROLE_STOCK = 'stock';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'members')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Shop $shop = null;

    #[ORM\ManyToOne(inversedBy: 'shopMemberships')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 50)]
    private string $role = self::ROLE_CASHIER;

    /** @var list<string> */
    #[ORM\Column]
    private array $permissions = [];

    #[ORM\Column]
    private bool $isActive = true;

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

    public function getShop(): ?Shop
    {
        return $this->shop;
    }

    public function setShop(?Shop $shop): static
    {
        $this->shop = $shop;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getRoleLabel(): string
    {
        return match ($this->role) {
            self::ROLE_MANAGER => 'Responsable',
            self::ROLE_STOCK => 'Magasinier',
            default => 'Vendeur / Caissier',
        };
    }

    /** @return list<string> */
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    /** @param list<string> $permissions */
    public function setPermissions(array $permissions): static
    {
        $this->permissions = $permissions;

        return $this;
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
}
