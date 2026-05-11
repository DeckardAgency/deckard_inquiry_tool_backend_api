<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Dto\ClientProduct;
use App\State\ClientProductProvider;
use ApiPlatform\OpenApi\Model\Operation;

#[ApiResource(
    shortName: 'ClientProduct',
    operations: [
        new GetCollection(
            uriTemplate: '/client/products',
            openapi: new Operation(
                summary: 'Retrieves the collection of products with client-specific prices',
                description: 'Returns all products with prices specific to the client of the authenticated user.'
            ),
            normalizationContext: ['groups' => ['client_product:read']],
            provider: ClientProductProvider::class
        )
    ]
)]
class ClientProductResource
{
    // This is an API resource class that uses DTOs (ClientProduct)
    // No properties or methods needed here as it's just a configuration class
}
