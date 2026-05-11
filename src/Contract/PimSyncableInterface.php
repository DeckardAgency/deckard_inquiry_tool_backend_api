<?php

declare(strict_types=1);

namespace App\Contract;

/**
 * Interface for entities that can be synchronized from PIM.
 *
 * Entities implementing this interface can have their data
 * sourced from the PIM system instead of being managed locally.
 */
interface PimSyncableInterface
{
    /**
     * Get the external PIM identifier.
     */
    public function getPimId(): ?string;

    /**
     * Set the external PIM identifier.
     */
    public function setPimId(?string $pimId): static;

    /**
     * Get the timestamp of last successful sync from PIM.
     */
    public function getPimSyncedAt(): ?\DateTimeImmutable;

    /**
     * Set the timestamp of last successful sync from PIM.
     */
    public function setPimSyncedAt(?\DateTimeImmutable $syncedAt): static;

    /**
     * Mark entity as synced (updates sync timestamp to now).
     */
    public function markAsSynced(): static;

    /**
     * Get the hash of last synced PIM data.
     */
    public function getPimDataHash(): ?string;

    /**
     * Set the hash of last synced PIM data.
     */
    public function setPimDataHash(?string $hash): static;

    /**
     * Check if this entity was created/synced from PIM.
     */
    public function isFromPim(): bool;

    /**
     * Set whether this entity is from PIM.
     */
    public function setIsFromPim(bool $isFromPim): static;

    /**
     * Check if this entity's master data is editable locally.
     * Returns false if entity is synced from PIM.
     */
    public function isMasterDataEditable(): bool;
}
