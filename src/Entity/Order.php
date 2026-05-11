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
use App\Controller\OrderPdfController;
use App\Repository\OrderRepository;
use App\State\OrderDraftProvider;
use App\State\Processor\OrderDraftProcessor;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
#[ORM\Index(name: "idx_order_status", columns: ["status"])]
#[ORM\Index(name: "idx_order_created_at", columns: ["created_at"])]
#[ORM\Index(name: "idx_order_user_id", columns: ["user_id"])]
#[ORM\Index(name: "idx_order_number", columns: ["order_number"])]
#[ApiResource(
    operations: [
        // Existing operations
        new Get(normalizationContext: ['groups' => ['order:read', 'order:item', 'order_item:read']]),
        new GetCollection(
            paginationItemsPerPage: 30,
            paginationClientItemsPerPage: true,
            normalizationContext: ['groups' => ['order:read', 'order:collection']]
        ),
        new GetCollection(
            uriTemplate: '/orders/export/excel',
            controller: 'App\Controller\OrderExcelController::exportToExcel',
            openapi: new Model\Operation(
                tags: ['Order'],
                responses: [
                    '200' => [
                        'description' => 'Downloads all orders and order items in Excel format',
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
                summary: 'Export all orders to Excel',
                description: 'Downloads all orders and order items in Excel format'
            ),
            paginationEnabled: false,
        ),
        new Get(
            uriTemplate: '/orders/{id}/export/pdf',
            controller: OrderPdfController::class,
            openapi: new Model\Operation(
                tags: ['Order'],
                responses: [
                    '200' => [
                        'description' => 'Downloads order details in PDF format',
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
                summary: 'Export order to PDF',
                description: 'Downloads a single order with all details in PDF format'
            ),
            read: false,
            name: 'order_export_pdf'
        ),
        new Post(
            normalizationContext: ['groups' => ['order:read']],
            denormalizationContext: ['groups' => ['order:write']],
            processor: 'App\State\Processor\OrderPriceProcessor'
        ),
        new Put(
            normalizationContext: ['groups' => ['order:read']],
            denormalizationContext: ['groups' => ['order:write']],
            processor: 'App\State\Processor\OrderPriceProcessor'
        ),
        new Patch(
            normalizationContext: ['groups' => ['order:read']],
            denormalizationContext: ['groups' => ['order:write']],
            processor: 'App\State\Processor\OrderPriceProcessor'
        ),
        new Delete(),

        // New operations for drafts
        new GetCollection(
            uriTemplate: '/drafts',
            openapi: new Operation(
                summary: 'Retrieves the collection of draft Orders for the current user',
                description: 'Returns all draft orders belonging to the authenticated user'
            ),
            paginationItemsPerPage: 30,
            paginationClientItemsPerPage: true,
            normalizationContext: ['groups' => ['order:read', 'order_item:read']],
            provider: OrderDraftProvider::class
        ),
        new Post(
            uriTemplate: '/orders/{id}/save-draft',
            openapi: new Operation(
                summary: 'Saves an order as draft',
                description: 'Saves or updates the order as a draft'
            ),
            denormalizationContext: ['groups' => ['order:write']],
            read: false,
            processor: OrderDraftProcessor::class
        ),
        new Post(
            uriTemplate: '/orders/{id}/submit',
            openapi: new Operation(
                summary: 'Submits an order from draft',
                description: 'Converts a draft order to a pending order'
            ),
            denormalizationContext: ['groups' => ['order:write']],
            read: false,
            processor: OrderDraftProcessor::class
        ),
        new Post(
            uriTemplate: '/orders/{id}/approve',
            openapi: new Operation(
                summary: 'Approves a pending order',
                description: 'Approves an order that is pending client admin approval'
            ),
            denormalizationContext: ['groups' => ['order:write']],
            read: false,
            processor: OrderDraftProcessor::class
        )
    ],
    normalizationContext: ['groups' => ['order:read']],
    denormalizationContext: ['groups' => ['order:write']]
)]
#[ApiFilter(OrderFilter::class, properties: ['name', 'createdAt'])]
#[ApiFilter(SearchFilter::class, properties: [
    'user.email' => 'exact',
    'status' => 'exact',
    'user.client.code' => 'exact',
    'orderNumber' => 'partial',
    'onBehalfOfClient.code' => 'exact'
])]
#[ApiFilter(DateFilter::class, properties: ['createdAt'])]
#[ApiFilter(BooleanFilter::class, properties: ['isDraft'])]
class Order
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_DISPATCHED = 'dispatched';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const CARRIER_DHL = 'dhl';
    public const CARRIER_FEDEX = 'fedex';
    public const CARRIER_OTHER = 'other';

    public const ALLOWED_CARRIERS = [
        self::CARRIER_DHL,
        self::CARRIER_FEDEX,
        self::CARRIER_OTHER,
    ];

    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[Groups(['order:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 50)]
    #[Groups(['order:read'])]
    private ?string $orderNumber = null;

    #[ORM\Column(length: 50)]
    #[Groups(['order:read', 'order:write'])]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(type: "float")]
    #[Groups(['order:read'])]
    private float $totalAmount = 0;

    #[ORM\Column(type: "text", nullable: true)]
    #[Groups(['order:read', 'order:write'])]
    private ?string $notes = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['order:read', 'order:write'])]
    private ?string $shippingAddress = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['order:read', 'order:write'])]
    private ?string $billingAddress = null;

    #[ORM\Column(type: "datetime")]
    #[Gedmo\Timestampable(on: "create")]
    #[Groups(['order:read'])]
    private ?DateTimeInterface $createdAt;

    #[ORM\Column(type: "datetime")]
    #[Gedmo\Timestampable(on: "update")]
    #[Groups(['order:read'])]
    private ?DateTimeInterface $updatedAt;

    #[ORM\Column(type: "boolean")]
    #[Groups(['order:read', 'order:write'])]
    private bool $isDraft = true;

    #[ORM\Column(type: "datetime", nullable: true)]
    #[Groups(['order:read'])]
    private ?DateTimeInterface $lastSavedAt = null;

    /**
     * @var Collection<int, OrderItem>
     */
    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'orderRef', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['order:read', 'order:write'])]
    #[ApiProperty(readableLink: true, writableLink: true)]
    private Collection $items;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['order:read', 'order:write'])]
    #[ApiProperty(readableLink: true, writableLink: false)]
    private ?User $user = null;

    /**
     * @var Collection<int, OrderLog>
     */
    #[ORM\OneToMany(targetEntity: OrderLog::class, mappedBy: 'order', cascade: ['persist'])]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    #[Groups(['order:read'])]
    private Collection $logs;

    // Tracking fields (for dispatched status)
    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['order:read', 'order:write'])]
    private ?string $trackingNumber = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['order:read', 'order:write'])]
    private ?string $trackingCarrier = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['order:read', 'order:write'])]
    private ?string $trackingUrl = null;

    #[ORM\Column(type: "datetime", nullable: true)]
    #[Groups(['order:read'])]
    private ?DateTimeInterface $dispatchedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['order:read'])]
    private ?User $dispatchedBy = null;

    // Cancellation fields (for canceled status)
    #[ORM\Column(type: "text", nullable: true)]
    #[Groups(['order:read', 'order:write'])]
    private ?string $cancellationReason = null;

    #[ORM\Column(type: "datetime", nullable: true)]
    #[Groups(['order:read'])]
    private ?DateTimeInterface $cancelledAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['order:read'])]
    private ?User $cancelledBy = null;

    /**
     * When set, this order was placed by an agent on behalf of this client
     */
    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['order:read', 'order:write'])]
    #[ApiProperty(readableLink: true)]
    private ?Client $onBehalfOfClient = null;

    /**
     * @var Collection<int, DispatchDocument>
     */
    #[ORM\OneToMany(targetEntity: DispatchDocument::class, mappedBy: 'order', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    #[Groups(['order:read'])]
    private Collection $dispatchDocuments;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->items = new ArrayCollection();
        $this->orderNumber = $this->generateOrderNumber();
        $this->lastSavedAt = new \DateTime();
        $this->logs = new ArrayCollection();
        $this->dispatchDocuments = new ArrayCollection();
    }

    private function generateOrderNumber(): string
    {
        // Generate a random order number with prefix
        return 'ORD-' . strtoupper(substr(uniqid(), -8));
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getOrderNumber(): ?string
    {
        return $this->orderNumber;
    }

    public function setOrderNumber(string $orderNumber): static
    {
        $this->orderNumber = $orderNumber;
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

    public function getTotalAmount(): float
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(float $totalAmount): static
    {
        $this->totalAmount = $totalAmount;
        return $this;
    }

    public function calculateTotalAmount(): static
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += $item->getSubtotal();
        }
        $this->totalAmount = $total;
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

    public function getShippingAddress(): ?string
    {
        return $this->shippingAddress;
    }

    public function setShippingAddress(?string $shippingAddress): static
    {
        $this->shippingAddress = $shippingAddress;
        return $this;
    }

    public function getBillingAddress(): ?string
    {
        return $this->billingAddress;
    }

    public function setBillingAddress(?string $billingAddress): static
    {
        $this->billingAddress = $billingAddress;
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
     * @return Collection<int, OrderItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(OrderItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setOrderRef($this);
            $this->calculateTotalAmount();
        }

        return $this;
    }

    public function removeItem(OrderItem $item): static
    {
        if ($this->items->removeElement($item)) {
            // set the owning side to null (unless already changed)
            if ($item->getOrderRef() === $this) {
                $item->setOrderRef(null);
            }
            $this->calculateTotalAmount();
        }

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
     * Check if the order is in draft state
     */
    public function isDraft(): bool
    {
        return $this->isDraft;
    }

    /**
     * Set the draft status of the order
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
     * Submit the draft order, converting it to a pending or submitted order
     */
    public function submitOrder(string $targetStatus = self::STATUS_SUBMITTED): static
    {
        if ($this->isDraft) {
            $this->isDraft = false;
            $this->status = $targetStatus;
        }

        return $this;
    }

    /**
     * Check if the order can be submitted (has required fields)
     */
    public function canBeSubmitted(): bool
    {
        // Check if order has at least one item
        if ($this->items->isEmpty()) {
            return false;
        }

        // Check if shipping address is set
        if (empty($this->shippingAddress)) {
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

        if ($this->items->isEmpty()) {
            $errors[] = 'Order must have at least one item';
        }

        if (empty($this->shippingAddress)) {
            $errors[] = 'Shipping address is required';
        }

        if ($this->user === null) {
            $errors[] = 'User must be set for the order';
        }

        return $errors;
    }

    /**
     * @return Collection<int, OrderLog>
     */
    public function getLogs(): Collection
    {
        return $this->logs;
    }

    public function addLog(OrderLog $log): static
    {
        if (!$this->logs->contains($log)) {
            $this->logs->add($log);
            $log->setOrder($this);
        }

        return $this;
    }

    public function removeLog(OrderLog $log): static
    {
        if ($this->logs->removeElement($log)) {
            // set the owning side to null (unless already changed)
            if ($log->getOrder() === $this) {
                $log->setOrder(null);
            }
        }

        return $this;
    }

    // Tracking getters and setters

    public function getTrackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    public function setTrackingNumber(?string $trackingNumber): static
    {
        $this->trackingNumber = $trackingNumber;
        return $this;
    }

    public function getTrackingCarrier(): ?string
    {
        return $this->trackingCarrier;
    }

    public function setTrackingCarrier(?string $trackingCarrier): static
    {
        $this->trackingCarrier = $trackingCarrier;
        return $this;
    }

    public function getTrackingUrl(): ?string
    {
        return $this->trackingUrl;
    }

    public function setTrackingUrl(?string $trackingUrl): static
    {
        $this->trackingUrl = $trackingUrl;
        return $this;
    }

    public function getDispatchedAt(): ?DateTimeInterface
    {
        return $this->dispatchedAt;
    }

    public function setDispatchedAt(?DateTimeInterface $dispatchedAt): static
    {
        $this->dispatchedAt = $dispatchedAt;
        return $this;
    }

    public function getDispatchedBy(): ?User
    {
        return $this->dispatchedBy;
    }

    public function setDispatchedBy(?User $dispatchedBy): static
    {
        $this->dispatchedBy = $dispatchedBy;
        return $this;
    }

    // Cancellation getters and setters

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
     * Generate tracking URL based on carrier
     */
    public function generateTrackingUrl(): ?string
    {
        if (!$this->trackingNumber || !$this->trackingCarrier) {
            return null;
        }

        $urls = [
            self::CARRIER_DHL => 'https://www.dhl.com/en/express/tracking.html?AWB=' . $this->trackingNumber,
            self::CARRIER_FEDEX => 'https://www.fedex.com/fedextrack/?trknbr=' . $this->trackingNumber,
        ];

        return $urls[strtolower($this->trackingCarrier)] ?? null;
    }

    // Dispatch Documents getters and setters

    /**
     * @return Collection<int, DispatchDocument>
     */
    public function getDispatchDocuments(): Collection
    {
        return $this->dispatchDocuments;
    }

    public function addDispatchDocument(DispatchDocument $document): static
    {
        if (!$this->dispatchDocuments->contains($document)) {
            $this->dispatchDocuments->add($document);
            $document->setOrder($this);
        }

        return $this;
    }

    public function removeDispatchDocument(DispatchDocument $document): static
    {
        if ($this->dispatchDocuments->removeElement($document)) {
            // set the owning side to null (unless already changed)
            if ($document->getOrder() === $this) {
                $document->setOrder(null);
            }
        }

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
