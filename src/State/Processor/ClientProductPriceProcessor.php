<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ClientProductPrice;
use App\Repository\ClientProductPriceRepository;
use App\Repository\ClientRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Processor for client product price updates
 */
class ClientProductPriceProcessor implements ProcessorInterface
{
    private ClientProductPriceRepository $clientProductPriceRepository;
    private ClientRepository $clientRepository;
    private ProductRepository $productRepository;
    private Security $security;

    public function __construct(
        ClientProductPriceRepository $clientProductPriceRepository,
        ClientRepository $clientRepository,
        ProductRepository $productRepository,
        Security $security
    ) {
        $this->clientProductPriceRepository = $clientProductPriceRepository;
        $this->clientRepository = $clientRepository;
        $this->productRepository = $productRepository;
        $this->security = $security;
    }

    /**
     * @inheritDoc
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        // For delete operations
        if ($operation->getName() === 'delete') {
            // Check if user has admin rights or manages this client
            $this->checkAccess($data->getClient());

            $this->clientProductPriceRepository->remove($data, true);
            return;
        }

        // For create and update operations
        if ($data instanceof ClientProductPrice) {
            // Check client and product existence
            $client = $data->getClient();
            $product = $data->getProduct();

            if (!$client || !$product) {
                throw new NotFoundHttpException('Client or Product not found');
            }

            // Check access
            $this->checkAccess($client);

            // Check if there's an existing price for this client/product pair
            if ($operation->getName() === 'post') {
                $existingPrice = $this->clientProductPriceRepository->findCustomPrice($client, $product);

                if ($existingPrice) {
                    // Update existing price instead of creating a new one
                    $existingPrice->setPrice($data->getPrice());
                    $existingPrice->setDiscountPercentage($data->getDiscountPercentage());
                    $existingPrice->setValidFrom($data->getValidFrom());
                    $existingPrice->setValidUntil($data->getValidUntil());

                    $this->clientProductPriceRepository->save($existingPrice, true);
                    return;
                }
            }

            // Save the data
            $this->clientProductPriceRepository->save($data, true);
        }
    }

    /**
     * Check if current user has access to modify this client's data
     */
    private function checkAccess($client): void
    {
        $user = $this->security->getUser();

        // Allow if user is an admin
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        // Allow if user belongs to this client
        if ($user && method_exists($user, 'getClient') && $user->getClient() && $user->getClient()->getId() === $client->getId()) {
            // Additionally check for client management rights
            if ($this->security->isGranted('ROLE_CLIENT_MANAGER')) {
                return;
            }
        }

        throw new AccessDeniedException('You do not have permission to manage prices for this client.');
    }
}
