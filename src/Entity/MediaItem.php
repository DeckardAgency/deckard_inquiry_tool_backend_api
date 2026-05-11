<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\Controller\CreateMediaItemAction;
use App\Repository\MediaItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Uid\Uuid;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: MediaItemRepository::class)]
#[ApiResource(
    operations: [
        new Get(normalizationContext: ['groups' => ['media_item:read']]),
        new GetCollection(normalizationContext: ['groups' => ['media_item:read']]),
        new Post(
            controller: CreateMediaItemAction::class,
            openapi: new Operation(
                requestBody: new RequestBody(
                    content: new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'file' => [
                                        'type' => 'string',
                                        'format' => 'binary'
                                    ]
                                ]
                            ]
                        ]
                    ])
                )
            ),
            validationContext: ['groups' => ['Default', 'media_item:create']],
            output: MediaItem::class,
            deserialize: false
        ),
        new Delete(),
        new Patch(normalizationContext: ['groups' => ['media_item:read']])
    ],
    normalizationContext: ['groups' => ['media_item:read']],
    denormalizationContext: ['groups' => ['media_item:write']]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'mimeType' => 'exact'
])]
class MediaItem
{
    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[Groups(['media_item:read', 'documentation:read', 'documentation:item'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(groups: ['media_item:write', 'media_item:create'])]
    #[Groups(['media_item:read', 'media_item:write', 'documentation:read', 'documentation:item'])]
    private ?string $filename = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(groups: ['media_item:write', 'media_item:create'])]
    #[Groups(['media_item:read', 'media_item:write', 'documentation:read', 'documentation:item'])]
    private ?string $mimeType = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(groups: ['media_item:write', 'media_item:create'])]
    #[Groups(['media_item:read', 'media_item:write', 'documentation:read', 'documentation:item'])]
    private ?string $filePath = null;

    #[Assert\NotNull(groups: ['media_item:create'])]
    #[Assert\File(
        maxSize: '5M',
        mimeTypes: [
            // Images
            'image/jpeg',
            'image/png',
            'image/webp',
            // PDF
            'application/pdf',
            // Excel files
            'application/vnd.ms-excel', // .xls
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
            // Optional: Word documents
            'application/msword', // .doc
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
            // Optional: Other common formats
            'text/csv', // .csv
            'text/plain',// .txt
        ],
        groups: ['media_item:create']
    )]
    private ?File $file = null;

    #[ORM\OneToMany(targetEntity: Product::class, mappedBy: 'featuredImage')]
    private Collection $products;

    #[ORM\Column(type: "datetime")]
    #[Gedmo\Timestampable(on: "create")]
    #[Groups(['media_item:read', 'documentation:read', 'documentation:item'])]
    private ?\DateTimeInterface $createdAt;

    #[ORM\Column(type: "datetime")]
    #[Gedmo\Timestampable(on: "update")]
    #[Groups(['media_item:read', 'documentation:read', 'documentation:item'])]
    private ?\DateTimeInterface $updatedAt;

    #[ORM\ManyToOne(inversedBy: 'imageGallery')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Product $product = null;

    #[ORM\OneToMany(targetEntity: Machine::class, mappedBy: 'featuredImage')]
    private Collection $machines;

    #[ORM\ManyToOne(inversedBy: 'imageGallery')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Machine $machine = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Product $productDocument = null;

    #[ORM\ManyToOne(targetEntity: Machine::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Machine $machineDocument = null;

    #[ORM\ManyToOne(targetEntity: Documentation::class, inversedBy: 'media')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    #[Groups(['media_item:read', 'media_item:write'])]
    private ?Documentation $documentation = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->products = new ArrayCollection();
        $this->machines = new ArrayCollection();

        // Set default values to prevent null values
        $this->filename = "placeholder.jpg";
        $this->mimeType = "image/jpeg";
        $this->filePath = "/uploads/placeholder.jpg";
    }

    public function __toString(): string
    {
        return $this->filename ?? 'New Media Item';
    }

    public function getId(): ?Uuid
    {
        return $this->id;
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

    public function getFile(): ?File
    {
        return $this->file;
    }

    public function setFile(?File $file): static
    {
        $this->file = $file;
        return $this;
    }

    /**
     * @return Collection<int, Product>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(Product $product): static
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
            $product->setFeaturedImage($this);
        }

        return $this;
    }

    public function removeProduct(Product $product): static
    {
        if ($this->products->removeElement($product)) {
            if ($product->getFeaturedImage() === $this) {
                $product->setFeaturedImage(null);
            }
        }

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    /**
     * @return Collection<int, Machine>
     */
    public function getMachines(): Collection
    {
        return $this->machines;
    }

    public function addMachine(Machine $machine): static
    {
        if (!$this->machines->contains($machine)) {
            $this->machines->add($machine);
            $machine->setFeaturedImage($this);
        }

        return $this;
    }

    public function removeMachine(Machine $machine): static
    {
        if ($this->machines->removeElement($machine)) {
            if ($machine->getFeaturedImage() === $this) {
                $machine->setFeaturedImage(null);
            }
        }

        return $this;
    }

    public function getMachine(): ?Machine
    {
        return $this->machine;
    }

    public function setMachine(?Machine $machine): static
    {
        $this->machine = $machine;

        return $this;
    }

    public function getProductDocument(): ?Product
    {
        return $this->productDocument;
    }

    public function setProductDocument(?Product $productDocument): static
    {
        $this->productDocument = $productDocument;

        return $this;
    }

    public function getMachineDocument(): ?Machine
    {
        return $this->machineDocument;
    }

    public function setMachineDocument(?Machine $machineDocument): static
    {
        $this->machineDocument = $machineDocument;

        return $this;
    }

    public function getDocumentation(): ?Documentation
    {
        return $this->documentation;
    }

    public function setDocumentation(?Documentation $documentation): static
    {
        $this->documentation = $documentation;

        return $this;
    }
}
