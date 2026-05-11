<?php

declare(strict_types=1);

namespace App\Service\Pim;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

/**
 * PIM API Client
 *
 * Communicates with the Deckard PIM REST API.
 */
class PimApiClient implements PimClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly PimFeatureManager $featureManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->featureManager->isPimEnabled()
            && $this->featureManager->getPimApiUrl() !== null
            && $this->featureManager->getPimApiKey() !== null;
    }

    public function getProducts(array $filters = [], int $page = 1, int $limit = 100): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        try {
            $response = $this->request('GET', '/products', [
                'query' => [
                    'channel' => $this->featureManager->getPimChannel(),
                    'page' => $page,
                    'itemsPerPage' => $limit,
                    ...$filters,
                ],
            ]);

            return $response['hydra:member'] ?? $response['data'] ?? [];
        } catch (ExceptionInterface $e) {
            $this->logger->error('Failed to fetch products from PIM', [
                'error' => $e->getMessage(),
                'filters' => $filters,
            ]);
            return [];
        }
    }

    public function getProduct(string $sku): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        try {
            $response = $this->request('GET', "/products/{$sku}", [
                'query' => [
                    'channel' => $this->featureManager->getPimChannel(),
                ],
            ]);

            return $response['data'] ?? $response;
        } catch (ExceptionInterface $e) {
            $this->logger->warning('Failed to fetch product from PIM', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function getProductsModifiedSince(\DateTimeInterface $since): array
    {
        return $this->getProducts([
            'updated_after' => $since->format('c'),
        ]);
    }

    public function getCategories(): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        try {
            $response = $this->request('GET', '/categories', [
                'query' => [
                    'channel' => $this->featureManager->getPimChannel(),
                ],
            ]);

            return $response['hydra:member'] ?? $response['data'] ?? [];
        } catch (ExceptionInterface $e) {
            $this->logger->error('Failed to fetch categories from PIM', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function getBrands(): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        try {
            $response = $this->request('GET', '/brands');
            return $response['hydra:member'] ?? $response['data'] ?? [];
        } catch (ExceptionInterface $e) {
            $this->logger->error('Failed to fetch brands from PIM', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function getAttributes(): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        try {
            $response = $this->request('GET', '/attributes');
            return $response['hydra:member'] ?? $response['data'] ?? [];
        } catch (ExceptionInterface $e) {
            $this->logger->error('Failed to fetch attributes from PIM', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function getProductCount(): int
    {
        if (!$this->isEnabled()) {
            return 0;
        }

        try {
            $response = $this->request('GET', '/products', [
                'query' => [
                    'channel' => $this->featureManager->getPimChannel(),
                    'itemsPerPage' => 1,
                ],
            ]);

            return $response['hydra:totalItems'] ?? $response['meta']['total'] ?? 0;
        } catch (ExceptionInterface $e) {
            $this->logger->error('Failed to get product count from PIM', [
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Make HTTP request to PIM API.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     * @throws ExceptionInterface
     */
    private function request(string $method, string $endpoint, array $options = []): array
    {
        $url = rtrim($this->featureManager->getPimApiUrl(), '/') . $endpoint;

        $response = $this->httpClient->request($method, $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->featureManager->getPimApiKey(),
                'Accept' => 'application/ld+json, application/json',
                'Content-Type' => 'application/json',
            ],
            'timeout' => $this->featureManager->getPimApiTimeout(),
            'verify_peer' => $this->featureManager->getPimApiVerifySsl(),
            'verify_host' => $this->featureManager->getPimApiVerifySsl(),
            ...$options,
        ]);

        return $response->toArray();
    }
}
