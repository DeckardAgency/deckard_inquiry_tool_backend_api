<?php

namespace App\MessageHandler;

use App\Message\UserInvitedMessage;
use App\Repository\UserInvitationRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;

#[AsMessageHandler]
class UserInvitedMessageHandler
{
    public function __construct(
        private readonly UserInvitationRepository $userInvitationRepository,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly Environment $twig,
        private readonly string $clientAppUrl,
        private readonly string $senderEmail = 'noreply@inquiry.deckard.com',
        private readonly string $senderName = 'Deckard Inquiry Tool'
    ) {
    }

    public function __invoke(UserInvitedMessage $message): void
    {
        $invitationId = $message->getInvitationId();

        $this->logger->info('Handling UserInvitedMessage', [
            'invitation_id' => $invitationId->toRfc4122()
        ]);

        try {
            $invitation = $this->userInvitationRepository->find($invitationId);

            if (!$invitation) {
                $this->logger->error('User invitation not found in database', [
                    'invitation_id' => $invitationId->toRfc4122()
                ]);
                return;
            }

            // Only send email for pending invitations
            if (!$invitation->isPending()) {
                $this->logger->info('Skipping email for non-pending invitation', [
                    'invitation_id' => $invitationId->toRfc4122(),
                    'status' => $invitation->getStatus()
                ]);
                return;
            }

            $this->logger->info('Found invitation', [
                'email' => $invitation->getEmail(),
                'status' => $invitation->getStatus(),
                'expires_at' => $invitation->getExpiresAt()?->format('Y-m-d H:i:s')
            ]);

            // Send the invitation email
            $this->sendInvitationEmail($invitation);

        } catch (\Exception $e) {
            $this->logger->error('Error in UserInvitedMessageHandler', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function sendInvitationEmail($invitation): void
    {
        try {
            // Build the registration URL with token
            $registrationUrl = sprintf(
                '%s/register?token=%s',
                rtrim($this->clientAppUrl, '/'),
                $invitation->getToken()
            );

            // Calculate days until expiration
            $now = new \DateTime();
            $expiresAt = $invitation->getExpiresAt();
            $expirationDays = $now->diff($expiresAt)->days;

            // Create template parameters
            $templateParams = [
                'invitation' => $invitation,
                'registrationUrl' => $registrationUrl,
                'expirationDays' => $expirationDays,
            ];

            // Render template
            $htmlContent = $this->twig->render('emails/user/invitation.html.twig', $templateParams);

            // Create email
            $email = (new Email())
                ->from(new Address($this->senderEmail, $this->senderName))
                ->to(new Address($invitation->getEmail(), $invitation->getFullName()))
                ->subject('You\'ve been invited to Deckard Inquiry Tool')
                ->html($htmlContent);

            $this->logger->info('Sending invitation email', [
                'from' => $this->senderEmail,
                'to' => $invitation->getEmail(),
                'subject' => $email->getSubject(),
                'registration_url' => $registrationUrl
            ]);

            // Send the email
            $this->mailer->send($email);

            $this->logger->info('Invitation email sent successfully', [
                'invitation_id' => $invitation->getId()->toRfc4122(),
                'email' => $invitation->getEmail()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send invitation email', [
                'invitation_id' => $invitation->getId()->toRfc4122(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
