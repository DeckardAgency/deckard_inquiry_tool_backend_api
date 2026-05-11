<?php

namespace App\MessageHandler;

use App\Message\InquiryOfferSentMessage;
use App\Repository\InquiryOfferRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;

#[AsMessageHandler]
class InquiryOfferSentMessageHandler
{
    public function __construct(
        private readonly InquiryOfferRepository $offerRepository,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
        private readonly string $senderEmail = 'noreply@inquiry.deckard.com',
        private readonly string $clientAppUrl = 'https://client.example.com',
        private readonly string $adminEmail = 'admin@inquiry.deckard.com',
        private readonly string $adminAppUrl = 'https://admin.example.com',
        private readonly string $uploadDirectory = ''
    ) {
    }

    public function __invoke(InquiryOfferSentMessage $message): void
    {
        $this->logger->info('Handling InquiryOfferSentMessage', [
            'offer_id' => $message->getOfferId()
        ]);

        try {
            $offer = $this->offerRepository->find(Uuid::fromString($message->getOfferId()));

            if (!$offer) {
                $this->logger->error('Offer not found', ['offer_id' => $message->getOfferId()]);
                return;
            }

            $inquiry = $offer->getInquiry();

            if (!$inquiry) {
                $this->logger->warning('No inquiry associated with offer');
                return;
            }

            $user = $inquiry->getUser();
            $contactEmail = $inquiry->getContactEmail() ?: ($user?->getEmail() ?? null);

            if (!$contactEmail) {
                $this->logger->warning('Cannot send offer notification: no contact email', [
                    'inquiry_id' => $inquiry->getId()->toRfc4122()
                ]);
                return;
            }

            $customerName = $user?->getFullName() ?? 'Customer';

            $templateParams = [
                'offer' => $offer,
                'inquiry' => $inquiry,
                'user' => $user,
                'clientAppUrl' => $this->clientAppUrl,
                'viewUrl' => $this->clientAppUrl . '/my-inquiries/active/' . $inquiry->getId()->toRfc4122() . '/view'
            ];

            $pdfAttachmentPath = $this->resolvePdfAttachmentPath($offer);

            $this->sendEmail(
                to: $contactEmail,
                toName: $customerName,
                subject: 'New Offer - Inquiry #' . $inquiry->getInquiryNumber() . ' (Offer ' . $offer->getOfferNumber() . ')',
                template: 'emails/customer/inquiry_offer_sent.html.twig',
                params: $templateParams,
                attachmentPath: $pdfAttachmentPath,
                attachmentFilename: $offer->getPdfDocument()?->getFilename()
            );

            $this->logger->info('Offer sent notification delivered to client', [
                'offer_id' => $offer->getId()->toRfc4122(),
                'client_email' => $contactEmail,
                'pdf_attached' => $pdfAttachmentPath !== null
            ]);

            $adminParams = [
                'offer' => $offer,
                'inquiry' => $inquiry,
                'adminAppUrl' => $this->adminAppUrl,
                'viewUrl' => $this->adminAppUrl . '/manual-entry/' . $inquiry->getId()->toRfc4122() . '/view'
            ];

            $this->sendEmail(
                to: $this->adminEmail,
                toName: 'Admin',
                subject: 'Offer Sent - Inquiry #' . $inquiry->getInquiryNumber() . ' (Offer ' . $offer->getOfferNumber() . ')',
                template: 'emails/admin/inquiry_offer_sent.html.twig',
                params: $adminParams
            );

            $this->logger->info('Offer sent confirmation delivered to admin', [
                'offer_id' => $offer->getId()->toRfc4122(),
                'admin_email' => $this->adminEmail
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error in InquiryOfferSentMessageHandler', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function sendEmail(
        string $to,
        string $toName,
        string $subject,
        string $template,
        array $params,
        ?string $attachmentPath = null,
        ?string $attachmentFilename = null
    ): void {
        try {
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

            if ($attachmentPath !== null) {
                $email->attachFromPath(
                    $attachmentPath,
                    $attachmentFilename ?? basename($attachmentPath),
                    'application/pdf'
                );
            }

            $this->mailer->send($email);

        } catch (\Exception $e) {
            $this->logger->error('Failed to send email', [
                'error' => $e->getMessage(),
                'template' => $template,
                'to' => $to
            ]);
        }
    }

    /**
     * Resolve the on-disk path for the offer PDF, mirroring the fallback logic
     * used by InquiryOfferPdfDownloadController. Returns null when the offer has
     * no PDF or the file can't be found on disk.
     */
    private function resolvePdfAttachmentPath(\App\Entity\InquiryOffer $offer): ?string
    {
        $pdf = $offer->getPdfDocument();
        if ($pdf === null || $this->uploadDirectory === '') {
            return null;
        }

        $candidate = $this->uploadDirectory . '/' . basename($pdf->getFilePath());
        if (file_exists($candidate)) {
            return $candidate;
        }

        $candidate = $this->uploadDirectory . $pdf->getFilePath();
        if (file_exists($candidate)) {
            return $candidate;
        }

        $this->logger->warning('Offer PDF file not found on disk; sending email without attachment', [
            'offer_id' => $offer->getId()->toRfc4122(),
            'file_path' => $pdf->getFilePath()
        ]);
        return null;
    }
}
