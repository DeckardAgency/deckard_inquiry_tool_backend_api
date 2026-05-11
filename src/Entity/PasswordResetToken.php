<?php

namespace App\Entity;

use App\Repository\PasswordResetTokenRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PasswordResetTokenRepository::class)]
#[ORM\Table(name: 'password_reset_token')]
#[ORM\Index(name: 'idx_password_reset_token', columns: ['token'])]
#[ORM\Index(name: 'idx_password_reset_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_password_reset_expires_at', columns: ['expires_at'])]
#[UniqueEntity('token')]
class PasswordResetToken
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_USED = 'used';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REVOKED = 'revoked';

    public const VALID_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_USED,
        self::STATUS_EXPIRED,
        self::STATUS_REVOKED,
    ];

    // Default expiration time in hours
    public const DEFAULT_EXPIRATION_HOURS = 1;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 128, unique: true)]
    private ?string $token = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $expiresAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $usedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: 'datetime')]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeInterface $createdAt = null;

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
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

    public function getUsedAt(): ?\DateTimeInterface
    {
        return $this->usedAt;
    }

    public function setUsedAt(?\DateTimeInterface $usedAt): static
    {
        $this->usedAt = $usedAt;

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

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;

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

    /**
     * Generate a secure random token
     */
    public function generateToken(): static
    {
        $this->token = bin2hex(random_bytes(64));

        return $this;
    }

    /**
     * Set default expiration
     */
    public function setDefaultExpiration(int $hours = self::DEFAULT_EXPIRATION_HOURS): static
    {
        $this->expiresAt = new \DateTime(sprintf('+%d hours', $hours));

        return $this;
    }

    /**
     * Check if the token has expired
     */
    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt < new \DateTime();
    }

    /**
     * Check if the token is pending (can still be used)
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the token has been used
     */
    public function isUsed(): bool
    {
        return $this->status === self::STATUS_USED;
    }

    /**
     * Check if the token has been revoked
     */
    public function isRevoked(): bool
    {
        return $this->status === self::STATUS_REVOKED;
    }

    /**
     * Check if the token can be used for password reset
     */
    public function canBeUsed(): bool
    {
        return $this->isPending() && !$this->isExpired();
    }

    /**
     * Mark the token as used
     */
    public function markAsUsed(): void
    {
        if (!$this->canBeUsed()) {
            throw new \LogicException('Token cannot be used');
        }

        $this->status = self::STATUS_USED;
        $this->usedAt = new \DateTime();
    }

    /**
     * Mark the token as revoked
     */
    public function markAsRevoked(): void
    {
        if ($this->isUsed()) {
            throw new \LogicException('Cannot revoke a used token');
        }

        $this->status = self::STATUS_REVOKED;
    }

    /**
     * Mark the token as expired (for cleanup jobs)
     */
    public function markAsExpired(): void
    {
        if ($this->isUsed()) {
            throw new \LogicException('Cannot expire a used token');
        }

        $this->status = self::STATUS_EXPIRED;
    }

    /**
     * Get partial email for display (e.g., j***@inquiry.deckard.com)
     */
    public function getPartialEmail(): ?string
    {
        if (!$this->user) {
            return null;
        }

        $email = $this->user->getEmail();
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }

        $local = $parts[0];
        $domain = $parts[1];

        // Show first character, then ***, then @domain
        $maskedLocal = substr($local, 0, 1) . '***';

        return $maskedLocal . '@' . $domain;
    }
}
