<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\OrderLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: OrderLogRepository::class)]
#[ORM\Table(name: 'order_log')]
#[ORM\Index(columns: ['order_id'], name: 'idx_order_log_order')]
#[ORM\Index(name: 'idx_order_log_created_at', columns: ['created_at'])]
#[ApiResource(
    operations: [
        new Get(
            normalizationContext: ['groups' => ['order_log:read']]
        ),
        new GetCollection(
            order: ['createdAt' => 'DESC'],
            normalizationContext: ['groups' => ['order_log:read']]
        )
    ],
//    security: "is_granted('ROLE_ADMIN') or (is_granted('ROLE_USER') and object.getOrder().getUser() == user)"
)]
class OrderLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['order_log:read', 'order:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'logs')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['order_log:read'])]
    private ?Order $order = null;

    #[ORM\Column(length: 50)]
    #[Groups(['order_log:read', 'order:read'])]
    private ?string $previousStatus = null;

    #[ORM\Column(length: 50)]
    #[Groups(['order_log:read', 'order:read'])]
    private ?string $newStatus = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['order_log:read', 'order:read'])]
    private ?string $comment = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['order_log:read'])]
    private ?User $changedBy = null;

    #[ORM\Column(type: 'datetime')]
    #[Gedmo\Timestampable(on: 'create')]
    #[Groups(['order_log:read', 'order:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['order_log:read'])]
    private ?array $metadata = [];

    public function __construct()
    {
        $this->id = Uuid::v4();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): static
    {
        $this->order = $order;
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
