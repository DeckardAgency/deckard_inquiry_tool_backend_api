<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\ClientProduct;
use App\Entity\Client;
use App\Repository\ClientProductPriceRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Provider for client-specific product listings
 */
class ClientProductProvider implements ProviderInterface
{
    private ProductRepository $productRepository;
    private ClientProductPriceRepository $clientProductPriceRepository;
    private Security $security;

    public function __construct(
        ProductRepository $productRepository,
        ClientProductPriceRepository $clientProductPriceRepository,
        Security $security
    ) {
        $this->productRepository = $productRepository;
        $this->clientProductPriceRepository = $clientProductPriceRepository;
        $this->security = $security;
    }

    /**
     * @inheritDoc
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // Get the current user
        $user = $this->security->getUser();
        if (!$user || !method_exists($user, 'getClient') || !$user->getClient()) {
            return [];
        }

        /** @var Client $client */
        $client = $user->getClient();

        // Get all products with their client prices
        $productsWithPrices = $this->productRepository->findProductsWithClientPrices($client);

        // Create DTOs for the client products
        $clientProducts = [];
        foreach ($productsWithPrices as $item) {
            $product = $item['product'];
            $clientPrice = $item['clientPrice'] ?? null;
            $discountPercentage = $item['discountPercentage'] ?? null;

            $clientProducts[] = ClientProduct::fromProduct(
                $product,
                $clientPrice,
                $discountPercentage
            );
        }

        return $clientProducts;
    }
}
