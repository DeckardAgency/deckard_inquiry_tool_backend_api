<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\MachineRepository;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: MachineRepository::class)]
#[ORM\Table]
#[ORM\Index(name: "idx_machine_ib_station", columns: ["ib_station_number"])]
#[ORM\Index(name: "idx_machine_ib_serial", columns: ["ib_serial_number"])]
#[ORM\Index(name: "idx_machine_article", columns: ["article_number"])]
#[ORM\Index(name: "idx_machine_delivery_date", columns: ["delivery_date"])]
#[ORM\Index(name: "idx_machine_warranty_end", columns: ["main_warranty_end"])]
#[ApiResource(
    operations: [
        new Get(normalizationContext: ['groups' => ['inquiry:read', 'inquiry_item:read', 'user:read', 'machine:read', 'media_item:read', 'product:read']]),
        new GetCollection(
            paginationItemsPerPage: 30,
            paginationClientItemsPerPage: true,
            normalizationContext: ['groups' => ['machine:read', 'machine:products', 'media_item:read']]
        ),
        new Post(
            normalizationContext: ['groups' => ['machine:read', 'media_item:read']],
            denormalizationContext: ['groups' => ['machine:write']]
        ),
        new Put(
            normalizationContext: ['groups' => ['machine:read', 'media_item:read']],
            denormalizationContext: ['groups' => ['machine:write']]
        ),
        new Patch(
            normalizationContext: ['groups' => ['machine:read', 'media_item:read']],
            denormalizationContext: ['groups' => ['machine:write']]
        ),
        new Delete()
    ],
    normalizationContext: ['groups' => ['inquiry:read', 'machine:read']],
    denormalizationContext: ['groups' => ['inquiry:write']]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'ibStationNumber' => 'partial',
    'ibSerialNumber' => 'partial',
    'articleNumber' => 'partial',
    'articleDescription' => 'partial',
    'category.slug' => 'exact'
])]
#[ApiFilter(OrderFilter::class, properties: ['articleDescription'])]
class Machine
{
    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[Groups(['machine:read', 'product:machines', 'installed_base:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(type: "datetime")]
    #[Gedmo\Timestampable(on: "create")]
    #[Groups(['machine:read'])]
    private ?DateTimeInterface $createdAt;

    #[ORM\Column(type: "datetime")]
    #[Gedmo\Timestampable(on: "update")]
    #[Groups(['machine:read'])]
    private ?DateTimeInterface $updatedAt;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['machine:read', 'machine:write', 'inquiry:read', 'product:machines', 'installed_base:read'])]
    private ?int $ibStationNumber = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['machine:read', 'machine:write', 'inquiry:read', 'product:machines', 'installed_base:read'])]
    private ?int $ibSerialNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['machine:read', 'machine:write', 'inquiry:read', 'product:machines', 'installed_base:read'])]
    private ?string $articleNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['machine:read', 'machine:write', 'inquiry:read', 'product:machines', 'installed_base:read'])]
    private ?string $articleDescription = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['machine:read', 'machine:write', 'inquiry:read'])]
    private ?string $orderNumber = null;

    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['machine:read', 'machine:write', 'inquiry:read'])]
    private ?DateTimeInterface $deliveryDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['machine:read', 'machine:write', 'inquiry:read'])]
    private ?string $kmsIdentificationNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['machine:read', 'machine:write', 'inquiry:read'])]
    private ?string $kmsIdNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['machine:read', 'machine:write', 'inquiry:read'])]
    private ?string $mcNumber = null;

    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['machine:read', 'machine:write', 'inquiry:read'])]
    private ?DateTimeInterface $mainWarrantyEnd = null;

    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['machine:read', 'machine:write', 'inquiry:read'])]
    private ?DateTimeInterface $extendedWarrantyEnd = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['machine:read', 'machine:write', 'inquiry:read'])]
    private ?int $fiStationNumber = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['machine:read', 'machine:write', 'inquiry:read'])]
    private ?int $fiSerialNumber = null;

    #[ORM\ManyToOne(targetEntity: MediaItem::class, inversedBy: 'machines')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['machine:read', 'machine:write', 'inquiry:read'])]
    #[ApiProperty(writableLink: true)]
    private ?MediaItem $featuredImage = null;

    /**
     * @var Collection<int, MediaItem>
     */
    #[ORM\OneToMany(targetEntity: MediaItem::class, mappedBy: 'machine')]
    #[Groups(['machine:read', 'machine:write'])]
    #[ApiProperty(writableLink: true)]
    private Collection $imageGallery;

    /**
     * @var Collection<int, MediaItem>
     */
    #[ORM\OneToMany(targetEntity: MediaItem::class, mappedBy: 'machineDocument', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['machine:read', 'machine:write'])]
    #[ApiProperty(writableLink: true)]
    private Collection $documents;

    /**
     * @var Collection<int, Product>
     */
    #[ORM\ManyToMany(targetEntity: Product::class, inversedBy: 'machines')]
    #[ORM\JoinTable(name: 'machine_product')]
    #[Groups(['machine:products', 'machine:write'])]
    private Collection $products;

    /**
     * @var Collection<int, ClientMachineInstalledBase>
     */
    #[ORM\OneToMany(targetEntity: ClientMachineInstalledBase::class, mappedBy: 'machine', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['machine:read:details'])]
    #[ApiProperty(readableLink: false, writableLink: false)]
    private Collection $installedBaseRelations;

    /**
     * Cached count of clients - updated via console command
     */
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['machine:read'])]
    private int $clientsCount = 0;

    #[ORM\ManyToOne(targetEntity: MachineCategory::class, inversedBy: 'machines')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['machine:read', 'machine:write'])]
    private ?MachineCategory $category = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->imageGallery = new ArrayCollection();
        $this->documents = new ArrayCollection();
        $this->products = new ArrayCollection();
        $this->installedBaseRelations = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getIbStationNumber(): ?int
    {
        return $this->ibStationNumber;
    }

    public function setIbStationNumber(?int $ibStationNumber): static
    {
        $this->ibStationNumber = $ibStationNumber;
        return $this;
    }

    public function getIbSerialNumber(): ?int
    {
        return $this->ibSerialNumber;
    }

    public function setIbSerialNumber(?int $ibSerialNumber): static
    {
        $this->ibSerialNumber = $ibSerialNumber;
        return $this;
    }

    public function getArticleNumber(): ?string
    {
        return $this->articleNumber;
    }

    public function setArticleNumber(?string $articleNumber): static
    {
        $this->articleNumber = $articleNumber;
        return $this;
    }

    public function getArticleDescription(): ?string
    {
        return $this->articleDescription;
    }

    public function setArticleDescription(?string $articleDescription): static
    {
        $this->articleDescription = $articleDescription;
        return $this;
    }

    public function getOrderNumber(): ?string
    {
        return $this->orderNumber;
    }

    public function setOrderNumber(?string $orderNumber): static
    {
        $this->orderNumber = $orderNumber;
        return $this;
    }

    public function getDeliveryDate(): ?DateTimeInterface
    {
        return $this->deliveryDate;
    }

    public function setDeliveryDate(?DateTimeInterface $deliveryDate): static
    {
        $this->deliveryDate = $deliveryDate;
        return $this;
    }

    public function getKmsIdentificationNumber(): ?string
    {
        return $this->kmsIdentificationNumber;
    }

    public function setKmsIdentificationNumber(?string $kmsIdentificationNumber): static
    {
        $this->kmsIdentificationNumber = $kmsIdentificationNumber;
        return $this;
    }

    public function getKmsIdNumber(): ?string
    {
        return $this->kmsIdNumber;
    }

    public function setKmsIdNumber(?string $kmsIdNumber): static
    {
        $this->kmsIdNumber = $kmsIdNumber;
        return $this;
    }

    public function getMcNumber(): ?string
    {
        return $this->mcNumber;
    }

    public function setMcNumber(?string $mcNumber): static
    {
        $this->mcNumber = $mcNumber;
        return $this;
    }

    public function getMainWarrantyEnd(): ?DateTimeInterface
    {
        return $this->mainWarrantyEnd;
    }

    public function setMainWarrantyEnd(?DateTimeInterface $mainWarrantyEnd): static
    {
        $this->mainWarrantyEnd = $mainWarrantyEnd;
        return $this;
    }

    public function getExtendedWarrantyEnd(): ?DateTimeInterface
    {
        return $this->extendedWarrantyEnd;
    }

    public function setExtendedWarrantyEnd(?DateTimeInterface $extendedWarrantyEnd): static
    {
        $this->extendedWarrantyEnd = $extendedWarrantyEnd;
        return $this;
    }

    public function getFiStationNumber(): ?int
    {
        return $this->fiStationNumber;
    }

    public function setFiStationNumber(?int $fiStationNumber): static
    {
        $this->fiStationNumber = $fiStationNumber;
        return $this;
    }

    public function getFiSerialNumber(): ?int
    {
        return $this->fiSerialNumber;
    }

    public function setFiSerialNumber(?int $fiSerialNumber): static
    {
        $this->fiSerialNumber = $fiSerialNumber;
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
            $imageGallery->setMachine($this);
        }
        return $this;
    }

    public function removeImageGallery(MediaItem $imageGallery): static
    {
        if ($this->imageGallery->removeElement($imageGallery)) {
            if ($imageGallery->getMachine() === $this) {
                $imageGallery->setMachine(null);
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
            $document->setMachineDocument($this);
        }
        return $this;
    }

    public function removeDocument(MediaItem $document): static
    {
        if ($this->documents->removeElement($document)) {
            if ($document->getMachineDocument() === $this) {
                $document->setMachineDocument(null);
            }
        }
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
        }
        return $this;
    }

    public function removeProduct(Product $product): static
    {
        $this->products->removeElement($product);
        return $this;
    }

    /**
     * @return Collection<int, ClientMachineInstalledBase>
     */
    public function getInstalledBaseRelations(): Collection
    {
        return $this->installedBaseRelations;
    }

    public function addInstalledBaseRelation(ClientMachineInstalledBase $relation): static
    {
        if (!$this->installedBaseRelations->contains($relation)) {
            $this->installedBaseRelations->add($relation);
            $relation->setMachine($this);
        }
        return $this;
    }

    public function removeInstalledBaseRelation(ClientMachineInstalledBase $relation): static
    {
        if ($this->installedBaseRelations->removeElement($relation)) {
            if ($relation->getMachine() === $this) {
                $relation->setMachine(null);
            }
        }
        return $this;
    }

    public function getClientsCount(): int
    {
        return $this->clientsCount;
    }

    public function setClientsCount(int $clientsCount): static
    {
        $this->clientsCount = $clientsCount;
        return $this;
    }

    public function getCategory(): ?MachineCategory
    {
        return $this->category;
    }

    public function setCategory(?MachineCategory $category): static
    {
        $this->category = $category;
        return $this;
    }
}
