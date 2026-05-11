<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\Repository\OrderItemRepository;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Gedmo\Mapping\Annotation as Gedmo;
use ApiPlatform\Metadata\ApiProperty;

#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
#[ApiResource(
    operations: [
        new Get(normalizationContext: ['groups' => ['order_item:read', 'product:read']]),
        new GetCollection(
            paginationItemsPerPage: 30,
            paginationClientItemsPerPage: true,
            normalizationContext: ['groups' => ['order_item:read']]
        ),
        new Post(
            normalizationContext: ['groups' => ['order_item:read']],
            denormalizationContext: ['groups' => ['order_item:write']]
        ),
        new Put(
            normalizationContext: ['groups' => ['order_item:read']],
            denormalizationContext: ['groups' => ['order_item:write']]
        ),
        new Patch(
            normalizationContext: ['groups' => ['order_item:read']],
            denormalizationContext: ['groups' => ['order_item:write']]
        ),
        new Delete()
    ],
    normalizationContext: ['groups' => ['order_item:read']],
    denormalizationContext: ['groups' => ['order_item:write']]
)]
class OrderItem
{
    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[Groups(['order_item:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['order_item:read', 'order_item:write'])]
    #[ApiProperty(readableLink: false, writableLink: false)]
    private ?Order $orderRef = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['order_item:read', 'order_item:write', 'order:read', 'order:write'])]
    #[ApiProperty(readableLink: true, writableLink: false)]
    private ?Product $product = null;

    #[ORM\Column(type: "integer")]
    #[Groups(['order_item:read', 'order_item:write', 'order:read', 'order:write'])]
    private int $quantity = 1;

    #[ORM\Column(type: "float")]
    #[Groups(['order_item:read', 'order:read'])]
    private float $unitPrice = 0;

    #[ORM\Column(type: "float")]
    #[Groups(['order_item:read', 'order:read'])]
    private float $subtotal = 0;

    #[ORM\Column(type: "datetime")]
    #[Gedmo\Timestampable(on: "create")]
    #[Groups(['order_item:read'])]
    private ?DateTimeInterface $createdAt;

    #[ORM\Column(type: "datetime")]
    #[Gedmo\Timestampable(on: "update")]
    #[Groups(['order_item:read'])]
    private ?DateTimeInterface $updatedAt;

    #[ORM\Column(type: "boolean")]
    #[Groups(['order_item:read'])]
    private bool $isCustomPrice = false;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['order_item:read', 'order_item:write', 'order:read', 'order:write'])]
    #[ApiProperty(readableLink: true)]
    private ?Client $onBehalfOfClient = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getOrderRef(): ?Order
    {
        return $this->orderRef;
    }

    public function setOrderRef(?Order $orderRef): static
    {
        $this->orderRef = $orderRef;
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

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;
        $this->calculateSubtotal();
        return $this;
    }

    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(float $unitPrice): static
    {
        $this->unitPrice = $unitPrice;
        $this->calculateSubtotal();
        return $this;
    }

    public function getSubtotal(): float
    {
        return $this->subtotal;
    }

    public function setSubtotal(float $subtotal): static
    {
        $this->subtotal = $subtotal;
        return $this;
    }

    private function calculateSubtotal(): void
    {
        $this->subtotal = $this->unitPrice * $this->quantity;
    }

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function isCustomPrice(): bool
    {
        return $this->isCustomPrice;
    }

    public function setIsCustomPrice(bool $isCustomPrice): static
    {
        $this->isCustomPrice = $isCustomPrice;
        return $this;
    }

    public function getOnBehalfOfClient(): ?Client
    {
        return $this->onBehalfOfClient;
    }

    public function setOnBehalfOfClient(?Client $onBehalfOfClient): static
    {
        $this->onBehalfOfClient = $onBehalfOfClient;
        return $this;
    }
}
