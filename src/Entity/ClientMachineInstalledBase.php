<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use App\Repository\ClientMachineInstalledBaseRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ClientMachineInstalledBaseRepository::class)]
#[ORM\Table(name: 'client_machine_installed_base')]
#[ORM\Index(name: "idx_cmib_client", columns: ["client_id"])]
#[ORM\Index(name: "idx_cmib_machine", columns: ["machine_id"])]
#[ORM\Index(name: "idx_cmib_installed_date", columns: ["installed_date"])]
#[ORM\Index(name: "idx_cmib_status", columns: ["status"])]
#[ORM\Index(name: "idx_cmib_composite", columns: ["client_id", "machine_id"])]
#[ORM\UniqueConstraint(name: "unique_client_machine", columns: ["client_id", "machine_id"])]
#[ApiResource(
    operations: [
        new GetCollection(
            paginationItemsPerPage: 50,
            paginationMaximumItemsPerPage: 100,
            paginationClientItemsPerPage: true,
            order: ['createdAt' => 'DESC'],
            normalizationContext: ['groups' => ['installed_base:read', 'client:read', 'machine:read']]
        ),
        new Post(
            normalizationContext: ['groups' => ['installed_base:read', 'client:read', 'machine:read']],
            denormalizationContext: ['groups' => ['installed_base:write']]
        ),
        new Get(
            normalizationContext: ['groups' => ['installed_base:read', 'installed_base:details', 'client:read', 'machine:read']]
        ),
        new Put(
            normalizationContext: ['groups' => ['installed_base:read', 'client:read', 'machine:read']],
            denormalizationContext: ['groups' => ['installed_base:write']]
        ),
        new Patch(
            normalizationContext: ['groups' => ['installed_base:read', 'client:read', 'machine:read']],
            denormalizationContext: ['groups' => ['installed_base:write']]
        ),
        new Delete()
    ],
    normalizationContext: ['groups' => ['installed_base:read', 'client:read', 'machine:read']],
    denormalizationContext: ['groups' => ['installed_base:write']],
    paginationPartial: true
)]
#[ApiFilter(SearchFilter::class, properties: [
    'client' => 'exact',
    'client.code' => 'exact',
    'client.name' => 'partial',
    'machine' => 'exact',
    'machine.id' => 'exact',
    'machine.ibStationNumber' => 'exact',
    'machine.ibSerialNumber' => 'exact',
    'machine.articleNumber' => 'partial',
    'status' => 'exact',
    'location' => 'partial'
])]
#[ApiFilter(DateFilter::class, properties: ['installedDate', 'warrantyEndDate', 'createdAt'])]
class ClientMachineInstalledBase
{
    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[Groups(['installed_base:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'installedBaseRelations')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['installed_base:read', 'installed_base:write', 'installed_base:details'])]
    #[ApiProperty(readableLink: true, writableLink: true)]
    private ?Client $client = null;

    #[ORM\ManyToOne(targetEntity: Machine::class, inversedBy: 'installedBaseRelations')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['installed_base:read', 'installed_base:write', 'installed_base:details'])]
    #[ApiProperty(readableLink: true, writableLink: true)]
    private ?Machine $machine = null;

    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['installed_base:read', 'installed_base:write'])]
    private ?\DateTimeInterface $installedDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['installed_base:read', 'installed_base:write'])]
    private ?string $location = null;

    #[ORM\Column(length: 50, options: ['default' => 'active'])]
    #[Assert\Choice(choices: ['active', 'inactive', 'maintenance', 'decommissioned'])]
    #[Groups(['installed_base:read', 'installed_base:write'])]
    private string $status = 'active';

    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['installed_base:read', 'installed_base:write'])]
    private ?\DateTimeInterface $warrantyEndDate = null;

    #[ORM\Column(type: "text", nullable: true)]
    #[Groups(['installed_base:read', 'installed_base:write'])]
    private ?string $notes = null;

    #[ORM\Column(type: "datetime")]
    #[Gedmo\Timestampable(on: "create")]
    #[Groups(['installed_base:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: "datetime")]
    #[Gedmo\Timestampable(on: "update")]
    #[Groups(['installed_base:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['installed_base:read', 'installed_base:write'])]
    private ?string $installedBy = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['installed_base:read', 'installed_base:write'])]
    private ?string $installationReference = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Groups(['installed_base:read', 'installed_base:write'])]
    private ?string $monthlyRate = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;
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

    public function getInstalledDate(): ?\DateTimeInterface
    {
        return $this->installedDate;
    }

    public function setInstalledDate(?\DateTimeInterface $installedDate): static
    {
        $this->installedDate = $installedDate;
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getWarrantyEndDate(): ?\DateTimeInterface
    {
        return $this->warrantyEndDate;
    }

    public function setWarrantyEndDate(?\DateTimeInterface $warrantyEndDate): static
    {
        $this->warrantyEndDate = $warrantyEndDate;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
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

    public function getInstalledBy(): ?string
    {
        return $this->installedBy;
    }

    public function setInstalledBy(?string $installedBy): static
    {
        $this->installedBy = $installedBy;
        return $this;
    }

    public function getInstallationReference(): ?string
    {
        return $this->installationReference;
    }

    public function setInstallationReference(?string $installationReference): static
    {
        $this->installationReference = $installationReference;
        return $this;
    }

    public function getMonthlyRate(): ?string
    {
        return $this->monthlyRate;
    }

    public function setMonthlyRate(?string $monthlyRate): static
    {
        $this->monthlyRate = $monthlyRate;
        return $this;
    }

    public function isUnderWarranty(): bool
    {
        if (!$this->warrantyEndDate) {
            return false;
        }
        return $this->warrantyEndDate > new \DateTime();
    }

    public function getInstallationAge(): ?int
    {
        if (!$this->installedDate) {
            return null;
        }
        $now = new \DateTime();
        return $now->diff($this->installedDate)->days;
    }
}
