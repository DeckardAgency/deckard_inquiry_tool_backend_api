<?php

namespace App\Dto;

use App\Entity\Product;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * Data transfer object for client-specific product information
 */
class ClientProduct
{
    #[Groups(['client_product:read'])]
    public Uuid $id;

    #[Groups(['client_product:read'])]
    public string $name;

    #[Groups(['client_product:read'])]
    public string $slug;

    #[Groups(['client_product:read'])]
    public ?string $partNo = null;

    #[Groups(['client_product:read'])]
    public ?string $shortDescription = null;

    #[Groups(['client_product:read'])]
    public ?string $unit = null;

    #[Groups(['client_product:read'])]
    public float $regularPrice;

    #[Groups(['client_product:read'])]
    public float $clientPrice;

    #[Groups(['client_product:read'])]
    public ?float $discountPercentage = null;

    #[Groups(['client_product:read'])]
    public ?string $technicalDescription = null;

    #[Groups(['client_product:read'])]
    public mixed $featuredImage = null;

    /**
     * Create a ClientProduct DTO from a Product entity and custom client price
     */
    public static function fromProduct(
        Product $product,
        ?float $clientPrice = null,
        ?float $discountPercentage = null
    ): self {
        $dto = new self();
        $dto->id = $product->getId();
        $dto->name = $product->getName();
        $dto->slug = $product->getSlug();
        $dto->partNo = $product->getPartNo();
        $dto->shortDescription = $product->getShortDescription();
        $dto->unit = $product->getUnit();
        $dto->regularPrice = $product->getPrice();
        $dto->clientPrice = $clientPrice ?? $product->getPrice();
        $dto->discountPercentage = $discountPercentage;
        $dto->technicalDescription = $product->getTechnicalDescription();
        $dto->featuredImage = $product->getFeaturedImage();

        return $dto;
    }
}
