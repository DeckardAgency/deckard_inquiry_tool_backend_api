<?php

namespace App\MessageHandler;

use App\Message\InquiryPartInfoRequestMessage;
use App\Entity\InquiryPartInfoRequest;
use App\Repository\InquiryPartInfoRequestRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;

#[AsMessageHandler]
class InquiryPartInfoRequestMessageHandler
{
    public function __construct(
        private readonly InquiryPartInfoRequestRepository $infoRequestRepository,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
        private readonly string $adminEmail = 'admin@inquiry.deckard.com',
        private readonly string $senderEmail = 'noreply@inquiry.deckard.com',
        private readonly string $clientAppUrl = 'https://client.example.com',
        private readonly string $adminAppUrl = 'https://admin.example.com'
    ) {
    }

    public function __invoke(InquiryPartInfoRequestMessage $message): void
    {
        $infoRequestId = $message->getInfoRequestId();
        $action = $message->getAction();

        $this->logger->info('Handling InquiryPartInfoRequestMessage', [
            'info_request_id' => $infoRequestId,
            'action' => $action
        ]);

        try {
            $infoRequest = $this->infoRequestRepository->find(Uuid::fromString($infoRequestId));

            if (!$infoRequest) {
                $this->logger->error('Info request not found in database', [
                    'info_request_id' => $infoRequestId
                ]);
                return;
            }

            match ($action) {
                'created' => $this->handleInfoRequestCreated($infoRequest),
                'client_responded' => $this->handleClientResponded($infoRequest),
                'revision_requested' => $this->handleRevisionRequested($infoRequest),
                'status_changed' => $this->handleStatusChanged($infoRequest, $message->getNewStatus()),
                default => $this->logger->warning('Unknown action', ['action' => $action])
            };

        } catch (\Exception $e) {
            $this->logger->error('Error in InquiryPartInfoRequestMessageHandler', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Handle when admin creates a new info request - notify client
     */
    private function handleInfoRequestCreated(InquiryPartInfoRequest $infoRequest): void
    {
        $inquiry = $infoRequest->getInquiry();
        $part = $infoRequest->getInquiryMachinePart();

        if (!$inquiry) {
            $this->logger->warning('No inquiry associated with info request');
            return;
        }

        $user = $inquiry->getUser();
        $contactEmail = $inquiry->getContactEmail() ?: ($user?->getEmail() ?? null);

        if (!$contactEmail) {
            $this->logger->warning('Cannot send client notification: no contact email', [
                'inquiry_id' => $inquiry->getId()->toRfc4122()
            ]);
            return;
        }

        $customerName = $user?->getFullName() ?? 'Customer';

        // Get the latest message (the initial request message)
        $latestMessage = $infoRequest->getLatestMessage();

        $templateParams = [
            'inquiry' => $inquiry,
            'infoRequest' => $infoRequest,
            'part' => $part,
            'message' => $latestMessage,
            'user' => $user,
            'clientAppUrl' => $this->clientAppUrl,
            'viewUrl' => $this->clientAppUrl . '/my-inquiries/active/' . $inquiry->getId()->toRfc4122() . '/view'
        ];

        $this->sendEmail(
            to: $contactEmail,
            toName: $customerName,
            subject: 'Information Required - Inquiry #' . $inquiry->getInquiryNumber(),
            template: 'emails/customer/inquiry_part_info_request.html.twig',
            params: $templateParams
        );

        $this->logger->info('Info request notification sent to client', [
            'info_request_id' => $infoRequest->getId()->toRfc4122(),
            'client_email' => $contactEmail
        ]);
    }

    /**
     * Handle when client responds - notify admin
     */
    private function handleClientResponded(InquiryPartInfoRequest $infoRequest): void
    {
        $inquiry = $infoRequest->getInquiry();
        $part = $infoRequest->getInquiryMachinePart();

        if (!$inquiry) {
            $this->logger->warning('No inquiry associated with info request');
            return;
        }

        // Get the latest message (the client's response)
        $latestMessage = $infoRequest->getLatestMessage();

        $templateParams = [
            'inquiry' => $inquiry,
            'infoRequest' => $infoRequest,
            'part' => $part,
            'message' => $latestMessage,
            'adminAppUrl' => $this->adminAppUrl,
            'viewUrl' => $this->adminAppUrl . '/manual-entry/' . $inquiry->getId()->toRfc4122() . '/view'
        ];

        $this->sendEmail(
            to: $this->adminEmail,
            toName: 'Admin',
            subject: 'Client Responded - Inquiry #' . $inquiry->getInquiryNumber(),
            template: 'emails/admin/inquiry_part_info_response.html.twig',
            params: $templateParams
        );

        $this->logger->info('Client response notification sent to admin', [
            'info_request_id' => $infoRequest->getId()->toRfc4122()
        ]);
    }

    /**
     * Handle when admin requests revision - notify client
     */
    private function handleRevisionRequested(InquiryPartInfoRequest $infoRequest): void
    {
        $inquiry = $infoRequest->getInquiry();
        $part = $infoRequest->getInquiryMachinePart();

        if (!$inquiry) {
            $this->logger->warning('No inquiry associated with info request');
            return;
        }

        $user = $inquiry->getUser();
        $contactEmail = $inquiry->getContactEmail() ?: ($user?->getEmail() ?? null);

        if (!$contactEmail) {
            $this->logger->warning('Cannot send client notification: no contact email', [
                'inquiry_id' => $inquiry->getId()->toRfc4122()
            ]);
            return;
        }

        $customerName = $user?->getFullName() ?? 'Customer';

        // Get the latest message (admin's revision request)
        $latestMessage = $infoRequest->getLatestMessage();

        $templateParams = [
            'inquiry' => $inquiry,
            'infoRequest' => $infoRequest,
            'part' => $part,
            'message' => $latestMessage,
            'user' => $user,
            'clientAppUrl' => $this->clientAppUrl,
            'viewUrl' => $this->clientAppUrl . '/my-inquiries/active/' . $inquiry->getId()->toRfc4122() . '/view'
        ];

        $this->sendEmail(
            to: $contactEmail,
            toName: $customerName,
            subject: 'Additional Information Required - Inquiry #' . $inquiry->getInquiryNumber(),
            template: 'emails/customer/inquiry_part_info_revision.html.twig',
            params: $templateParams
        );

        $this->logger->info('Revision request notification sent to client', [
            'info_request_id' => $infoRequest->getId()->toRfc4122(),
            'client_email' => $contactEmail
        ]);
    }

    /**
     * Handle generic status changes
     */
    private function handleStatusChanged(InquiryPartInfoRequest $infoRequest, ?string $newStatus): void
    {
        if (!$newStatus) {
            return;
        }

        $inquiry = $infoRequest->getInquiry();

        // Only send notification when request is accepted
        if ($newStatus === InquiryPartInfoRequest::STATUS_ACCEPTED && $inquiry) {
            $user = $inquiry->getUser();
            $contactEmail = $inquiry->getContactEmail() ?: ($user?->getEmail() ?? null);

            if ($contactEmail) {
                $part = $infoRequest->getInquiryMachinePart();
                $customerName = $user?->getFullName() ?? 'Customer';

                $templateParams = [
                    'inquiry' => $inquiry,
                    'infoRequest' => $infoRequest,
                    'part' => $part,
                    'user' => $user,
                    'clientAppUrl' => $this->clientAppUrl,
                    'viewUrl' => $this->clientAppUrl . '/my-inquiries/active/' . $inquiry->getId()->toRfc4122() . '/view'
                ];

                $this->sendEmail(
                    to: $contactEmail,
                    toName: $customerName,
                    subject: 'Information Accepted - Inquiry #' . $inquiry->getInquiryNumber(),
                    template: 'emails/customer/inquiry_part_info_accepted.html.twig',
                    params: $templateParams
                );

                $this->logger->info('Info accepted notification sent to client', [
                    'info_request_id' => $infoRequest->getId()->toRfc4122()
                ]);
            }
        }
    }

    /**
     * Send email helper method
     */
    private function sendEmail(string $to, string $toName, string $subject, string $template, array $params): void
    {
        try {
            // Check if template exists
            if (!$this->twig->getLoader()->exists($template)) {
                $this->logger->warning('Email template not found', ['template' => $template]);
                return;
            }

            $htmlContent = $this->twig->render($template, $params);

            $email = (new Email())
                ->from(new Address($this->senderEmail, 'Deckard Inquiry System'))
                ->to(new Address($to, $toName))
                ->subject($subject)
                ->html($htmlContent);

            $this->mailer->send($email);

        } catch (\Exception $e) {
            $this->logger->error('Failed to send email', [
                'error' => $e->getMessage(),
                'template' => $template,
                'to' => $to
            ]);
        }
    }
}
