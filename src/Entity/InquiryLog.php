<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\InquiryLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: InquiryLogRepository::class)]
#[ORM\Table(name: 'inquiry_log')]
#[ORM\Index(name: 'idx_inquiry_log_inquiry', columns: ['inquiry_id'])]
#[ORM\Index(name: 'idx_inquiry_log_created_at', columns: ['created_at'])]
#[ApiResource(
    operations: [
        new Get(
            normalizationContext: ['groups' => ['inquiry_log:read']]
        ),
        new GetCollection(
            order: ['createdAt' => 'DESC'],
            normalizationContext: ['groups' => ['inquiry_log:read']]
        )
    ],
//    security: "is_granted('ROLE_ADMIN') or (is_granted('ROLE_USER') and object.getInquiry().getUser() == user)"
)]
class InquiryLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['inquiry_log:read', 'inquiry:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Inquiry::class, inversedBy: 'logs')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['inquiry_log:read'])]
    private ?Inquiry $inquiry = null;

    #[ORM\Column(length: 50)]
    #[Groups(['inquiry_log:read', 'inquiry:read'])]
    private ?string $previousStatus = null;

    #[ORM\Column(length: 50)]
    #[Groups(['inquiry_log:read', 'inquiry:read'])]
    private ?string $newStatus = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['inquiry_log:read', 'inquiry:read'])]
    private ?string $comment = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['inquiry_log:read'])]
    private ?User $changedBy = null;

    #[ORM\Column(type: 'datetime')]
    #[Gedmo\Timestampable(on: 'create')]
    #[Groups(['inquiry_log:read', 'inquiry:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['inquiry_log:read'])]
    private ?array $metadata = [];

    public function __construct()
    {
        $this->id = Uuid::v4();
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

    public function getPreviousStatus(): ?string
    {
        return $this->previousStatus;
    }

    public function setPreviousStatus(string $previousStatus): static
    {
        $this->previousStatus = $previousStatus;
        return $this;
    }

    public function getNewStatus(): ?string
    {
        return $this->newStatus;
    }

    public function setNewStatus(string $newStatus): static
    {
        $this->newStatus = $newStatus;
        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;
        return $this;
    }

    public function getChangedBy(): ?User
    {
        return $this->changedBy;
    }

    public function setChangedBy(?User $changedBy): static
    {
        $this->changedBy = $changedBy;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * Add metadata entry
     */
    public function addMetadata(string $key, mixed $value): static
    {
        if ($this->metadata === null) {
            $this->metadata = [];
        }
        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * Get human-readable status transition description
     */
    public function getTransitionDescription(): string
    {
        return sprintf(
            'Status changed from %s to %s',
            $this->previousStatus,
            $this->newStatus
        );
    }
}
