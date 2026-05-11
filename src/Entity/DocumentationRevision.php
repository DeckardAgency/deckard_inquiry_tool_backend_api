<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Repository\DocumentationRevisionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DocumentationRevisionRepository::class)]
#[ORM\Table]
#[ApiResource(
    operations: [
        new Get(normalizationContext: ['groups' => ['documentation_revision:read', 'documentation_revision:item']]),
        new GetCollection(normalizationContext: ['groups' => ['documentation_revision:read']]),
    ],
    normalizationContext: ['groups' => ['documentation_revision:read']],
    order: ['editedAt' => 'DESC']
)]
#[ApiResource(
    uriTemplate: '/documentations/{documentationId}/revisions',
    operations: [
        new GetCollection(normalizationContext: ['groups' => ['documentation_revision:read']])
    ],
    uriVariables: [
        'documentationId' => new Link(toProperty: 'documentation', fromClass: Documentation::class)
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'id' => 'exact',
    'documentation.id' => 'exact',
    'editedBy.id' => 'exact'
])]
#[ApiFilter(OrderFilter::class, properties: ['editedAt', 'revisionNumber'])]
class DocumentationRevision
{
    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[Groups(['documentation_revision:read', 'documentation:item'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Documentation::class, inversedBy: 'revisions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['documentation_revision:read'])]
    private ?Documentation $documentation = null;

    #[ORM\Column(type: "text")]
    #[Groups(['documentation_revision:read', 'documentation_revision:item', 'documentation:item'])]
    private ?string $content = null;

    #[ORM\Column(type: "text")]
    #[Groups(['documentation_revision:read', 'documentation_revision:item', 'documentation:item'])]
    private ?string $title = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['documentation_revision:read', 'documentation:item'])]
    private ?User $editedBy = null;

    #[ORM\Column(type: "datetime")]
    #[Groups(['documentation_revision:read', 'documentation:item'])]
    private ?\DateTimeInterface $editedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['documentation_revision:read', 'documentation:item'])]
    private ?string $changeNote = null;

    #[ORM\Column(type: "integer")]
    #[Groups(['documentation_revision:read', 'documentation:item'])]
    private int $revisionNumber = 1;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->editedAt = new \DateTime();
    }

    public function __toString(): string
    {
        return sprintf('Revision %d - %s', $this->revisionNumber, $this->editedAt?->format('Y-m-d H:i:s') ?? '');
    }

    public function getId(): ?Uuid
    {
        return $this->id;
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

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
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

    public function getEditedBy(): ?User
    {
        return $this->editedBy;
    }

    public function setEditedBy(?User $editedBy): static
    {
        $this->editedBy = $editedBy;
        return $this;
    }

    public function getEditedAt(): ?\DateTimeInterface
    {
        return $this->editedAt;
    }

    public function setEditedAt(\DateTimeInterface $editedAt): static
    {
        $this->editedAt = $editedAt;
        return $this;
    }

    public function getChangeNote(): ?string
    {
        return $this->changeNote;
    }

    public function setChangeNote(?string $changeNote): static
    {
        $this->changeNote = $changeNote;
        return $this;
    }

    public function getRevisionNumber(): int
    {
        return $this->revisionNumber;
    }

    public function setRevisionNumber(int $revisionNumber): static
    {
        $this->revisionNumber = $revisionNumber;
        return $this;
    }
}
