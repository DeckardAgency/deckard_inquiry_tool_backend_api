<?php

namespace App\MessageHandler;

use App\Entity\Inquiry;
use App\Message\InquiryCreatedMessage;
use App\Repository\InquiryRepository;
use App\Service\InquiryLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Psr\Log\LoggerInterface;
use Twig\Environment;

#[AsMessageHandler]
class InquiryCreatedMessageHandler
{
    public function __construct(
        private readonly InquiryRepository $inquiryRepository,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly Environment $twig,
        private readonly InquiryLogService $inquiryLogService,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $adminEmail = 'admin@inquiry.deckard.com',
        private readonly string $senderEmail = 'noreply@inquiry.deckard.com',
        private readonly string $adminAppUrl = 'https://admin.example.com',
        private readonly string $clientAppUrl = 'https://client.example.com'
    ) {
    }

    public function __invoke(InquiryCreatedMessage $message): void
    {
        $inquiryId = $message->getInquiryId();

        $this->logger->info('Handling InquiryCreatedMessage', [
            'inquiry_id' => $inquiryId->toRfc4122()
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

            $this->logger->info('Found inquiry', [
                'inquiry_number' => $inquiry->getInquiryNumber(),
                'inquiry_status' => $inquiry->getStatus(),
                'machines_count' => $inquiry->getMachines()->count()
            ]);

            // Log inquiry creation if it's submitted (not draft)
            if ($inquiry->getStatus() === Inquiry::STATUS_SUBMITTED) {
                $description = 'Inquiry created and submitted';

                $log = $this->inquiryLogService->logInquirySubmission(
                    $inquiry,
                    $description,
                    [
                        'operation' => 'create'
                    ]
                );

                if ($log !== null) {
                    $this->entityManager->flush();

                    $this->logger->info('Inquiry creation logged', [
                        'inquiry_id' => $inquiryId->toRfc4122(),
                        'log_id' => $log->getId()->toRfc4122()
                    ]);
                }
            }

            // Send email to admin
            $this->sendAdminNotification($inquiry);

            // Send email to customer if there's a valid user with email
            $this->sendCustomerNotification($inquiry);

        } catch (\Exception $e) {
            $this->logger->error('Error in InquiryCreatedMessageHandler', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function sendAdminNotification($inquiry): void
    {
        try {
            $templateParams = [
                'inquiry' => $inquiry,
                'machines' => $inquiry->getMachines(),
                'adminAppUrl' => $this->adminAppUrl,
                'viewUrl' => $this->adminAppUrl . '/manual-entry/' . $inquiry->getId()->toRfc4122() . '/view',
            ];

            // Render template
            $htmlContent = $this->twig->render('emails/admin/inquiry_created.html.twig', $templateParams);

            // Create email for admin
            $email = (new Email())
                ->from(new Address($this->senderEmail, 'Inquiry System'))
                ->to(new Address($this->adminEmail, 'Admin'))
                ->subject('New Inquiry Received: ' . $inquiry->getInquiryNumber())
                ->html($htmlContent);

            $this->logger->info('Sending admin email notification', [
                'from' => $this->senderEmail,
                'to' => $this->adminEmail,
                'subject' => 'New Inquiry Received: ' . $inquiry->getInquiryNumber()
            ]);

            // Send the email
            $this->mailer->send($email);

            $this->logger->info('Admin email notification sent successfully');
        } catch (\Exception $e) {
            $this->logger->error('Failed to send admin email notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function sendCustomerNotification($inquiry): void
    {
        try {
            $user = $inquiry->getUser();
            $contactEmail = $inquiry->getContactEmail() ?: ($user?->getEmail() ?? null);

            if (!$contactEmail) {
                $this->logger->warning('Cannot send customer notification: no contact email', [
                    'inquiry_id' => $inquiry->getId()->toRfc4122(),
                    'inquiry_number' => $inquiry->getInquiryNumber()
                ]);
                return;
            }

            $customerName = $user?->getFullName() ?? 'Customer';

            $templateParams = [
                'inquiry' => $inquiry,
                'user' => $user,
                'machines' => $inquiry->getMachines(),
                'clientAppUrl' => $this->clientAppUrl,
                'viewUrl' => $this->clientAppUrl . '/my-inquiries/active/inquiry/' . $inquiry->getId()->toRfc4122() . '/view',
            ];

            // Render template
            $htmlContent = $this->twig->render('emails/customer/inquiry_confirmation.html.twig', $templateParams);

            // Create email for customer
            $email = (new Email())
                ->from(new Address($this->senderEmail, 'Deckard Orders'))
                ->to(new Address($contactEmail, $customerName))
                ->subject('Your Deckard Inquiry #' . $inquiry->getInquiryNumber() . ' Confirmation')
                ->html($htmlContent);

            $this->logger->info('Sending customer email notification', [
                'from' => $this->senderEmail,
                'to' => $contactEmail,
                'subject' => 'Your Deckard Inquiry #' . $inquiry->getInquiryNumber() . ' Confirmation'
            ]);

            // Send the email
            $this->mailer->send($email);

            $this->logger->info('Customer email notification sent successfully');
        } catch (\Exception $e) {
            $this->logger->error('Failed to send customer email notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
