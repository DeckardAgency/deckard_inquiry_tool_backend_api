<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\Repository\InquiryMachineRepository;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Gedmo\Mapping\Annotation as Gedmo;
use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: InquiryMachineRepository::class)]
#[ApiResource(
    operations: [
        new Get(normalizationContext: ['groups' => ['inquiry_machine:read', 'machine:read', 'media_item:read']]),
        new GetCollection(
            paginationItemsPerPage: 30,
            paginationClientItemsPerPage: true,
            normalizationContext: ['groups' => ['inquiry_machine:read', 'machine:read', 'media_item:read']]
        ),
        new Post(
            normalizationContext: ['groups' => ['inquiry_machine:read', 'media_item:read']],
            denormalizationContext: ['groups' => ['inquiry_machine:write']]
        ),
        new Put(
            normalizationContext: ['groups' => ['inquiry_machine:read', 'media_item:read']],
            denormalizationContext: ['groups' => ['inquiry_machine:write']]
        ),
        new Patch(
            normalizationContext: ['groups' => ['inquiry_machine:read', 'media_item:read']],
            denormalizationContext: ['groups' => ['inquiry_machine:write']]
        ),
        new Delete()
    ],
    normalizationContext: ['groups' => ['inquiry_machine:read', 'machine:read', 'media_item:read']],
    denormalizationContext: ['groups' => ['inquiry_machine:write']]
)]
class InquiryMachine
{
    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[Groups(['inquiry_machine:read', 'inquiry:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Inquiry::class, inversedBy: 'machines')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['inquiry_machine:read', 'inquiry_machine:write'])]
    #[ApiProperty(readableLink: false, writableLink: false)]
    private ?Inquiry $inquiry = null;

    #[ORM\ManyToOne(targetEntity: Machine::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['inquiry_machine:read', 'inquiry_machine:write', 'inquiry:read', 'inquiry:write', 'machine:read'])]
    #[ApiProperty(readableLink: true, writableLink: true)]
    private ?Machine $machine = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    #[Groups(['inquiry_machine:read', 'inquiry_machine:write', 'inquiry:read', 'inquiry:write'])]
    private ?string $customMachineId = null;

    #[ORM\Column(type: "text", nullable: true)]
    #[Groups(['inquiry_machine:read', 'inquiry_machine:write', 'inquiry:read', 'inquiry:write'])]
    private ?string $notes = null;

    #[ORM\Column(type: "datetime")]
    #[Gedmo\Timestampable(on: "create")]
    #[Groups(['inquiry_machine:read', 'inquiry:read'])]
    private ?DateTimeInterface $createdAt;

    #[ORM\Column(type: "datetime")]
    #[Gedmo\Timestampable(on: "update")]
    #[Groups(['inquiry_machine:read', 'inquiry:read'])]
    private ?DateTimeInterface $updatedAt;

    /**
     * @var Collection<int, InquiryMachinePart>
     */
    #[ORM\OneToMany(targetEntity: InquiryMachinePart::class, mappedBy: 'inquiryMachine', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['inquiry_machine:read', 'inquiry_machine:write', 'inquiry:read', 'inquiry:write'])]
    #[ApiProperty(readableLink: true, writableLink: true)]
    private Collection $products;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['inquiry_machine:read', 'inquiry_machine:write', 'inquiry:read', 'inquiry:write'])]
    #[ApiProperty(readableLink: true)]
    private ?Client $onBehalfOfClient = null;

    /**
     * @var Collection<int, MediaItem>
     */
    #[ORM\ManyToMany(targetEntity: MediaItem::class)]
    #[ORM\JoinTable(name: 'inquiry_machine_media_item')]
    #[Groups(['inquiry_machine:read', 'inquiry_machine:write', 'inquiry:read', 'inquiry:write'])]
    #[ApiProperty(readableLink: true, writableLink: true)]
    private Collection $mediaItems;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->products = new ArrayCollection();
        $this->mediaItems = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getInquiry(): ?Inquiry
    {
        return $this->inquiry;
    }

    public function setInquiry(?Inquiry $inquiry): static
    {
        $this->inquiry = $inquiry;
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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getCustomMachineId(): ?string
    {
        return $this->customMachineId;
    }

    public function setCustomMachineId(?string $customMachineId): static
    {
        $this->customMachineId = $customMachineId;
        return $this;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
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

    /**
     * @return Collection<int, InquiryMachinePart>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(InquiryMachinePart $part): static
    {
        if (!$this->products->contains($part)) {
            $this->products->add($part);
            $part->setInquiryMachine($this);
        }

        return $this;
    }

    public function removeProduct(InquiryMachinePart $part): static
    {
        if ($this->products->removeElement($part)) {
            if ($part->getInquiryMachine() === $this) {
                $part->setInquiryMachine(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, MediaItem>
     */
    public function getMediaItems(): Collection
    {
        return $this->mediaItems;
    }

    public function addMediaItem(MediaItem $mediaItem): static
    {
        if (!$this->mediaItems->contains($mediaItem)) {
            $this->mediaItems->add($mediaItem);
        }

        return $this;
    }

    public function removeMediaItem(MediaItem $mediaItem): static
    {
        $this->mediaItems->removeElement($mediaItem);

        return $this;
    }

    public function getOnBehalfOfClient(): ?Client
    {
        return $this->onBehalfOfClient;
    }

    public function setOnBehalfOfClient(?Client $onBehalfOfClient): static
    {
        $this->onBehalfOfClient = $onBehalfOfClient;
        return $this;
    }

    /**
     * Validate that either machine OR customMachineId is provided, but not both
     */
    #[Assert\Callback]
    public function validateMachineOrCustomMachineId(ExecutionContextInterface $context): void
    {
        // At least one must be provided
        if ($this->machine === null && ($this->customMachineId === null || trim($this->customMachineId) === '')) {
            $context->buildViolation('Either machine or customMachineId must be provided.')
                ->atPath('machine')
                ->addViolation();
        }

        // Both cannot be provided
        if ($this->machine !== null && $this->customMachineId !== null && trim($this->customMachineId) !== '') {
            $context->buildViolation('Cannot provide both machine and customMachineId.')
                ->atPath('machine')
                ->addViolation();
        }
    }
}
