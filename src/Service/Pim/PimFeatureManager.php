<?php

declare(strict_types=1);

namespace App\Service\Pim;

/**
 * PIM Feature Manager
 *
 * Central service for checking PIM integration status.
 * Controls whether product data is editable locally or synced from PIM.
 *
 * Usage:
 *   - $pimManager->isPimEnabled() - Check if PIM integration is active
 *   - $pimManager->canEditProducts() - Check if products are locally editable
 *   - $pimManager->getPimApiUrl() - Get PIM API endpoint
 */
class PimFeatureManager
{
    public function __construct(
        private readonly array $pimConfig,
    ) {
    }

    /**
     * Check if PIM integration is enabled.
     */
    public function isPimEnabled(): bool
    {
        return $this->pimConfig['enabled'] ?? false;
    }

    /**
     * Check if products can be created/edited locally.
     * Returns false when PIM is enabled (products are synced from PIM).
     */
    public function canEditProducts(): bool
    {
        return !$this->isPimEnabled();
    }

    /**
     * Check if categories can be created/edited locally.
     */
    public function canEditCategories(): bool
    {
        return !$this->isPimEnabled();
    }

    /**
     * Check if brands can be created/edited locally.
     */
    public function canEditBrands(): bool
    {
        return !$this->isPimEnabled();
    }

    /**
     * Check if attributes can be created/edited locally.
     */
    public function canEditAttributes(): bool
    {
        return !$this->isPimEnabled();
    }

    /**
     * Get PIM API URL.
     */
    public function getPimApiUrl(): ?string
    {
        return $this->pimConfig['api']['url'] ?? null;
    }

    /**
     * Get PIM API key.
     */
    public function getPimApiKey(): ?string
    {
        return $this->pimConfig['api']['key'] ?? null;
    }

    /**
     * Get PIM API timeout in seconds.
     */
    public function getPimApiTimeout(): int
    {
        return $this->pimConfig['api']['timeout'] ?? 30;
    }

    public function getPimApiVerifySsl(): bool
    {
        return $this->pimConfig['api']['verify_ssl'] ?? true;
    }

    /**
     * Get the PIM channel code to sync from.
     */
    public function getPimChannel(): string
    {
        return $this->pimConfig['channel'] ?? 'default';
    }

    /**
     * Get sync batch size.
     */
    public function getSyncBatchSize(): int
    {
        return $this->pimConfig['sync']['batch_size'] ?? 100;
    }

    /**
     * Get list of entities to sync from PIM.
     */
    public function getSyncEntities(): array
    {
        return $this->pimConfig['sync']['entities'] ?? [];
    }

    /**
     * Check if a specific entity type should be synced from PIM.
     */
    public function shouldSyncEntity(string $entity): bool
    {
        if (!$this->isPimEnabled()) {
            return false;
        }

        $entities = $this->getSyncEntities();
        return $entities[$entity] ?? false;
    }

    /**
     * Get webhook secret for verification.
     */
    public function getWebhookSecret(): ?string
    {
        return $this->pimConfig['webhook']['secret'] ?? null;
    }

    /**
     * Check if webhooks are enabled.
     */
    public function isWebhookEnabled(): bool
    {
        if (!$this->isPimEnabled()) {
            return false;
        }

        return ($this->pimConfig['webhook']['enabled'] ?? false)
            && !empty($this->getWebhookSecret());
    }

    /**
     * Get list of entities that are always managed locally (never synced from PIM).
     */
    public function getLocalOnlyEntities(): array
    {
        return $this->pimConfig['local_only'] ?? [
            'prices',
            'price_lists',
            'tier_prices',
            'stock',
            'inventory_sources',
            'tax_classes',
            'promotions',
            'orders',
        ];
    }

    /**
     * Get PIM admin URL for linking.
     */
    public function getPimAdminUrl(): ?string
    {
        $apiUrl = $this->getPimApiUrl();
        if (!$apiUrl) {
            return null;
        }

        // Convert API URL to admin URL (strip /api/v1)
        return preg_replace('#/api/v\d+$#', '', $apiUrl);
    }

    /**
     * Get full configuration array.
     */
    public function getConfig(): array
    {
        return $this->pimConfig;
    }
}
