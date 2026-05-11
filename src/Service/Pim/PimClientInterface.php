<?php

declare(strict_types=1);

namespace App\Service\Pim;

/**
 * Interface for PIM API client.
 *
 * Defines methods for communicating with the PIM API.
 */
interface PimClientInterface
{
    /**
     * Check if PIM client is enabled and configured.
     */
    public function isEnabled(): bool;

    /**
     * Get products from PIM.
     *
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function getProducts(array $filters = [], int $page = 1, int $limit = 100): array;

    /**
     * Get a single product by SKU.
     *
     * @return array<string, mixed>|null
     */
    public function getProduct(string $sku): ?array;

    /**
     * Get products modified since a given date.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getProductsModifiedSince(\DateTimeInterface $since): array;

    /**
     * Get categories from PIM.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCategories(): array;

    /**
     * Get brands from PIM.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBrands(): array;

    /**
     * Get attributes from PIM.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAttributes(): array;

    /**
     * Get total product count from PIM.
     */
    public function getProductCount(): int;
}
