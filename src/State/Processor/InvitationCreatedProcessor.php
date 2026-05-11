<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\UserInvitation;
use App\Message\UserInvitedMessage;
use App\Repository\UserRepository;
use App\Repository\UserInvitationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

class InvitationCreatedProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly MessageBusInterface $messageBus,
        private readonly UserRepository $userRepository,
        private readonly UserInvitationRepository $userInvitationRepository,
        private readonly LoggerInterface $logger,
        private readonly int $invitationExpirationDays = 7
    ) {
    }

    /**
     * @param UserInvitation $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserInvitation
    {
        $this->logger->info('Processing invitation creation', [
            'email' => $data->getEmail()
        ]);

        // Validate email is not already registered
        $existingUser = $this->userRepository->findOneBy(['email' => $data->getEmail()]);
        if ($existingUser) {
            throw new BadRequestHttpException(sprintf(
                'User with email "%s" already exists',
                $data->getEmail()
            ));
        }

        // Validate no pending invitation exists for this email
        $existingInvitation = $this->userInvitationRepository->findPendingByEmail($data->getEmail());
        if ($existingInvitation) {
            throw new BadRequestHttpException(sprintf(
                'A pending invitation already exists for email "%s"',
                $data->getEmail()
            ));
        }

        // Validate client has available user slots
        $client = $data->getClient();
        if ($client && !$client->canAddActiveUser()) {
            $maxUsers = $client->getMaxActiveUsers();
            $activeUsers = $client->countActiveUsers();
            throw new BadRequestHttpException(sprintf(
                'Cannot send invitation. Client has reached the maximum number of active users (%d/%d). Please contact support to increase your user limit.',
                $activeUsers,
                $maxUsers
            ));
        }

        // Generate token if not already set
        if (!$data->getToken()) {
            $data->generateToken();
        }

        // Set expiration if not already set
        if (!$data->getExpiresAt()) {
            $data->setDefaultExpiration($this->invitationExpirationDays);
        }

        // Set created by current user
        $currentUser = $this->security->getUser();
        if ($currentUser) {
            $data->setCreatedBy($currentUser);
        }

        // Set default roles if not provided
        if (empty($data->getRoles())) {
            $data->setRoles(['ROLE_USER']);
        }

        // Persist the invitation
        $this->entityManager->persist($data);
        $this->entityManager->flush();

        $this->logger->info('Invitation created successfully', [
            'invitation_id' => $data->getId()->toRfc4122(),
            'email' => $data->getEmail(),
            'token' => $data->getToken()
        ]);

        // Dispatch message to send invitation email asynchronously
        try {
            $this->messageBus->dispatch(new UserInvitedMessage($data->getId()));

            $this->logger->info('UserInvitedMessage dispatched', [
                'invitation_id' => $data->getId()->toRfc4122()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to dispatch UserInvitedMessage', [
                'invitation_id' => $data->getId()->toRfc4122(),
                'error' => $e->getMessage()
            ]);
            // Don't fail the request if message dispatch fails
        }

        return $data;
    }
}
