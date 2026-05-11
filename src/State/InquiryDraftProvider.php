<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\InquiryRepository;
use Symfony\Bundle\SecurityBundle\Security;

class InquiryDraftProvider implements ProviderInterface
{
    public function __construct(
        private InquiryRepository $inquiryRepository,
        private Security $security
    ) {
    }

    /**
     * Provides a collection of draft inquiries for the current user.
     *
     * @param Operation $operation
     * @param array $uriVariables
     * @param array $context
     * @return object|array|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // Get the current authenticated user
        $user = $this->security->getUser();

        if (!$user) {
            // Return empty array if no user is authenticated
            return [];
        }

        // Return all draft inquiries for the current user
        return $this->inquiryRepository->findDraftsByUser($user);
    }
}
