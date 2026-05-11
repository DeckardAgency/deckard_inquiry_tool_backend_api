<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Repository\UserInvitationRepository;
use App\State\Processor\InvitationCreatedProcessor;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['user_invitation:read']],
        ),
        new Post(
            normalizationContext: ['groups' => ['user_invitation:read']],
            denormalizationContext: ['groups' => ['user_invitation:create']],
            processor: InvitationCreatedProcessor::class
        ),
        new Get(
            normalizationContext: ['groups' => ['user_invitation:read']],
        ),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['user_invitation:read']],
    denormalizationContext: ['groups' => ['user_invitation:create']],
)]
#[ORM\Entity(repositoryClass: UserInvitationRepository::class)]
#[ORM\Table(name: 'user_invitation')]
#[ORM\Index(name: 'idx_user_invitation_token', columns: ['token'])]
#[ORM\Index(name: 'idx_user_invitation_email', columns: ['email'])]
#[ORM\Index(name: 'idx_user_invitation_status', columns: ['status'])]
#[ORM\Index(name: 'idx_user_invitation_expires_at', columns: ['expires_at'])]
#[UniqueEntity('token')]
class UserInvitation
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REVOKED = 'revoked';

    public const VALID_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_COMPLETED,
        self::STATUS_EXPIRED,
        self::STATUS_REVOKED,
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['user_invitation:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(groups: ['user_invitation:create'])]
    #[Assert\Email(groups: ['user_invitation:create'])]
    #[Groups(['user_invitation:read', 'user_invitation:create', 'user_invitation:verify'])]
    private ?string $email = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(groups: ['user_invitation:create'])]
    #[Assert\Length(min: 2, max: 100, groups: ['user_invitation:create'])]
    #[Groups(['user_invitation:read', 'user_invitation:create', 'user_invitation:verify'])]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(groups: ['user_invitation:create'])]
    #[Assert\Length(min: 2, max: 100, groups: ['user_invitation:create'])]
    #[Groups(['user_invitation:read', 'user_invitation:create', 'user_invitation:verify'])]
    private ?string $lastName = null;

    #[ORM\Column(length: 128, unique: true)]
    #[Groups(['user_invitation:read'])]
    private ?string $token = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: self::VALID_STATUSES)]
    #[Groups(['user_invitation:read'])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'datetime')]
    #[Groups(['user_invitation:read', 'user_invitation:verify'])]
    private ?\DateTimeInterface $expiresAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['user_invitation:read'])]
    private ?\DateTimeInterface $completedAt = null;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['user_invitation:read', 'user_invitation:create'])]
    private ?Client $client = null;

    #[ORM\Column(type: 'json')]
    #[Groups(['user_invitation:read', 'user_invitation:create'])]
    private array $roles = ['ROLE_USER', 'ROLE_CLIENT'];

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['user_invitation:read'])]
    private ?User $createdBy = null;

    #[ORM\Column(type: 'datetime')]
    #[Gedmo\Timestampable(on: 'create')]
    #[Groups(['user_invitation:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime')]
    #[Gedmo\Timestampable(on: 'update')]
    #[Groups(['user_invitation:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->generateToken();
        $this->setDefaultExpiration();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token): static
    {
        $this->token = $token;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid status: %s', $status));
        }

        $this->status = $status;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeInterface $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getCompletedAt(): ?\DateTimeInterface
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeInterface $completedAt): static
    {
        $this->completedAt = $completedAt;

        return $this;
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

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): static
    {
        $this->createdBy = $createdBy;

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

    /**
     * Generate a secure random token
     */
    public function generateToken(): static
    {
        $this->token = bin2hex(random_bytes(64));

        return $this;
    }

    /**
     * Set default expiration (7 days from now)
     */
    public function setDefaultExpiration(int $days = 7): static
    {
        $this->expiresAt = new \DateTime(sprintf('+%d days', $days));

        return $this;
    }

    /**
     * Check if the invitation has expired
     */
    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt < new \DateTime();
    }

    /**
     * Check if the invitation is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the invitation has been completed
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if the invitation has been revoked
     */
    public function isRevoked(): bool
    {
        return $this->status === self::STATUS_REVOKED;
    }

    /**
     * Check if the invitation can be completed
     */
    public function canBeCompleted(): bool
    {
        return $this->isPending() && !$this->isExpired() && !$this->isRevoked();
    }

    /**
     * Mark the invitation as completed
     */
    public function markAsCompleted(): void
    {
        if (!$this->canBeCompleted()) {
            throw new \LogicException('Invitation cannot be completed');
        }

        $this->status = self::STATUS_COMPLETED;
        $this->completedAt = new \DateTime();
    }

    /**
     * Mark the invitation as revoked
     */
    public function markAsRevoked(): void
    {
        if ($this->isCompleted()) {
            throw new \LogicException('Cannot revoke a completed invitation');
        }

        $this->status = self::STATUS_REVOKED;
    }

    /**
     * Mark the invitation as expired (for cleanup jobs)
     */
    public function markAsExpired(): void
    {
        if ($this->isCompleted()) {
            throw new \LogicException('Cannot expire a completed invitation');
        }

        $this->status = self::STATUS_EXPIRED;
    }

    /**
     * Get serialized property indicating if expired (for API responses)
     */
    #[Groups(['user_invitation:verify'])]
    public function getIsExpired(): bool
    {
        return $this->isExpired();
    }
}
