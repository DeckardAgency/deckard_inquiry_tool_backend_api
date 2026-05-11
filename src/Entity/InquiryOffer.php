<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Filter\UuidSearchFilter;
use App\Repository\InquiryOfferRepository;
use App\State\Processor\InquiryOfferProcessor;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InquiryOfferRepository::class)]
#[ORM\Table(name: 'inquiry_offer')]
#[ORM\Index(name: 'idx_inquiry_offer_inquiry', columns: ['inquiry_id'])]
#[ORM\Index(name: 'idx_inquiry_offer_status', columns: ['status'])]
#[ApiResource(
    operations: [
        new Get(
            normalizationContext: ['groups' => ['inquiry_offer:read', 'inquiry_offer_item:read', 'media_item:read', 'user:read']]
        ),
        new GetCollection(
            paginationItemsPerPage: 30,
            normalizationContext: ['groups' => ['inquiry_offer:read', 'inquiry_offer_item:read', 'media_item:read']]
        ),
        new Post(
            normalizationContext: ['groups' => ['inquiry_offer:read', 'inquiry_offer_item:read', 'media_item:read']],
            denormalizationContext: ['groups' => ['inquiry_offer:write']],
            security: "is_granted('ROLE_ADMIN')"
        ),
        new Patch(
            normalizationContext: ['groups' => ['inquiry_offer:read', 'inquiry_offer_item:read', 'media_item:read']],
            denormalizationContext: ['groups' => ['inquiry_offer:update']]
        ),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
        new Post(
            uriTemplate: '/inquiry_offers/{id}/send',
            openapi: new Operation(
                summary: 'Send an offer to the client',
                description: 'Changes offer status from draft to sent and notifies the client'
            ),
            denormalizationContext: ['groups' => ['inquiry_offer:send']],
            read: false,
            validate: false,
            processor: InquiryOfferProcessor::class,
            security: "is_granted('ROLE_ADMIN')"
        ),
        new Patch(
            uriTemplate: '/inquiry_offers/{id}/respond',
            openapi: new Operation(
                summary: 'Client responds to an offer (accept or reject)',
                description: 'Client accepts or rejects a sent offer'
            ),
            normalizationContext: ['groups' => ['inquiry_offer:read', 'inquiry_offer_item:read']],
            denormalizationContext: ['groups' => ['inquiry_offer:respond']],
            processor: InquiryOfferProcessor::class
        )
    ],
    normalizationContext: ['groups' => ['inquiry_offer:read']],
    denormalizationContext: ['groups' => ['inquiry_offer:write']]
)]
#[ApiResource(
    uriTemplate: '/inquiries/{inquiryId}/offers',
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['inquiry_offer:read', 'inquiry_offer_item:read', 'media_item:read']]
        )
    ],
    uriVariables: [
        'inquiryId' => new Link(
            fromProperty: 'offers',
            fromClass: Inquiry::class
        )
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'status' => 'exact',
    'offerNumber' => 'partial'
])]
#[ApiFilter(UuidSearchFilter::class, properties: [
    'inquiry.id' => 'exact'
])]
#[ApiFilter(DateFilter::class, properties: ['createdAt', 'updatedAt'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'updatedAt', 'status'], arguments: ['orderParameterName' => 'order'])]
class InquiryOffer
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['inquiry_offer:read', 'inquiry:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Inquiry::class, inversedBy: 'offers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['inquiry_offer:read', 'inquiry_offer:write'])]
    #[ApiProperty(readableLink: true, writableLink: false)]
    #[Assert\NotNull]
    private ?Inquiry $inquiry = null;

    #[ORM\Column(length: 50)]
    #[Groups(['inquiry_offer:read', 'inquiry:read'])]
    private ?string $offerNumber = null;

    #[ORM\Column(length: 50)]
    #[Groups(['inquiry_offer:read', 'inquiry_offer:update', 'inquiry_offer:respond', 'inquiry:read'])]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['inquiry_offer:read', 'inquiry_offer:write', 'inquiry_offer:update'])]
    private ?string $notes = null;

    #[ORM\Column(type: 'float')]
    #[Groups(['inquiry_offer:read', 'inquiry:read'])]
    private float $totalAmount = 0;

    #[ORM\ManyToOne(targetEntity: MediaItem::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['inquiry_offer:read', 'inquiry_offer:write', 'inquiry_offer:update'])]
    #[ApiProperty(readableLink: true, writableLink: false)]
    private ?MediaItem $pdfDocument = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['inquiry_offer:read', 'inquiry_offer:respond', 'inquiry:read'])]
    private ?string $rejectionReason = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['inquiry_offer:read', 'inquiry:read'])]
    private ?DateTimeInterface $respondedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['inquiry_offer:read'])]
    #[ApiProperty(readableLink: true)]
    private ?User $createdBy = null;

    #[ORM\Column(type: 'datetime')]
    #[Gedmo\Timestampable(on: 'create')]
    #[Groups(['inquiry_offer:read', 'inquiry:read'])]
    private ?DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime')]
    #[Gedmo\Timestampable(on: 'update')]
    #[Groups(['inquiry_offer:read'])]
    private ?DateTimeInterface $updatedAt = null;

    /**
     * @var Collection<int, InquiryOfferItem>
     */
    #[ORM\OneToMany(
        targetEntity: InquiryOfferItem::class,
        mappedBy: 'inquiryOffer',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    #[Groups(['inquiry_offer:read', 'inquiry_offer:write'])]
    #[ApiProperty(readableLink: true, writableLink: true)]
    private Collection $items;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->items = new ArrayCollection();
        $this->offerNumber = $this->generateOfferNumber();
    }

    private function generateOfferNumber(): string
    {
        return 'OFR-' . strtoupper(substr(uniqid(), -8));
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getInquiry(): ?Inquiry
    {
        return $this->inquiry;
    }

    public function setInquiry(?Inquiry $inquiry): static
    {
        $this->inquiry = $inquiry;
        return $this;
    }

    public function getOfferNumber(): ?string
    {
        return $this->offerNumber;
    }

    public function setOfferNumber(string $offerNumber): static
    {
        $this->offerNumber = $offerNumber;
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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getTotalAmount(): float
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(float $totalAmount): static
    {
        $this->totalAmount = $totalAmount;
        return $this;
    }

    public function getPdfDocument(): ?MediaItem
    {
        return $this->pdfDocument;
    }

    public function setPdfDocument(?MediaItem $pdfDocument): static
    {
        $this->pdfDocument = $pdfDocument;
        return $this;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): static
    {
        $this->rejectionReason = $rejectionReason;
        return $this;
    }

    public function getRespondedAt(): ?DateTimeInterface
    {
        return $this->respondedAt;
    }

    public function setRespondedAt(?DateTimeInterface $respondedAt): static
    {
        $this->respondedAt = $respondedAt;
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

    /**
     * @return Collection<int, InquiryOfferItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(InquiryOfferItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setInquiryOffer($this);
        }

        return $this;
    }

    public function removeItem(InquiryOfferItem $item): static
    {
        if ($this->items->removeElement($item)) {
            if ($item->getInquiryOffer() === $this) {
                $item->setInquiryOffer(null);
            }
        }

        return $this;
    }

    /**
     * Recalculate total amount from items
     */
    public function recalculateTotalAmount(): static
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += $item->getSubtotal();
        }
        $this->totalAmount = $total;
        return $this;
    }

    /**
     * Get the number of items
     */
    #[Groups(['inquiry_offer:read', 'inquiry:read'])]
    public function getItemCount(): int
    {
        return $this->items->count();
    }

    /**
     * Check if the offer is in draft status
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if the offer has been sent
     */
    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    /**
     * Check if the offer has been accepted
     */
    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    /**
     * Check if the offer has been rejected
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Get all valid statuses
     */
    public static function getValidStatuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_SENT,
            self::STATUS_ACCEPTED,
            self::STATUS_REJECTED,
        ];
    }
}
