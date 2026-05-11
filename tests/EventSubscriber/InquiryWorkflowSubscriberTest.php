<?php

namespace App\Tests\EventSubscriber;

use App\Entity\Client;
use App\Entity\Inquiry;
use App\Entity\InquiryLog;
use App\Entity\User;
use App\EventSubscriber\InquiryWorkflowSubscriber;
use App\Message\InquiryStatusChangedMessage;
use App\Service\InquiryLogService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

class InquiryWorkflowSubscriberTest extends TestCase
{
    private MessageBusInterface $messageBus;
    private LoggerInterface $logger;
    private Security $security;
    private InquiryLogService $inquiryLogService;
    private EntityManagerInterface $entityManager;
    private InquiryWorkflowSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->inquiryLogService = $this->createMock(InquiryLogService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->subscriber = new InquiryWorkflowSubscriber(
            $this->messageBus,
            $this->logger,
            $this->security,
            $this->inquiryLogService,
            $this->entityManager
        );
    }

    public function testGetSubscribedEvents(): void
    {
        $events = InquiryWorkflowSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey('workflow.inquiry.completed', $events);
        $this->assertEquals('onCompleted', $events['workflow.inquiry.completed']);
        $this->assertArrayHasKey('workflow.inquiry.guard', $events);
        $this->assertEquals('onGuard', $events['workflow.inquiry.guard']);
    }

