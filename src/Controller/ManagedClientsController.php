<?php

namespace App\Controller;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

class ManagedClientsController extends AbstractController
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/api/v1/agent/managed-clients', name: 'agent_managed_clients', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Authentication required.');
        }

        if (!in_array('ROLE_USER_CLIENT_AGENT', $user->getRoles(), true)) {
            throw new AccessDeniedHttpException('Only users with ROLE_USER_CLIENT_AGENT can access managed clients.');
        }

        $client = $user->getClient();

        if (!$client || !$client->getIsClientAgent()) {
            throw new AccessDeniedHttpException('Your company is not configured as a client agent.');
        }

        $managedClients = $client->getManagedClients()->toArray();

        $this->logger->info('Agent fetching managed clients', [
            'agentUserId' => $user->getId()->toRfc4122(),
            'agentClientId' => $client->getId()->toRfc4122(),
            'managedClientCount' => count($managedClients),
        ]);

        $json = $this->serializer->serialize($managedClients, 'jsonld', [
            'groups' => ['client:read'],
        ]);

        // Wrap in Hydra collection format
        $data = json_decode($json, true);
        $collection = [
            '@context' => '/api/v1/contexts/Client',
            '@id' => '/api/v1/agent/managed-clients',
            '@type' => 'Collection',
            'totalItems' => count($data),
            'member' => $data,
        ];

        return new JsonResponse($collection);
    }
}
