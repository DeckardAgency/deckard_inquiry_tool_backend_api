<?php

namespace App\MessageHandler;

use App\Message\InquiryStatusChangedMessage;
use App\Entity\Inquiry;
use App\Repository\InquiryRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Twig\Environment;
use Psr\Log\LoggerInterface;

#[AsMessageHandler]
class InquiryStatusChangedMessageHandler
{
    public function __construct(
        private readonly InquiryRepository $inquiryRepository,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
        private readonly string $adminEmail = 'admin@inquiry.deckard.com',
        private readonly string $senderEmail = 'noreply@inquiry.deckard.com',
        private readonly string $adminAppUrl = 'https://admin.example.com',
        private readonly string $clientAppUrl = 'https://client.example.com'
    ) {
    }

    public function __invoke(InquiryStatusChangedMessage $message): void
    {
        $inquiryId = $message->getInquiryId();
        $newStatus = $message->getNewStatus();

        $this->logger->info('Handling InquiryStatusChangedMessage', [
            'inquiry_id' => $inquiryId->toRfc4122(),
            'new_status' => $newStatus
        ]);

        try {
            $inquiry = $this->inquiryRepository->find($inquiryId);

            if (!$inquiry) {
                $this->logger->error('Inquiry not found in database', [
                    'inquiry_id' => $inquiryId->toRfc4122()
                ]);
                return;
            }

            // Skip processing for draft inquiries
            if ($inquiry->isDraft()) {
                $this->logger->info('Skipping notification for draft inquiry', [
                    'inquiry_id' => $inquiryId->toRfc4122(),
                    'inquiry_number' => $inquiry->getInquiryNumber()
                ]);
                return;
            }

            // Send admin notification
            $this->sendAdminNotification($inquiry, $newStatus);

            // Send customer notification
            $this->sendCustomerNotification($inquiry, $newStatus);

        } catch (\Exception $e) {
            $this->logger->error('Error in InquiryStatusChangedMessageHandler', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function sendAdminNotification(Inquiry $inquiry, string $newStatus): void
    {
        try {
            // Determine which template to use based on the new status
            $templateName = match ($newStatus) {
                Inquiry::STATUS_IN_REVIEW => 'emails/admin/inquiry_in_review.html.twig',
                Inquiry::STATUS_MORE_INFO => 'emails/admin/inquiry_more_info.html.twig',
                Inquiry::STATUS_INFORMATION_PROVIDED => 'emails/admin/inquiry_information_provided.html.twig',
                Inquiry::STATUS_IN_PROGRESS => 'emails/admin/inquiry_in_progress.html.twig',
                Inquiry::STATUS_COMPLETED => 'emails/admin/inquiry_completed.html.twig',
                Inquiry::STATUS_CANCELED => 'emails/admin/inquiry_canceled.html.twig',
                default => 'emails/admin/inquiry_status_changed.html.twig',
            };

            // Get appropriate subject based on status
            $subject = match ($newStatus) {
                Inquiry::STATUS_IN_REVIEW => 'Inquiry #' . $inquiry->getInquiryNumber() . ' is now in review',
                Inquiry::STATUS_MORE_INFO => 'Inquiry #' . $inquiry->getInquiryNumber() . ' requires more information',
                Inquiry::STATUS_INFORMATION_PROVIDED => 'Inquiry #' . $inquiry->getInquiryNumber() . ' - information has been provided',
                Inquiry::STATUS_IN_PROGRESS => 'Inquiry #' . $inquiry->getInquiryNumber() . ' is now in progress',
                Inquiry::STATUS_COMPLETED => 'Inquiry #' . $inquiry->getInquiryNumber() . ' has been completed',
                Inquiry::STATUS_CANCELED => 'Inquiry #' . $inquiry->getInquiryNumber() . ' has been canceled',
                default => 'Inquiry #' . $inquiry->getInquiryNumber() . ' status changed to ' . $newStatus,
            };

            $this->logger->info('Preparing to send admin email notification', [
                'template' => $templateName,
                'subject' => $subject
            ]);

            // Check if template exists
            if (!$this->twig->getLoader()->exists($templateName)) {
                $this->logger->warning('Template not found, falling back to generic template', [
                    'missing_template' => $templateName
                ]);
                $templateName = 'emails/admin/inquiry_status_changed.html.twig';

                // Check if the fallback template exists
                if (!$this->twig->getLoader()->exists($templateName)) {
                    throw new \Exception('Email template not found: ' . $templateName);
                }
            }

            $templateParams = [
                'inquiry' => $inquiry,
                'status' => $newStatus,
                'machines' => $inquiry->getMachines(),
                'adminAppUrl' => $this->adminAppUrl,
                'viewUrl' => $this->adminAppUrl . '/manual-entry/' . $inquiry->getId()->toRfc4122() . '/view',
            ];

            // Render email content using Twig
            $htmlContent = $this->twig->render($templateName, $templateParams);

            // Create and send the email
            $email = (new Email())
                ->from(new Address($this->senderEmail, 'Inquiry System'))
                ->to(new Address($this->adminEmail, 'Admin'))
                ->subject($subject)
                ->html($htmlContent);

            $this->mailer->send($email);

            $this->logger->info('Admin email notification sent', [
                'status' => $newStatus,
                'inquiry_number' => $inquiry->getInquiryNumber()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to send admin email notification', [
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'inquiry_id' => $inquiry->getId()->toRfc4122()
            ]);
        }
    }

    private function sendCustomerNotification(Inquiry $inquiry, string $newStatus): void
    {
        try {
            $user = $inquiry->getUser();
            $contactEmail = $inquiry->getContactEmail() ?: ($user?->getEmail() ?? null);

            if (!$contactEmail) {
                $this->logger->warning('Cannot send customer status notification: no contact email', [
                    'inquiry_id' => $inquiry->getId()->toRfc4122(),
                    'inquiry_number' => $inquiry->getInquiryNumber()
                ]);
                return;
            }

            $customerName = $user?->getFullName() ?? 'Customer';

            // Get the appropriate subject based on status
            $subject = match ($newStatus) {
                Inquiry::STATUS_IN_REVIEW => 'Update on Deckard Inquiry #' . $inquiry->getInquiryNumber() . ': Currently Under Review',
                Inquiry::STATUS_MORE_INFO => 'Action Required: More information for Deckard Inquiry #' . $inquiry->getInquiryNumber(),
                Inquiry::STATUS_INFORMATION_PROVIDED => 'Update on Deckard Inquiry #' . $inquiry->getInquiryNumber() . ': Information Received',
                Inquiry::STATUS_IN_PROGRESS => 'Update on Deckard Inquiry #' . $inquiry->getInquiryNumber() . ': In Progress',
                Inquiry::STATUS_COMPLETED => 'Your Deckard Inquiry #' . $inquiry->getInquiryNumber() . ' is Now Complete',
                Inquiry::STATUS_CANCELED => 'Cancellation Confirmation for Deckard Inquiry #' . $inquiry->getInquiryNumber(),
                default => 'Update on Deckard Inquiry #' . $inquiry->getInquiryNumber(),
            };

            $templateParams = [
                'inquiry' => $inquiry,
                'user' => $user,
                'status' => $newStatus,
                'machines' => $inquiry->getMachines(),
                'clientAppUrl' => $this->clientAppUrl,
                'viewUrl' => $this->clientAppUrl . '/my-inquiries/active/inquiry/' . $inquiry->getId()->toRfc4122() . '/view',
            ];

            // Determine which template to use based on status
            $templateName = match ($newStatus) {
                Inquiry::STATUS_IN_REVIEW => 'emails/customer/inquiry_in_review.html.twig',
                Inquiry::STATUS_MORE_INFO => 'emails/customer/inquiry_more_info.html.twig',
                Inquiry::STATUS_INFORMATION_PROVIDED => 'emails/customer/inquiry_information_provided.html.twig',
                Inquiry::STATUS_IN_PROGRESS => 'emails/customer/inquiry_in_progress.html.twig',
                Inquiry::STATUS_COMPLETED => 'emails/customer/inquiry_completed.html.twig',
                Inquiry::STATUS_CANCELED => 'emails/customer/inquiry_canceled.html.twig',
                default => 'emails/customer/inquiry_status_changed.html.twig',
            };

            // Check if template exists, fallback to generic template if not
            if (!$this->twig->getLoader()->exists($templateName)) {
                $this->logger->warning('Customer template not found, using generic template', [
                    'missing_template' => $templateName,
                    'status' => $newStatus
                ]);
                $templateName = 'emails/customer/inquiry_status_changed.html.twig';
            }

            // Render email content using Twig
            $htmlContent = $this->twig->render($templateName, $templateParams);

            // Create and send the email
            $email = (new Email())
                ->from(new Address($this->senderEmail, 'Deckard Orders'))
                ->to(new Address($contactEmail, $customerName))
                ->subject($subject)
                ->html($htmlContent);

            $this->mailer->send($email);

            $this->logger->info('Customer status notification email sent', [
                'customer_email' => $contactEmail,
                'status' => $newStatus,
                'inquiry_number' => $inquiry->getInquiryNumber()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to send customer status notification', [
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'inquiry_id' => $inquiry->getId()->toRfc4122()
            ]);
        }
    }
}
