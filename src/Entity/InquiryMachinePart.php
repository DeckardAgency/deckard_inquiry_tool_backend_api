<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\Repository\InquiryMachinePartRepository;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Gedmo\Mapping\Annotation as Gedmo;
use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InquiryMachinePartRepository::class)]
#[ApiResource(
    operations: [
        new Get(normalizationContext: ['groups' => ['inquiry_machine_part:read', 'media_item:read']]),
        new GetCollection(
            paginationItemsPerPage: 30,
            paginationClientItemsPerPage: true,
            normalizationContext: ['groups' => ['inquiry_machine_part:read', 'media_item:read']]
        ),
        new Post(
            normalizationContext: ['groups' => ['inquiry_machine_part:read', 'media_item:read']],
            denormalizationContext: ['groups' => ['inquiry_machine_part:write']]
        ),
        new Put(
            normalizationContext: ['groups' => ['inquiry_machine_part:read', 'media_item:read']],
            denormalizationContext: ['groups' => ['inquiry_machine_part:write']]
        ),
        new Patch(
            normalizationContext: ['groups' => ['inquiry_machine_part:read', 'media_item:read']],
            denormalizationContext: ['groups' => ['inquiry_machine_part:write']]
        ),
        new Delete()
    ],
    normalizationContext: ['groups' => ['inquiry_machine_part:read', 'media_item:read']],
    denormalizationContext: ['groups' => ['inquiry_machine_part:write']]
)]
class InquiryMachinePart
{
    public const INFO_STATUS_NONE = 'none';
    public const INFO_STATUS_CLEAR = 'clear';
    public const INFO_STATUS_PENDING_INFO = 'pending_info';
    public const INFO_STATUS_INFO_PROVIDED = 'info_provided';

    #[ORM\Id]
    #[ORM\Column(type: "uuid", unique: true)]
    #[Groups(['inquiry_machine_part:read', 'inquiry_machine:read', 'inquiry:read', 'info_request:read', 'inquiry_offer_item:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: InquiryMachine::class, inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['inquiry_machine_part:read', 'inquiry_machine_part:write'])]
    #[ApiProperty(readableLink: false, writableLink: false)]
    private ?InquiryMachine $inquiryMachine = null;

