<?php

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Inquiry;
use App\Entity\InquiryMachinePart;
use App\Entity\InquiryPartInfoMessage;
use App\Entity\InquiryOffer;
use App\Entity\InquiryPartInfoRequest;
use App\Entity\User;
use App\Message\InquiryPartInfoRequestMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

#[AsDecorator('api_platform.doctrine.orm.state.persist_processor', priority: 15)]
class InquiryPartInfoRequestProcessor implements ProcessorInterface, ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    public function __construct(
        private ProcessorInterface $decorated,
        private MessageBusInterface $messageBus,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private WorkflowInterface $inquiryStateMachine,
        private Security $security
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        // Handle InquiryOffer creation - set createdBy
        if ($data instanceof InquiryOffer && $operation->getMethod() === 'POST' && !$data->getCreatedBy()) {
            $authenticatedUser = $this->security->getUser();
            if ($authenticatedUser instanceof User) {
                $data->setCreatedBy($authenticatedUser);
            }
        }

        // Handle InquiryPartInfoRequest creation
        if ($data instanceof InquiryPartInfoRequest && $operation->getMethod() === 'POST') {
            $this->handleInfoRequestCreation($data);
        }

        // Handle InquiryPartInfoRequest status update
        if ($data instanceof InquiryPartInfoRequest &&
            ($operation->getMethod() === 'PUT' || $operation->getMethod() === 'PATCH')) {
            $this->handleInfoRequestUpdate($data);
        }

        // Handle InquiryPartInfoMessage creation
        if ($data instanceof InquiryPartInfoMessage && $operation->getMethod() === 'POST') {
            $this->handleMessageCreation($data);
        }

        // Call the decorated processor to handle the actual persistence
        return $this->decorated->process($data, $operation, $uriVariables, $context);
    }

    /**
     * Handle creation of a new info request
     */
    private function handleInfoRequestCreation(InquiryPartInfoRequest $infoRequest): void
    {
        $authenticatedUser = $this->security->getUser();

        if (!$authenticatedUser instanceof User) {
            throw new BadRequestHttpException('You must be authenticated to create an info request.');
        }

        // Set the creator
        $infoRequest->setCreatedBy($authenticatedUser);

        // Validate that the part exists and get the inquiry
        $part = $infoRequest->getInquiryMachinePart();
        if (!$part) {
            throw new BadRequestHttpException('Inquiry machine part is required.');
        }

        // Set inquiry from the part's machine
        $machine = $part->getInquiryMachine();
        if ($machine && $machine->getInquiry()) {
            $infoRequest->setInquiry($machine->getInquiry());
        } else {
            throw new BadRequestHttpException('Could not determine inquiry for this part.');
        }

        // Handle nested messages - set sender for each
        foreach ($infoRequest->getMessages() as $message) {
            $message->setSender($authenticatedUser);
            $message->setInfoRequest($infoRequest);

            // Set default sender type based on user role if not specified
            if (!$message->getSenderType()) {
                $message->setSenderType(InquiryPartInfoMessage::SENDER_TYPE_ADMIN);
            }
        }

        // Update part info status to pending
        $part->setInfoStatus(InquiryMachinePart::INFO_STATUS_PENDING_INFO);

        $inquiry = $infoRequest->getInquiry();

        // If inquiry is not already in more_info status, transition it
        if ($inquiry && $inquiry->getStatus() !== Inquiry::STATUS_MORE_INFO) {
            $this->tryTransitionToMoreInfo($inquiry);
        }

        $this->logger->info('Info request created', [
            'info_request_id' => $infoRequest->getId()->toRfc4122(),
            'inquiry_id' => $inquiry?->getId()->toRfc4122(),
            'part_id' => $part->getId()->toRfc4122(),
            'created_by' => $authenticatedUser->getEmail(),
            'message_count' => $infoRequest->getMessages()->count()
        ]);

        // Dispatch message for email notification
        if ($inquiry) {
            $this->messageBus->dispatch(new InquiryPartInfoRequestMessage(
                $infoRequest->getId()->toRfc4122(),
                'created'
            ));
        }
    }

    /**
     * Handle info request status update
     */
    private function handleInfoRequestUpdate(InquiryPartInfoRequest $infoRequest): void
    {
        $originalData = $this->entityManager->getUnitOfWork()->getOriginalEntityData($infoRequest);

        if (!$originalData) {
            return;
        }

        $oldStatus = $originalData['status'] ?? null;
        $newStatus = $infoRequest->getStatus();

        if ($oldStatus !== $newStatus) {
            $this->handleStatusChange($infoRequest, $oldStatus, $newStatus);
        }
    }

    /**
     * Handle status change of an info request
     */
    private function handleStatusChange(InquiryPartInfoRequest $infoRequest, string $oldStatus, string $newStatus): void
    {
        $part = $infoRequest->getInquiryMachinePart();
        $inquiry = $infoRequest->getInquiry();

        $this->logger->info('Info request status changed', [
            'info_request_id' => $infoRequest->getId()->toRfc4122(),
            'old_status' => $oldStatus,
            'new_status' => $newStatus
        ]);

        // Update part info status based on request status
        if ($part) {
            switch ($newStatus) {
                case InquiryPartInfoRequest::STATUS_ACCEPTED:
                    $part->setInfoStatus(InquiryMachinePart::INFO_STATUS_CLEAR);
                    break;
                case InquiryPartInfoRequest::STATUS_NEEDS_REVISION:
                    $part->setInfoStatus(InquiryMachinePart::INFO_STATUS_PENDING_INFO);
                    break;
                case InquiryPartInfoRequest::STATUS_RESPONDED:
                    $part->setInfoStatus(InquiryMachinePart::INFO_STATUS_INFO_PROVIDED);
                    break;
            }
        }

        // Check if all info requests are accepted, then transition inquiry to information_provided
        if ($inquiry && $newStatus === InquiryPartInfoRequest::STATUS_ACCEPTED) {
            $this->checkAndTransitionInquiry($inquiry);
        }

        // Dispatch notification for status change
        $this->messageBus->dispatch(new InquiryPartInfoRequestMessage(
            $infoRequest->getId()->toRfc4122(),
            'status_changed',
            $newStatus
        ));
    }

    /**
     * Handle creation of a new message in an info request thread
     */
    private function handleMessageCreation(InquiryPartInfoMessage $message): void
    {
        $authenticatedUser = $this->security->getUser();

        if (!$authenticatedUser instanceof User) {
            throw new BadRequestHttpException('You must be authenticated to send a message.');
        }

        // Set the sender
        $message->setSender($authenticatedUser);

        // Determine sender type based on user roles
        $roles = $authenticatedUser->getRoles();
        if (in_array('ROLE_ADMIN', $roles) || in_array('ROLE_SUPER_ADMIN', $roles)) {
            $message->setSenderType(InquiryPartInfoMessage::SENDER_TYPE_ADMIN);
        } else {
            $message->setSenderType(InquiryPartInfoMessage::SENDER_TYPE_CLIENT);
        }

        $infoRequest = $message->getInfoRequest();

        // If client is responding, update the info request status
        if ($message->getSenderType() === InquiryPartInfoMessage::SENDER_TYPE_CLIENT && $infoRequest) {
            $infoRequest->markAsResponded();

            // Update part status
            $part = $infoRequest->getInquiryMachinePart();
            if ($part) {
                $part->setInfoStatus(InquiryMachinePart::INFO_STATUS_INFO_PROVIDED);
            }

            // Check if all pending requests are now responded
            $inquiry = $infoRequest->getInquiry();
            if ($inquiry) {
                $this->checkAndTransitionToInformationProvided($inquiry);
            }

            $this->logger->info('Client responded to info request', [
                'info_request_id' => $infoRequest->getId()->toRfc4122(),
                'user' => $authenticatedUser->getEmail()
            ]);

            // Dispatch notification
            $this->messageBus->dispatch(new InquiryPartInfoRequestMessage(
                $infoRequest->getId()->toRfc4122(),
                'client_responded'
            ));
        }

        // If admin is sending a follow-up (needs revision), update status
        if ($message->getSenderType() === InquiryPartInfoMessage::SENDER_TYPE_ADMIN && $infoRequest) {
            // Check if this is a follow-up request (status was responded)
            if ($infoRequest->getStatus() === InquiryPartInfoRequest::STATUS_RESPONDED) {
                $infoRequest->markAsNeedsRevision();

                $part = $infoRequest->getInquiryMachinePart();
                if ($part) {
                    $part->setInfoStatus(InquiryMachinePart::INFO_STATUS_PENDING_INFO);
                }

                // Transition inquiry back to more_info if needed
                $inquiry = $infoRequest->getInquiry();
                if ($inquiry && $inquiry->getStatus() === Inquiry::STATUS_INFORMATION_PROVIDED) {
                    $this->tryTransitionToMoreInfo($inquiry);
                }

                // Dispatch notification
                $this->messageBus->dispatch(new InquiryPartInfoRequestMessage(
                    $infoRequest->getId()->toRfc4122(),
                    'revision_requested'
                ));
            }
        }
    }

    /**
     * Try to transition inquiry to more_info status
     */
    private function tryTransitionToMoreInfo(Inquiry $inquiry): void
    {
        $validStatuses = [
            Inquiry::STATUS_SUBMITTED,
            Inquiry::STATUS_IN_REVIEW,
            Inquiry::STATUS_INFORMATION_PROVIDED
        ];

        if (in_array($inquiry->getStatus(), $validStatuses)) {
            if ($this->inquiryStateMachine->can($inquiry, 'request_more_info')) {
                $this->inquiryStateMachine->apply($inquiry, 'request_more_info');

                $this->logger->info('Inquiry transitioned to more_info', [
                    'inquiry_id' => $inquiry->getId()->toRfc4122()
                ]);
            }
        }
    }

    /**
     * Check if all pending info requests are responded and transition inquiry
     */
    private function checkAndTransitionToInformationProvided(Inquiry $inquiry): void
    {
        // Check if all info requests are responded or accepted
        $hasPending = false;
        foreach ($inquiry->getPartInfoRequests() as $request) {
            if ($request->isPending()) {
                $hasPending = true;
                break;
            }
        }

        // If no pending requests and inquiry is in more_info status, transition
        if (!$hasPending && $inquiry->getStatus() === Inquiry::STATUS_MORE_INFO) {
            if ($this->inquiryStateMachine->can($inquiry, 'provide_information')) {
                $this->inquiryStateMachine->apply($inquiry, 'provide_information');

                $this->logger->info('Inquiry transitioned to information_provided', [
                    'inquiry_id' => $inquiry->getId()->toRfc4122()
                ]);
            }
        }
    }

    /**
     * Check if all info requests are accepted and update inquiry accordingly
     */
    private function checkAndTransitionInquiry(Inquiry $inquiry): void
    {
        // If all info requests are accepted, the admin can proceed to next status
        // This doesn't auto-transition but logs that all info is now cleared
        if ($inquiry->allInfoRequestsAccepted()) {
            $this->logger->info('All info requests accepted for inquiry', [
                'inquiry_id' => $inquiry->getId()->toRfc4122()
            ]);
        }
    }
}
