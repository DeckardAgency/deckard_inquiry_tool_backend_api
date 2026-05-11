<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\Repository\DocumentationRepository;
use App\State\Processor\DocumentationRevisionProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Uid\Uuid;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DocumentationRepository::class)]
#[ORM\Table]
#[ApiResource(
    operations: [
        new Get(normalizationContext: ['groups' => ['documentation:read', 'documentation:item']]),
        new GetCollection(normalizationContext: ['groups' => ['documentation:read']]),
        new Post(
            normalizationContext: ['groups' => ['documentation:read']],
            denormalizationContext: ['groups' => ['documentation:write']],
            processor: DocumentationRevisionProcessor::class
        ),
        new Put(
            normalizationContext: ['groups' => ['documentation:read']],
            denormalizationContext: ['groups' => ['documentation:write']],
            processor: DocumentationRevisionProcessor::class
        ),
        new Patch(
            normalizationContext: ['groups' => ['documentation:read']],
            denormalizationContext: ['groups' => ['documentation:write']],
            processor: DocumentationRevisionProcessor::class
        ),
        new Delete()
    ],
    normalizationContext: ['groups' => ['documentation:read']],
    denormalizationContext: ['groups' => ['documentation:write']],
    order: ['sortOrder' => 'ASC', 'title' => 'ASC']
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'title' => 'partial',
    'slug' => 'exact',
    'category' => 'exact'
])]
#[ApiFilter(BooleanFilter::class, properties: ['isPublished'])]
#[ApiFilter(OrderFilter::class, properties: ['sortOrder', 'title', 'createdAt', 'updatedAt'])]
class Documentation
{
    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[Groups(['documentation:read', 'documentation_revision:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['documentation:read', 'documentation:write'])]
    #[Assert\NotBlank]
    private ?string $title = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Gedmo\Slug(fields: ["title"])]
    #[Groups(['documentation:read'])]
    private ?string $slug = null;

    #[ORM\Column(type: "text")]
    #[Groups(['documentation:read', 'documentation:write', 'documentation:item'])]
    #[Assert\NotBlank]
    private ?string $content = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['documentation:read', 'documentation:write'])]
    private ?string $category = null;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    #[Groups(['documentation:read', 'documentation:write'])]
    private int $sortOrder = 0;

    #[ORM\Column(type: "boolean", options: ["default" => true])]
    #[Groups(['documentation:read', 'documentation:write'])]
    #[SerializedName('isPublished')]
    private bool $isPublished = true;

    /**
     * @var Collection<int, DocumentationRevision>
     */
    #[ORM\OneToMany(targetEntity: DocumentationRevision::class, mappedBy: 'documentation', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['editedAt' => 'DESC'])]
    #[Groups(['documentation:item'])]
    private Collection $revisions;

    /**
     * @var Collection<int, MediaItem>
     */
    #[ORM\OneToMany(targetEntity: MediaItem::class, mappedBy: 'documentation', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['documentation:read', 'documentation:item'])]
    private Collection $media;

    #[ORM\Column(type: "datetime")]
    #[Gedmo\Timestampable(on: "create")]
    #[Groups(['documentation:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: "datetime")]
    #[Gedmo\Timestampable(on: "update")]
    #[Groups(['documentation:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->revisions = new ArrayCollection();
        $this->media = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->title ?? 'New Documentation';
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
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

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }

    public function getIsPublished(): bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): static
    {
        $this->isPublished = $isPublished;
        return $this;
    }

    /**
     * @return Collection<int, DocumentationRevision>
     */
    public function getRevisions(): Collection
    {
        return $this->revisions;
    }

    public function addRevision(DocumentationRevision $revision): static
    {
        if (!$this->revisions->contains($revision)) {
            $this->revisions->add($revision);
            $revision->setDocumentation($this);
        }

        return $this;
    }

    public function removeRevision(DocumentationRevision $revision): static
    {
        if ($this->revisions->removeElement($revision)) {
            if ($revision->getDocumentation() === $this) {
                $revision->setDocumentation(null);
            }
        }

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
     * @return Collection<int, MediaItem>
     */
    public function getMedia(): Collection
    {
        return $this->media;
    }

    public function addMedia(MediaItem $mediaItem): static
    {
        if (!$this->media->contains($mediaItem)) {
            $this->media->add($mediaItem);
            $mediaItem->setDocumentation($this);
        }

        return $this;
    }

    public function removeMedia(MediaItem $mediaItem): static
    {
        if ($this->media->removeElement($mediaItem)) {
            if ($mediaItem->getDocumentation() === $this) {
                $mediaItem->setDocumentation(null);
            }
        }

        return $this;
    }
}
