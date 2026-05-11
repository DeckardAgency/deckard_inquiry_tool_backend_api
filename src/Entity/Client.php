<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use App\State\Processor\ClientUsersProcessor;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\ClientRepository;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['client:read']]
        ),
        new Post(
            normalizationContext: ['groups' => ['client:read']],
            denormalizationContext: ['groups' => ['client:write']]
        ),
        new Get(
            normalizationContext: ['groups' => ['client:read', 'client:read:details']]
        ),
        new Put(
            normalizationContext: ['groups' => ['client:read']],
            denormalizationContext: ['groups' => ['client:write']],
            processor: ClientUsersProcessor::class
        ),
        new Patch(
            normalizationContext: ['groups' => ['client:read']],
            denormalizationContext: ['groups' => ['client:write']],
            processor: ClientUsersProcessor::class
        ),
        new Delete()
    ],
    normalizationContext: ['groups' => ['client:read']],
    denormalizationContext: ['groups' => ['client:write']]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'name' => 'partial',
    'code' => 'exact'
])]
#[ApiFilter(BooleanFilter::class, properties: ['isArchived', 'isActive', 'isClientAgent'])]
#[ORM\Entity(repositoryClass: ClientRepository::class)]
#[ORM\Table]
#[ORM\Index(name: "idx_client_code", columns: ["code"])]
#[ORM\Index(name: "idx_client_name", columns: ["name"])]
class Client
{
    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[Groups(['client:read', 'user:read', 'installed_base:read', 'area_manager:read', 'order:read', 'inquiry:read'])]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['client:read', 'client:write', 'user:read', 'installed_base:read', 'order:read', 'inquiry:read'])]
    private ?string $name = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 50)]
    #[Groups(['client:read', 'client:write', 'user:read', 'installed_base:read', 'order:read', 'inquiry:read'])]
    private ?string $code = null;

    #[ORM\Column(type: "text", nullable: true)]
    #[Groups(['client:read', 'client:write'])]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['client:read', 'client:write'])]
    private ?string $address = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['client:read', 'client:write'])]
    private ?string $phoneNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Email]
    #[Groups(['client:read', 'client:write'])]
    private ?string $email = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['client:read', 'client:write'])]
    private ?string $vatNumber = null;

    #[ORM\Column(type: "datetime")]
    #[Gedmo\Timestampable(on: "create")]
    #[Groups(['client:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: "datetime")]
    #[Gedmo\Timestampable(on: "update")]
    #[Groups(['client:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'client')]
    #[Groups(['client:read'])]
    #[ApiProperty(readableLink: false, writableLink: false)]
    private Collection $users;

    /**
     * @var Collection<int, ClientProductPrice>
     */
    #[ORM\OneToMany(targetEntity: ClientProductPrice::class, mappedBy: 'client', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['client:read:details', 'client:write'])]
    private Collection $productPrices;

    /**
     * @var Collection<int, ClientMachineInstalledBase>
     */
    #[ORM\OneToMany(targetEntity: ClientMachineInstalledBase::class, mappedBy: 'client', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['client:read:details'])]
    #[ApiProperty(readableLink: false, writableLink: false)]
    private Collection $installedBaseRelations;

    /**
     * Cached count of machines - updated via console command
     */
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['client:read'])]
    private int $machinesCount = 0;

    /**
     * Maximum number of active users allowed for this client
     * Null means unlimited active users
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['client:read', 'client:write', 'user:read'])]
    #[Assert\PositiveOrZero]
    private ?int $maxActiveUsers = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    #[Groups(['client:read', 'client:write', 'user:read', 'order:read', 'inquiry:read'])]
    private bool $isActive = true;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['client:read', 'client:write', 'user:read', 'order:read', 'inquiry:read'])]
    private bool $isArchived = false;

    /**
     * When enabled, ROLE_CLIENT users must have their order submissions
     * approved by a ROLE_CLIENT_ADMIN before they reach Deckard.
     */
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['client:read', 'client:write', 'user:read'])]
    private bool $requiresOrderApproval = false;

    /**
     * When enabled, ROLE_CLIENT users must have their inquiry submissions
     * approved by a ROLE_CLIENT_ADMIN before they reach Deckard.
     */
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['client:read', 'client:write', 'user:read'])]
    private bool $requiresInquiryApproval = false;

    /**
     * @var Collection<int, Area>
     */
    #[ORM\OneToMany(targetEntity: Area::class, mappedBy: 'client', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['client:read:details'])]
    private Collection $areas;

    /**
     * Whether this client acts as an agent that can place orders/inquiries on behalf of other clients
     */
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['client:read', 'client:write', 'user:read'])]
    private bool $isClientAgent = false;

    /**
     * Clients managed by this agent company (only relevant when isClientAgent = true)
     * @var Collection<int, Client>
     */
    #[ORM\ManyToMany(targetEntity: Client::class, inversedBy: 'managedByAgents')]
    #[ORM\JoinTable(name: 'client_agent_managed_clients',
        joinColumns: [new ORM\JoinColumn(name: 'agent_client_id', referencedColumnName: 'id')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'managed_client_id', referencedColumnName: 'id')]
    )]
    #[Groups(['client:read:details', 'client:write'])]
    #[ApiProperty(readableLink: false, writableLink: false)]
    private Collection $managedClients;

    /**
     * Agent companies that manage this client (inverse side)
     * @var Collection<int, Client>
     */
    #[ORM\ManyToMany(targetEntity: Client::class, mappedBy: 'managedClients')]
    private Collection $managedByAgents;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->users = new ArrayCollection();
        $this->productPrices = new ArrayCollection();
        $this->installedBaseRelations = new ArrayCollection();
        $this->areas = new ArrayCollection();
        $this->managedClients = new ArrayCollection();
        $this->managedByAgents = new ArrayCollection();
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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;
        return $this;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?string $phoneNumber): static
    {
        $this->phoneNumber = $phoneNumber;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getVatNumber(): ?string
    {
        return $this->vatNumber;
    }

    public function setVatNumber(?string $vatNumber): static
    {
        $this->vatNumber = $vatNumber;
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
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->setClient($this);
        }
        return $this;
    }

    public function removeUser(User $user): static
    {
        if ($this->users->removeElement($user)) {
            if ($user->getClient() === $this) {
                $user->setClient(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, ClientProductPrice>
     */
    public function getProductPrices(): Collection
    {
        return $this->productPrices;
    }

    public function addProductPrice(ClientProductPrice $productPrice): static
    {
        if (!$this->productPrices->contains($productPrice)) {
            $this->productPrices->add($productPrice);
            $productPrice->setClient($this);
        }
        return $this;
    }

    public function removeProductPrice(ClientProductPrice $productPrice): static
    {
        if ($this->productPrices->removeElement($productPrice)) {
            if ($productPrice->getClient() === $this) {
                $productPrice->setClient(null);
            }
        }
        return $this;
    }

    public function getProductPrice(Product $product): ?ClientProductPrice
    {
        foreach ($this->productPrices as $productPrice) {
            if ($productPrice->getProduct()->getId() === $product->getId()) {
                return $productPrice;
            }
        }
        return null;
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
            $relation->setClient($this);
        }
        return $this;
    }

    public function removeInstalledBaseRelation(ClientMachineInstalledBase $relation): static
    {
        if ($this->installedBaseRelations->removeElement($relation)) {
            if ($relation->getClient() === $this) {
                $relation->setClient(null);
            }
        }
        return $this;
    }

    public function getMachinesCount(): int
    {
        return $this->machinesCount;
    }

    public function setMachinesCount(int $machinesCount): static
    {
        $this->machinesCount = $machinesCount;
        return $this;
    }

    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        // If setting to active, ensure it's not archived
        if ($isActive && $this->isArchived) {
            $this->isArchived = false;
        }

        return $this;
    }

    public function getIsArchived(): bool
    {
        return $this->isArchived;
    }

    public function setIsArchived(bool $isArchived): static
    {
        $this->isArchived = $isArchived;

        // If setting to archived, ensure it's not active
        if ($isArchived && $this->isActive) {
            $this->isActive = false;
        }

        return $this;
    }

    public function getMaxActiveUsers(): ?int
    {
        return $this->maxActiveUsers;
    }

    public function setMaxActiveUsers(?int $maxActiveUsers): static
    {
        $this->maxActiveUsers = $maxActiveUsers;
        return $this;
    }

    /**
     * Count the number of currently active users for this client
     */
    public function countActiveUsers(): int
    {
        return $this->users->filter(fn(User $user) => $user->getIsActive())->count();
    }

    /**
     * Check if the client can have more active users
     */
    public function canAddActiveUser(): bool
    {
        // If maxActiveUsers is null, there's no limit
        if ($this->maxActiveUsers === null) {
            return true;
        }

        return $this->countActiveUsers() < $this->maxActiveUsers;
    }

    public function getRequiresOrderApproval(): bool
    {
        return $this->requiresOrderApproval;
    }

    public function setRequiresOrderApproval(bool $requiresOrderApproval): static
    {
        $this->requiresOrderApproval = $requiresOrderApproval;
        return $this;
    }

    public function getRequiresInquiryApproval(): bool
    {
        return $this->requiresInquiryApproval;
    }

    public function setRequiresInquiryApproval(bool $requiresInquiryApproval): static
    {
        $this->requiresInquiryApproval = $requiresInquiryApproval;
        return $this;
    }

    /**
     * @return Collection<int, Area>
     */
    public function getAreas(): Collection
    {
        return $this->areas;
    }

    public function addArea(Area $area): static
    {
        if (!$this->areas->contains($area)) {
            $this->areas->add($area);
            $area->setClient($this);
        }
        return $this;
    }

    public function removeArea(Area $area): static
    {
        if ($this->areas->removeElement($area)) {
            if ($area->getClient() === $this) {
                $area->setClient(null);
            }
        }
        return $this;
    }

    public function getIsClientAgent(): bool
    {
        return $this->isClientAgent;
    }

    public function setIsClientAgent(bool $isClientAgent): static
    {
        $this->isClientAgent = $isClientAgent;
        return $this;
    }

    /**
     * @return Collection<int, Client>
     */
    public function getManagedClients(): Collection
    {
        return $this->managedClients;
    }

    public function addManagedClient(Client $client): static
    {
        if (!$this->managedClients->contains($client)) {
            $this->managedClients->add($client);
        }
        return $this;
    }

    public function removeManagedClient(Client $client): static
    {
        $this->managedClients->removeElement($client);
        return $this;
    }

    /**
     * @return Collection<int, Client>
     */
    public function getManagedByAgents(): Collection
    {
        return $this->managedByAgents;
    }

    /**
     * Get count of managed clients
     */
    #[Groups(['client:read'])]
    public function getManagedClientCount(): int
    {
        return $this->managedClients->count();
    }

    /**
     * Check if this agent manages a specific client
     */
    public function managesClient(Client $client): bool
    {
        return $this->managedClients->contains($client);
    }
}
