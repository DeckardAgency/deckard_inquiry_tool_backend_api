<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model;
use ApiPlatform\OpenApi\Model\Operation;
use App\Controller\InquiryPdfController;
use App\Repository\InquiryRepository;
use App\State\InquiryDraftProvider;
use App\State\Processor\InquiryDraftProcessor;
use App\State\Processor\InquiryMediaItemProcessor;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: InquiryRepository::class)]
#[ORM\Table]
#[ORM\Index(name: "idx_inquiry_status", columns: ["status"])]
#[ORM\Index(name: "idx_inquiry_created_at", columns: ["created_at"])]
#[ORM\Index(name: "idx_inquiry_user_id", columns: ["user_id"])]
#[ORM\Index(name: "idx_inquiry_number", columns: ["inquiry_number"])]
#[ORM\Index(name: "idx_inquiry_is_draft", columns: ["is_draft"])]
#[ApiResource(
    operations: [
        // Standard operations
        new Get(normalizationContext: ['groups' => ['inquiry:read', 'inquiry_item:read', 'user:read', 'machine:read', 'media_item:read']]),
        new GetCollection(
            paginationItemsPerPage: 30,
            paginationClientItemsPerPage: true,
            normalizationContext: ['groups' => ['inquiry:read', 'media_item:read']]
        ),
        new GetCollection(
            uriTemplate: '/inquiries/export/excel',
            controller: 'App\Controller\InquiryExcelController::exportToExcel',
            openapi: new Model\Operation(
                tags: ['Inquiry'],
                responses: [
                    '200' => [
                        'description' => 'Downloads all inquiries and inquiry parts in Excel format',
                        'content' => [
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => [
                                'schema' => [
                                    'type' => 'string',
                                    'format' => 'binary'
                                ]
                            ]
                        ]
                    ]
                ],
                summary: 'Export all inquiries to Excel',
                description: 'Downloads all inquiries and inquiry parts in Excel format'
            ),
            paginationEnabled: false,
        ),
        new Get(
            uriTemplate: '/inquiries/{id}/export/pdf',
            controller: InquiryPdfController::class,
            openapi: new Model\Operation(
                tags: ['Inquiry'],
                responses: [
                    '200' => [
                        'description' => 'Downloads inquiry details in PDF format',
                        'content' => [
                            'application/pdf' => [
                                'schema' => [
                                    'type' => 'string',
                                    'format' => 'binary'
                                ]
                            ]
                        ]
                    ]
                ],
                summary: 'Export inquiry to PDF',
                description: 'Downloads a single inquiry with all details in PDF format'
            ),
            read: false,
            name: 'inquiry_export_pdf'
        ),
        new Post(
            normalizationContext: ['groups' => ['inquiry:read', 'media_item:read']],
            denormalizationContext: ['groups' => ['inquiry:write']],
            processor: InquiryMediaItemProcessor::class
        ),
        new Put(
            normalizationContext: ['groups' => ['inquiry:read', 'media_item:read']],
            denormalizationContext: ['groups' => ['inquiry:write']]
        ),
        new Patch(
            normalizationContext: ['groups' => ['inquiry:read', 'media_item:read']],
            denormalizationContext: ['groups' => ['inquiry:write']]
        ),
        new Delete(),

        // Draft operations
        new GetCollection(
            uriTemplate: '/inquiry-drafts',
            openapi: new Operation(
                summary: 'Retrieves the collection of draft Inquiries for the current user',
                description: 'Returns all draft inquiries belonging to the authenticated user'
            ),
            paginationItemsPerPage: 30,
            paginationClientItemsPerPage: true,
            normalizationContext: ['groups' => ['inquiry:read', 'inquiry_item:read', 'media_item:read']],
            provider: InquiryDraftProvider::class
        ),
        new Post(
            uriTemplate: '/inquiries/{id}/save-draft',
            openapi: new Operation(
                summary: 'Saves an inquiry as draft',
                description: 'Saves or updates the inquiry as a draft'
            ),
            denormalizationContext: ['groups' => ['inquiry:write']],
            read: false,
            processor: InquiryDraftProcessor::class
        ),
        new Post(
            uriTemplate: '/inquiries/{id}/submit',
            openapi: new Operation(
                summary: 'Submits an inquiry from draft',
                description: 'Converts a draft inquiry to a submitted inquiry'
            ),
            denormalizationContext: ['groups' => ['inquiry:write']],
            read: false,
            processor: InquiryDraftProcessor::class
        ),
        new Post(
            uriTemplate: '/inquiries/{id}/approve',
            openapi: new Operation(
                summary: 'Approves a pending inquiry',
                description: 'Approves an inquiry that is pending client admin approval'
            ),
            denormalizationContext: ['groups' => ['inquiry:write']],
            read: false,
            processor: InquiryDraftProcessor::class
        ),
    ],
    normalizationContext: ['groups' => ['inquiry:read', 'machine:read', 'media_item:read']],
    denormalizationContext: ['groups' => ['inquiry:write']]
)]
#[ApiFilter(OrderFilter::class, properties: ['name', 'createdAt'])]
#[ApiFilter(SearchFilter::class, properties: [
    'user.email' => 'exact',
    'status' => 'exact',
    'inquiryNumber' => 'partial',
    'user.client.code' => 'exact',
    'onBehalfOfClient.code' => 'exact'
])]
#[ApiFilter(DateFilter::class, properties: ['createdAt'])]
#[ApiFilter(BooleanFilter::class, properties: ['isDraft'])]
class Inquiry
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_IN_REVIEW = 'in_review';
    public const STATUS_MORE_INFO = 'more_info';
    public const STATUS_INFORMATION_PROVIDED = 'information_provided';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[Groups(['inquiry:read', 'info_request:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 50)]
    #[Groups(['inquiry:read', 'info_request:read'])]
    private ?string $inquiryNumber = null;

    #[ORM\Column(length: 50)]
    #[Groups(['inquiry:read', 'inquiry:write', 'info_request:read'])]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(type: "text", nullable: true)]
    #[Groups(['inquiry:read', 'inquiry:write'])]
    private ?string $notes = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['inquiry:read', 'inquiry:write'])]
    private ?string $contactEmail = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['inquiry:read', 'inquiry:write'])]
    private ?string $contactPhone = null;

    #[ORM\Column(type: "datetime")]
    #[Gedmo\Timestampable(on: "create")]
    #[Groups(['inquiry:read'])]
    private ?DateTimeInterface $createdAt;

    #[ORM\Column(type: "datetime")]
    #[Gedmo\Timestampable(on: "update")]
    #[Groups(['inquiry:read'])]
    private ?DateTimeInterface $updatedAt;

    #[ORM\Column(type: "boolean")]
    #[Groups(['inquiry:read', 'inquiry:write'])]
    private bool $isDraft = true;

    #[ORM\Column(type: "datetime", nullable: true)]
    #[Groups(['inquiry:read'])]
    private ?DateTimeInterface $lastSavedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'inquiries')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['inquiry:read', 'inquiry:write'])]
    #[ApiProperty(readableLink: true, writableLink: false)]
    private ?User $user = null;

    /**
     * @var Collection<int, InquiryMachine>
     */
    #[ORM\OneToMany(targetEntity: InquiryMachine::class, mappedBy: 'inquiry', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['inquiry:read', 'inquiry:write'])]
    #[ApiProperty(readableLink: true, writableLink: true)]
    private Collection $machines;

    /**
     * @var Collection<int, InquiryLog>
     */
    #[ORM\OneToMany(targetEntity: InquiryLog::class, mappedBy: 'inquiry', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['inquiry:read'])]
    #[ApiProperty(readableLink: true)]
    private Collection $logs;

    /**
     * @var Collection<int, InquiryPartInfoRequest>
     */
    #[ORM\OneToMany(targetEntity: InquiryPartInfoRequest::class, mappedBy: 'inquiry', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    #[Groups(['inquiry:read'])]
    #[ApiProperty(readableLink: true)]
    private Collection $partInfoRequests;

    /**
     * @var Collection<int, InquiryOffer>
     */
    #[ORM\OneToMany(targetEntity: InquiryOffer::class, mappedBy: 'inquiry', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    #[Groups(['inquiry:read'])]
    #[ApiProperty(readableLink: true)]
    private Collection $offers;

    /**
     * When set, this inquiry was placed by an agent on behalf of this client
     */
    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['inquiry:read', 'inquiry:write'])]
    #[ApiProperty(readableLink: true)]
    private ?Client $onBehalfOfClient = null;

    // Cancellation fields (for canceled status)
    #[ORM\Column(type: "text", nullable: true)]
    #[Groups(['inquiry:read', 'inquiry:write'])]
    private ?string $cancellationReason = null;

    #[ORM\Column(type: "datetime", nullable: true)]
    #[Groups(['inquiry:read', 'inquiry:write'])]
    private ?DateTimeInterface $cancelledAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['inquiry:read', 'inquiry:write'])]
    private ?User $cancelledBy = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->machines = new ArrayCollection();
        $this->logs = new ArrayCollection();
        $this->partInfoRequests = new ArrayCollection();
        $this->offers = new ArrayCollection();
        $this->inquiryNumber = $this->generateInquiryNumber();
        $this->lastSavedAt = new \DateTime();
    }

    private function generateInquiryNumber(): string
    {
        // Generate a random inquiry number with prefix
        return 'INQ-' . strtoupper(substr(uniqid(), -8));
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getInquiryNumber(): ?string
    {
        return $this->inquiryNumber;
    }

    public function setInquiryNumber(string $inquiryNumber): static
    {
        $this->inquiryNumber = $inquiryNumber;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        // Update isDraft flag based on status
        if ($status === self::STATUS_DRAFT) {
            $this->isDraft = true;
        } elseif ($this->isDraft && $status !== self::STATUS_DRAFT) {
            $this->isDraft = false;
        }

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

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function setContactEmail(?string $contactEmail): static
    {
        $this->contactEmail = $contactEmail;
        return $this;
    }

    public function getContactPhone(): ?string
    {
        return $this->contactPhone;
    }

    public function setContactPhone(?string $contactPhone): static
    {
        $this->contactPhone = $contactPhone;
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Check if the inquiry is in draft state
     */
    public function isDraft(): bool
    {
        return $this->isDraft;
    }

    /**
     * Set the draft status of the inquiry
     */
    public function setIsDraft(bool $isDraft): static
    {
        $this->isDraft = $isDraft;

        // Update status based on draft state
        if ($isDraft && $this->status !== self::STATUS_CANCELED) {
            $this->status = self::STATUS_DRAFT;
        } elseif (!$isDraft && $this->status === self::STATUS_DRAFT) {
            $this->status = self::STATUS_SUBMITTED;
        }

        return $this;
    }

    /**
     * Get the last time this draft was saved
     */
    public function getLastSavedAt(): ?DateTimeInterface
    {
        return $this->lastSavedAt;
    }

    /**
     * Set the last saved time
     */
    public function setLastSavedAt(?DateTimeInterface $lastSavedAt): static
    {
        $this->lastSavedAt = $lastSavedAt;
        return $this;
    }

    /**
     * Save the current state as a draft
     */
    public function saveDraft(): static
    {
        $this->lastSavedAt = new \DateTime();
        $this->isDraft = true;
        $this->status = self::STATUS_DRAFT;

        return $this;
    }

    /**
     * Submit the draft inquiry, converting it to a pending or submitted inquiry
     */
    public function submitInquiry(string $targetStatus = self::STATUS_SUBMITTED): static
    {
        if ($this->isDraft) {
            $this->isDraft = false;
            $this->status = $targetStatus;
        }

        return $this;
    }

    /**
     * @return Collection<int, InquiryMachine>
     */
    public function getMachines(): Collection
    {
        return $this->machines;
    }

    public function addMachine(InquiryMachine $machine): static
    {
        if (!$this->machines->contains($machine)) {
            $this->machines->add($machine);
            $machine->setInquiry($this);
        }

        return $this;
    }

    public function removeMachine(InquiryMachine $machine): static
    {
        if ($this->machines->removeElement($machine)) {
            if ($machine->getInquiry() === $this) {
                $machine->setInquiry(null);
            }
        }

        return $this;
    }

    /**
     * Check if the inquiry can be submitted (has required fields)
     */
    public function canBeSubmitted(): bool
    {
        // Check if inquiry has at least one machine
        if ($this->machines->isEmpty()) {
            return false;
        }

        // Check if all machines have at least one product
        foreach ($this->machines as $machine) {
            if ($machine->getProducts()->isEmpty()) {
                return false;
            }
        }

        // Check if contact information is set
        if (empty($this->contactEmail) && empty($this->contactPhone)) {
            return false;
        }

        // Check if user is set
        if ($this->user === null) {
            return false;
        }

        return true;
    }

    /**
     * Get validation errors preventing submission
     * @return array<string> List of validation errors
     */
    public function getSubmissionErrors(): array
    {
        $errors = [];

        if ($this->machines->isEmpty()) {
            $errors[] = 'Inquiry must have at least one machine';
        } else {
            // Check if all machines have at least one part
            foreach ($this->machines as $machine) {
                if ($machine->getProducts()->isEmpty()) {
                    $errors[] = 'All machines in inquiry must have at least one part';
                    break;
                }
            }
        }

        if (empty($this->contactEmail) && empty($this->contactPhone)) {
            $errors[] = 'At least one contact method (email or phone) is required';
        }

        if ($this->user === null) {
            $errors[] = 'User must be set for the inquiry';
        }

        return $errors;
    }

    /**
     * @return Collection<int, InquiryLog>
     */
    public function getLogs(): Collection
    {
        return $this->logs;
    }

    public function addLog(InquiryLog $log): static
    {
        if (!$this->logs->contains($log)) {
            $this->logs->add($log);
            $log->setInquiry($this);
        }

        return $this;
    }

    public function removeLog(InquiryLog $log): static
    {
        if ($this->logs->removeElement($log)) {
            // set the owning side to null (unless already changed)
            if ($log->getInquiry() === $this) {
                $log->setInquiry(null);
            }
        }

        return $this;
    }

    public function getCancellationReason(): ?string
    {
        return $this->cancellationReason;
    }

    public function setCancellationReason(?string $cancellationReason): static
    {
        $this->cancellationReason = $cancellationReason;
        return $this;
    }

    public function getCancelledAt(): ?DateTimeInterface
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?DateTimeInterface $cancelledAt): static
    {
        $this->cancelledAt = $cancelledAt;
        return $this;
    }

    public function getCancelledBy(): ?User
    {
        return $this->cancelledBy;
    }

    public function setCancelledBy(?User $cancelledBy): static
    {
        $this->cancelledBy = $cancelledBy;
        return $this;
    }

    /**
     * Cancel the inquiry with a reason and the user who canceled it
     */
    public function cancel(string $reason, User $cancelledBy): static
    {
        $this->status = self::STATUS_CANCELED;
        $this->cancellationReason = $reason;
        $this->cancelledAt = new \DateTime();
        $this->cancelledBy = $cancelledBy;

        return $this;
    }

    /**
     * @return Collection<int, InquiryPartInfoRequest>
     */
    public function getPartInfoRequests(): Collection
    {
        return $this->partInfoRequests;
    }

    public function addPartInfoRequest(InquiryPartInfoRequest $partInfoRequest): static
    {
        if (!$this->partInfoRequests->contains($partInfoRequest)) {
            $this->partInfoRequests->add($partInfoRequest);
            $partInfoRequest->setInquiry($this);
        }

        return $this;
    }

    public function removePartInfoRequest(InquiryPartInfoRequest $partInfoRequest): static
    {
        if ($this->partInfoRequests->removeElement($partInfoRequest)) {
            if ($partInfoRequest->getInquiry() === $this) {
                $partInfoRequest->setInquiry(null);
            }
        }

        return $this;
    }

    /**
     * Get all pending info requests
     */
    public function getPendingInfoRequests(): array
    {
        return $this->partInfoRequests->filter(
            fn(InquiryPartInfoRequest $request) => $request->isPending()
        )->toArray();
    }

    /**
     * Check if there are any pending info requests
     */
    public function hasPendingInfoRequests(): bool
    {
        return !empty($this->getPendingInfoRequests());
    }

    /**
     * Get count of pending info requests
     */
    #[Groups(['inquiry:read'])]
    public function getPendingInfoRequestCount(): int
    {
        return count($this->getPendingInfoRequests());
    }

    /**
     * Get all responded info requests (awaiting admin review)
     */
    public function getRespondedInfoRequests(): array
    {
        return $this->partInfoRequests->filter(
            fn(InquiryPartInfoRequest $request) => $request->getStatus() === InquiryPartInfoRequest::STATUS_RESPONDED
        )->toArray();
    }

    /**
     * Check if all info requests are accepted
     */
    public function allInfoRequestsAccepted(): bool
    {
        if ($this->partInfoRequests->isEmpty()) {
            return true;
        }

        foreach ($this->partInfoRequests as $request) {
            if (!$request->isAccepted()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return Collection<int, InquiryOffer>
     */
    public function getOffers(): Collection
    {
        return $this->offers;
    }

    public function addOffer(InquiryOffer $offer): static
    {
        if (!$this->offers->contains($offer)) {
            $this->offers->add($offer);
            $offer->setInquiry($this);
        }

        return $this;
    }

    public function removeOffer(InquiryOffer $offer): static
    {
        if ($this->offers->removeElement($offer)) {
            if ($offer->getInquiry() === $this) {
                $offer->setInquiry(null);
            }
        }

        return $this;
    }

    /**
     * Get count of offers
     */
    #[Groups(['inquiry:read'])]
    public function getOfferCount(): int
    {
        return $this->offers->count();
    }

    /**
     * Get all parts that need info (across all machines)
     */
    public function getPartsNeedingInfo(): array
    {
        $parts = [];
        foreach ($this->machines as $machine) {
            foreach ($machine->getProducts() as $part) {
                if ($part->needsInfo()) {
                    $parts[] = $part;
                }
            }
        }
        return $parts;
    }

    /**
     * Check if all parts have been reviewed (clear or info provided)
     */
    public function allPartsReviewed(): bool
    {
        foreach ($this->machines as $machine) {
            foreach ($machine->getProducts() as $part) {
                if ($part->getInfoStatus() === InquiryMachinePart::INFO_STATUS_NONE) {
                    return false;
                }
            }
        }
        return true;
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
