<?php

namespace App\Tests\MessageHandler;

use App\Entity\Client;
use App\Entity\Inquiry;
use App\Entity\InquiryLog;
use App\Entity\Machine;
use App\Entity\User;
use App\Message\InquiryCreatedMessage;
use App\MessageHandler\InquiryCreatedMessageHandler;
use App\Repository\InquiryRepository;
use App\Service\InquiryLogService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;

class InquiryCreatedMessageHandlerTest extends TestCase
{
    private InquiryRepository $inquiryRepository;
    private MailerInterface $mailer;
    private LoggerInterface $logger;
    private Environment $twig;
    private InquiryLogService $inquiryLogService;
    private EntityManagerInterface $entityManager;
    private InquiryCreatedMessageHandler $handler;

    protected function setUp(): void
    {
        $this->inquiryRepository = $this->createMock(InquiryRepository::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->twig = $this->createMock(Environment::class);
        $this->inquiryLogService = $this->createMock(InquiryLogService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->handler = new InquiryCreatedMessageHandler(
            $this->inquiryRepository,
            $this->mailer,
            $this->logger,
            $this->twig,
            $this->inquiryLogService,
            $this->entityManager,
            'admin@test.com',
            'noreply@test.com'
        );
    }

    public function testHandleInquiryCreatedSuccessfully(): void
    {
        // Arrange
        $inquiryId = Uuid::v4();
        $inquiry = $this->createTestInquiry($inquiryId);
        $message = new InquiryCreatedMessage($inquiryId);

        $this->inquiryRepository
            ->expects($this->once())
            ->method('find')
            ->with($inquiryId)
            ->willReturn($inquiry);

        // Mock the log that will be created
        $mockLog = $this->createMock(InquiryLog::class);
        $mockLog->method('getId')->willReturn(Uuid::v4());

        $this->inquiryLogService
            ->expects($this->once())
            ->method('logInquirySubmission')
            ->with(
                $inquiry,
                'Inquiry created and submitted',
                ['operation' => 'create']
            )
            ->willReturn($mockLog);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->twig
            ->expects($this->exactly(2))
            ->method('render')
            ->willReturnCallback(function ($template, $params) use ($inquiry) {
                if (str_contains($template, 'admin')) {
                    $this->assertEquals('emails/admin/inquiry_created.html.twig', $template);
                    $this->assertArrayHasKey('inquiry', $params);
                    $this->assertArrayHasKey('machines', $params);
                    $this->assertArrayHasKey('base_url', $params);
                    $this->assertSame($inquiry, $params['inquiry']);
                } else {
                    $this->assertEquals('emails/customer/inquiry_confirmation.html.twig', $template);
                    $this->assertArrayHasKey('inquiry', $params);
                    $this->assertArrayHasKey('user', $params);
                    $this->assertArrayHasKey('machines', $params);
                    $this->assertArrayHasKey('base_url', $params);
                }
                return '<html>Test Email</html>';
            });

        $this->mailer
            ->expects($this->exactly(2))
            ->method('send')
            ->with($this->isInstanceOf(Email::class));

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info');

        // Act
        ($this->handler)($message);

        // Assert - expectations verified by mocks
    }

    public function testSkipNotificationsForDraftInquiry(): void
    {
        // Arrange
        $inquiryId = Uuid::v4();
        $inquiry = $this->createTestInquiry($inquiryId);
        $inquiry->setStatus(Inquiry::STATUS_DRAFT);

        $message = new InquiryCreatedMessage($inquiryId);

        $this->inquiryRepository
            ->expects($this->once())
            ->method('find')
            ->with($inquiryId)
            ->willReturn($inquiry);

        // Should NOT log or send any emails for draft inquiries
        $this->inquiryLogService
            ->expects($this->never())
            ->method('logInquirySubmission');

        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $this->mailer
            ->expects($this->never())
            ->method('send');

        $this->logger
            ->expects($this->atLeast(2))
            ->method('info')
            ->willReturnCallback(function ($message, $context) {
                static $callCount = 0;
                $callCount++;
                if ($callCount === 2) {
                    $this->assertStringContainsString('Skipping notification for draft inquiry', $message);
                }
            });

        // Act
        ($this->handler)($message);
    }

    public function testInquiryNotFound(): void
    {
        // Arrange
        $inquiryId = Uuid::v4();
        $message = new InquiryCreatedMessage($inquiryId);

        $this->inquiryRepository
            ->expects($this->once())
            ->method('find')
            ->with($inquiryId)
            ->willReturn(null);

        // Should NOT log when inquiry is not found
        $this->inquiryLogService
            ->expects($this->never())
            ->method('logInquirySubmission');

        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Inquiry not found in database',
                ['inquiry_id' => $inquiryId->toRfc4122()]
            );

        $this->mailer
            ->expects($this->never())
            ->method('send');

        // Act
        ($this->handler)($message);
    }

    public function testSkipCustomerNotificationWhenNoEmail(): void
    {
        // Arrange
        $inquiryId = Uuid::v4();
        $inquiry = $this->createTestInquiry($inquiryId);
        $inquiry->setUser(null); // No user associated
        $inquiry->setContactEmail(null); // No contact email

        $message = new InquiryCreatedMessage($inquiryId);

        $this->inquiryRepository
            ->expects($this->once())
            ->method('find')
            ->with($inquiryId)
            ->willReturn($inquiry);

        // Should still log the submission
        $mockLog = $this->createMock(InquiryLog::class);
        $mockLog->method('getId')->willReturn(Uuid::v4());

        $this->inquiryLogService
            ->expects($this->once())
            ->method('logInquirySubmission')
            ->willReturn($mockLog);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->twig
            ->method('render')
            ->willReturn('<html>Admin Email</html>');

        // Should send admin email but NOT customer email
        $this->mailer
            ->expects($this->once()) // Only admin email
            ->method('send');

        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                'Cannot send customer notification: no contact email',
                $this->anything()
            );

        // Act
        ($this->handler)($message);
    }

