<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use App\Repository\SupportTicketRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SupportTicketRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new Get(
            normalizationContext: ['groups' => ['support_ticket:read', 'support_ticket:read:item', 'media_item:read', 'user:read']],
        ),
        new GetCollection(
            normalizationContext: ['groups' => ['support_ticket:read', 'media_item:read', 'user:read']],
        ),
        new Post(
            inputFormats: ['multipart' => ['multipart/form-data']],
            normalizationContext: ['groups' => ['support_ticket:read', 'media_item:read', 'user:read']],
            denormalizationContext: ['groups' => ['support_ticket:write']],
            deserialize: false,
            processor: \App\State\SupportTicketProcessor::class
        ),
        new Patch(
            normalizationContext: ['groups' => ['support_ticket:read', 'media_item:read', 'user:read']],
            denormalizationContext: ['groups' => ['support_ticket:update']],
        ),
    ],
    normalizationContext: ['groups' => ['support_ticket:read', 'media_item:read', 'user:read']],
    denormalizationContext: ['groups' => ['support_ticket:write']],
    paginationEnabled: true,
    paginationItemsPerPage: 30
)]
#[ApiFilter(SearchFilter::class, properties: ['status' => 'exact', 'urgency' => 'exact', 'user' => 'exact'])]
class SupportTicket
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[ORM\Column(type: Types::GUID)]
    #[Groups(['support_ticket:read'])]
    private ?string $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Subject is required')]
    #[Assert\Length(min: 3, max: 255)]
    #[Groups(['support_ticket:read', 'support_ticket:write'])]
    private ?string $subject = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Message is required')]
    #[Assert\Length(min: 10)]
    #[Groups(['support_ticket:read', 'support_ticket:write'])]
    private ?string $message = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['support_ticket:read', 'support_ticket:write'])]
    private ?string $orderId = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['support_ticket:read', 'support_ticket:write'])]
    private ?string $machine = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['low', 'medium', 'high'])]
    #[Groups(['support_ticket:read', 'support_ticket:write'])]
    private string $urgency = 'medium';

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['open', 'in_progress', 'resolved', 'closed'])]
    #[Groups(['support_ticket:read', 'support_ticket:update'])]
    private string $status = 'open';

    #[ORM\ManyToOne(targetEntity: MediaItem::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['support_ticket:read'])]
    private ?MediaItem $attachment = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['support_ticket:read'])]
    private ?User $user = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['support_ticket:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['support_ticket:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function setOrderId(?string $orderId): static
    {
        $this->orderId = $orderId;

        return $this;
    }

    public function getMachine(): ?string
    {
        return $this->machine;
    }

    public function setMachine(?string $machine): static
    {
        $this->machine = $machine;

        return $this;
    }

    public function getUrgency(): string
    {
        return $this->urgency;
    }

    public function setUrgency(string $urgency): static
    {
        $this->urgency = $urgency;

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

    public function getAttachment(): ?MediaItem
    {
        return $this->attachment;
    }

    public function setAttachment(?MediaItem $attachment): static
    {
        $this->attachment = $attachment;

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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
