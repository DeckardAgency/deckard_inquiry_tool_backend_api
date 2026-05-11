<?php

namespace App\MessageHandler;

use App\Entity\InquiryOffer;
use App\Message\InquiryOfferRespondedMessage;
use App\Repository\InquiryOfferRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;

#[AsMessageHandler]
class InquiryOfferRespondedMessageHandler
{
    public function __construct(
        private readonly InquiryOfferRepository $offerRepository,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
        private readonly string $adminEmail = 'admin@inquiry.deckard.com',
        private readonly string $senderEmail = 'noreply@inquiry.deckard.com',
        private readonly string $adminAppUrl = 'https://admin.example.com'
    ) {
    }

    public function __invoke(InquiryOfferRespondedMessage $message): void
    {
        $this->logger->info('Handling InquiryOfferRespondedMessage', [
            'offer_id' => $message->getOfferId(),
            'response_status' => $message->getResponseStatus()
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

            $templateParams = [
                'offer' => $offer,
                'inquiry' => $inquiry,
                'adminAppUrl' => $this->adminAppUrl,
                'viewUrl' => $this->adminAppUrl . '/manual-entry/' . $inquiry->getId()->toRfc4122() . '/view'
            ];

            if ($message->getResponseStatus() === InquiryOffer::STATUS_ACCEPTED) {
                $this->sendEmail(
                    to: $this->adminEmail,
                    toName: 'Admin',
                    subject: 'Offer Accepted - Inquiry #' . $inquiry->getInquiryNumber() . ' (Offer ' . $offer->getOfferNumber() . ')',
                    template: 'emails/admin/inquiry_offer_accepted.html.twig',
                    params: $templateParams
                );
            } elseif ($message->getResponseStatus() === InquiryOffer::STATUS_REJECTED) {
                $this->sendEmail(
                    to: $this->adminEmail,
                    toName: 'Admin',
                    subject: 'Offer Rejected - Inquiry #' . $inquiry->getInquiryNumber() . ' (Offer ' . $offer->getOfferNumber() . ')',
                    template: 'emails/admin/inquiry_offer_rejected.html.twig',
                    params: $templateParams
                );
            }

            $this->logger->info('Offer response notification sent to admin', [
                'offer_id' => $offer->getId()->toRfc4122(),
                'response' => $message->getResponseStatus()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error in InquiryOfferRespondedMessageHandler', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function sendEmail(string $to, string $toName, string $subject, string $template, array $params): void
    {
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
