<?php

namespace App\Tests\Service;

use App\Entity\Client;
use App\Entity\Order;
use App\Entity\OrderLog;
use App\Entity\User;
use App\Repository\OrderLogRepository;
use App\Service\OrderLogService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Uid\Uuid;

class OrderLogServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private Security $security;
    private OrderLogService $service;
    private OrderLogRepository $repository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->repository = $this->createMock(OrderLogRepository::class);

        $this->entityManager
            ->method('getRepository')
            ->with(OrderLog::class)
            ->willReturn($this->repository);

        $this->service = new OrderLogService(
            $this->entityManager,
            $this->security
        );
    }

    public function testLogStatusChangeCreatesLog(): void
    {
        // Arrange
        $order = $this->createTestOrder();
        $user = $this->createTestUser();

        $this->security
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($log) use ($order) {
                return $log instanceof OrderLog
                    && $log->getOrder() === $order
                    && $log->getPreviousStatus() === Order::STATUS_SUBMITTED
                    && $log->getNewStatus() === Order::STATUS_CONFIRMED
                    && $log->getComment() === 'Order confirmed by admin';
            }));

        // Act
        $log = $this->service->logStatusChange(
            $order,
            Order::STATUS_SUBMITTED,
            Order::STATUS_CONFIRMED,
            'Order confirmed by admin'
        );

        // Assert
        $this->assertInstanceOf(OrderLog::class, $log);
        $this->assertEquals(Order::STATUS_SUBMITTED, $log->getPreviousStatus());
        $this->assertEquals(Order::STATUS_CONFIRMED, $log->getNewStatus());
        $this->assertEquals('Order confirmed by admin', $log->getComment());
        $this->assertEquals($user, $log->getChangedBy());
    }

    public function testLogStatusChangeAddsMetadata(): void
    {
        // Arrange
        $order = $this->createTestOrder();
        $user = $this->createTestUser();

        $this->security
            ->method('getUser')
            ->willReturn($user);

        $this->entityManager
            ->method('persist');

        $customMetadata = [
            'action' => 'payment_confirmed',
            'payment_method' => 'credit_card'
        ];

        // Act
        $log = $this->service->logStatusChange(
            $order,
            Order::STATUS_SUBMITTED,
            Order::STATUS_CONFIRMED,
            'Payment confirmed',
            $customMetadata
        );

        // Assert
        $metadata = $log->getMetadata();
        $this->assertArrayHasKey('order_number', $metadata);
        $this->assertArrayHasKey('order_total', $metadata);
        $this->assertArrayHasKey('items_count', $metadata);
        $this->assertArrayHasKey('action', $metadata);
        $this->assertArrayHasKey('payment_method', $metadata);
        $this->assertEquals('payment_confirmed', $metadata['action']);
        $this->assertEquals('credit_card', $metadata['payment_method']);
        $this->assertEquals($order->getOrderNumber(), $metadata['order_number']);
        $this->assertEquals($order->getTotalAmount(), $metadata['order_total']);
    }

    public function testLogStatusChangeSkipsDraftStatus(): void
    {
        // Arrange
        $order = $this->createTestOrder();

        $this->entityManager
            ->expects($this->never())
            ->method('persist');

        // Act - trying to transition TO draft should be skipped
        $log = $this->service->logStatusChange(
            $order,
            Order::STATUS_SUBMITTED,
            Order::STATUS_DRAFT,
            'Reverting to draft'
        );

        // Assert
        $this->assertNull($log);
    }

    public function testLogStatusChangeAllowsFromDraft(): void
    {
        // Arrange
        $order = $this->createTestOrder();
        $user = $this->createTestUser();

        $this->security
            ->method('getUser')
            ->willReturn($user);

        $this->entityManager
            ->expects($this->once())
            ->method('persist');

        // Act - transition FROM draft should be allowed
        $log = $this->service->logStatusChange(
            $order,
            Order::STATUS_DRAFT,
            Order::STATUS_SUBMITTED,
            'Submitted from draft'
        );

        // Assert
        $this->assertInstanceOf(OrderLog::class, $log);
        $this->assertEquals(Order::STATUS_DRAFT, $log->getPreviousStatus());
        $this->assertEquals(Order::STATUS_SUBMITTED, $log->getNewStatus());
    }

    public function testLogStatusChangeSkipsWhenStatusUnchanged(): void
    {
        // Arrange
        $order = $this->createTestOrder();

        $this->entityManager
            ->expects($this->never())
            ->method('persist');

        // Act - same status should be skipped
        $log = $this->service->logStatusChange(
            $order,
            Order::STATUS_CONFIRMED,
            Order::STATUS_CONFIRMED,
            'No change'
        );

        // Assert
        $this->assertNull($log);
    }

    public function testLogStatusChangeWithoutUser(): void
    {
        // Arrange
        $order = $this->createTestOrder();

        $this->security
            ->expects($this->once())
            ->method('getUser')
            ->willReturn(null); // No authenticated user

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($log) {
                return $log instanceof OrderLog
                    && $log->getChangedBy() === null;
            }));

        // Act
        $log = $this->service->logStatusChange(
            $order,
            Order::STATUS_SUBMITTED,
            Order::STATUS_CONFIRMED,
            'System update'
        );

        // Assert
        $this->assertInstanceOf(OrderLog::class, $log);
        $this->assertNull($log->getChangedBy());
    }

    public function testLogOrderSubmissionCreatesLog(): void
    {
        // Arrange
        $order = $this->createTestOrder();
        $order->setStatus(Order::STATUS_SUBMITTED);
        $user = $this->createTestUser();

        $this->security
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($log) use ($order) {
                return $log instanceof OrderLog
                    && $log->getOrder() === $order
                    && $log->getPreviousStatus() === Order::STATUS_DRAFT
                    && $log->getNewStatus() === Order::STATUS_SUBMITTED
                    && $log->getComment() === 'Custom submission comment';
            }));

        // Act
        $log = $this->service->logOrderSubmission(
            $order,
            'Custom submission comment'
        );

        // Assert
        $this->assertInstanceOf(OrderLog::class, $log);
        $this->assertEquals(Order::STATUS_DRAFT, $log->getPreviousStatus());
        $this->assertEquals(Order::STATUS_SUBMITTED, $log->getNewStatus());
        $this->assertEquals('Custom submission comment', $log->getComment());
    }

    public function testLogOrderSubmissionWithDefaultComment(): void
    {
        // Arrange
        $order = $this->createTestOrder();
        $order->setStatus(Order::STATUS_SUBMITTED);

        $this->security
            ->method('getUser')
            ->willReturn(null);

        $this->entityManager
            ->method('persist');

        // Act
        $log = $this->service->logOrderSubmission($order);

        // Assert
        $this->assertEquals('Order submitted from draft', $log->getComment());
    }

    public function testLogOrderSubmissionAddsSubmissionMetadata(): void
    {
        // Arrange
        $order = $this->createTestOrder();
        $order->setStatus(Order::STATUS_SUBMITTED);

        $this->security
            ->method('getUser')
            ->willReturn(null);

        $this->entityManager
            ->method('persist');

        // Act
        $log = $this->service->logOrderSubmission($order);

        // Assert
        $metadata = $log->getMetadata();
        $this->assertArrayHasKey('submission_type', $metadata);
        $this->assertEquals('draft_to_submitted', $metadata['submission_type']);
        $this->assertArrayHasKey('order_number', $metadata);
        $this->assertArrayHasKey('order_total', $metadata);
        $this->assertArrayHasKey('items_count', $metadata);
        $this->assertEquals($order->getOrderNumber(), $metadata['order_number']);
    }

    public function testLogOrderSubmissionReturnsNullWhenNotSubmitted(): void
    {
        // Arrange
        $order = $this->createTestOrder();
        $order->setStatus(Order::STATUS_DRAFT); // Still draft

        $this->entityManager
            ->expects($this->never())
            ->method('persist');

        // Act
        $log = $this->service->logOrderSubmission($order);

        // Assert
        $this->assertNull($log);
    }

    public function testGetOrderHistory(): void
    {
        // Arrange
        $order = $this->createTestOrder();
        $expectedLogs = [
            $this->createMock(OrderLog::class),
            $this->createMock(OrderLog::class),
            $this->createMock(OrderLog::class)
        ];

        $this->repository
            ->expects($this->once())
            ->method('findByOrder')
            ->with($order)
            ->willReturn($expectedLogs);

        // Act
        $logs = $this->service->getOrderHistory($order);

        // Assert
        $this->assertIsArray($logs);
        $this->assertCount(3, $logs);
        $this->assertEquals($expectedLogs, $logs);
    }

    public function testGetOrderHistoryEmpty(): void
    {
        // Arrange
        $order = $this->createTestOrder();

        $this->repository
            ->expects($this->once())
            ->method('findByOrder')
            ->with($order)
            ->willReturn([]);

        // Act
        $logs = $this->service->getOrderHistory($order);

        // Assert
        $this->assertIsArray($logs);
        $this->assertEmpty($logs);
    }

    public function testGetLastStatusChange(): void
    {
        // Arrange
        $order = $this->createTestOrder();
        $lastLog = $this->createMock(OrderLog::class);

        $this->repository
            ->expects($this->once())
            ->method('findLatestForOrder')
            ->with($order)
            ->willReturn($lastLog);

        // Act
        $log = $this->service->getLastStatusChange($order);

        // Assert
        $this->assertEquals($lastLog, $log);
    }

    public function testGetLastStatusChangeReturnsNullWhenNoHistory(): void
    {
        // Arrange
        $order = $this->createTestOrder();

        $this->repository
            ->expects($this->once())
            ->method('findLatestForOrder')
            ->with($order)
            ->willReturn(null);

        // Act
        $log = $this->service->getLastStatusChange($order);

        // Assert
        $this->assertNull($log);
    }

    public function testLogBulkStatusChange(): void
    {
        // Arrange
        $order1 = $this->createTestOrder();
        $order1->setStatus(Order::STATUS_SUBMITTED);

        $order2 = $this->createTestOrder();
        $order2->setStatus(Order::STATUS_CONFIRMED);

        $order3 = $this->createTestOrder();
        $order3->setStatus(Order::STATUS_SUBMITTED);

        $orders = [$order1, $order2, $order3];
        $user = $this->createTestUser();

        $this->security
            ->method('getUser')
            ->willReturn($user);

        $this->entityManager
            ->expects($this->exactly(3))
            ->method('persist');

        // Act
        $logs = $this->service->logBulkStatusChange(
            $orders,
            Order::STATUS_DISPATCHED,
            'Bulk dispatch'
        );

        // Assert
        $this->assertIsArray($logs);
        $this->assertCount(3, $logs);

        foreach ($logs as $log) {
            $this->assertInstanceOf(OrderLog::class, $log);
            $this->assertEquals(Order::STATUS_DISPATCHED, $log->getNewStatus());
            $this->assertEquals('Bulk dispatch', $log->getComment());
            $metadata = $log->getMetadata();
            $this->assertArrayHasKey('bulk_update', $metadata);
            $this->assertTrue($metadata['bulk_update']);
        }

        // Verify statuses were updated
        $this->assertEquals(Order::STATUS_DISPATCHED, $order1->getStatus());
        $this->assertEquals(Order::STATUS_DISPATCHED, $order2->getStatus());
        $this->assertEquals(Order::STATUS_DISPATCHED, $order3->getStatus());
    }

    public function testLogBulkStatusChangeSkipsSameStatus(): void
    {
        // Arrange
        $order1 = $this->createTestOrder();
        $order1->setStatus(Order::STATUS_CONFIRMED);

        $order2 = $this->createTestOrder();
        $order2->setStatus(Order::STATUS_CONFIRMED); // Same status as target

        $orders = [$order1, $order2];
        $user = $this->createTestUser();

        $this->security
            ->method('getUser')
            ->willReturn($user);

        $this->entityManager
            ->expects($this->never())
            ->method('persist'); // Both already have target status

        // Act
        $logs = $this->service->logBulkStatusChange(
            $orders,
            Order::STATUS_CONFIRMED,
            'Bulk update'
        );

        // Assert
        $this->assertIsArray($logs);
        $this->assertEmpty($logs);
    }

    public function testLogBulkStatusChangeSkipsDraftTarget(): void
    {
        // Arrange
        $order1 = $this->createTestOrder();
        $order1->setStatus(Order::STATUS_SUBMITTED);

        $order2 = $this->createTestOrder();
        $order2->setStatus(Order::STATUS_CONFIRMED);

        $orders = [$order1, $order2];

        $this->entityManager
            ->expects($this->never())
            ->method('persist'); // No logs should be created when target is draft

        // Act
        $logs = $this->service->logBulkStatusChange(
            $orders,
            Order::STATUS_DRAFT,
            'Reverting to draft'
        );

        // Assert
        $this->assertIsArray($logs);
        $this->assertEmpty($logs);
    }

    public function testLogBulkStatusChangeMixedResults(): void
    {
        // Arrange
        $order1 = $this->createTestOrder();
        $order1->setStatus(Order::STATUS_SUBMITTED);

        $order2 = $this->createTestOrder();
        $order2->setStatus(Order::STATUS_DISPATCHED); // Already at target

        $order3 = $this->createTestOrder();
        $order3->setStatus(Order::STATUS_CONFIRMED);

        $orders = [$order1, $order2, $order3];
        $user = $this->createTestUser();

        $this->security
            ->method('getUser')
            ->willReturn($user);

        // Only 2 persists: order1 and order3 (order2 already at target status)
        $this->entityManager
            ->expects($this->exactly(2))
            ->method('persist');

        // Act
        $logs = $this->service->logBulkStatusChange(
            $orders,
            Order::STATUS_DISPATCHED,
            'Bulk dispatch'
        );

        // Assert
        $this->assertIsArray($logs);
        $this->assertCount(2, $logs); // Only order1 and order3 logged
    }

    private function createTestOrder(): Order
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

        $order = $this->getMockBuilder(Order::class)
            ->onlyMethods(['getId', 'getItems', 'addLog'])
            ->getMock();

        $orderId = Uuid::v4();
        $order->method('getId')->willReturn($orderId);
        $order->method('getItems')->willReturn(new ArrayCollection());
        $order->method('addLog')->willReturn($order);

        $order->setOrderNumber('ORD-TEST-001');
        $order->setUser($user);
        $order->setStatus(Order::STATUS_SUBMITTED);
        $order->setTotalAmount(250.00);
        $order->setCreatedAt(new \DateTime());
        $order->setUpdatedAt(new \DateTime());

        return $order;
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
