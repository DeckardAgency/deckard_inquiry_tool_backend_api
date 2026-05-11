<?php

namespace App\Controller;

use App\Repository\ClientProductPriceRepository;
use App\Repository\ClientRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/api/v1')]
class ClientProductController extends AbstractController
{
    public function __construct(
        private ClientRepository $clientRepository,
        private ProductRepository $productRepository,
        private ClientProductPriceRepository $clientProductPriceRepository,
        private SerializerInterface $serializer,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/client/{clientId}/products/debug-relations', name: 'api_client_products_debug_relations', methods: ['GET'])]
    public function debugRelations(string $clientId): JsonResponse
    {
        // Only allow debug in development environment
        if ($this->getParameter('kernel.environment') === 'prod') {
            return $this->json(['error' => 'Debug endpoint not available in production'], Response::HTTP_FORBIDDEN);
        }

        try {
            $client = $this->findClientOrThrow($clientId);
            $debugInfo = $this->generateDebugInfo($client);

            return $this->json($debugInfo);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/client/{clientId}/products', name: 'api_client_products', methods: ['GET'])]
    public function getClientProducts(string $clientId, Request $request): JsonResponse
    {
        try {
            $client = $this->findClientOrThrow($clientId);
            $filters = $this->extractFilters($request);

            $allClientPrices = $this->clientProductPriceRepository->findBy(['client' => $client]);
            $filteredClientPrices = $this->applyFilters($allClientPrices, $filters);

            $formattedProducts = $this->formatProductsResponse($filteredClientPrices);

            $response = $this->buildResponse($clientId, $formattedProducts, $allClientPrices, $filteredClientPrices, $filters);

            return $this->json($response);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/client/products', name: 'api_current_client_products', methods: ['GET'])]
    public function getCurrentClientProducts(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user || !method_exists($user, 'getClient') || !$user->getClient()) {
            return $this->json(['error' => 'User not associated with any client'], Response::HTTP_FORBIDDEN);
        }

        return $this->getClientProducts($user->getClient()->getId(), $request);
    }

    #[Route('/client/{clientId}/products/{productId}/price', name: 'api_set_client_product_price', methods: ['POST'])]
    public function setClientProductPrice(string $clientId, string $productId, Request $request): JsonResponse
    {
        try {
            $this->checkPermissions();

            $client = $this->findClientOrThrow($clientId);
            $product = $this->findProductOrThrow($productId);

            $data = $this->validatePriceData($request);

            $clientProductPrice = $this->createOrUpdateClientProductPrice($client, $product, $data);

            $responseData = $this->formatClientProductPriceResponse($clientProductPrice);
            $statusCode = $clientProductPrice->getId() ? Response::HTTP_OK : Response::HTTP_CREATED;

            return $this->json($responseData, $statusCode);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function findClientOrThrow(string $clientId): object
    {
        $client = $this->clientRepository->find(Uuid::fromString($clientId));

        if (!$client) {
            throw new \RuntimeException('Client not found');
        }

        return $client;
    }

    private function findProductOrThrow(string $productId): object
    {
        $product = $this->productRepository->find(Uuid::fromString($productId));

        if (!$product) {
            throw new \RuntimeException('Product not found');
        }

        return $product;
    }

    private function extractFilters(Request $request): array
    {
        // Get all query parameters to handle arrays properly
        $queryParams = $request->query->all();

        // Handle machines.articleDescription (both single and array formats)
        $machineFilter = null;
        if (isset($queryParams['machines.articleDescription'])) {
            $machineFilter = $queryParams['machines.articleDescription'];
        } elseif (isset($queryParams['machines_articleDescription'])) {
            $machineFilter = $queryParams['machines_articleDescription'];
        }

        // Handle both single values and arrays
        if (is_array($machineFilter)) {
            $machineArticleDescriptions = array_filter($machineFilter); // Remove empty values
        } elseif ($machineFilter) {
            $machineArticleDescriptions = [$machineFilter];
        } else {
            $machineArticleDescriptions = [];
        }

        return [
            'machineArticleDescriptions' => $machineArticleDescriptions, // Changed to plural
            'productName' => $request->query->get('product.name')
                ?: $request->query->get('product_name'),
            'productPartNo' => $request->query->get('product.partNo')
                ?: $request->query->get('product_partNo'),
        ];
    }


    private function applyFilters(array $clientPrices, array $filters): array
    {
        $filtered = [];

        foreach ($clientPrices as $clientPrice) {
            $product = $clientPrice->getProduct();

            if (!$product || !$this->productMatchesFilters($product, $filters)) {
                continue;
            }

            $filtered[] = $clientPrice;
        }

        return $filtered;
    }

    private function productMatchesFilters(object $product, array $filters): bool
    {
        // Apply product name filter
        if ($filters['productName'] && stripos($product->getName(), $filters['productName']) === false) {
            return false;
        }

        // Apply product part number filter
        if ($filters['productPartNo'] && stripos($product->getPartNo(), $filters['productPartNo']) === false) {
            return false;
        }

        // Apply machine article description filter (now supports multiple values)
        if (!empty($filters['machineArticleDescriptions'])) {
            $hasMatchingMachine = false;
            foreach ($product->getMachines() as $machine) {
                if ($machine->getArticleDescription()) {
                    // Check if machine matches ANY of the filter values
                    foreach ($filters['machineArticleDescriptions'] as $filterValue) {
                        if (stripos($machine->getArticleDescription(), $filterValue) !== false) {
                            $hasMatchingMachine = true;
                            break 2; // Break out of both loops
                        }
                    }
                }
            }
            if (!$hasMatchingMachine) {
                return false;
            }
        }

        return true;
    }

    private function formatProductsResponse(array $clientPrices): array
    {
        $formattedProducts = [];

        foreach ($clientPrices as $clientProductPrice) {
            $product = $clientProductPrice->getProduct();

            $formattedProducts[] = [
                '@id' => '/api/v1/products/' . $product->getId(),
                '@type' => 'Product',
                'id' => $product->getId(),
                'name' => $product->getName(),
                'slug' => $product->getSlug(),
                'partNo' => $product->getPartNo(),
                'shortDescription' => $product->getShortDescription(),
                'technicalDescription' => $product->getTechnicalDescription(),
                'statistic' => $product->getStatistic(),
                'machineText' => $product->getMachineText(),
                'unit' => $product->getUnit(),
                'weight' => $product->getWeight(),
                'regularPrice' => $product->getPrice(),
                'clientPrice' => $clientProductPrice->getPrice(),
                'discountPercentage' => $clientProductPrice->getDiscountPercentage(),
                'effectivePrice' => $clientProductPrice->getEffectivePrice(),
                'isValid' => $clientProductPrice->isValid(),
                'validFrom' => $clientProductPrice->getValidFrom()?->format('Y-m-d\TH:i:sP'),
                'validUntil' => $clientProductPrice->getValidUntil()?->format('Y-m-d\TH:i:sP'),
                'featuredImage' => $this->formatFeaturedImage($product),
                'imageGallery' => $this->formatImageGallery($product),
                'machines' => $this->formatMachines($product),
            ];
        }

        return $formattedProducts;
    }

    private function formatFeaturedImage(object $product): ?array
    {
        if (!$product->getFeaturedImage()) {
            return null;
        }

        $image = $product->getFeaturedImage();
        return [
            '@id' => '/api/v1/media_items/' . $image->getId(),
            '@type' => 'MediaItem',
            'id' => $image->getId(),
            'filename' => $image->getFilename(),
            'mimeType' => $image->getMimeType(),
            'filePath' => $image->getFilePath(),
        ];
    }

    private function formatImageGallery(object $product): array
    {
        if (!method_exists($product, 'getImageGallery') || !$product->getImageGallery()) {
            return [];
        }

        $gallery = [];
        foreach ($product->getImageGallery() as $image) {
            $gallery[] = [
                '@id' => '/api/v1/media_items/' . $image->getId(),
                '@type' => 'MediaItem',
                'id' => $image->getId(),
                'filename' => $image->getFilename(),
                'mimeType' => $image->getMimeType(),
                'filePath' => $image->getFilePath(),
            ];
        }

        return $gallery;
    }

    private function formatMachines(object $product): array
    {
        $machines = [];
        foreach ($product->getMachines() as $machine) {
            $machines[] = [
                '@id' => '/api/v1/machines/' . $machine->getId(),
                '@type' => 'Machine',
                'id' => $machine->getId(),
                'ibStationNumber' => $machine->getIbStationNumber(),
                'ibSerialNumber' => $machine->getIbSerialNumber(),
                'articleNumber' => $machine->getArticleNumber(),
                'articleDescription' => $machine->getArticleDescription(),
            ];
        }

        return $machines;
    }

    private function buildResponse(string $clientId, array $formattedProducts, array $allClientPrices, array $filteredClientPrices, array $filters): array
    {
        $response = [
            '@context' => '/api/v1/contexts/ClientProduct',
            '@id' => '/api/v1/client/' . $clientId . '/products',
            '@type' => 'hydra:Collection',
            'hydra:totalItems' => count($formattedProducts),
            'hydra:member' => $formattedProducts,
        ];

        // Add debug info only in development
        if ($this->getParameter('kernel.environment') !== 'prod') {
            $response['debug'] = [
                'total_client_prices_before_filter' => count($allClientPrices),
                'total_after_filter' => count($filteredClientPrices),
                'applied_filters' => $filters,
                'filter_params_detected' => [
                    'machines.articleDescription' => !empty($filters['machineArticleDescriptions']), // Updated
                    'product.name' => !empty($filters['productName']),
                    'product.partNo' => !empty($filters['productPartNo'])
                ]
            ];
        }

        // Add search template if filters are available
        if (array_filter($filters)) {
            $response['hydra:search'] = $this->buildSearchTemplate($clientId);
        }

        return $response;
    }
    private function buildSearchTemplate(string $clientId): array
    {
        return [
            '@type' => 'hydra:IriTemplate',
            'hydra:template' => '/api/v1/client/' . $clientId . '/products{?machines.articleDescription[],product.name,product.partNo}',
            'hydra:variableRepresentation' => 'BasicRepresentation',
            'hydra:mapping' => [
                [
                    '@type' => 'IriTemplateMapping',
                    'variable' => 'machines.articleDescription[]',
                    'property' => 'machines.articleDescription',
                    'required' => false
                ],
                [
                    '@type' => 'IriTemplateMapping',
                    'variable' => 'product.name',
                    'property' => 'product.name',
                    'required' => false
                ],
                [
                    '@type' => 'IriTemplateMapping',
                    'variable' => 'product.partNo',
                    'property' => 'product.partNo',
                    'required' => false
                ]
            ]
        ];
    }

    private function checkPermissions(): void
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_CLIENT_MANAGER')) {
            throw new \RuntimeException('Insufficient permissions');
        }
    }

    private function validatePriceData(Request $request): array
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['price']) || !is_numeric($data['price']) || $data['price'] <= 0) {
            throw new \RuntimeException('Valid price is required');
        }

        return $data;
    }

    private function createOrUpdateClientProductPrice(object $client, object $product, array $data): object
    {
        $existingPrice = $this->clientProductPriceRepository->findOneBy([
            'client' => $client,
            'product' => $product
        ]);

        if ($existingPrice) {
            $this->updateClientProductPrice($existingPrice, $data);
            return $existingPrice;
        }

        return $this->createNewClientProductPrice($client, $product, $data);
    }

    private function updateClientProductPrice(object $clientProductPrice, array $data): void
    {
        $clientProductPrice->setPrice($data['price']);

        if (isset($data['discountPercentage'])) {
            $clientProductPrice->setDiscountPercentage($data['discountPercentage']);
        }

        if (isset($data['validFrom'])) {
            $clientProductPrice->setValidFrom(new \DateTime($data['validFrom']));
        }

        if (isset($data['validUntil'])) {
            $clientProductPrice->setValidUntil(new \DateTime($data['validUntil']));
        }

        $this->entityManager->flush();
    }

    private function createNewClientProductPrice(object $client, object $product, array $data): object
    {
        $clientProductPrice = new \App\Entity\ClientProductPrice();
        $clientProductPrice->setClient($client);
        $clientProductPrice->setProduct($product);
        $clientProductPrice->setPrice($data['price']);

        if (isset($data['discountPercentage'])) {
            $clientProductPrice->setDiscountPercentage($data['discountPercentage']);
        }

        if (isset($data['validFrom'])) {
            $clientProductPrice->setValidFrom(new \DateTime($data['validFrom']));
        }

        if (isset($data['validUntil'])) {
            $clientProductPrice->setValidUntil(new \DateTime($data['validUntil']));
        }

        $this->entityManager->persist($clientProductPrice);
        $this->entityManager->flush();

        return $clientProductPrice;
    }

    private function formatClientProductPriceResponse(object $clientProductPrice): array
    {
        return [
            '@context' => '/api/v1/contexts/ClientProductPrice',
            '@id' => '/api/v1/client_product_prices/' . $clientProductPrice->getId(),
            '@type' => 'ClientProductPrice',
            'id' => $clientProductPrice->getId(),
            'client' => '/api/v1/clients/' . $clientProductPrice->getClient()->getId(),
            'product' => '/api/v1/products/' . $clientProductPrice->getProduct()->getId(),
            'price' => $clientProductPrice->getPrice(),
            'discountPercentage' => $clientProductPrice->getDiscountPercentage(),
            'validFrom' => $clientProductPrice->getValidFrom()?->format('Y-m-d\TH:i:sP'),
            'validUntil' => $clientProductPrice->getValidUntil()?->format('Y-m-d\TH:i:sP')
        ];
    }

    private function generateDebugInfo(object $client): array
    {
        $clientPrices = $this->clientProductPriceRepository->findBy(['client' => $client], [], 3);
        $em = $this->entityManager;

        $debugInfo = [
            'client_prices_sample' => [],
            'mapping_debug' => []
        ];

        foreach ($clientPrices as $price) {
            $product = $price->getProduct();
            $debugInfo['client_prices_sample'][] = [
                'price_id' => $price->getId(),
                'product_is_null' => $product === null,
                'product_id' => $product?->getId(),
                'product_name' => $product?->getName(),
            ];
        }

        // Add various DQL tests...
        $this->addDqlTests($debugInfo, $em, $client);

        return $debugInfo;
    }

    private function addDqlTests(array &$debugInfo, $em, object $client): void
    {
        // Test 1: Simple DQL without explicit JOIN
        try {
            $dql1 = "SELECT cpp FROM App\Entity\ClientProductPrice cpp WHERE cpp.client = :client";
            $query1 = $em->createQuery($dql1);
            $query1->setParameter('client', $client);
            $query1->setMaxResults(3);
            $results1 = $query1->getResult();

            $debugInfo['simple_dql_query'] = [
                'success' => true,
                'count' => count($results1),
                'can_access_product' => $results1 && $results1[0]->getProduct() !== null
            ];
        } catch (\Exception $e) {
            $debugInfo['simple_dql_query'] = [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }

        // Add other DQL tests as needed...
    }
}
