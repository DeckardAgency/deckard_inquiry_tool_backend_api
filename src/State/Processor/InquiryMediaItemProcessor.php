<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Inquiry;
use App\Entity\MediaItem;
use App\Entity\User;
use App\Repository\MediaItemRepository;
use App\Security\Voter\AgentOrderVoter;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Processor to handle MediaItem associations in Inquiry
 * This ensures that mediaItem IRIs are properly resolved to entities
 */
class InquiryMediaItemProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private MediaItemRepository $mediaItemRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private Security $security
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        // Only process Inquiry entities
        if (!$data instanceof Inquiry) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        // Handle onBehalfOfClient: validate for agents, clear for non-agents
        if ($data->getOnBehalfOfClient() !== null) {
            $authenticatedUser = $this->security->getUser();
            if ($authenticatedUser instanceof User) {
                if (in_array('ROLE_USER_CLIENT_AGENT', $authenticatedUser->getRoles(), true)) {
                    AgentOrderVoter::validateAgentAuthorization($authenticatedUser, $data->getOnBehalfOfClient());
                } elseif (!in_array('ROLE_ADMIN', $authenticatedUser->getRoles(), true)) {
                    // Non-agent, non-admin user — silently clear onBehalfOfClient
                    $data->setOnBehalfOfClient(null);
                }
            }
        }

        // Intercept direct POST submissions when approval is required
        // (mirrors the same logic in OrderPriceProcessor for orders)
        if ($operation->getMethod() === 'POST'
            && $data->getStatus() === Inquiry::STATUS_SUBMITTED
        ) {
            $inquiryUser = $data->getUser();
            // For agent inquiries, check the target client's approval settings
            $approvalClient = $data->getOnBehalfOfClient() ?? $inquiryUser?->getClient();
            if ($inquiryUser instanceof User
                && $approvalClient
                && $approvalClient->getRequiresInquiryApproval()
                && !in_array('ROLE_CLIENT_ADMIN', $inquiryUser->getRoles(), true)
                && !in_array('ROLE_ADMIN', $inquiryUser->getRoles(), true)
            ) {
                $data->setStatus(Inquiry::STATUS_PENDING_APPROVAL);
                $data->setIsDraft(false);
                $this->logger->info('Inquiry set to pending_approval due to client requiresInquiryApproval', [
                    'inquiry_id' => $data->getId()?->toRfc4122(),
                    'user' => $inquiryUser->getEmail(),
                    'client' => $inquiryUser->getClient()->getCode()
                ]);
            }
        }

        $this->logger->info('Processing Inquiry for mediaItems', [
            'inquiry_id' => $data->getId()?->toRfc4122(),
            'machines_count' => $data->getMachines()->count()
        ]);

        // Process each machine and its parts to ensure mediaItems are properly associated
        foreach ($data->getMachines() as $machine) {
            $this->logger->info('Processing machine', [
                'machine_id' => $machine->getId()?->toRfc4122(),
                'products_count' => $machine->getProducts()->count()
            ]);

            foreach ($machine->getProducts() as $part) {
                $mediaItems = $part->getMediaItems();

                $this->logger->info('Processing part', [
                    'part_id' => $part->getId()?->toRfc4122(),
                    'part_name' => $part->getPartName(),
                    'media_items_count' => $mediaItems->count()
                ]);

                // If mediaItems collection is empty but we expect them to be there,
                // it might mean they were sent as IRIs but not properly denormalized
                // This shouldn't happen with proper API Platform config, but we log it
                if ($mediaItems->count() === 0) {
                    $this->logger->warning('Part has no mediaItems', [
                        'part_id' => $part->getId()?->toRfc4122(),
                        'part_name' => $part->getPartName()
                    ]);
                }

                // Ensure all mediaItems are managed entities
                $mediaItemsToAdd = [];
                foreach ($mediaItems as $mediaItem) {
                    if ($mediaItem instanceof MediaItem) {
                        // Check if mediaItem is managed
                        if (!$this->entityManager->contains($mediaItem)) {
                            // Fetch the mediaItem from database
                            $managedMediaItem = $this->mediaItemRepository->find($mediaItem->getId());
                            if ($managedMediaItem) {
                                $mediaItemsToAdd[] = $managedMediaItem;
                                $this->logger->info('Reattached mediaItem', [
                                    'media_item_id' => $managedMediaItem->getId()->toRfc4122()
                                ]);
                            }
                        } else {
                            $mediaItemsToAdd[] = $mediaItem;
                        }
                    }
                }

                // Clear and re-add mediaItems to ensure they're properly managed
                if (!empty($mediaItemsToAdd)) {
                    foreach ($mediaItemsToAdd as $mediaItem) {
                        // MediaItem should already be in collection, but ensure it's managed
                        if (!$this->entityManager->contains($mediaItem)) {
                            $this->entityManager->persist($mediaItem);
                        }
                    }
                }
            }
        }

        // Now persist the inquiry with all properly managed mediaItems
        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
