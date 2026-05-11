<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\ClientProductPriceRepository;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;

#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['client_product_price:read']]
        ),
        new Post(
            normalizationContext: ['groups' => ['client_product_price:read']],
            denormalizationContext: ['groups' => ['client_product_price:write']]
        ),
        new Get(
            normalizationContext: ['groups' => ['client_product_price:read']]
        ),
        new Put(
            normalizationContext: ['groups' => ['client_product_price:read']],
            denormalizationContext: ['groups' => ['client_product_price:write']]
        ),
        new Patch(
            normalizationContext: ['groups' => ['client_product_price:read']],
            denormalizationContext: ['groups' => ['client_product_price:write']]
        ),
        new Delete()
    ],
    normalizationContext: ['groups' => ['client_product_price:read']],
    denormalizationContext: ['groups' => ['client_product_price:write']]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'client.code' => 'exact',
])]
#[ORM\Entity(repositoryClass: ClientProductPriceRepository::class)]
#[UniqueEntity(
    fields: ['client', 'product'],
    message: 'This client already has a custom price for this product.'
)]
class ClientProductPrice
{
    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[Groups(['client_product_price:read', 'client:read:details', 'client_product:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'productPrices')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['client_product_price:read', 'client_product_price:write', 'client_product:read'])]
    private ?Client $client = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'clientProductPrices')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['client_product_price:read', 'client_product_price:write', 'client:read:details', 'client_product:read'])]
    private ?Product $product = null;

    #[ORM\Column(type: "float")]
    #[Assert\NotBlank]
    #[Assert\GreaterThan(0)]
    #[Groups(['client_product_price:read', 'client_product_price:write', 'client:read:details', 'client_product:read'])]
    private float $price = 0;

    #[ORM\Column(type: "float", nullable: true)]
    #[Groups(['client_product_price:read', 'client_product_price:write', 'client:read:details'])]
    private ?float $discountPercentage = null;

    #[ORM\Column(type: "datetime", nullable: true)]
    #[Groups(['client_product_price:read', 'client_product_price:write'])]
    private ?\DateTimeInterface $validFrom = null;

    #[ORM\Column(type: "datetime", nullable: true)]
    #[Groups(['client_product_price:read', 'client_product_price:write'])]
    private ?\DateTimeInterface $validUntil = null;

    #[ORM\Column(type: "datetime")]
    #[Gedmo\Timestampable(on: "create")]
    #[Groups(['client_product_price:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: "datetime")]
    #[Gedmo\Timestampable(on: "update")]
    #[Groups(['client_product_price:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
    }

    // Rest of methods remain the same...
    // (All the getters and setters)

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;

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

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getDiscountPercentage(): ?float
    {
        return $this->discountPercentage;
    }

    public function setDiscountPercentage(?float $discountPercentage): static
    {
        $this->discountPercentage = $discountPercentage;

        return $this;
    }

    public function getValidFrom(): ?\DateTimeInterface
    {
        return $this->validFrom;
    }

    public function setValidFrom(?\DateTimeInterface $validFrom): static
    {
        $this->validFrom = $validFrom;

        return $this;
    }

    public function getValidUntil(): ?\DateTimeInterface
    {
        return $this->validUntil;
    }

    public function setValidUntil(?\DateTimeInterface $validUntil): static
    {
        $this->validUntil = $validUntil;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    /**
     * Check if the custom price is currently valid
     */
    public function isValid(): bool
    {
        $now = new \DateTime();

        if ($this->validFrom !== null && $this->validFrom > $now) {
            return false;
        }

        if ($this->validUntil !== null && $this->validUntil < $now) {
            return false;
        }

        return true;
    }

    /**
     * Calculate the effective price after applying discount
     */
    public function getEffectivePrice(): float
    {
        if ($this->discountPercentage === null || $this->discountPercentage <= 0) {
            return $this->price;
        }

        $discount = ($this->price * $this->discountPercentage) / 100;
        return $this->price - $discount;
    }
}
