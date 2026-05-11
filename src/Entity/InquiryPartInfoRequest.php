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
use App\Filter\MessageTextSearchFilter;
use App\Filter\UuidSearchFilter;
use App\Repository\InquiryPartInfoRequestRepository;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InquiryPartInfoRequestRepository::class)]
#[ORM\Table(name: 'inquiry_part_info_request')]
#[ORM\Index(name: 'idx_info_request_inquiry', columns: ['inquiry_id'])]
#[ORM\Index(name: 'idx_info_request_part', columns: ['inquiry_machine_part_id'])]
#[ORM\Index(name: 'idx_info_request_status', columns: ['status'])]
#[ApiResource(
    operations: [
        new Get(
            normalizationContext: ['groups' => ['info_request:read', 'info_message:read', 'media_item:read', 'user:read']]
        ),
        new GetCollection(
            paginationItemsPerPage: 30,
            normalizationContext: ['groups' => ['info_request:read', 'info_message:read', 'media_item:read']]
        ),
        new Post(
            normalizationContext: ['groups' => ['info_request:read', 'info_message:read', 'media_item:read']],
            denormalizationContext: ['groups' => ['info_request:write']],
            security: "is_granted('ROLE_ADMIN')"
        ),
        new Patch(
            normalizationContext: ['groups' => ['info_request:read', 'info_message:read', 'media_item:read']],
            denormalizationContext: ['groups' => ['info_request:update']]
        ),
        new Delete(security: "is_granted('ROLE_ADMIN')")
    ],
    normalizationContext: ['groups' => ['info_request:read']],
    denormalizationContext: ['groups' => ['info_request:write']]
)]
#[ApiResource(
    uriTemplate: '/inquiries/{inquiryId}/part-info-requests',
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['info_request:read', 'info_message:read', 'media_item:read']]
        )
    ],
    uriVariables: [
        'inquiryId' => new Link(
            fromProperty: 'partInfoRequests',
            fromClass: Inquiry::class
        )
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'status' => 'exact',
    'inquiry.inquiryNumber' => 'partial',
    'inquiryMachinePart.partName' => 'partial',
    'inquiryMachinePart.partNumber' => 'partial'
])]
#[ApiFilter(UuidSearchFilter::class, properties: [
    'inquiry.id' => 'exact',
    'inquiryMachinePart.id' => 'exact'
])]
#[ApiFilter(DateFilter::class, properties: ['createdAt', 'updatedAt'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'updatedAt', 'status'], arguments: ['orderParameterName' => 'order'])]
#[ApiFilter(MessageTextSearchFilter::class)]
class InquiryPartInfoRequest
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RESPONDED = 'responded';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_NEEDS_REVISION = 'needs_revision';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['info_request:read', 'inquiry:read', 'inquiry_machine_part:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Inquiry::class, inversedBy: 'partInfoRequests')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['info_request:read', 'info_request:write'])]
    #[ApiProperty(readableLink: true, writableLink: false)]
    #[Assert\NotNull]
    private ?Inquiry $inquiry = null;

    #[ORM\ManyToOne(targetEntity: InquiryMachinePart::class, inversedBy: 'infoRequests')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['info_request:read', 'info_request:write'])]
    #[ApiProperty(readableLink: true, writableLink: false)]
    #[Assert\NotNull]
    private ?InquiryMachinePart $inquiryMachinePart = null;

    #[ORM\Column(length: 50)]
    #[Groups(['info_request:read', 'info_request:update', 'inquiry:read', 'inquiry_machine_part:read'])]
    private string $status = self::STATUS_PENDING;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['info_request:read'])]
    #[ApiProperty(readableLink: true)]
    private ?User $createdBy = null;

    #[ORM\Column(type: 'datetime')]
    #[Gedmo\Timestampable(on: 'create')]
    #[Groups(['info_request:read', 'inquiry:read', 'inquiry_machine_part:read'])]
    private ?DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime')]
    #[Gedmo\Timestampable(on: 'update')]
    #[Groups(['info_request:read'])]
    private ?DateTimeInterface $updatedAt = null;

    /**
     * @var Collection<int, InquiryPartInfoMessage>
     */
    #[ORM\OneToMany(
        targetEntity: InquiryPartInfoMessage::class,
        mappedBy: 'infoRequest',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    #[Groups(['info_request:read', 'info_request:write'])]
    #[ApiProperty(readableLink: true, writableLink: true)]
    private Collection $messages;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->messages = new ArrayCollection();
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

    public function getInquiryMachinePart(): ?InquiryMachinePart
    {
        return $this->inquiryMachinePart;
    }

    public function setInquiryMachinePart(?InquiryMachinePart $inquiryMachinePart): static
    {
        $this->inquiryMachinePart = $inquiryMachinePart;

        // Also set the inquiry from the part for convenience
        if ($inquiryMachinePart !== null && $inquiryMachinePart->getInquiryMachine() !== null) {
            $this->inquiry = $inquiryMachinePart->getInquiryMachine()->getInquiry();
        }

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
     * @return Collection<int, InquiryPartInfoMessage>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(InquiryPartInfoMessage $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setInfoRequest($this);
        }

        return $this;
    }

    public function removeMessage(InquiryPartInfoMessage $message): static
    {
        if ($this->messages->removeElement($message)) {
            if ($message->getInfoRequest() === $this) {
                $message->setInfoRequest(null);
            }
        }

        return $this;
    }

    /**
     * Get the latest message in the thread
     */
    public function getLatestMessage(): ?InquiryPartInfoMessage
    {
        if ($this->messages->isEmpty()) {
            return null;
        }

        $messages = $this->messages->toArray();
        usort($messages, fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());

        return $messages[0];
    }

    /**
     * Get message count
     */
    #[Groups(['info_request:read', 'inquiry:read', 'inquiry_machine_part:read'])]
    public function getMessageCount(): int
    {
        return $this->messages->count();
    }

    /**
     * Check if the request has been responded to
     */
    public function hasResponse(): bool
    {
        foreach ($this->messages as $message) {
            if ($message->getSenderType() === InquiryPartInfoMessage::SENDER_TYPE_CLIENT) {
                return true;
            }
        }
        return false;
    }

    /**
     * Mark as responded (call when client submits response)
     */
    public function markAsResponded(): static
    {
        if ($this->status === self::STATUS_PENDING || $this->status === self::STATUS_NEEDS_REVISION) {
            $this->status = self::STATUS_RESPONDED;
        }
        return $this;
    }

    /**
     * Mark as accepted (call when admin approves the response)
     */
    public function markAsAccepted(): static
    {
        $this->status = self::STATUS_ACCEPTED;
        return $this;
    }

    /**
     * Mark as needs revision (call when admin requests more info)
     */
    public function markAsNeedsRevision(): static
    {
        $this->status = self::STATUS_NEEDS_REVISION;
        return $this;
    }

    /**
     * Check if the request is still pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING || $this->status === self::STATUS_NEEDS_REVISION;
    }

    /**
     * Check if the request is accepted
     */
    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    /**
     * Get all valid statuses
     */
    public static function getValidStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_RESPONDED,
            self::STATUS_ACCEPTED,
            self::STATUS_NEEDS_REVISION,
        ];
    }
}
