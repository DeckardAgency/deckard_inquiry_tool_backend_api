<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\Repository\AreaManagerAvailabilityRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AreaManagerAvailabilityRepository::class)]
#[ORM\Table(name: 'area_manager_availabilities')]
#[ORM\Index(columns: ['area_manager_id'], name: 'idx_area_manager_availability_manager')]
#[ORM\Index(columns: ['is_active'], name: 'idx_area_manager_availability_active')]
#[ApiResource(
    normalizationContext: ['groups' => ['area_manager_availability:read']],
    denormalizationContext: ['groups' => ['area_manager_availability:write']],
    paginationEnabled: true,
)]
class AreaManagerAvailability
{
    public const DAY_MONDAY = 1;
    public const DAY_TUESDAY = 2;
    public const DAY_WEDNESDAY = 3;
    public const DAY_THURSDAY = 4;
    public const DAY_FRIDAY = 5;
    public const DAY_SATURDAY = 6;
    public const DAY_SUNDAY = 7;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['area_manager_availability:read', 'area_manager:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: AreaManager::class, inversedBy: 'availabilities')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    #[Groups(['area_manager_availability:read', 'area_manager_availability:write'])]
    private ?AreaManager $areaManager = null;

    #[ORM\Column(type: 'smallint')]
    #[Assert\NotNull]
    #[Assert\Range(min: 1, max: 7)]
    #[Groups(['area_manager_availability:read', 'area_manager_availability:write', 'area_manager:read'])]
    private ?int $dayOfWeek = null;

    #[ORM\Column(type: 'time')]
    #[Assert\NotNull]
    #[Groups(['area_manager_availability:read', 'area_manager_availability:write', 'area_manager:read'])]
    private ?\DateTimeInterface $startTime = null;

    #[ORM\Column(type: 'time')]
    #[Assert\NotNull]
    #[Groups(['area_manager_availability:read', 'area_manager_availability:write', 'area_manager:read'])]
    private ?\DateTimeInterface $endTime = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Timezone]
    #[Groups(['area_manager_availability:read', 'area_manager_availability:write', 'area_manager:read'])]
    private ?string $timezone = 'UTC';

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    #[Groups(['area_manager_availability:read', 'area_manager_availability:write', 'area_manager:read'])]
    private bool $isActive = true;

    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['area_manager_availability:read', 'area_manager_availability:write'])]
    private ?\DateTimeInterface $validFrom = null;

    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['area_manager_availability:read', 'area_manager_availability:write'])]
    private ?\DateTimeInterface $validUntil = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['area_manager_availability:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['area_manager_availability:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
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

    public function getDayOfWeek(): ?int
    {
        return $this->dayOfWeek;
    }

    public function setDayOfWeek(int $dayOfWeek): self
    {
        $this->dayOfWeek = $dayOfWeek;
        return $this;
    }

    public function getStartTime(): ?\DateTimeInterface
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTimeInterface $startTime): self
    {
        $this->startTime = $startTime;
        return $this;
    }

    public function getEndTime(): ?\DateTimeInterface
    {
        return $this->endTime;
    }

    public function setEndTime(\DateTimeInterface $endTime): self
    {
        $this->endTime = $endTime;
        return $this;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setTimezone(string $timezone): self
    {
        $this->timezone = $timezone;
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

    public function getValidFrom(): ?\DateTimeInterface
    {
        return $this->validFrom;
    }

    public function setValidFrom(?\DateTimeInterface $validFrom): self
    {
        $this->validFrom = $validFrom;
        return $this;
    }

    public function getValidUntil(): ?\DateTimeInterface
    {
        return $this->validUntil;
    }

    public function setValidUntil(?\DateTimeInterface $validUntil): self
    {
        $this->validUntil = $validUntil;
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
     * Check if manager is available at specific date/time
     */
    public function isAvailableAt(\DateTimeInterface $dateTime): bool
    {
        if (!$this->isActive) {
            return false;
        }

        // Convert to manager's timezone
        $managerDateTime = new \DateTime($dateTime->format('Y-m-d H:i:s'), new \DateTimeZone($this->timezone));

        // Check if within date validity range
        if ($this->validFrom !== null && $managerDateTime < $this->validFrom) {
            return false;
        }

        if ($this->validUntil !== null && $managerDateTime > $this->validUntil) {
            return false;
        }

        // Check day of week
        $currentDayOfWeek = (int) $managerDateTime->format('N'); // 1 (Monday) to 7 (Sunday)
        if ($currentDayOfWeek !== $this->dayOfWeek) {
            return false;
        }

        // Check time range
        $currentTime = $managerDateTime->format('H:i:s');
        $startTime = $this->startTime->format('H:i:s');
        $endTime = $this->endTime->format('H:i:s');

        return $currentTime >= $startTime && $currentTime <= $endTime;
    }

    /**
     * Get day name
     */
    public function getDayName(): string
    {
        return match ($this->dayOfWeek) {
            self::DAY_MONDAY => 'Monday',
            self::DAY_TUESDAY => 'Tuesday',
            self::DAY_WEDNESDAY => 'Wednesday',
            self::DAY_THURSDAY => 'Thursday',
            self::DAY_FRIDAY => 'Friday',
            self::DAY_SATURDAY => 'Saturday',
            self::DAY_SUNDAY => 'Sunday',
            default => 'Unknown',
        };
    }
}