    public function testOnCompletedSuccessfully(): void
    {
        // Arrange
        $inquiry = $this->createTestInquiry();
        $user = $this->createTestUser();

        $this->security
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $mockLog = $this->createMock(InquiryLog::class);
        $mockLog->method('getId')->willReturn(Uuid::v4());
        $mockLog->method('getTransitionDescription')->willReturn('Status changed from submitted to in_review');

        $this->inquiryLogService
            ->expects($this->once())
            ->method('logStatusChange')
            ->with(
                $inquiry,
                Inquiry::STATUS_SUBMITTED,
                Inquiry::STATUS_IN_REVIEW,
                $this->stringContains('Status changed by Test User via workflow'),
                $this->callback(function ($metadata) {
                    return isset($metadata['transition'])
                        && isset($metadata['modified_by'])
                        && isset($metadata['message_id'])
                        && isset($metadata['processed_at']);
                })
            )
            ->willReturn($mockLog);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $transition = new Transition('review', Inquiry::STATUS_SUBMITTED, Inquiry::STATUS_IN_REVIEW);
        $marking = new Marking([Inquiry::STATUS_IN_REVIEW => 1]);

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->method('getName')->willReturn('inquiry');
        $event = new CompletedEvent($inquiry, $marking, $transition, $workflow);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($message) use ($inquiry) {
                return $message instanceof InquiryStatusChangedMessage
                    && $message->getInquiryId()->equals($inquiry->getId())
                    && $message->getOldStatus() === Inquiry::STATUS_SUBMITTED
                    && $message->getNewStatus() === Inquiry::STATUS_IN_REVIEW;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $this->logger
            ->expects($this->atLeast(2))
            ->method('info')
            ->willReturnCallback(function ($message, $context) {
                static $callCount = 0;
                $callCount++;

                if ($callCount === 1) {
                    $this->assertStringContainsString('workflow transition completed', $message);
                    $this->assertArrayHasKey('old_status', $context);
                    $this->assertArrayHasKey('new_status', $context);
                    $this->assertEquals(Inquiry::STATUS_SUBMITTED, $context['old_status']);
                    $this->assertEquals(Inquiry::STATUS_IN_REVIEW, $context['new_status']);
                }
            });

        // Act
        $this->subscriber->onCompleted($event);
    }

    public function testOnCompletedSkipsDraftInquiries(): void
    {
        // Arrange
        $inquiry = $this->createTestInquiry();
        $inquiry->setIsDraft(true);

        $transition = new Transition('submit', Inquiry::STATUS_DRAFT, Inquiry::STATUS_SUBMITTED);
        $marking = new Marking([Inquiry::STATUS_SUBMITTED => 1]);

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->method('getName')->willReturn('inquiry');
        $event = new CompletedEvent($inquiry, $marking, $transition, $workflow);

        // Should NOT dispatch message or log for draft inquiries
        $this->messageBus
            ->expects($this->never())
            ->method('dispatch');

        $this->inquiryLogService
            ->expects($this->never())
            ->method('logStatusChange');

        // Act
        $this->subscriber->onCompleted($event);
    }

    public function testOnCompletedExtractsOldStatusFromTransition(): void
    {
        // Arrange
        $inquiry = $this->createTestInquiry();
        $user = $this->createTestUser();

        $this->security
            ->method('getUser')
            ->willReturn($user);

        $mockLog = $this->createMock(InquiryLog::class);
        $mockLog->method('getId')->willReturn(Uuid::v4());
        $mockLog->method('getTransitionDescription')->willReturn('Status changed from in_review to in_progress');

        $this->inquiryLogService
            ->method('logStatusChange')
            ->willReturn($mockLog);

        $this->entityManager
            ->method('flush');

        // Test transition from in_review to in_progress
        $transition = new Transition('start_progress', Inquiry::STATUS_IN_REVIEW, Inquiry::STATUS_IN_PROGRESS);
        $marking = new Marking([Inquiry::STATUS_IN_PROGRESS => 1]);

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->method('getName')->willReturn('inquiry');
        $event = new CompletedEvent($inquiry, $marking, $transition, $workflow);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($message) {
                return $message instanceof InquiryStatusChangedMessage
                    && $message->getOldStatus() === Inquiry::STATUS_IN_REVIEW
                    && $message->getNewStatus() === Inquiry::STATUS_IN_PROGRESS;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        // Act
        $this->subscriber->onCompleted($event);
    }

    public function testOnCompletedWithoutAuthenticatedUser(): void
    {
        // Arrange
        $inquiry = $this->createTestInquiry();

        $this->security
            ->expects($this->once())
            ->method('getUser')
            ->willReturn(null); // No authenticated user

        $mockLog = $this->createMock(InquiryLog::class);
        $mockLog->method('getId')->willReturn(Uuid::v4());
        $mockLog->method('getTransitionDescription')->willReturn('Status changed');

        $this->inquiryLogService
            ->expects($this->once())
            ->method('logStatusChange')
            ->with(
                $inquiry,
                Inquiry::STATUS_SUBMITTED,
                Inquiry::STATUS_IN_REVIEW,
                'Status changed by Unknown User via workflow',
                $this->anything()
            )
            ->willReturn($mockLog);

        $this->entityManager
            ->method('flush');

        $transition = new Transition('review', Inquiry::STATUS_SUBMITTED, Inquiry::STATUS_IN_REVIEW);
        $marking = new Marking([Inquiry::STATUS_IN_REVIEW => 1]);

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->method('getName')->willReturn('inquiry');
        $event = new CompletedEvent($inquiry, $marking, $transition, $workflow);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        // Act
        $this->subscriber->onCompleted($event);
    }

    public function testOnCompletedLogsTransitionDetails(): void
    {
        // Arrange
        $inquiry = $this->createTestInquiry();
        $user = $this->createTestUser();

        $this->security
            ->method('getUser')
            ->willReturn($user);

        $mockLog = $this->createMock(InquiryLog::class);
        $mockLog->method('getId')->willReturn(Uuid::v4());
        $mockLog->method('getTransitionDescription')->willReturn('Status changed from in_progress to completed');

        $this->inquiryLogService
            ->method('logStatusChange')
            ->willReturn($mockLog);

        $this->entityManager
            ->method('flush');

        $transition = new Transition('complete', Inquiry::STATUS_IN_PROGRESS, Inquiry::STATUS_COMPLETED);
        $marking = new Marking([Inquiry::STATUS_COMPLETED => 1]);

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->method('getName')->willReturn('inquiry');
        $event = new CompletedEvent($inquiry, $marking, $transition, $workflow);

        $this->logger
            ->expects($this->atLeast(2))
            ->method('info')
            ->willReturnCallback(function ($message, $context) {
                static $callCount = 0;
                $callCount++;

                if ($callCount === 1) {
                    // First log should contain transition details
                    $this->assertArrayHasKey('inquiry_id', $context);
                    $this->assertArrayHasKey('inquiry_number', $context);
                    $this->assertArrayHasKey('transition', $context);
                    $this->assertArrayHasKey('old_status', $context);
                    $this->assertArrayHasKey('new_status', $context);
                    $this->assertArrayHasKey('modified_by', $context);
                    $this->assertEquals('complete', $context['transition']);
                    $this->assertEquals(Inquiry::STATUS_IN_PROGRESS, $context['old_status']);
                    $this->assertEquals(Inquiry::STATUS_COMPLETED, $context['new_status']);
                }
            });

        $this->messageBus
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        // Act
        $this->subscriber->onCompleted($event);
    }

    public function testOnCompletedSetsModifiedByInformation(): void
    {
        // Arrange
        $inquiry = $this->createTestInquiry();
        $user = $this->createTestUser();
        $user->setEmail('john.admin@test.com');
        $user->setFirstName('John');
        $user->setLastName('Admin');

        $this->security
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $mockLog = $this->createMock(InquiryLog::class);
        $mockLog->method('getId')->willReturn(Uuid::v4());
        $mockLog->method('getTransitionDescription')->willReturn('Status changed');

        $this->inquiryLogService
            ->expects($this->once())
            ->method('logStatusChange')
            ->with(
                $inquiry,
                $this->anything(),
                $this->anything(),
                'Status changed by John Admin via workflow',
                $this->callback(function ($metadata) {
                    $modifiedBy = $metadata['modified_by'];
                    return isset($modifiedBy['id'])
                        && isset($modifiedBy['email'])
                        && $modifiedBy['email'] === 'john.admin@test.com'
                        && $modifiedBy['fullName'] === 'John Admin'
                        && $modifiedBy['firstName'] === 'John'
                        && $modifiedBy['lastName'] === 'Admin';
                })
            )
            ->willReturn($mockLog);

        $this->entityManager
            ->method('flush');

        $transition = new Transition('cancel', Inquiry::STATUS_SUBMITTED, Inquiry::STATUS_CANCELED);
        $marking = new Marking([Inquiry::STATUS_CANCELED => 1]);

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->method('getName')->willReturn('inquiry');
        $event = new CompletedEvent($inquiry, $marking, $transition, $workflow);

        $this->messageBus
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        // Act
        $this->subscriber->onCompleted($event);
    }

    public function testOnGuardBlocksSubmitWhenInquiryCannotBeSubmitted(): void
    {
        // Arrange
        $inquiry = $this->createTestInquiry();

        // Mock the inquiry to return false for canBeSubmitted()
        $inquiry = $this->getMockBuilder(Inquiry::class)
            ->onlyMethods(['getId', 'canBeSubmitted', 'getSubmissionErrors'])
            ->getMock();

        $inquiry->method('getId')->willReturn(Uuid::v4());
        $inquiry->method('canBeSubmitted')->willReturn(false);
        $inquiry->method('getSubmissionErrors')->willReturn(['Missing required field: machines']);

        $transition = new Transition('submit', Inquiry::STATUS_DRAFT, Inquiry::STATUS_SUBMITTED);
        $workflow = $this->createMock(WorkflowInterface::class);
        $event = new GuardEvent($inquiry, new Marking([Inquiry::STATUS_DRAFT => 1]), $transition, $workflow);

        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                'Inquiry submission blocked',
                $this->callback(function ($context) {
                    return isset($context['inquiry_id'])
                        && isset($context['errors'])
                        && is_array($context['errors']);
                })
            );

        // Act
        $this->subscriber->onGuard($event);

        // Assert
        $this->assertTrue($event->isBlocked());
    }

    public function testOnGuardAllowsSubmitWhenInquiryCanBeSubmitted(): void
    {
        // Arrange
        $inquiry = $this->getMockBuilder(Inquiry::class)
            ->onlyMethods(['canBeSubmitted'])
            ->getMock();

        $inquiry->method('canBeSubmitted')->willReturn(true);

        $transition = new Transition('submit', Inquiry::STATUS_DRAFT, Inquiry::STATUS_SUBMITTED);
        $workflow = $this->createMock(WorkflowInterface::class);
        $event = new GuardEvent($inquiry, new Marking([Inquiry::STATUS_DRAFT => 1]), $transition, $workflow);

        $this->logger
            ->expects($this->never())
            ->method('warning');

        // Act
        $this->subscriber->onGuard($event);

        // Assert
        $this->assertFalse($event->isBlocked());
    }

    public function testOnGuardIgnoresNonSubmitTransitions(): void
    {
        // Arrange
        $inquiry = $this->getMockBuilder(Inquiry::class)
            ->onlyMethods(['canBeSubmitted'])
            ->getMock();

        // Even if inquiry cannot be submitted, non-submit transitions should not be blocked
        $inquiry->expects($this->never())->method('canBeSubmitted');

        $transition = new Transition('review', Inquiry::STATUS_SUBMITTED, Inquiry::STATUS_IN_REVIEW);
        $workflow = $this->createMock(WorkflowInterface::class);
        $event = new GuardEvent($inquiry, new Marking([Inquiry::STATUS_SUBMITTED => 1]), $transition, $workflow);

        $this->logger
            ->expects($this->never())
            ->method('warning');

        // Act
        $this->subscriber->onGuard($event);

        // Assert - should not block non-submit transitions
        $this->assertFalse($event->isBlocked());
    }

    public function testOnCompletedHandlesAllInquiryStatuses(): void
    {
        $transitions = [
            ['submit', Inquiry::STATUS_DRAFT, Inquiry::STATUS_SUBMITTED],
            ['review', Inquiry::STATUS_SUBMITTED, Inquiry::STATUS_IN_REVIEW],
            ['request_more_info', Inquiry::STATUS_SUBMITTED, Inquiry::STATUS_MORE_INFO],
            ['provide_information', Inquiry::STATUS_MORE_INFO, Inquiry::STATUS_INFORMATION_PROVIDED],
            ['start_progress', Inquiry::STATUS_SUBMITTED, Inquiry::STATUS_IN_PROGRESS],
            ['complete', Inquiry::STATUS_IN_PROGRESS, Inquiry::STATUS_COMPLETED],
            ['cancel', Inquiry::STATUS_SUBMITTED, Inquiry::STATUS_CANCELED],
        ];

        foreach ($transitions as [$transitionName, $from, $to]) {
            // Reset mocks
            $this->setUp();

            $inquiry = $this->createTestInquiry();
            $inquiry->setStatus($to);
            $user = $this->createTestUser();

            $this->security
                ->method('getUser')
                ->willReturn($user);

            $mockLog = $this->createMock(InquiryLog::class);
            $mockLog->method('getId')->willReturn(Uuid::v4());
            $mockLog->method('getTransitionDescription')->willReturn("Status changed from $from to $to");

            $this->inquiryLogService
                ->method('logStatusChange')
                ->willReturn($mockLog);

            $this->entityManager
                ->method('flush');

            $transition = new Transition($transitionName, $from, $to);
            $marking = new Marking([$to => 1]);

            $workflow = $this->createMock(WorkflowInterface::class);
            $workflow->method('getName')->willReturn('inquiry');
            $event = new CompletedEvent($inquiry, $marking, $transition, $workflow);

            $this->messageBus
                ->expects($this->once())
                ->method('dispatch')
                ->with($this->callback(function ($message) use ($from, $to) {
                    return $message instanceof InquiryStatusChangedMessage
                        && $message->getOldStatus() === $from
                        && $message->getNewStatus() === $to;
                }))
                ->willReturn(new Envelope(new \stdClass()));

            // Act
            $this->subscriber->onCompleted($event);
        }

        // Assert
        $this->assertTrue(true); // All transitions handled successfully
    }

    public function testOnCompletedDoesNotLogWhenServiceReturnsNull(): void
    {
        // Arrange
        $inquiry = $this->createTestInquiry();
        $user = $this->createTestUser();

        $this->security
            ->method('getUser')
            ->willReturn($user);

        // InquiryLogService returns null (e.g., when status hasn't changed)
        $this->inquiryLogService
            ->expects($this->once())
            ->method('logStatusChange')
            ->willReturn(null);

        // EntityManager flush should NOT be called when log is null
        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $transition = new Transition('review', Inquiry::STATUS_SUBMITTED, Inquiry::STATUS_IN_REVIEW);
        $marking = new Marking([Inquiry::STATUS_IN_REVIEW => 1]);

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->method('getName')->willReturn('inquiry');
        $event = new CompletedEvent($inquiry, $marking, $transition, $workflow);

        $this->messageBus
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        // Act
        $this->subscriber->onCompleted($event);
    }

    private function createTestInquiry(): Inquiry
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

        $inquiryId = Uuid::v4();
        $inquiry->method('getId')->willReturn($inquiryId);
        $inquiry->setInquiryNumber('INQ-TEST-001');
        $inquiry->setUser($user);
        $inquiry->setStatus(Inquiry::STATUS_SUBMITTED);
        $inquiry->setCreatedAt(new \DateTime());
        $inquiry->setUpdatedAt(new \DateTime());

        return $inquiry;
    }

    private function createTestUser(): User
    {
        $client = new Client();
        $client->setName('Test Client');
        $client->setCode('TEST');
        $client->setEmail('client@test.com');

        $user = $this->getMockBuilder(User::class)
            ->onlyMethods(['getId'])
            ->getMock();

        $userId = Uuid::v4();
        $user->method('getId')->willReturn($userId);
        $user->setEmail('test@test.com');
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setPhoneNumber('+1234567890');
        $user->setClient($client);
        $user->setPassword('dummy');

        return $user;
    }
}
