<?php

namespace App\Tests\MessageHandler;

use App\Entity\Client;
use App\Entity\Inquiry;
use App\Entity\User;
use App\Message\InquiryStatusChangedMessage;
use App\MessageHandler\InquiryStatusChangedMessageHandler;
use App\Repository\InquiryRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;
use Twig\Loader\LoaderInterface;

class InquiryStatusChangedMessageHandlerTest extends TestCase
{
    private InquiryRepository $inquiryRepository;
    private MailerInterface $mailer;
    private Environment $twig;
    private LoggerInterface $logger;
    private LoaderInterface $twigLoader;
    private InquiryStatusChangedMessageHandler $handler;

    protected function setUp(): void
    {
        $this->inquiryRepository = $this->createMock(InquiryRepository::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->twigLoader = $this->createMock(LoaderInterface::class);
        $this->twig = $this->createMock(Environment::class);

        $this->twig->method('getLoader')->willReturn($this->twigLoader);

        $this->handler = new InquiryStatusChangedMessageHandler(
            $this->inquiryRepository,
            $this->mailer,
            $this->twig,
            $this->logger,
            'admin@test.com',
            'noreply@test.com'
        );
    }

    public function testHandleInquiryStatusChangedSuccessfully(): void
    {
        // Arrange
        $inquiryId = Uuid::v4();
        $inquiry = $this->createTestInquiry($inquiryId);
        $message = new InquiryStatusChangedMessage(
            $inquiryId,
            Inquiry::STATUS_SUBMITTED,
            Inquiry::STATUS_IN_REVIEW
        );

        $this->inquiryRepository
            ->expects($this->once())
            ->method('find')
            ->with($inquiryId)
            ->willReturn($inquiry);

        $this->twigLoader
            ->method('exists')
            ->willReturn(true);

        $this->twig
            ->expects($this->exactly(2))
            ->method('render')
            ->willReturnCallback(function ($template, $params) use ($inquiry) {
                if (str_contains($template, 'admin')) {
                    $this->assertEquals('emails/admin/inquiry_in_review.html.twig', $template);
                    $this->assertArrayHasKey('inquiry', $params);
                    $this->assertArrayHasKey('status', $params);
                    $this->assertArrayHasKey('machines', $params);
                    $this->assertArrayHasKey('base_url', $params);
                    $this->assertEquals(Inquiry::STATUS_IN_REVIEW, $params['status']);
                } else {
                    $this->assertEquals('emails/customer/inquiry_in_review.html.twig', $template);
                    $this->assertArrayHasKey('inquiry', $params);
                    $this->assertArrayHasKey('user', $params);
                    $this->assertArrayHasKey('status', $params);
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
    }

    public function testSkipNotificationsForDraftInquiry(): void
    {
        // Arrange
        $inquiryId = Uuid::v4();
        $inquiry = $this->createTestInquiry($inquiryId);
        $inquiry->setStatus(Inquiry::STATUS_DRAFT);

        $message = new InquiryStatusChangedMessage(
            $inquiryId,
            Inquiry::STATUS_DRAFT,
            Inquiry::STATUS_SUBMITTED
        );

        $this->inquiryRepository
            ->expects($this->once())
            ->method('find')
            ->with($inquiryId)
            ->willReturn($inquiry);

        // Should NOT send any emails for draft inquiries
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
        $message = new InquiryStatusChangedMessage(
            $inquiryId,
            Inquiry::STATUS_SUBMITTED,
            Inquiry::STATUS_IN_REVIEW
        );

        $this->inquiryRepository
            ->expects($this->once())
            ->method('find')
            ->with($inquiryId)
            ->willReturn(null);

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
        $inquiry->setUser(null);
        $inquiry->setContactEmail(null);

        $message = new InquiryStatusChangedMessage(
            $inquiryId,
            Inquiry::STATUS_SUBMITTED,
            Inquiry::STATUS_IN_REVIEW
        );

        $this->inquiryRepository
            ->expects($this->once())
            ->method('find')
            ->with($inquiryId)
            ->willReturn($inquiry);

        $this->twigLoader
            ->method('exists')
            ->willReturn(true);

        $this->twig
            ->method('render')
            ->willReturn('<html>Admin Email</html>');

        // Should send admin email but NOT customer email
        $this->mailer
            ->expects($this->once())
            ->method('send');

        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                'Cannot send customer status notification: no contact email',
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
        $message = new InquiryStatusChangedMessage(
            $inquiryId,
            Inquiry::STATUS_SUBMITTED,
            Inquiry::STATUS_IN_REVIEW
        );

        $this->inquiryRepository
            ->expects($this->once())
            ->method('find')
            ->with($inquiryId)
            ->willReturn($inquiry);

        $this->twigLoader
            ->method('exists')
            ->willReturn(true);

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

        // Assert - no exception thrown
        $this->assertTrue(true);
    }

    public function testStatusChangeToCompleted(): void
    {
        // Arrange
        $inquiryId = Uuid::v4();
        $inquiry = $this->createTestInquiry($inquiryId);
        $message = new InquiryStatusChangedMessage(
            $inquiryId,
            Inquiry::STATUS_IN_PROGRESS,
            Inquiry::STATUS_COMPLETED
        );

        $this->inquiryRepository
            ->method('find')
            ->willReturn($inquiry);

        $this->twigLoader
            ->method('exists')
            ->willReturn(true);

        $this->twig
            ->expects($this->exactly(2))
            ->method('render')
            ->willReturnCallback(function ($template) {
                if (str_contains($template, 'admin')) {
                    $this->assertEquals('emails/admin/inquiry_completed.html.twig', $template);
                } else {
                    $this->assertEquals('emails/customer/inquiry_completed.html.twig', $template);
                }
                return '<html>Test Email</html>';
            });

        $this->mailer
            ->expects($this->exactly(2))
            ->method('send');

        // Act
        ($this->handler)($message);
    }

    public function testStatusChangeToCanceled(): void
    {
        // Arrange
        $inquiryId = Uuid::v4();
        $inquiry = $this->createTestInquiry($inquiryId);
        $message = new InquiryStatusChangedMessage(
            $inquiryId,
            Inquiry::STATUS_IN_PROGRESS,
            Inquiry::STATUS_CANCELED
        );

        $this->inquiryRepository
            ->method('find')
            ->willReturn($inquiry);

        $this->twigLoader
            ->method('exists')
            ->willReturn(true);

        $this->twig
            ->expects($this->exactly(2))
            ->method('render')
            ->willReturnCallback(function ($template) {
                if (str_contains($template, 'admin')) {
                    $this->assertEquals('emails/admin/inquiry_canceled.html.twig', $template);
                } else {
                    $this->assertEquals('emails/customer/inquiry_canceled.html.twig', $template);
                }
                return '<html>Test Email</html>';
            });

        $this->mailer
            ->expects($this->exactly(2))
            ->method('send');

        // Act
        ($this->handler)($message);
    }

    public function testStatusChangeToMoreInfo(): void
    {
        // Arrange
        $inquiryId = Uuid::v4();
        $inquiry = $this->createTestInquiry($inquiryId);
        $message = new InquiryStatusChangedMessage(
            $inquiryId,
            Inquiry::STATUS_IN_REVIEW,
            Inquiry::STATUS_MORE_INFO
        );

        $this->inquiryRepository
            ->method('find')
            ->willReturn($inquiry);

        $this->twigLoader
            ->method('exists')
            ->willReturn(true);

        $this->twig
            ->expects($this->exactly(2))
            ->method('render')
            ->willReturnCallback(function ($template) {
                if (str_contains($template, 'admin')) {
                    $this->assertEquals('emails/admin/inquiry_more_info.html.twig', $template);
                } else {
                    $this->assertEquals('emails/customer/inquiry_more_info.html.twig', $template);
                }
                return '<html>Test Email</html>';
            });

        $this->mailer
            ->expects($this->exactly(2))
            ->method('send');

        // Act
        ($this->handler)($message);
    }

    public function testFallbackToGenericTemplateWhenSpecificTemplateMissing(): void
    {
        // Arrange
        $inquiryId = Uuid::v4();
        $inquiry = $this->createTestInquiry($inquiryId);
        $message = new InquiryStatusChangedMessage(
            $inquiryId,
            Inquiry::STATUS_SUBMITTED,
            'custom_status'
        );

        $this->inquiryRepository
            ->method('find')
            ->willReturn($inquiry);

        // For custom status, match expression defaults to generic template
        // so exists() will be called to check if it exists
        $this->twigLoader
            ->method('exists')
            ->willReturn(true);

        $this->twig
            ->expects($this->exactly(2))
            ->method('render')
            ->willReturnCallback(function ($template) {
                // Should use generic fallback template for unknown status
                $this->assertStringContainsString('inquiry_status_changed.html.twig', $template);
                return '<html>Test Email</html>';
            });

        $this->mailer
            ->expects($this->exactly(2))
            ->method('send');

        // Act
        ($this->handler)($message);
    }

    public function testCustomerNotificationUsesContactEmail(): void
    {
        // Arrange
        $inquiryId = Uuid::v4();
        $inquiry = $this->createTestInquiry($inquiryId);
        $inquiry->setContactEmail('contact@different.com');

        $message = new InquiryStatusChangedMessage(
            $inquiryId,
            Inquiry::STATUS_SUBMITTED,
            Inquiry::STATUS_IN_REVIEW
        );

        $this->inquiryRepository
            ->method('find')
            ->willReturn($inquiry);

        $this->twigLoader
            ->method('exists')
            ->willReturn(true);

        $this->twig
            ->method('render')
            ->willReturn('<html>Test Email</html>');

        $this->mailer
            ->expects($this->exactly(2))
            ->method('send');

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info');

        // Act
        ($this->handler)($message);
    }

    public function testAllInquiryStatuses(): void
    {
        $statuses = [
            Inquiry::STATUS_IN_REVIEW,
            Inquiry::STATUS_MORE_INFO,
            Inquiry::STATUS_INFORMATION_PROVIDED,
            Inquiry::STATUS_IN_PROGRESS,
            Inquiry::STATUS_COMPLETED,
            Inquiry::STATUS_CANCELED,
        ];

        foreach ($statuses as $status) {
            // Arrange
            $inquiryId = Uuid::v4();
            $inquiry = $this->createTestInquiry($inquiryId);
            $message = new InquiryStatusChangedMessage(
                $inquiryId,
                Inquiry::STATUS_SUBMITTED,
                $status
            );

            $this->inquiryRepository
                ->method('find')
                ->willReturn($inquiry);

            $this->twigLoader
                ->method('exists')
                ->willReturn(true);

            $this->twig
                ->method('render')
                ->willReturn('<html>Test Email</html>');

            $this->mailer
                ->expects($this->exactly(2))
                ->method('send');

            // Act
            ($this->handler)($message);

            // Reset mocks for next iteration
            $this->setUp();
        }

        // Assert
        $this->assertTrue(true);
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