    public function testHandleEmailSendingFailureGracefully(): void
    {
        // Arrange
        $inquiryId = Uuid::v4();
        $inquiry = $this->createTestInquiry($inquiryId);
        $message = new InquiryCreatedMessage($inquiryId);

        $this->inquiryRepository
            ->expects($this->once())
            ->method('find')
            ->with($inquiryId)
            ->willReturn($inquiry);

        // Logging should complete successfully even if email fails
        $mockLog = $this->createMock(InquiryLog::class);
        $mockLog->method('getId')->willReturn(Uuid::v4());

        $this->inquiryLogService
            ->expects($this->once())
            ->method('logInquirySubmission')
            ->willReturn($mockLog);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->twig
            ->method('render')
            ->willReturn('<html>Test Email</html>');

        // Mailer throws exception
        $this->mailer
            ->expects($this->atLeastOnce())
            ->method('send')
            ->willThrowException(new \Exception('SMTP connection failed'));

        // Should log the error but NOT re-throw
        $this->logger
            ->expects($this->atLeastOnce())
            ->method('error')
            ->with(
                $this->stringContains('Failed to send'),
                $this->anything()
            );

        // Act - should not throw exception
        ($this->handler)($message);

        // Assert - no exception thrown, verified by reaching this point
        $this->assertTrue(true);
    }

    public function testSendCustomerNotificationWithContactEmail(): void
    {
        // Arrange
        $inquiryId = Uuid::v4();
        $inquiry = $this->createTestInquiry($inquiryId);
        $inquiry->setContactEmail('contact@different.com');

        $message = new InquiryCreatedMessage($inquiryId);

        $this->inquiryRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($inquiry);

        // Mock logging
        $mockLog = $this->createMock(InquiryLog::class);
        $mockLog->method('getId')->willReturn(Uuid::v4());

        $this->inquiryLogService
            ->expects($this->once())
            ->method('logInquirySubmission')
            ->willReturn($mockLog);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->twig
            ->method('render')
            ->willReturn('<html>Test Email</html>');

        // Should send both admin and customer emails
        $this->mailer
            ->expects($this->exactly(2))
            ->method('send')
            ->with($this->isInstanceOf(Email::class));

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info');

        // Act
        ($this->handler)($message);
    }

    public function testSendCustomerNotificationWithUserEmail(): void
    {
        // Arrange
        $inquiryId = Uuid::v4();
        $inquiry = $this->createTestInquiry($inquiryId);
        $inquiry->setContactEmail(null); // No contact email, should use user email

        $message = new InquiryCreatedMessage($inquiryId);

        $this->inquiryRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($inquiry);

        // Mock logging
        $mockLog = $this->createMock(InquiryLog::class);
        $mockLog->method('getId')->willReturn(Uuid::v4());

        $this->inquiryLogService
            ->expects($this->once())
            ->method('logInquirySubmission')
            ->willReturn($mockLog);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->twig
            ->method('render')
            ->willReturn('<html>Test Email</html>');

        // Should send both admin and customer emails
        $this->mailer
            ->expects($this->exactly(2))
            ->method('send')
            ->with($this->isInstanceOf(Email::class));

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info');

        // Act
        ($this->handler)($message);
    }

    public function testInquiryWithMachines(): void
    {
        // Arrange
        $inquiryId = Uuid::v4();
        $inquiry = $this->createTestInquiry($inquiryId);

        $message = new InquiryCreatedMessage($inquiryId);

        $this->inquiryRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($inquiry);

        // Mock logging
        $mockLog = $this->createMock(InquiryLog::class);
        $mockLog->method('getId')->willReturn(Uuid::v4());

        $this->inquiryLogService
            ->expects($this->once())
            ->method('logInquirySubmission')
            ->willReturn($mockLog);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->twig
            ->expects($this->exactly(2))
            ->method('render')
            ->willReturnCallback(function ($template, $params) {
                $this->assertArrayHasKey('machines', $params);
                // Machines collection is passed to template
                return '<html>Test Email</html>';
            });

        $this->mailer
            ->expects($this->exactly(2))
            ->method('send');

        // Act
        ($this->handler)($message);
    }

    private function createTestInquiry(Uuid $inquiryId): Inquiry
    {
        $client = new Client();
        $client->setName('Test Client');
        $client->setCode('TEST');
        $client->setEmail('client@test.com');

        $user = new User();
        $user->setEmail('user@test.com');
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setPhoneNumber('+1234567890');
        $user->setClient($client);
        $user->setPassword('dummy');

        $inquiry = $this->getMockBuilder(Inquiry::class)
            ->onlyMethods(['getId'])
            ->getMock();

        $inquiry->method('getId')->willReturn($inquiryId);
        $inquiry->setInquiryNumber('INQ-TEST-001');
        $inquiry->setUser($user);
        $inquiry->setStatus(Inquiry::STATUS_SUBMITTED);
        $inquiry->setCreatedAt(new \DateTime());
        $inquiry->setUpdatedAt(new \DateTime());

        return $inquiry;
    }
}
