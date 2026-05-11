<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Inquiry;
use App\Repository\InquiryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Processor for managing draft inquiries (saving and submitting)
 */
class InquiryDraftProcessor implements ProcessorInterface
{
    private InquiryRepository $inquiryRepository;
    private Security $security;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private WorkflowInterface $inquiryStateMachine;

    public function __construct(
        EntityManagerInterface $entityManager,
        Security               $security,
        WorkflowInterface      $inquiryStateMachine,
        LoggerInterface        $logger = null
    )
    {
        $this->inquiryRepository = $entityManager->getRepository(Inquiry::class);
        $this->security = $security;
        $this->entityManager = $entityManager;
        $this->inquiryStateMachine = $inquiryStateMachine;
        $this->logger = $logger;
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Inquiry
    {
        $user = $this->security->getUser();

        if (!$user) {
            throw new AccessDeniedHttpException('You must be logged in to manage draft inquiries');
        }

        // Extract inquiry ID from URI variables
        $inquiryId = $uriVariables['id'] ?? null;

        if (!$inquiryId) {
            throw new NotFoundHttpException('Inquiry ID is required');
        }

        // Find the inquiry
        $inquiry = $this->inquiryRepository->find($inquiryId);

        if (!$inquiry) {
            throw new NotFoundHttpException('Inquiry not found');
        }

        // ROLE_ADMIN bypasses all ownership checks
        $isAdmin = in_array('ROLE_ADMIN', $user->getRoles(), true);
        if (!$isAdmin) {
            // Check access: owner OR same-client ROLE_CLIENT_ADMIN
            $inquiryUser = $inquiry->getUser();
            $isOwner = $inquiryUser && $inquiryUser->getId()->equals($user->getId());
            $isClientAdmin = in_array('ROLE_CLIENT_ADMIN', $user->getRoles(), true);
            $isSameClient = $user->getClient() && $inquiryUser && $inquiryUser->getClient()
                && $user->getClient()->getId()->equals($inquiryUser->getClient()->getId());

            if (!$isOwner && !($isClientAdmin && $isSameClient)) {
                throw new AccessDeniedHttpException('You do not have permission to modify this inquiry');
            }
        }

        $isClientAdmin = in_array('ROLE_CLIENT_ADMIN', $user->getRoles(), true) || $isAdmin;

        // Determine the operation (save-draft, submit, or approve)
        $path = $operation->getUriTemplate();

        if (str_contains($path, '/save-draft')) {
            // Save as draft
            $inquiry->saveDraft();
            $this->entityManager->flush();

            if ($this->logger) {
                $this->logger->info('Inquiry saved as draft', [
                    'inquiry_id' => $inquiry->getId()->toRfc4122(),
                    'inquiry_number' => $inquiry->getInquiryNumber()
                ]);
            }
        } elseif (str_contains($path, '/approve')) {
            // Approve a pending inquiry (ROLE_CLIENT_ADMIN or ROLE_ADMIN only)
            if (!$isClientAdmin) {
                throw new AccessDeniedHttpException('Only client administrators can approve inquiries');
            }

            if ($inquiry->getStatus() !== Inquiry::STATUS_PENDING_APPROVAL) {
                throw new AccessDeniedHttpException('Only inquiries pending approval can be approved');
            }

            // Apply workflow transition — this fires InquiryWorkflowSubscriber
            // which handles logging and email notifications
            $this->inquiryStateMachine->apply($inquiry, 'approve');
            $this->entityManager->flush();

            if ($this->logger) {
                $this->logger->info('Inquiry approved', [
                    'inquiry_id' => $inquiry->getId()->toRfc4122(),
                    'inquiry_number' => $inquiry->getInquiryNumber(),
                    'approved_by' => $user->getEmail()
                ]);
            }
        } elseif (str_contains($path, '/submit')) {
            // Check if the inquiry can be submitted before processing
            if (!$inquiry->canBeSubmitted()) {
                throw new \InvalidArgumentException(
                    'Inquiry cannot be submitted. ' . implode(', ', $inquiry->getSubmissionErrors())
                );
            }

            // For agent inquiries, check the target client's approval settings
            $approvalClient = $inquiry->getOnBehalfOfClient() ?? $user->getClient();
            $requiresApproval = $approvalClient
                && $approvalClient->getRequiresInquiryApproval()
                && !in_array('ROLE_CLIENT_ADMIN', $user->getRoles(), true)
                && !in_array('ROLE_ADMIN', $user->getRoles(), true);

            // Apply the appropriate workflow transition
            // This fires InquiryWorkflowSubscriber which handles logging and notifications
            $transition = $requiresApproval ? 'submit_for_approval' : 'submit';
            $this->inquiryStateMachine->apply($inquiry, $transition);

            // Mark as no longer a draft (status is already changed by workflow,
            // so setIsDraft won't override it)
            $inquiry->setIsDraft(false);
            $this->entityManager->flush();

            if ($this->logger) {
                $this->logger->info('Draft inquiry submitted', [
                    'inquiry_id' => $inquiry->getId()->toRfc4122(),
                    'inquiry_number' => $inquiry->getInquiryNumber(),
                    'new_status' => $inquiry->getStatus()
                ]);
            }
        }

        return $inquiry;
    }
}
