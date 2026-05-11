<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\Repository\AreaCriteriaRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AreaCriteriaRepository::class)]
#[ORM\Table(name: 'area_criteria')]
#[ORM\Index(columns: ['area_id'], name: 'idx_area_criteria_area')]
#[ORM\Index(columns: ['is_active'], name: 'idx_area_criteria_active')]
#[ORM\Index(columns: ['priority'], name: 'idx_area_criteria_priority')]
#[ApiResource(
    normalizationContext: ['groups' => ['area_criteria:read']],
    denormalizationContext: ['groups' => ['area_criteria:write']],
    paginationEnabled: true,
)]
class AreaCriteria
{
    public const FIELD_TYPE_COUNTRY = 'country';
    public const FIELD_TYPE_REGION = 'region';
    public const FIELD_TYPE_POSTAL_CODE = 'postal_code';
    public const FIELD_TYPE_COMPANY_SIZE = 'company_size';
    public const FIELD_TYPE_INDUSTRY = 'industry';
    public const FIELD_TYPE_PRODUCT_TYPE = 'product_type';
    public const FIELD_TYPE_MACHINE_TYPE = 'machine_type';
    public const FIELD_TYPE_ORDER_VALUE = 'order_value';
    public const FIELD_TYPE_CUSTOM = 'custom';

