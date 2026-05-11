<?php

namespace App\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\InvitationVerifyOutput;
use App\Repository\UserInvitationRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InvitationVerifyProvider implements ProviderInterface
{
    public function __construct(
        private readonly UserInvitationRepository $userInvitationRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function provide(?Operation $operation = null, array $uriVariables = [], array $context = []): InvitationVerifyOutput
    {
        $token = $uriVariables['token'] ?? null;

        if (!$token) {
            throw new NotFoundHttpException('Token is required');
        }

        $this->logger->info('Verifying invitation token', [
            'token' => substr($token, 0, 10) . '...' // Only log first 10 chars for security
        ]);

        // Find invitation by token
        $invitation = $this->userInvitationRepository->findByToken($token);

        if (!$invitation) {
            throw new NotFoundHttpException('Invalid invitation token');
        }

        $this->logger->info('Invitation found', [
            'invitation_id' => $invitation->getId()->toRfc4122(),
            'email' => $invitation->getEmail(),
            'status' => $invitation->getStatus(),
            'is_expired' => $invitation->isExpired()
        ]);

        // Return invitation data
        return new InvitationVerifyOutput(
            $invitation->getEmail(),
            $invitation->getFirstName(),
            $invitation->getLastName(),
            $invitation->getExpiresAt(),
            $invitation->isExpired()
        );
    }
}
