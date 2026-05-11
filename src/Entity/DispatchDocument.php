<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\DispatchDocumentRepository;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DispatchDocumentRepository::class)]
#[ORM\Table(name: 'dispatch_document')]
#[ORM\Index(name: "idx_dispatch_document_order", columns: ["order_id"])]
#[ORM\Index(name: "idx_dispatch_document_type", columns: ["document_type"])]
#[ApiResource(
    operations: [
        new Get(normalizationContext: ['groups' => ['dispatch_document:read']]),
        new GetCollection(normalizationContext: ['groups' => ['dispatch_document:read']])
    ],
    normalizationContext: ['groups' => ['dispatch_document:read']]
)]
class DispatchDocument
{
    public const TYPE_INVOICE = 'invoice';
    public const TYPE_SHIPPING_SHEET = 'shipping_sheet';
    public const TYPE_OTHER = 'other';

    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[Groups(['dispatch_document:read', 'order:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'dispatchDocuments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['dispatch_document:read'])]
    private ?Order $order = null;

    #[ORM\Column(length: 255)]
    #[Groups(['dispatch_document:read', 'order:read'])]
    private ?string $filename = null;

    #[ORM\Column(length: 255)]
    #[Groups(['dispatch_document:read', 'order:read'])]
    private ?string $originalFilename = null;

    #[ORM\Column(length: 100)]
    #[Groups(['dispatch_document:read', 'order:read'])]
    private ?string $mimeType = null;

    #[ORM\Column(length: 500)]
    private ?string $filePath = null;

    #[ORM\Column(length: 50)]
    #[Groups(['dispatch_document:read', 'order:read'])]
    private string $documentType = self::TYPE_OTHER;

    #[ORM\Column(type: "integer")]
    #[Groups(['dispatch_document:read', 'order:read'])]
    private int $fileSize = 0;

    #[ORM\Column(type: "datetime")]
    #[Gedmo\Timestampable(on: "create")]
    #[Groups(['dispatch_document:read', 'order:read'])]
    private ?DateTimeInterface $createdAt;

    #[ORM\Column(type: "datetime")]
    #[Gedmo\Timestampable(on: "update")]
    #[Groups(['dispatch_document:read', 'order:read'])]
    private ?DateTimeInterface $updatedAt;

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

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): static
    {
        $this->filename = $filename;
        return $this;
    }

    public function getOriginalFilename(): ?string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(string $originalFilename): static
    {
        $this->originalFilename = $originalFilename;
        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): static
    {
        $this->filePath = $filePath;
        return $this;
    }

    public function getDocumentType(): string
    {
        return $this->documentType;
    }

    public function setDocumentType(string $documentType): static
    {
        $this->documentType = $documentType;
        return $this;
    }

    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    public function setFileSize(int $fileSize): static
    {
        $this->fileSize = $fileSize;
        return $this;
    }

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * Get the full filesystem path for this document
     */
    public function getFullPath(string $uploadsDir): string
    {
        return $uploadsDir . '/' . $this->filePath;
    }

    /**
     * Get document type label
     */
    public function getDocumentTypeLabel(): string
    {
        return match ($this->documentType) {
            self::TYPE_INVOICE => 'Invoice',
            self::TYPE_SHIPPING_SHEET => 'Shipping Sheet',
            default => 'Other Document'
        };
    }
}