    public const OPERATOR_EQUALS = 'equals';
    public const OPERATOR_NOT_EQUALS = 'not_equals';
    public const OPERATOR_CONTAINS = 'contains';
    public const OPERATOR_NOT_CONTAINS = 'not_contains';
    public const OPERATOR_STARTS_WITH = 'starts_with';
    public const OPERATOR_ENDS_WITH = 'ends_with';
    public const OPERATOR_IN = 'in';
    public const OPERATOR_NOT_IN = 'not_in';
    public const OPERATOR_GREATER_THAN = 'greater_than';
    public const OPERATOR_LESS_THAN = 'less_than';
    public const OPERATOR_GREATER_THAN_OR_EQUAL = 'greater_than_or_equal';
    public const OPERATOR_LESS_THAN_OR_EQUAL = 'less_than_or_equal';
    public const OPERATOR_BETWEEN = 'between';
    public const OPERATOR_REGEX = 'regex';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['area_criteria:read', 'area:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Area::class, inversedBy: 'areaCriteria')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    #[Groups(['area_criteria:read', 'area_criteria:write'])]
    private ?Area $area = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['area_criteria:read', 'area_criteria:write', 'area:read'])]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['area_criteria:read', 'area_criteria:write'])]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [
        self::FIELD_TYPE_COUNTRY,
        self::FIELD_TYPE_REGION,
        self::FIELD_TYPE_POSTAL_CODE,
        self::FIELD_TYPE_COMPANY_SIZE,
        self::FIELD_TYPE_INDUSTRY,
        self::FIELD_TYPE_PRODUCT_TYPE,
        self::FIELD_TYPE_MACHINE_TYPE,
        self::FIELD_TYPE_ORDER_VALUE,
        self::FIELD_TYPE_CUSTOM,
    ])]
    #[Groups(['area_criteria:read', 'area_criteria:write', 'area:read'])]
    private ?string $fieldType = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['area_criteria:read', 'area_criteria:write', 'area:read'])]
    private ?string $fieldPath = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [
        self::OPERATOR_EQUALS,
        self::OPERATOR_NOT_EQUALS,
        self::OPERATOR_CONTAINS,
        self::OPERATOR_NOT_CONTAINS,
        self::OPERATOR_STARTS_WITH,
        self::OPERATOR_ENDS_WITH,
        self::OPERATOR_IN,
        self::OPERATOR_NOT_IN,
        self::OPERATOR_GREATER_THAN,
        self::OPERATOR_LESS_THAN,
        self::OPERATOR_GREATER_THAN_OR_EQUAL,
        self::OPERATOR_LESS_THAN_OR_EQUAL,
        self::OPERATOR_BETWEEN,
        self::OPERATOR_REGEX,
    ])]
    #[Groups(['area_criteria:read', 'area_criteria:write', 'area:read'])]
    private ?string $operator = null;

    #[ORM\Column(type: 'json')]
    #[Assert\NotNull]
    #[Groups(['area_criteria:read', 'area_criteria:write', 'area:read'])]
    private ?array $value = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    #[Groups(['area_criteria:read', 'area_criteria:write', 'area:read'])]
    private bool $isActive = true;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['area_criteria:read', 'area_criteria:write', 'area:read'])]
    private int $priority = 0;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['area_criteria:read', 'area_criteria:write'])]
    private ?array $metadata = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['area_criteria:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['area_criteria:read'])]
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

    public function getArea(): ?Area
    {
        return $this->area;
    }

    public function setArea(?Area $area): self
    {
        $this->area = $area;
        return $this;
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

    public function getFieldType(): ?string
    {
        return $this->fieldType;
    }

    public function setFieldType(string $fieldType): self
    {
        $this->fieldType = $fieldType;
        return $this;
    }

    public function getFieldPath(): ?string
    {
        return $this->fieldPath;
    }

    public function setFieldPath(?string $fieldPath): self
    {
        $this->fieldPath = $fieldPath;
        return $this;
    }

    public function getOperator(): ?string
    {
        return $this->operator;
    }

    public function setOperator(string $operator): self
    {
        $this->operator = $operator;
        return $this;
    }

    public function getValue(): ?array
    {
        return $this->value;
    }

    public function setValue(?array $value): self
    {
        $this->value = $value;
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
     * Evaluate criteria against provided data
     */
    public function evaluate(array $data): bool
    {
        $fieldValue = $this->extractFieldValue($data);

        if ($fieldValue === null && $this->operator !== self::OPERATOR_EQUALS && $this->operator !== self::OPERATOR_NOT_EQUALS) {
            return false;
        }

        return match ($this->operator) {
            self::OPERATOR_EQUALS => $fieldValue === $this->value[0] ?? null,
            self::OPERATOR_NOT_EQUALS => $fieldValue !== $this->value[0] ?? null,
            self::OPERATOR_CONTAINS => is_string($fieldValue) && str_contains($fieldValue, $this->value[0] ?? ''),
            self::OPERATOR_NOT_CONTAINS => is_string($fieldValue) && !str_contains($fieldValue, $this->value[0] ?? ''),
            self::OPERATOR_STARTS_WITH => is_string($fieldValue) && str_starts_with($fieldValue, $this->value[0] ?? ''),
            self::OPERATOR_ENDS_WITH => is_string($fieldValue) && str_ends_with($fieldValue, $this->value[0] ?? ''),
            self::OPERATOR_IN => in_array($fieldValue, $this->value, true),
            self::OPERATOR_NOT_IN => !in_array($fieldValue, $this->value, true),
            self::OPERATOR_GREATER_THAN => is_numeric($fieldValue) && $fieldValue > ($this->value[0] ?? 0),
            self::OPERATOR_LESS_THAN => is_numeric($fieldValue) && $fieldValue < ($this->value[0] ?? 0),
            self::OPERATOR_GREATER_THAN_OR_EQUAL => is_numeric($fieldValue) && $fieldValue >= ($this->value[0] ?? 0),
            self::OPERATOR_LESS_THAN_OR_EQUAL => is_numeric($fieldValue) && $fieldValue <= ($this->value[0] ?? 0),
            self::OPERATOR_BETWEEN => is_numeric($fieldValue) && $fieldValue >= ($this->value[0] ?? 0) && $fieldValue <= ($this->value[1] ?? 0),
            self::OPERATOR_REGEX => is_string($fieldValue) && preg_match($this->value[0] ?? '//', $fieldValue) === 1,
            default => false,
        };
    }

    /**
     * Extract field value from data using field path
     */
    private function extractFieldValue(array $data): mixed
    {
        if ($this->fieldPath === null) {
            return $data[$this->fieldType] ?? null;
        }

        $keys = explode('.', $this->fieldPath);
        $value = $data;

        foreach ($keys as $key) {
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } elseif (is_object($value)) {
                $getter = 'get' . ucfirst($key);
                if (method_exists($value, $getter)) {
                    $value = $value->$getter();
                } elseif (property_exists($value, $key)) {
                    $value = $value->$key;
                } else {
                    return null;
                }
            } else {
                return null;
            }
        }

        return $value;
    }
}