    #[ORM\Column(length: 255)]
    #[Groups(['inquiry_machine_part:read', 'inquiry_machine_part:write', 'inquiry_machine:read', 'inquiry_machine:write', 'inquiry:read', 'inquiry:write', 'info_request:read', 'inquiry_offer_item:read'])]
    private ?string $partName = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['inquiry_machine_part:read', 'inquiry_machine_part:write', 'inquiry_machine:read', 'inquiry_machine:write', 'inquiry:read', 'inquiry:write', 'info_request:read', 'inquiry_offer_item:read'])]
    private ?string $partNumber = null;

    #[ORM\Column(type: "text", nullable: true)]
    #[Groups(['inquiry_machine_part:read', 'inquiry_machine_part:write', 'inquiry_machine:read', 'inquiry_machine:write', 'inquiry:read', 'inquiry:write'])]
    private ?string $shortDescription = null;

    #[ORM\Column(type: "text", nullable: true)]
    #[Groups(['inquiry_machine_part:read', 'inquiry_machine_part:write', 'inquiry_machine:read', 'inquiry_machine:write', 'inquiry:read', 'inquiry:write'])]
    private ?string $additionalNotes = null;

    #[ORM\Column(type: "datetime")]
    #[Gedmo\Timestampable(on: "create")]
    #[Groups(['inquiry_machine_part:read', 'inquiry_machine:read', 'inquiry:read'])]
    private ?DateTimeInterface $createdAt;

    #[ORM\Column(type: "datetime")]
    #[Gedmo\Timestampable(on: "update")]
    #[Groups(['inquiry_machine_part:read', 'inquiry_machine:read', 'inquiry:read'])]
    private ?DateTimeInterface $updatedAt;

    /**
     * @var Collection<int, MediaItem>
     */
    #[ORM\ManyToMany(targetEntity: MediaItem::class)]
    #[ORM\JoinTable(name: 'inquiry_machine_part_media_item')]
    #[Groups(['inquiry_machine_part:read', 'inquiry_machine_part:write', 'inquiry_machine:read', 'inquiry_machine:write', 'inquiry:read', 'inquiry:write'])]
    #[ApiProperty(readableLink: true, writableLink: true)]
    private Collection $mediaItems;

    #[ORM\Column(length: 50, options: ['default' => self::INFO_STATUS_NONE])]
    #[Groups(['inquiry_machine_part:read', 'inquiry_machine_part:write', 'inquiry_machine:read', 'inquiry:read'])]
    #[Assert\Choice(choices: [self::INFO_STATUS_NONE, self::INFO_STATUS_CLEAR, self::INFO_STATUS_PENDING_INFO, self::INFO_STATUS_INFO_PROVIDED])]
    private string $infoStatus = self::INFO_STATUS_NONE;

    /**
     * @var Collection<int, InquiryPartInfoRequest>
     */
    #[ORM\OneToMany(
        targetEntity: InquiryPartInfoRequest::class,
        mappedBy: 'inquiryMachinePart',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    #[Groups(['inquiry_machine_part:read', 'inquiry:read'])]
    #[ApiProperty(readableLink: true)]
    private Collection $infoRequests;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->mediaItems = new ArrayCollection();
        $this->infoRequests = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getInquiryMachine(): ?InquiryMachine
    {
        return $this->inquiryMachine;
    }

    public function setInquiryMachine(?InquiryMachine $inquiryMachine): static
    {
        $this->inquiryMachine = $inquiryMachine;
        return $this;
    }

    public function getPartName(): ?string
    {
        return $this->partName;
    }

    public function setPartName(string $partName): static
    {
        $this->partName = $partName;
        return $this;
    }

    public function getPartNumber(): ?string
    {
        return $this->partNumber;
    }

    public function setPartNumber(?string $partNumber): static
    {
        $this->partNumber = $partNumber;
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

    public function getAdditionalNotes(): ?string
    {
        return $this->additionalNotes;
    }

    public function setAdditionalNotes(?string $additionalNotes): static
    {
        $this->additionalNotes = $additionalNotes;
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

    public function getInfoStatus(): string
    {
        return $this->infoStatus;
    }

    public function setInfoStatus(string $infoStatus): static
    {
        $this->infoStatus = $infoStatus;
        return $this;
    }

    /**
     * @return Collection<int, InquiryPartInfoRequest>
     */
    public function getInfoRequests(): Collection
    {
        return $this->infoRequests;
    }

    public function addInfoRequest(InquiryPartInfoRequest $infoRequest): static
    {
        if (!$this->infoRequests->contains($infoRequest)) {
            $this->infoRequests->add($infoRequest);
            $infoRequest->setInquiryMachinePart($this);
        }

        return $this;
    }

    public function removeInfoRequest(InquiryPartInfoRequest $infoRequest): static
    {
        if ($this->infoRequests->removeElement($infoRequest)) {
            if ($infoRequest->getInquiryMachinePart() === $this) {
                $infoRequest->setInquiryMachinePart(null);
            }
        }

        return $this;
    }

    /**
     * Mark part as clear (no info needed)
     */
    public function markAsClear(): static
    {
        $this->infoStatus = self::INFO_STATUS_CLEAR;
        return $this;
    }

    /**
     * Mark part as pending info
     */
    public function markAsPendingInfo(): static
    {
        $this->infoStatus = self::INFO_STATUS_PENDING_INFO;
        return $this;
    }

    /**
     * Mark part as info provided
     */
    public function markAsInfoProvided(): static
    {
        $this->infoStatus = self::INFO_STATUS_INFO_PROVIDED;
        return $this;
    }

    /**
     * Check if part needs info
     */
    public function needsInfo(): bool
    {
        return $this->infoStatus === self::INFO_STATUS_PENDING_INFO;
    }

    /**
     * Check if part is clear
     */
    public function isClear(): bool
    {
        return $this->infoStatus === self::INFO_STATUS_CLEAR;
    }

    /**
     * Check if part has info provided
     */
    public function hasInfoProvided(): bool
    {
        return $this->infoStatus === self::INFO_STATUS_INFO_PROVIDED;
    }

    /**
     * Get the latest pending info request
     */
    public function getLatestPendingInfoRequest(): ?InquiryPartInfoRequest
    {
        foreach ($this->infoRequests as $infoRequest) {
            if ($infoRequest->isPending()) {
                return $infoRequest;
            }
        }
        return null;
    }

    /**
     * Check if there is any pending info request
     */
    public function hasPendingInfoRequest(): bool
    {
        return $this->getLatestPendingInfoRequest() !== null;
    }

    /**
     * Get valid info statuses
     */
    public static function getValidInfoStatuses(): array
    {
        return [
            self::INFO_STATUS_NONE,
            self::INFO_STATUS_CLEAR,
            self::INFO_STATUS_PENDING_INFO,
            self::INFO_STATUS_INFO_PROVIDED,
        ];
    }
}
