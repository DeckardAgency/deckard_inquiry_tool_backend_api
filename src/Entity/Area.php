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
use App\Repository\AreaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AreaRepository::class)]
#[ORM\Table(name: 'areas')]
#[ORM\Index(columns: ['client_id'], name: 'idx_area_client')]
#[ORM\Index(columns: ['parent_area_id'], name: 'idx_area_parent')]
#[ORM\Index(columns: ['is_active'], name: 'idx_area_active')]
#[ApiResource(
    normalizationContext: ['groups' => ['area:read']],
    denormalizationContext: ['groups' => ['area:write']],
    paginationEnabled: true,
)]
#[ApiFilter(SearchFilter::class, properties: [
    'name' => 'partial',
    'code' => 'partial',
    'client' => 'exact',
    'client.name' => 'partial',
    'parentArea' => 'exact',
])]
#[ApiFilter(BooleanFilter::class, properties: ['isActive'])]
#[ApiFilter(OrderFilter::class, properties: ['name', 'code', 'priority', 'createdAt', 'updatedAt'])]
class Area
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['area:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['area:read', 'area:write'])]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['area:read', 'area:write'])]
    private ?string $description = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    #[Assert\Regex(
        pattern: '/^[A-Z0-9_-]+$/',
        message: 'Code must contain only uppercase letters, numbers, hyphens, and underscores'
    )]
    #[Groups(['area:read', 'area:write'])]
    private ?string $code = null;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'areas')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    #[Groups(['area:read', 'area:write', 'area_manager:read'])]
    private ?Client $client = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'childAreas')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['area:read', 'area:write'])]
    private ?self $parentArea = null;

    #[ORM\OneToMany(mappedBy: 'parentArea', targetEntity: self::class)]
    #[Groups(['area:read'])]
    private Collection $childAreas;

    #[ORM\OneToMany(mappedBy: 'area', targetEntity: AreaManager::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[Groups(['area:read'])]
    private Collection $areaManagers;

    #[ORM\OneToMany(mappedBy: 'area', targetEntity: AreaCriteria::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[Groups(['area:read'])]
    private Collection $areaCriteria;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    #[Groups(['area:read', 'area:write'])]
    private bool $isActive = true;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['area:read', 'area:write'])]
    private int $priority = 0;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['area:read', 'area:write'])]
    private ?array $metadata = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['area:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['area:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->childAreas = new ArrayCollection();
        $this->areaManagers = new ArrayCollection();
        $this->areaCriteria = new ArrayCollection();
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = strtoupper($code);
        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): self
    {
        $this->client = $client;
        return $this;
    }

    public function getParentArea(): ?self
    {
        return $this->parentArea;
    }

    public function setParentArea(?self $parentArea): self
    {
        $this->parentArea = $parentArea;
        return $this;
    }

    /**
     * @return Collection<int, Area>
     */
    public function getChildAreas(): Collection
    {
        return $this->childAreas;
    }

    public function addChildArea(Area $childArea): self
    {
        if (!$this->childAreas->contains($childArea)) {
            $this->childAreas->add($childArea);
            $childArea->setParentArea($this);
        }

        return $this;
    }

    public function removeChildArea(Area $childArea): self
    {
        if ($this->childAreas->removeElement($childArea)) {
            if ($childArea->getParentArea() === $this) {
                $childArea->setParentArea(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, AreaManager>
     */
    public function getAreaManagers(): Collection
    {
        return $this->areaManagers;
    }

    public function addAreaManager(AreaManager $areaManager): self
    {
        if (!$this->areaManagers->contains($areaManager)) {
            $this->areaManagers->add($areaManager);
            $areaManager->setArea($this);
        }

        return $this;
    }

    public function removeAreaManager(AreaManager $areaManager): self
    {
        if ($this->areaManagers->removeElement($areaManager)) {
            if ($areaManager->getArea() === $this) {
                $areaManager->setArea(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, AreaCriteria>
     */
    public function getAreaCriteria(): Collection
    {
        return $this->areaCriteria;
    }

    public function addAreaCriterion(AreaCriteria $areaCriterion): self
    {
        if (!$this->areaCriteria->contains($areaCriterion)) {
            $this->areaCriteria->add($areaCriterion);
            $areaCriterion->setArea($this);
        }

        return $this;
    }

    public function removeAreaCriterion(AreaCriteria $areaCriterion): self
    {
        if ($this->areaCriteria->removeElement($areaCriterion)) {
            if ($areaCriterion->getArea() === $this) {
                $areaCriterion->setArea(null);
            }
        }

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

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): self
    {
        $this->priority = $priority;
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
     * Get all active managers for this area
     */
    public function getActiveManagers(): array
    {
        return $this->areaManagers->filter(function (AreaManager $am) {
            return $am->isActive() && $am->getManager()->getIsActive();
        })->toArray();
    }

    /**
     * Get the full hierarchical path of this area
     */
    public function getHierarchicalPath(): string
    {
        $path = [$this->name];
        $parent = $this->parentArea;

        while ($parent !== null) {
            array_unshift($path, $parent->getName());
            $parent = $parent->getParentArea();
        }

        return implode(' > ', $path);
    }

    /**
     * Check if this area is a descendant of the given area
     */
    public function isDescendantOf(Area $area): bool
    {
        $parent = $this->parentArea;

        while ($parent !== null) {
            if ($parent->getId()->equals($area->getId())) {
                return true;
            }
            $parent = $parent->getParentArea();
        }

        return false;
    }
}
