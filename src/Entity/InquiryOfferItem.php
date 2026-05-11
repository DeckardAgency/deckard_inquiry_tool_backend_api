<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\InquiryOfferItemRepository;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InquiryOfferItemRepository::class)]
#[ORM\Table(name: 'inquiry_offer_item')]
#[ORM\Index(name: 'idx_inquiry_offer_item_offer', columns: ['inquiry_offer_id'])]
#[ApiResource(
    operations: [
        new Get(normalizationContext: ['groups' => ['inquiry_offer_item:read']]),
        new GetCollection(
            paginationItemsPerPage: 30,
            normalizationContext: ['groups' => ['inquiry_offer_item:read']]
        ),
        new Post(
            normalizationContext: ['groups' => ['inquiry_offer_item:read']],
            denormalizationContext: ['groups' => ['inquiry_offer_item:write']],
            security: "is_granted('ROLE_ADMIN')"
        ),
        new Patch(
            normalizationContext: ['groups' => ['inquiry_offer_item:read']],
            denormalizationContext: ['groups' => ['inquiry_offer_item:write']],
            security: "is_granted('ROLE_ADMIN')"
        ),
        new Delete(security: "is_granted('ROLE_ADMIN')")
    ],
    normalizationContext: ['groups' => ['inquiry_offer_item:read']],
    denormalizationContext: ['groups' => ['inquiry_offer_item:write']]
)]
class InquiryOfferItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['inquiry_offer_item:read', 'inquiry_offer:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: InquiryOffer::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['inquiry_offer_item:read', 'inquiry_offer_item:write'])]
    #[ApiProperty(readableLink: false, writableLink: false)]
    private ?InquiryOffer $inquiryOffer = null;

    #[ORM\ManyToOne(targetEntity: InquiryMachinePart::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['inquiry_offer_item:read', 'inquiry_offer_item:write', 'inquiry_offer:read', 'inquiry_offer:write'])]
    #[ApiProperty(readableLink: true, writableLink: false)]
    #[Assert\NotNull]
    private ?InquiryMachinePart $inquiryMachinePart = null;

    #[ORM\Column(type: 'integer')]
    #[Groups(['inquiry_offer_item:read', 'inquiry_offer_item:write', 'inquiry_offer:read', 'inquiry_offer:write'])]
    #[Assert\Positive]
    private int $quantity = 1;

    #[ORM\Column(type: 'float')]
    #[Groups(['inquiry_offer_item:read', 'inquiry_offer_item:write', 'inquiry_offer:read', 'inquiry_offer:write'])]
    #[Assert\PositiveOrZero]
    private float $unitPrice = 0;

    #[ORM\Column(type: 'float')]
    #[Groups(['inquiry_offer_item:read', 'inquiry_offer:read'])]
    private float $subtotal = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['inquiry_offer_item:read', 'inquiry_offer_item:write', 'inquiry_offer:read', 'inquiry_offer:write'])]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime')]
    #[Gedmo\Timestampable(on: 'create')]
    #[Groups(['inquiry_offer_item:read', 'inquiry_offer:read'])]
    private ?DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime')]
    #[Gedmo\Timestampable(on: 'update')]
    #[Groups(['inquiry_offer_item:read'])]
    private ?DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getInquiryOffer(): ?InquiryOffer
    {
        return $this->inquiryOffer;
    }

    public function setInquiryOffer(?InquiryOffer $inquiryOffer): static
    {
        $this->inquiryOffer = $inquiryOffer;
        return $this;
    }

    public function getInquiryMachinePart(): ?InquiryMachinePart
    {
        return $this->inquiryMachinePart;
    }

    public function setInquiryMachinePart(?InquiryMachinePart $inquiryMachinePart): static
    {
        $this->inquiryMachinePart = $inquiryMachinePart;
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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
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

    private function calculateSubtotal(): void
    {
        $this->subtotal = $this->unitPrice * $this->quantity;
    }
}
