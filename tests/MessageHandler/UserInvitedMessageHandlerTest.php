<?php

namespace App\Tests\MessageHandler;

use App\Entity\Client;
use App\Entity\User;
use App\Entity\UserInvitation;
use App\Message\UserInvitedMessage;
use App\MessageHandler\UserInvitedMessageHandler;
use App\Repository\UserInvitationRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;

class UserInvitedMessageHandlerTest extends TestCase
{
    private UserInvitationRepository $userInvitationRepository;
    private MailerInterface $mailer;
    private LoggerInterface $logger;
    private Environment $twig;
    private UserInvitedMessageHandler $handler;
    private string $clientAppUrl = 'http://localhost:4200';
    private string $senderEmail = 'noreply@test.com';

    protected function setUp(): void
    {
        $this->userInvitationRepository = $this->createMock(UserInvitationRepository::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->twig = $this->createMock(Environment::class);

        $this->handler = new UserInvitedMessageHandler(
            $this->userInvitationRepository,
            $this->mailer,
            $this->logger,
            $this->twig,
            $this->clientAppUrl,
            $this->senderEmail,
            'Deckard Inquiry Tool'
        );
    }

    public function testHandleUserInvitedMessageSuccessfully(): void
    {
        // Arrange
        $invitation = $this->createTestInvitation();
        $message = new UserInvitedMessage($invitation->getId());

        $this->userInvitationRepository
            ->expects($this->once())
            ->method('find')
            ->with($invitation->getId())
            ->willReturn($invitation);

        $this->twig
            ->expects($this->once())
            ->method('render')
            ->with(
                'emails/user/invitation.html.twig',
                $this->callback(function ($params) use ($invitation) {
                    return $params['invitation'] === $invitation
                        && str_contains($params['registrationUrl'], $invitation->getToken())
                        && isset($params['expirationDays']);
                })
            )
            ->willReturn('<html>Email content</html>');

        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) use ($invitation) {
                $to = $email->getTo();
                return count($to) === 1
                    && $to[0]->getAddress() === $invitation->getEmail()
                    && str_contains($email->getSubject(), 'invited');
            }));

        // Act
        ($this->handler)($message);
    }

    public function testSkipsEmailForNonPendingInvitation(): void
    {
        // Arrange
        $invitation = $this->createTestInvitation();
        $invitation->setStatus(UserInvitation::STATUS_COMPLETED);
        $message = new UserInvitedMessage($invitation->getId());

        $this->userInvitationRepository
            ->expects($this->once())
            ->method('find')
            ->with($invitation->getId())
            ->willReturn($invitation);

        $this->twig
            ->expects($this->never())
            ->method('render');

        $this->mailer
            ->expects($this->never())
            ->method('send');

        // Act
        ($this->handler)($message);
    }

    public function testHandlesInvitationNotFound(): void
    {
        // Arrange
        $invitationId = Uuid::v4();
        $message = new UserInvitedMessage($invitationId);

        $this->userInvitationRepository
            ->expects($this->once())
            ->method('find')
            ->with($invitationId)
            ->willReturn(null);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'User invitation not found in database',
                $this->arrayHasKey('invitation_id')
            );

        $this->mailer
            ->expects($this->never())
            ->method('send');

        // Act
        ($this->handler)($message);
    }

    public function testHandlesEmailSendingFailure(): void
    {
        // Arrange
        $invitation = $this->createTestInvitation();
        $message = new UserInvitedMessage($invitation->getId());

        $this->userInvitationRepository
            ->method('find')
            ->willReturn($invitation);

        $this->twig
            ->method('render')
            ->willReturn('<html>Email content</html>');

        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->willThrowException(new \Exception('SMTP connection failed'));

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Failed to send invitation email',
                $this->callback(function ($context) {
                    return isset($context['error'])
                        && str_contains($context['error'], 'SMTP connection failed');
                })
            );

        // Act
        ($this->handler)($message);
    }

    public function testBuildsCorrectRegistrationUrl(): void
    {
        // Arrange
        $invitation = $this->createTestInvitation();
        $message = new UserInvitedMessage($invitation->getId());

        $this->userInvitationRepository
            ->method('find')
            ->willReturn($invitation);

        $capturedParams = null;
        $this->twig
            ->expects($this->once())
            ->method('render')
            ->willReturnCallback(function ($template, $params) use (&$capturedParams) {
                $capturedParams = $params;
                return '<html>Email content</html>';
            });

        $this->mailer
            ->method('send');

        // Act
        ($this->handler)($message);

        // Assert
        $this->assertNotNull($capturedParams);
        $expectedUrl = sprintf(
            '%s/register?token=%s',
            $this->clientAppUrl,
            $invitation->getToken()
        );
        $this->assertEquals($expectedUrl, $capturedParams['registrationUrl']);
    }

    public function testCalculatesExpirationDaysCorrectly(): void
    {
        // Arrange
        $invitation = $this->createTestInvitation();
        $invitation->setExpiresAt(new \DateTime('+7 days'));
        $message = new UserInvitedMessage($invitation->getId());

        $this->userInvitationRepository
            ->method('find')
            ->willReturn($invitation);

        $capturedParams = null;
        $this->twig
            ->expects($this->once())
            ->method('render')
            ->willReturnCallback(function ($template, $params) use (&$capturedParams) {
                $capturedParams = $params;
                return '<html>Email content</html>';
            });

        $this->mailer
            ->method('send');

        // Act
        ($this->handler)($message);

        // Assert
        $this->assertNotNull($capturedParams);
        // Allow 6 or 7 days due to timing differences
        $this->assertGreaterThanOrEqual(6, $capturedParams['expirationDays']);
        $this->assertLessThanOrEqual(7, $capturedParams['expirationDays']);
    }

    public function testLogsSuccessfulEmailSending(): void
    {
        // Arrange
        $invitation = $this->createTestInvitation();
        $message = new UserInvitedMessage($invitation->getId());

        $this->userInvitationRepository
            ->method('find')
            ->willReturn($invitation);

        $this->twig
            ->method('render')
            ->willReturn('<html>Email content</html>');

        $this->mailer
            ->method('send');

        // Expect at least 4 info log calls
        $this->logger
            ->expects($this->exactly(4))
            ->method('info');

        // Act
        ($this->handler)($message);
    }

    private function createTestInvitation(): UserInvitation
    {
        $client = new Client();
        $client->setName('Test Client');
        $client->setCode('TEST');
        $client->setEmail('client@test.com');

        $createdBy = $this->getMockBuilder(User::class)
            ->onlyMethods(['getId'])
            ->getMock();
        $createdBy->method('getId')->willReturn(Uuid::v4());
        $createdBy->setEmail('admin@test.com');
        $createdBy->setFirstName('Admin');
        $createdBy->setLastName('User');
        $createdBy->setPhoneNumber('+1234567890');
        $createdBy->setClient($client);
        $createdBy->setPassword('dummy');

        $invitation = new UserInvitation();
        $invitation->setEmail('invited@test.com');
        $invitation->setFirstName('John');
        $invitation->setLastName('Doe');
        $invitation->setClient($client);
        $invitation->setCreatedBy($createdBy);
        $invitation->setExpiresAt(new \DateTime('+7 days'));

        return $invitation;
    }
}
