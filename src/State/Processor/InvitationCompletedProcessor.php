<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\CompleteInvitationInput;
use App\Dto\InvitationCompletedOutput;
use App\Entity\User;
use App\Repository\UserInvitationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class InvitationCompletedProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly UserInvitationRepository $userInvitationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param CompleteInvitationInput $data
     */
    public function process(mixed $data, ?Operation $operation = null, array $uriVariables = [], array $context = []): InvitationCompletedOutput
    {
        $token = $uriVariables['token'] ?? null;

        if (!$token) {
            throw new BadRequestHttpException('Token is required');
        }

        $this->logger->info('Processing invitation completion', [
            'token' => substr($token, 0, 10) . '...' // Only log first 10 chars for security
        ]);

        // Find invitation by token
        $invitation = $this->userInvitationRepository->findByToken($token);

        if (!$invitation) {
            throw new NotFoundHttpException('Invalid invitation token');
        }

        // Check if invitation can be completed
        if ($invitation->isExpired()) {
            throw new BadRequestHttpException('This invitation has expired');
        }

        if ($invitation->isCompleted()) {
            throw new BadRequestHttpException('This invitation has already been used');
        }

        if ($invitation->isRevoked()) {
            throw new BadRequestHttpException('This invitation has been revoked');
        }

        if (!$invitation->canBeCompleted()) {
            throw new BadRequestHttpException('This invitation cannot be completed');
        }

        // Validate client has available user slots
        $client = $invitation->getClient();
        if ($client && !$client->canAddActiveUser()) {
            $maxUsers = $client->getMaxActiveUsers();
            $activeUsers = $client->countActiveUsers();
            throw new BadRequestHttpException(sprintf(
                'Cannot complete invitation. The company has reached the maximum number of active users (%d/%d). Please contact your administrator.',
                $activeUsers,
                $maxUsers
            ));
        }

        $this->logger->info('Creating user from invitation', [
            'email' => $invitation->getEmail(),
            'invitation_id' => $invitation->getId()->toRfc4122()
        ]);

        // Create new user
        $user = new User();
        $user->setEmail($invitation->getEmail());
        $user->setFirstName($invitation->getFirstName());
        $user->setLastName($invitation->getLastName());

        // Hash and set password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $data->password);
        $user->setPassword($hashedPassword);

        // Set client if provided in invitation
        if ($invitation->getClient()) {
            $user->setClient($invitation->getClient());
        }

        // Set roles from invitation
        $roles = $invitation->getRoles();
        if (!empty($roles)) {
            $user->setRoles($roles);
        }

        // Set user as active
        $user->setIsActive(true);

        // Persist user
        $this->entityManager->persist($user);

        // Mark invitation as completed
        $invitation->markAsCompleted();

        // Flush all changes
        $this->entityManager->flush();

        $this->logger->info('User created successfully from invitation', [
            'user_id' => $user->getId()->toRfc4122(),
            'email' => $user->getEmail(),
            'invitation_id' => $invitation->getId()->toRfc4122()
        ]);

        return new InvitationCompletedOutput(
            'Account created successfully. You can now login.',
            true
        );
    }
}
