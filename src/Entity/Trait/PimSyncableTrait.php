<?php

declare(strict_types=1);

namespace App\Entity\Trait;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Trait for entities that can be synchronized from PIM.
 *
 * Provides all PIM tracking fields and methods.
 * Use with PimSyncableInterface for type safety.
 *
 * @see \App\Contract\PimSyncableInterface
 */
trait PimSyncableTrait
{
    /**
     * External PIM identifier (null if created locally in webshop).
     */
    #[ORM\Column(type: Types::STRING, length: 36, nullable: true, unique: true)]
    #[Groups(['pim:read'])]
    private ?string $pimId = null;

    /**
     * Timestamp of last successful sync from PIM.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['pim:read'])]
    private ?\DateTimeImmutable $pimSyncedAt = null;

    /**
     * Hash of last synced PIM data (for change detection).
     */
    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $pimDataHash = null;

    /**
     * Whether this entity was created/synced from PIM.
     * When true, master data fields are read-only in webshop.
     */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['pim:read'])]
    private bool $isFromPim = false;

    public function getPimId(): ?string
    {
        return $this->pimId;
    }

    public function setPimId(?string $pimId): static
    {
        $this->pimId = $pimId;
        return $this;
    }

    public function getPimSyncedAt(): ?\DateTimeImmutable
    {
        return $this->pimSyncedAt;
    }

    public function setPimSyncedAt(?\DateTimeImmutable $pimSyncedAt): static
    {
        $this->pimSyncedAt = $pimSyncedAt;
        return $this;
    }

    /**
     * Mark entity as synced from PIM (updates timestamp to now).
     */
    public function markAsSynced(): static
    {
        $this->pimSyncedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getPimDataHash(): ?string
    {
        return $this->pimDataHash;
    }

    public function setPimDataHash(?string $pimDataHash): static
    {
        $this->pimDataHash = $pimDataHash;
        return $this;
    }

    public function isFromPim(): bool
    {
        return $this->isFromPim;
    }

    public function setIsFromPim(bool $isFromPim): static
    {
        $this->isFromPim = $isFromPim;
        return $this;
    }

    /**
     * Check if this entity's master data is editable locally.
     * Returns false if entity is synced from PIM.
     */
    public function isMasterDataEditable(): bool
    {
        return !$this->isFromPim;
    }
}
