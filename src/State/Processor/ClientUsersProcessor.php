<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Client;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Processor to handle updating users when a Client is updated.
 * This handles the inverse side of the Client-User OneToMany relationship.
 *
 * @implements ProcessorInterface<Client, Client|void>
 */
final class ClientUsersProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ProcessorInterface $processor,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly RequestStack $requestStack
    ) {
    }

    /**
     * @param Client $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Client
    {
        // Get the request content to check for users array
        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            $content = json_decode($request->getContent(), true);

            if (isset($content['users']) && is_array($content['users'])) {
                $this->updateClientUsers($data, $content['users']);
            }
        }

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }

    /**
     * Update the users assigned to this client
     *
     * @param Client $client
     * @param array $userIris Array of user IRIs (e.g., ["/api/v1/users/uuid"])
     */
    private function updateClientUsers(Client $client, array $userIris): void
    {
        // Extract user IDs from IRIs
        $newUserIds = [];
        foreach ($userIris as $iri) {
            // Extract UUID from IRI like "/api/v1/users/uuid"
            if (preg_match('/\/api\/v1\/users\/([a-f0-9-]+)$/i', $iri, $matches)) {
                $newUserIds[] = $matches[1];
            }
        }

        // Get current users assigned to this client
        $currentUsers = $client->getUsers()->toArray();
        $currentUserIds = array_map(fn(User $user) => $user->getId()->toRfc4122(), $currentUsers);

        // Find users to remove (in current but not in new)
        $usersToRemove = array_diff($currentUserIds, $newUserIds);
        foreach ($usersToRemove as $userId) {
            $user = $this->userRepository->find($userId);
            if ($user && $user->getClient() === $client) {
                $user->setClient(null);
                $this->entityManager->persist($user);
            }
        }

        // Find users to add (in new but not in current)
        $usersToAdd = array_diff($newUserIds, $currentUserIds);
        foreach ($usersToAdd as $userId) {
            $user = $this->userRepository->find($userId);
            if ($user) {
                $user->setClient($client);
                $this->entityManager->persist($user);
            }
        }
    }
}
