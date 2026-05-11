<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use App\Repository\AreaManagerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AreaManagerRepository::class)]
#[ORM\Table(name: 'area_managers')]
#[ORM\Index(columns: ['area_id'], name: 'idx_area_manager_area')]
#[ORM\Index(columns: ['manager_id'], name: 'idx_area_manager_manager')]
#[ORM\Index(columns: ['is_active'], name: 'idx_area_manager_active')]
#[ORM\Index(columns: ['is_primary'], name: 'idx_area_manager_primary')]
#[ORM\UniqueConstraint(name: 'unique_area_manager', columns: ['area_id', 'manager_id'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    normalizationContext: ['groups' => ['area_manager:read']],
    denormalizationContext: ['groups' => ['area_manager:write']],
    paginationEnabled: true,
)]
#[ApiFilter(SearchFilter::class, properties: [
    'area' => 'exact',
    'area.client' => 'exact',
    'area.code' => 'exact',
    'manager' => 'exact',
    'manager.email' => 'partial',
    'manager.firstName' => 'partial',
    'manager.lastName' => 'partial',
])]
#[ApiFilter(BooleanFilter::class, properties: ['isActive', 'isPrimary'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'updatedAt', 'isPrimary'])]
class AreaManager
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['area_manager:read', 'area:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Area::class, inversedBy: 'areaManagers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    #[Groups(['area_manager:read', 'area_manager:write'])]
    private ?Area $area = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    #[Groups(['area_manager:read', 'area_manager:write', 'area:read'])]
    private ?User $manager = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['area_manager:read', 'area_manager:write', 'area:read'])]
    private bool $isPrimary = false;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    #[Groups(['area_manager:read', 'area_manager:write', 'area:read'])]
    private bool $isActive = true;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['area_manager:read', 'area_manager:write'])]
    private int $maxCapacity = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['area_manager:read'])]
    private int $currentAssignmentCount = 0;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['area_manager:read', 'area_manager:write'])]
    private ?array $specializations = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['area_manager:read', 'area_manager:write'])]
    private ?array $metadata = null;

    #[ORM\OneToMany(mappedBy: 'areaManager', targetEntity: AreaManagerAvailability::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[Groups(['area_manager:read'])]
    private Collection $availabilities;

    #[ORM\OneToMany(mappedBy: 'areaManager', targetEntity: AreaAssignment::class)]
    #[Groups(['area_manager:read'])]
    private Collection $assignments;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['area_manager:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['area_manager:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->availabilities = new ArrayCollection();
        $this->assignments = new ArrayCollection();
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

    public function getArea(): ?Area
    {
        return $this->area;
    }

    public function setArea(?Area $area): self
    {
        $this->area = $area;
        return $this;
    }

    public function getManager(): ?User
    {
        return $this->manager;
    }

    public function setManager(?User $manager): self
    {
        $this->manager = $manager;
        return $this;
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function getIsPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function setIsPrimary(bool $isPrimary): self
    {
        $this->isPrimary = $isPrimary;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getMaxCapacity(): int
    {
        return $this->maxCapacity;
    }

    public function setMaxCapacity(int $maxCapacity): self
    {
        $this->maxCapacity = $maxCapacity;
        return $this;
    }

    public function getCurrentAssignmentCount(): int
    {
        return $this->currentAssignmentCount;
    }

    public function setCurrentAssignmentCount(int $currentAssignmentCount): self
    {
        $this->currentAssignmentCount = $currentAssignmentCount;
        return $this;
    }

    public function incrementAssignmentCount(): self
    {
        $this->currentAssignmentCount++;
        return $this;
    }

    public function decrementAssignmentCount(): self
    {
        if ($this->currentAssignmentCount > 0) {
            $this->currentAssignmentCount--;
        }
        return $this;
    }

    public function getSpecializations(): ?array
    {
        return $this->specializations;
    }

    public function setSpecializations(?array $specializations): self
    {
        $this->specializations = $specializations;
        return $this;
    }

    public function hasSpecialization(string $specialization): bool
    {
        return $this->specializations !== null && in_array($specialization, $this->specializations, true);
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

    /**
     * @return Collection<int, AreaManagerAvailability>
     */
    public function getAvailabilities(): Collection
    {
        return $this->availabilities;
    }

    public function addAvailability(AreaManagerAvailability $availability): self
    {
        if (!$this->availabilities->contains($availability)) {
            $this->availabilities->add($availability);
            $availability->setAreaManager($this);
        }

        return $this;
    }

    public function removeAvailability(AreaManagerAvailability $availability): self
    {
        if ($this->availabilities->removeElement($availability)) {
            if ($availability->getAreaManager() === $this) {
                $availability->setAreaManager(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, AreaAssignment>
     */
    public function getAssignments(): Collection
    {
        return $this->assignments;
    }

    public function addAssignment(AreaAssignment $assignment): self
    {
        if (!$this->assignments->contains($assignment)) {
            $this->assignments->add($assignment);
            $assignment->setAreaManager($this);
        }

        return $this;
    }

    public function removeAssignment(AreaAssignment $assignment): self
    {
        if ($this->assignments->removeElement($assignment)) {
            if ($assignment->getAreaManager() === $this) {
                $assignment->setAreaManager(null);
            }
        }

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
     * Check if manager is at capacity
     */
    public function isAtCapacity(): bool
    {
        if ($this->maxCapacity === 0) {
            return false; // No limit
        }

        return $this->currentAssignmentCount >= $this->maxCapacity;
    }

    /**
     * Get available capacity
     */
    public function getAvailableCapacity(): int
    {
        if ($this->maxCapacity === 0) {
            return PHP_INT_MAX; // Unlimited
        }

        return max(0, $this->maxCapacity - $this->currentAssignmentCount);
    }

    /**
     * Check if manager is available at specific time
     */
    public function isAvailableAt(\DateTimeInterface $dateTime): bool
    {
        if (!$this->isActive) {
            return false;
        }

        // If no availability rules, assume always available
        if ($this->availabilities->isEmpty()) {
            return true;
        }

        foreach ($this->availabilities as $availability) {
            if ($availability->isAvailableAt($dateTime)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get active assignments count
     */
    public function getActiveAssignmentsCount(): int
    {
        return $this->assignments->filter(function (AreaAssignment $assignment) {
            return $assignment->isActive();
        })->count();
    }
}
