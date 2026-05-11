<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Repository\InquiryPartInfoMessageRepository;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: InquiryPartInfoMessageRepository::class)]
#[ORM\Table(name: 'inquiry_part_info_message')]
#[ORM\Index(name: 'idx_info_message_request', columns: ['info_request_id'])]
#[ORM\Index(name: 'idx_info_message_created_at', columns: ['created_at'])]
#[ApiResource(
    operations: [
        new Get(
            normalizationContext: ['groups' => ['info_message:read', 'media_item:read', 'user:read']]
        ),
        new GetCollection(
            paginationItemsPerPage: 50,
            normalizationContext: ['groups' => ['info_message:read', 'media_item:read']]
        ),
        new Post(
            normalizationContext: ['groups' => ['info_message:read', 'media_item:read']],
            denormalizationContext: ['groups' => ['info_message:write']]
        ),
        new Delete(security: "is_granted('ROLE_ADMIN')")
    ],
    normalizationContext: ['groups' => ['info_message:read']],
    denormalizationContext: ['groups' => ['info_message:write']]
)]
#[ApiResource(
    uriTemplate: '/inquiry-part-info-requests/{infoRequestId}/messages',
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['info_message:read', 'media_item:read', 'user:read']]
        ),
        new Post(
            normalizationContext: ['groups' => ['info_message:read', 'media_item:read']],
            denormalizationContext: ['groups' => ['info_message:write']]
        )
    ],
    uriVariables: [
        'infoRequestId' => new Link(
            fromProperty: 'messages',
            fromClass: InquiryPartInfoRequest::class
        )
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'infoRequest.id' => 'exact',
    'senderType' => 'exact'
])]
class InquiryPartInfoMessage
{
    public const SENDER_TYPE_ADMIN = 'admin';
    public const SENDER_TYPE_CLIENT = 'client';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[Groups(['info_message:read', 'info_request:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: InquiryPartInfoRequest::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['info_message:read', 'info_message:write'])]
    #[ApiProperty(readableLink: false, writableLink: false)]
    #[Assert\NotNull]
    private ?InquiryPartInfoRequest $infoRequest = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['info_message:read', 'info_request:read'])]
    #[ApiProperty(readableLink: true)]
    private ?User $sender = null;

    #[ORM\Column(length: 20)]
    #[Groups(['info_message:read', 'info_message:write', 'info_request:read', 'info_request:write'])]
    #[Assert\Choice(choices: [self::SENDER_TYPE_ADMIN, self::SENDER_TYPE_CLIENT])]
    private string $senderType = self::SENDER_TYPE_ADMIN;

    #[ORM\Column(type: 'text')]
    #[Groups(['info_message:read', 'info_message:write', 'info_request:read', 'info_request:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 10000)]
    private ?string $messageText = null;

    #[ORM\Column(type: 'datetime')]
    #[Gedmo\Timestampable(on: 'create')]
    #[Groups(['info_message:read', 'info_request:read'])]
    private ?DateTimeInterface $createdAt = null;

    /**
     * @var Collection<int, MediaItem>
     */
    #[ORM\ManyToMany(targetEntity: MediaItem::class)]
    #[ORM\JoinTable(name: 'inquiry_part_info_message_media_item')]
    #[Groups(['info_message:read', 'info_message:write', 'info_request:read', 'info_request:write'])]
    #[ApiProperty(readableLink: true, writableLink: true)]
    private Collection $mediaItems;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->mediaItems = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getInfoRequest(): ?InquiryPartInfoRequest
    {
        return $this->infoRequest;
    }

    public function setInfoRequest(?InquiryPartInfoRequest $infoRequest): static
    {
        $this->infoRequest = $infoRequest;
        return $this;
    }

    public function getSender(): ?User
    {
        return $this->sender;
    }

    public function setSender(?User $sender): static
    {
        $this->sender = $sender;
        return $this;
    }

    public function getSenderType(): string
    {
        return $this->senderType;
    }

    public function setSenderType(string $senderType): static
    {
        $this->senderType = $senderType;
        return $this;
    }

    public function getMessageText(): ?string
    {
        return $this->messageText;
    }

    public function setMessageText(string $messageText): static
    {
        $this->messageText = $messageText;
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

    /**
     * Check if message is from admin
     */
    public function isFromAdmin(): bool
    {
        return $this->senderType === self::SENDER_TYPE_ADMIN;
    }

    /**
     * Check if message is from client
     */
    public function isFromClient(): bool
    {
        return $this->senderType === self::SENDER_TYPE_CLIENT;
    }

    /**
     * Check if message has attachments
     */
    public function hasAttachments(): bool
    {
        return !$this->mediaItems->isEmpty();
    }

    /**
     * Get attachment count
     */
    #[Groups(['info_message:read', 'info_request:read'])]
    public function getAttachmentCount(): int
    {
        return $this->mediaItems->count();
    }

    /**
     * Get valid sender types
     */
    public static function getValidSenderTypes(): array
    {
        return [
            self::SENDER_TYPE_ADMIN,
            self::SENDER_TYPE_CLIENT,
        ];
    }
}
