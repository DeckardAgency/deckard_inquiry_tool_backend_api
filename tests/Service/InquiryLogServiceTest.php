<?php

namespace App\Tests\Service;

use App\Entity\Client;
use App\Entity\Inquiry;
use App\Entity\InquiryLog;
use App\Entity\User;
use App\Repository\InquiryLogRepository;
use App\Service\InquiryLogService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\Uuid;

class InquiryLogServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private Security $security;
    private InquiryLogService $service;
    private InquiryLogRepository $repository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->repository = $this->createMock(InquiryLogRepository::class);

        $this->entityManager
            ->method('getRepository')
            ->with(InquiryLog::class)
            ->willReturn($this->repository);

        $this->service = new InquiryLogService(
            $this->entityManager,
            $this->security
        );
    }

    public function testLogStatusChangeCreatesLog(): void
    {
        // Arrange
        $inquiry = $this->createTestInquiry();
        $user = $this->createTestUser();

        $this->security
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($log) use ($inquiry) {
                return $log instanceof InquiryLog
                    && $log->getInquiry() === $inquiry
                    && $log->getPreviousStatus() === Inquiry::STATUS_SUBMITTED
                    && $log->getNewStatus() === Inquiry::STATUS_IN_REVIEW
                    && $log->getComment() === 'Status updated by admin';
            }));

        // Act
        $log = $this->service->logStatusChange(
            $inquiry,
            Inquiry::STATUS_SUBMITTED,
            Inquiry::STATUS_IN_REVIEW,
            'Status updated by admin'
        );

        // Assert
        $this->assertInstanceOf(InquiryLog::class, $log);
        $this->assertEquals(Inquiry::STATUS_SUBMITTED, $log->getPreviousStatus());
        $this->assertEquals(Inquiry::STATUS_IN_REVIEW, $log->getNewStatus());
        $this->assertEquals('Status updated by admin', $log->getComment());
        $this->assertEquals($user, $log->getChangedBy());
    }

    public function testLogStatusChangeAddsMetadata(): void
    {
        // Arrange
        $inquiry = $this->createTestInquiry();
        $user = $this->createTestUser();

        $this->security
            ->method('getUser')
            ->willReturn($user);

        $this->entityManager
            ->method('persist');

        $customMetadata = [
            'action' => 'manual_review',
            'notes' => 'Requires urgent attention'
        ];

        // Act
        $log = $this->service->logStatusChange(
            $inquiry,
            Inquiry::STATUS_SUBMITTED,
            Inquiry::STATUS_IN_REVIEW,
            'Status updated',
            $customMetadata
        );

        // Assert
        $metadata = $log->getMetadata();
        $this->assertArrayHasKey('inquiry_number', $metadata);
        $this->assertArrayHasKey('machines_count', $metadata);
        $this->assertArrayHasKey('action', $metadata);
        $this->assertArrayHasKey('notes', $metadata);
        $this->assertEquals('manual_review', $metadata['action']);
        $this->assertEquals('Requires urgent attention', $metadata['notes']);
        $this->assertEquals($inquiry->getInquiryNumber(), $metadata['inquiry_number']);
    }

    public function testLogStatusChangeSkipsDraftStatus(): void
    {
        // Arrange
        $inquiry = $this->createTestInquiry();

        $this->entityManager
            ->expects($this->never())
            ->method('persist');

        // Act - trying to transition TO draft should be skipped
        $log = $this->service->logStatusChange(
            $inquiry,
            Inquiry::STATUS_SUBMITTED,
            Inquiry::STATUS_DRAFT,
            'Reverting to draft'
        );

        // Assert
        $this->assertNull($log);
    }

    public function testLogStatusChangeAllowsFromDraft(): void
    {
        // Arrange
        $inquiry = $this->createTestInquiry();
        $user = $this->createTestUser();

        $this->security
            ->method('getUser')
            ->willReturn($user);

        $this->entityManager
            ->expects($this->once())
            ->method('persist');

        // Act - transition FROM draft should be allowed
        $log = $this->service->logStatusChange(
            $inquiry,
            Inquiry::STATUS_DRAFT,
            Inquiry::STATUS_SUBMITTED,
            'Submitted from draft'
        );

        // Assert
        $this->assertInstanceOf(InquiryLog::class, $log);
        $this->assertEquals(Inquiry::STATUS_DRAFT, $log->getPreviousStatus());
        $this->assertEquals(Inquiry::STATUS_SUBMITTED, $log->getNewStatus());
    }

    public function testLogStatusChangeSkipsWhenStatusUnchanged(): void
    {
        // Arrange
        $inquiry = $this->createTestInquiry();

        $this->entityManager
            ->expects($this->never())
            ->method('persist');

        // Act - same status should be skipped
        $log = $this->service->logStatusChange(
            $inquiry,
            Inquiry::STATUS_SUBMITTED,
            Inquiry::STATUS_SUBMITTED,
            'No change'
        );

        // Assert
        $this->assertNull($log);
    }

    public function testLogStatusChangeWithoutUser(): void
    {
        // Arrange
        $inquiry = $this->createTestInquiry();

        $this->security
            ->expects($this->once())
            ->method('getUser')
            ->willReturn(null); // No authenticated user

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($log) {
                return $log instanceof InquiryLog
                    && $log->getChangedBy() === null;
            }));

        // Act
        $log = $this->service->logStatusChange(
            $inquiry,
            Inquiry::STATUS_SUBMITTED,
            Inquiry::STATUS_IN_REVIEW,
            'System update'
        );

        // Assert
        $this->assertInstanceOf(InquiryLog::class, $log);
        $this->assertNull($log->getChangedBy());
    }

    public function testLogInquirySubmissionCreatesLog(): void
    {
        // Arrange
        $inquiry = $this->createTestInquiry();
        $inquiry->setStatus(Inquiry::STATUS_SUBMITTED);
        $user = $this->createTestUser();

        $this->security
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($log) use ($inquiry) {
                return $log instanceof InquiryLog
                    && $log->getInquiry() === $inquiry
                    && $log->getPreviousStatus() === Inquiry::STATUS_DRAFT
                    && $log->getNewStatus() === Inquiry::STATUS_SUBMITTED
                    && $log->getComment() === 'Custom submission comment';
            }));

        // Act
        $log = $this->service->logInquirySubmission(
            $inquiry,
            'Custom submission comment'
        );

        // Assert
        $this->assertInstanceOf(InquiryLog::class, $log);
        $this->assertEquals(Inquiry::STATUS_DRAFT, $log->getPreviousStatus());
        $this->assertEquals(Inquiry::STATUS_SUBMITTED, $log->getNewStatus());
        $this->assertEquals('Custom submission comment', $log->getComment());
    }

    public function testLogInquirySubmissionWithDefaultComment(): void
    {
        // Arrange
        $inquiry = $this->createTestInquiry();
        $inquiry->setStatus(Inquiry::STATUS_SUBMITTED);

        $this->security
            ->method('getUser')
            ->willReturn(null);

        $this->entityManager
            ->method('persist');

        // Act
        $log = $this->service->logInquirySubmission($inquiry);

        // Assert
        $this->assertEquals('Inquiry submitted from draft', $log->getComment());
    }

    public function testLogInquirySubmissionAddsSubmissionMetadata(): void
    {
        // Arrange
        $inquiry = $this->createTestInquiry();
        $inquiry->setStatus(Inquiry::STATUS_SUBMITTED);

        $this->security
            ->method('getUser')
            ->willReturn(null);

        $this->entityManager
            ->method('persist');

        $customMetadata = [
            'source' => 'mobile_app',
            'version' => '2.1.0'
        ];

        // Act
        $log = $this->service->logInquirySubmission(
            $inquiry,
            'Submission via mobile',
            $customMetadata
        );

        // Assert
        $metadata = $log->getMetadata();
        $this->assertArrayHasKey('submission_type', $metadata);
        $this->assertEquals('draft_to_submitted', $metadata['submission_type']);
        $this->assertArrayHasKey('source', $metadata);
        $this->assertEquals('mobile_app', $metadata['source']);
        $this->assertArrayHasKey('inquiry_number', $metadata);
        $this->assertArrayHasKey('machines_count', $metadata);
    }

    public function testLogInquirySubmissionReturnsNullWhenNotSubmitted(): void
    {
        // Arrange
        $inquiry = $this->createTestInquiry();
        $inquiry->setStatus(Inquiry::STATUS_DRAFT); // Still draft

        $this->entityManager
            ->expects($this->never())
            ->method('persist');

        // Act
        $log = $this->service->logInquirySubmission($inquiry);

        // Assert
        $this->assertNull($log);
    }

    public function testGetInquiryHistory(): void
    {
        // Arrange
        $inquiry = $this->createTestInquiry();
        $expectedLogs = [
            $this->createMock(InquiryLog::class),
            $this->createMock(InquiryLog::class),
            $this->createMock(InquiryLog::class)
        ];

        $this->repository
            ->expects($this->once())
            ->method('findByInquiry')
            ->with($inquiry)
            ->willReturn($expectedLogs);

        // Act
        $logs = $this->service->getInquiryHistory($inquiry);

        // Assert
        $this->assertIsArray($logs);
        $this->assertCount(3, $logs);
        $this->assertEquals($expectedLogs, $logs);
    }

    public function testGetInquiryHistoryEmpty(): void
    {
        // Arrange
        $inquiry = $this->createTestInquiry();

        $this->repository
            ->expects($this->once())
            ->method('findByInquiry')
            ->with($inquiry)
            ->willReturn([]);

        // Act
        $logs = $this->service->getInquiryHistory($inquiry);

        // Assert
        $this->assertIsArray($logs);
        $this->assertEmpty($logs);
    }

    public function testGetLastStatusChange(): void
    {
        // Arrange
        $inquiry = $this->createTestInquiry();
        $lastLog = $this->createMock(InquiryLog::class);
        $olderLog = $this->createMock(InquiryLog::class);

        $this->repository
            ->expects($this->once())
            ->method('findByInquiry')
            ->with($inquiry)
            ->willReturn([$lastLog, $olderLog]); // Assuming ordered by date DESC

        // Act
        $log = $this->service->getLastStatusChange($inquiry);

        // Assert
        $this->assertEquals($lastLog, $log);
    }

    public function testGetLastStatusChangeReturnsNullWhenNoHistory(): void
    {
        // Arrange
        $inquiry = $this->createTestInquiry();

        $this->repository
            ->expects($this->once())
            ->method('findByInquiry')
            ->with($inquiry)
            ->willReturn([]);

        // Act
        $log = $this->service->getLastStatusChange($inquiry);

        // Assert
        $this->assertNull($log);
    }

    public function testLogBulkStatusChange(): void
    {
        // Arrange
        $inquiry1 = $this->createTestInquiry();
        $inquiry1->setStatus(Inquiry::STATUS_SUBMITTED);

        $inquiry2 = $this->createTestInquiry();
        $inquiry2->setStatus(Inquiry::STATUS_IN_REVIEW);

        $inquiry3 = $this->createTestInquiry();
        $inquiry3->setStatus(Inquiry::STATUS_SUBMITTED);

        $inquiries = [$inquiry1, $inquiry2, $inquiry3];
        $user = $this->createTestUser();

        $this->security
            ->method('getUser')
            ->willReturn($user);

        $this->entityManager
            ->expects($this->exactly(3))
            ->method('persist');

        // Act
        $logs = $this->service->logBulkStatusChange(
            $inquiries,
            Inquiry::STATUS_IN_PROGRESS,
            'Bulk update to in progress'
        );

        // Assert
        $this->assertIsArray($logs);
        $this->assertCount(3, $logs);

        foreach ($logs as $log) {
            $this->assertInstanceOf(InquiryLog::class, $log);
            $this->assertEquals(Inquiry::STATUS_IN_PROGRESS, $log->getNewStatus());
            $this->assertEquals('Bulk update to in progress', $log->getComment());
            $metadata = $log->getMetadata();
            $this->assertArrayHasKey('bulk_update', $metadata);
            $this->assertTrue($metadata['bulk_update']);
        }

        // Verify statuses were updated
        $this->assertEquals(Inquiry::STATUS_IN_PROGRESS, $inquiry1->getStatus());
        $this->assertEquals(Inquiry::STATUS_IN_PROGRESS, $inquiry2->getStatus());
        $this->assertEquals(Inquiry::STATUS_IN_PROGRESS, $inquiry3->getStatus());
    }

    public function testLogBulkStatusChangeSkipsSameStatus(): void
    {
        // Arrange
        $inquiry1 = $this->createTestInquiry();
        $inquiry1->setStatus(Inquiry::STATUS_SUBMITTED);

        $inquiry2 = $this->createTestInquiry();
        $inquiry2->setStatus(Inquiry::STATUS_SUBMITTED); // Same status as target

        $inquiries = [$inquiry1, $inquiry2];
        $user = $this->createTestUser();

        $this->security
            ->method('getUser')
            ->willReturn($user);

        $this->entityManager
            ->expects($this->never())
            ->method('persist'); // Both already have target status

        // Act
        $logs = $this->service->logBulkStatusChange(
            $inquiries,
            Inquiry::STATUS_SUBMITTED,
            'Bulk update'
        );

        // Assert - only inquiry1's status changed (from submitted to submitted - wait, this won't create a log)
        // Actually, both inquiries are already submitted, so no logs should be created
        $this->assertIsArray($logs);
        $this->assertEmpty($logs); // Both inquiries already have the target status
    }

    public function testLogBulkStatusChangeSkipsDraftTarget(): void
    {
        // Arrange
        $inquiry1 = $this->createTestInquiry();
        $inquiry1->setStatus(Inquiry::STATUS_SUBMITTED);

        $inquiry2 = $this->createTestInquiry();
        $inquiry2->setStatus(Inquiry::STATUS_IN_REVIEW);

        $inquiries = [$inquiry1, $inquiry2];

        $this->entityManager
            ->expects($this->never())
            ->method('persist'); // No logs should be created when target is draft

        // Act
        $logs = $this->service->logBulkStatusChange(
            $inquiries,
            Inquiry::STATUS_DRAFT,
            'Reverting to draft'
        );

        // Assert
        $this->assertIsArray($logs);
        $this->assertEmpty($logs);
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
            ->onlyMethods(['getId', 'getMachines', 'addLog'])
            ->getMock();

        $inquiryId = Uuid::v4();
        $inquiry->method('getId')->willReturn($inquiryId);
        $inquiry->method('getMachines')->willReturn(new ArrayCollection());
        $inquiry->method('addLog')->willReturn($inquiry);

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
        $user->setEmail('admin@test.com');
        $user->setFirstName('Admin');
        $user->setLastName('User');
        $user->setPhoneNumber('+1234567890');
        $user->setClient($client);
        $user->setPassword('dummy');

        return $user;
    }
}
