<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use App\Repository\ProductRepository;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Gedmo\Mapping\Annotation as Gedmo;
use ApiPlatform\OpenApi\Model;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table]
#[ORM\Index(name: "idx_product_part_no", columns: ["part_no"])]
#[ORM\Index(name: "idx_product_name", columns: ["name"])]
#[ORM\Index(name: "idx_product_slug", columns: ["slug"])]
#[ApiResource(
    operations: [
        new Get(normalizationContext: ['groups' => ['product:read', 'media_item:read', 'machine:read', 'product:machines']]),
        new GetCollection(
            paginationItemsPerPage: 30,
            paginationClientItemsPerPage: true,
            normalizationContext: ['groups' => ['product:read', 'media_item:read', 'product:machines']]
        ),
        new GetCollection(
            uriTemplate: '/products/export/excel',
            controller: 'App\Controller\ProductExcelController::exportToExcel',
            openapi: new Model\Operation(
                tags: ['Product'],
                responses: [
                    '200' => [
                        'description' => 'Excel file with products',
                        'content' => [
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => [
                                'schema' => [
                                    'type' => 'string',
                                    'format' => 'binary'
                                ]
                            ]
                        ]
                    ]
                ],
                summary: 'Export all products to Excel',
                description: 'Downloads all products in Excel format'
            ),
            paginationEnabled: false,
            name: 'export_excel'
        ),
        new Post(
            normalizationContext: ['groups' => ['product:read', 'media_item:read', 'product:machines']],
            denormalizationContext: ['groups' => ['product:write', 'media_item:write']]
        ),
        new Put(
            normalizationContext: ['groups' => ['product:read', 'media_item:read', 'product:machines']],
            denormalizationContext: ['groups' => ['product:write', 'media_item:write']]
        ),
        new Patch(
            normalizationContext: ['groups' => ['product:read', 'media_item:read', 'product:machines']],
            denormalizationContext: ['groups' => ['product:write', 'media_item:write']]
        ),
        new Delete()
    ],
    normalizationContext: ['groups' => ['product:read', 'media_item:read']],
    denormalizationContext: ['groups' => ['product:write', 'media_item:write']]
)]
#[ApiFilter(OrderFilter::class, properties: ['name', 'createdAt'])]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'slug' => 'exact',
    'name' => 'partial',
    'partNo' => 'partial',
    'shortDescription' => 'partial',
    'machines.articleDescription' => 'partial'
])]
#[ApiFilter(PropertyFilter::class)]
class Product
{
    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[Groups(['product:read', 'product:list', 'order_item:read', 'order:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    #[Gedmo\Slug(fields: ["partNo"])]
    #[Groups(['product:read', 'product:write', 'order_item:read', 'order:read'])]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Gedmo\Slug(fields: ["name"])]
    #[Groups(['product:read'])]
    private ?string $slug = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['product:read', 'product:write', 'order_item:read', 'order:read'])]
    private ?string $partNo = null;

    #[ORM\Column(type: "text", nullable: true)]
    #[Groups(['product:read', 'product:write', 'order_item:read'])]
    private ?string $shortDescription = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['product:read', 'product:write'])]
    private ?string $unit = null;

    #[ORM\Column(type: "float")]
    #[Groups(['product:read', 'product:write', 'order_item:read', 'order:read'])]
    private ?float $price = 0;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['product:read', 'product:write', 'order_item:read', 'order:read'])]
    private ?string $weight = null;

    #[ORM\Column(type: "text", nullable: true)]
    #[Groups(['product:read', 'product:write'])]
    private ?string $technicalDescription = null;

    #[ORM\Column(type: "text", nullable: true)]
    #[Groups(['product:read', 'product:write'])]
    private ?string $machineText = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['product:read', 'product:write'])]
    private ?string $statistic = null;

    #[ORM\ManyToOne(targetEntity: MediaItem::class, cascade: ['persist'], inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['product:read', 'product:write'])]
    #[ApiProperty(writableLink: true)]
    private ?MediaItem $featuredImage = null;

    #[ORM\Column(type: "datetime")]
    #[Gedmo\Timestampable(on: "create")]
    #[Groups(['product:read'])]
    private ?DateTimeInterface $createdAt;

    #[ORM\Column(type: "datetime")]
    #[Gedmo\Timestampable(on: "update")]
    #[Groups(['product:read'])]
    private ?DateTimeInterface $updatedAt;

    /**
     * @var Collection<int, MediaItem>
     */
    #[ORM\OneToMany(targetEntity: MediaItem::class, mappedBy: 'product', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['product:read', 'product:write'])]
    #[ApiProperty(writableLink: true)]
    private Collection $imageGallery;

    /**
     * @var Collection<int, MediaItem>
     */
    #[ORM\OneToMany(targetEntity: MediaItem::class, mappedBy: 'productDocument', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['product:read', 'product:write'])]
    #[ApiProperty(writableLink: true)]
    private Collection $documents;

    /**
     * @var Collection<int, Machine>
     */
    #[ORM\ManyToMany(targetEntity: Machine::class, mappedBy: 'products')]
    #[Groups(['product:machines', 'product:write'])]
    #[ApiProperty(writableLink: true)]
    private Collection $machines;

    /**
     * @var Collection<int, ClientProductPrice>
     */
    #[ORM\OneToMany(targetEntity: ClientProductPrice::class, mappedBy: 'product', cascade: ['remove'], orphanRemoval: true)]
    private Collection $clientProductPrices;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->imageGallery = new ArrayCollection();
        $this->documents = new ArrayCollection();
        $this->machines = new ArrayCollection();
        $this->clientProductPrices = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getPartNo(): ?string
    {
        return $this->partNo;
    }

    public function setPartNo(?string $partNo): static
    {
        $this->partNo = $partNo;
        return $this;
    }

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(?string $shortDescription): static
    {
        $this->shortDescription = $shortDescription;
        return $this;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(?string $unit): static
    {
        $this->unit = $unit;
        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): static
    {
        $this->price = $price;
        return $this;
    }

    public function getWeight(): ?string
    {
        return $this->weight;
    }

    public function setWeight(?string $weight): static
    {
        $this->weight = $weight;
        return $this;
    }

    public function getTechnicalDescription(): ?string
    {
        return $this->technicalDescription;
    }

    public function setTechnicalDescription(?string $technicalDescription): static
    {
        $this->technicalDescription = $technicalDescription;
        return $this;
    }

    public function getMachineText(): ?string
    {
        return $this->machineText;
    }

    public function setMachineText(?string $machineText): static
    {
        $this->machineText = $machineText;

        return $this;
    }

    public function getStatistic(): ?string
    {
        return $this->statistic;
    }

    public function setStatistic(?string $statistic): static
    {
        $this->statistic = $statistic;

        return $this;
    }

    public function getFeaturedImage(): ?MediaItem
    {
        return $this->featuredImage;
    }

    public function setFeaturedImage(?MediaItem $featuredImage): static
    {
        $this->featuredImage = $featuredImage;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return Collection<int, MediaItem>
     */
    public function getImageGallery(): Collection
    {
        return $this->imageGallery;
    }

    public function addImageGallery(MediaItem $imageGallery): static
    {
        if (!$this->imageGallery->contains($imageGallery)) {
            $this->imageGallery->add($imageGallery);
            $imageGallery->setProduct($this);
        }

        return $this;
    }

    public function removeImageGallery(MediaItem $imageGallery): static
    {
        if ($this->imageGallery->removeElement($imageGallery)) {
            // set the owning side to null (unless already changed)
            if ($imageGallery->getProduct() === $this) {
                $imageGallery->setProduct(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, MediaItem>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(MediaItem $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setProductDocument($this);
        }

        return $this;
    }

    public function removeDocument(MediaItem $document): static
    {
        if ($this->documents->removeElement($document)) {
            // set the owning side to null (unless already changed)
            if ($document->getProductDocument() === $this) {
                $document->setProductDocument(null);
            }
        }

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
            $machine->addProduct($this);
        }

        return $this;
    }

    public function removeMachine(Machine $machine): static
    {
        if ($this->machines->removeElement($machine)) {
            $machine->removeProduct($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, ClientProductPrice>
     */
    public function getClientProductPrices(): Collection
    {
        return $this->clientProductPrices;
    }

    public function addClientProductPrice(ClientProductPrice $clientProductPrice): static
    {
        if (!$this->clientProductPrices->contains($clientProductPrice)) {
            $this->clientProductPrices->add($clientProductPrice);
            $clientProductPrice->setProduct($this);
        }

        return $this;
    }

    public function removeClientProductPrice(ClientProductPrice $clientProductPrice): static
    {
        if ($this->clientProductPrices->removeElement($clientProductPrice)) {
            if ($clientProductPrice->getProduct() === $this) {
                $clientProductPrice->setProduct(null);
            }
        }

        return $this;
    }
}
