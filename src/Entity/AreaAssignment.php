<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use App\Repository\AreaAssignmentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AreaAssignmentRepository::class)]
#[ORM\Table(name: 'area_assignments')]
#[ORM\Index(columns: ['area_manager_id'], name: 'idx_area_assignment_manager')]
#[ORM\Index(columns: ['inquiry_id'], name: 'idx_area_assignment_inquiry')]
#[ORM\Index(columns: ['order_id'], name: 'idx_area_assignment_order')]
#[ORM\Index(columns: ['is_active'], name: 'idx_area_assignment_active')]
#[ORM\Index(columns: ['assigned_at'], name: 'idx_area_assignment_assigned_at')]
#[ApiResource(
    normalizationContext: ['groups' => ['area_assignment:read']],
    denormalizationContext: ['groups' => ['area_assignment:write']],
    paginationEnabled: true,
)]
class AreaAssignment
{
    public const ASSIGNMENT_TYPE_AUTO = 'auto';
    public const ASSIGNMENT_TYPE_MANUAL = 'manual';
    public const ASSIGNMENT_TYPE_REASSIGNED = 'reassigned';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['area_assignment:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: AreaManager::class, inversedBy: 'assignments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    #[Groups(['area_assignment:read', 'area_assignment:write'])]
    private ?AreaManager $areaManager = null;

    #[ORM\ManyToOne(targetEntity: Inquiry::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    #[Groups(['area_assignment:read', 'area_assignment:write'])]
    private ?Inquiry $inquiry = null;

    #[ORM\ManyToOne(targetEntity: Order::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    #[Groups(['area_assignment:read', 'area_assignment:write'])]
    private ?Order $order = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [
        self::ASSIGNMENT_TYPE_AUTO,
        self::ASSIGNMENT_TYPE_MANUAL,
        self::ASSIGNMENT_TYPE_REASSIGNED,
    ])]
    #[Groups(['area_assignment:read', 'area_assignment:write'])]
    private ?string $assignmentType = self::ASSIGNMENT_TYPE_AUTO;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['area_assignment:read', 'area_assignment:write'])]
    private ?string $assignmentStrategy = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    #[Groups(['area_assignment:read', 'area_assignment:write'])]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['area_assignment:read'])]
    private ?\DateTimeImmutable $assignedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['area_assignment:read', 'area_assignment:write'])]
    private ?\DateTimeImmutable $unassignedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['area_assignment:read'])]
    private ?User $assignedBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['area_assignment:read'])]
    private ?User $unassignedBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['area_assignment:read', 'area_assignment:write'])]
    private ?string $assignmentReason = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['area_assignment:read', 'area_assignment:write'])]
    private ?string $unassignmentReason = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['area_assignment:read', 'area_assignment:write'])]
    private ?array $metadata = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['area_assignment:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['area_assignment:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->assignedAt = new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getAreaManager(): ?AreaManager
    {
        return $this->areaManager;
    }

    public function setAreaManager(?AreaManager $areaManager): self
    {
        $this->areaManager = $areaManager;
        return $this;
    }

    public function getInquiry(): ?Inquiry
    {
        return $this->inquiry;
    }

    public function setInquiry(?Inquiry $inquiry): self
    {
        $this->inquiry = $inquiry;
        return $this;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): self
    {
        $this->order = $order;
        return $this;
    }

    public function getAssignmentType(): ?string
    {
        return $this->assignmentType;
    }

    public function setAssignmentType(string $assignmentType): self
    {
        $this->assignmentType = $assignmentType;
        return $this;
    }

    public function getAssignmentStrategy(): ?string
    {
        return $this->assignmentStrategy;
    }

    public function setAssignmentStrategy(?string $assignmentStrategy): self
    {
        $this->assignmentStrategy = $assignmentStrategy;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getAssignedAt(): ?\DateTimeImmutable
    {
        return $this->assignedAt;
    }

    public function setAssignedAt(\DateTimeImmutable $assignedAt): self
    {
        $this->assignedAt = $assignedAt;
        return $this;
    }

    public function getUnassignedAt(): ?\DateTimeImmutable
    {
        return $this->unassignedAt;
    }

    public function setUnassignedAt(?\DateTimeImmutable $unassignedAt): self
    {
        $this->unassignedAt = $unassignedAt;
        return $this;
    }

    public function getAssignedBy(): ?User
    {
        return $this->assignedBy;
    }

    public function setAssignedBy(?User $assignedBy): self
    {
        $this->assignedBy = $assignedBy;
        return $this;
    }

    public function getUnassignedBy(): ?User
    {
        return $this->unassignedBy;
    }

    public function setUnassignedBy(?User $unassignedBy): self
    {
        $this->unassignedBy = $unassignedBy;
        return $this;
    }

    public function getAssignmentReason(): ?string
    {
        return $this->assignmentReason;
    }

    public function setAssignmentReason(?string $assignmentReason): self
    {
        $this->assignmentReason = $assignmentReason;
        return $this;
    }

    public function getUnassignmentReason(): ?string
    {
        return $this->unassignmentReason;
    }

    public function setUnassignmentReason(?string $unassignmentReason): self
    {
        $this->unassignmentReason = $unassignmentReason;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Unassign this assignment
     */
    public function unassign(?User $unassignedBy = null, ?string $reason = null): self
    {
        $this->isActive = false;
        $this->unassignedAt = new \DateTimeImmutable();
        $this->unassignedBy = $unassignedBy;
        $this->unassignmentReason = $reason;

        return $this;
    }

    /**
     * Get entity type (inquiry or order)
     */
    public function getEntityType(): ?string
    {
        if ($this->inquiry !== null) {
            return 'inquiry';
        }
        if ($this->order !== null) {
            return 'order';
        }
        return null;
    }

    /**
     * Get entity ID
     */
    public function getEntityId(): ?Uuid
    {
        if ($this->inquiry !== null) {
            return $this->inquiry->getId();
        }
        if ($this->order !== null) {
            return $this->order->getId();
        }
        return null;
    }

    /**
     * Get entity reference number
     */
    public function getEntityReferenceNumber(): ?string
    {
        if ($this->inquiry !== null) {
            return $this->inquiry->getInquiryNumber();
        }
        if ($this->order !== null) {
            return $this->order->getOrderNumber();
        }
        return null;
    }
}
