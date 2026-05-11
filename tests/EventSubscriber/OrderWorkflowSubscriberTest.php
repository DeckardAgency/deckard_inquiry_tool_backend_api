<?php

namespace App\Tests\EventSubscriber;

use App\Entity\Client;
use App\Entity\Order;
use App\Entity\OrderLog;
use App\Entity\User;
use App\EventSubscriber\OrderWorkflowSubscriber;
use App\Message\OrderStatusChangedMessage;
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

class OrderWorkflowSubscriberTest extends TestCase
{
    private MessageBusInterface $messageBus;
    private LoggerInterface $logger;
    private Security $security;
    private OrderWorkflowSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->security = $this->createMock(Security::class);

        $this->subscriber = new OrderWorkflowSubscriber(
            $this->messageBus,
            $this->logger,
            $this->security
        );
    }

    public function testGetSubscribedEvents(): void
    {
        $events = OrderWorkflowSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey('workflow.order.completed', $events);
        $this->assertEquals('onCompleted', $events['workflow.order.completed']);
        $this->assertArrayHasKey('workflow.order.guard', $events);
        $this->assertEquals('onGuard', $events['workflow.order.guard']);
    }

    public function testOnCompletedSuccessfully(): void
    {
        // Arrange
        $order = $this->createTestOrder();
        $user = $this->createTestUser();

        $this->security
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $transition = new Transition('confirm', Order::STATUS_SUBMITTED, Order::STATUS_CONFIRMED);
        $marking = new Marking([Order::STATUS_CONFIRMED => 1]);

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->method('getName')->willReturn('order');
        $event = new CompletedEvent($order, $marking, $transition, $workflow);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($message) use ($order) {
                return $message instanceof OrderStatusChangedMessage
                    && $message->getOrderId()->equals($order->getId())
                    && $message->getOldStatus() === Order::STATUS_SUBMITTED
                    && $message->getNewStatus() === Order::STATUS_CONFIRMED
                    && $message->getModifiedBy() !== null
                    && $message->getModifiedBy()['fullName'] === 'Test User';
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
                    $this->assertEquals(Order::STATUS_SUBMITTED, $context['old_status']);
                    $this->assertEquals(Order::STATUS_CONFIRMED, $context['new_status']);
                }
            });

        // Act
        $this->subscriber->onCompleted($event);
    }

    public function testOnCompletedSkipsDraftOrders(): void
    {
        // Arrange
        $order = $this->createTestOrder();
        $order->setStatus(Order::STATUS_DRAFT);

        $transition = new Transition('submit', Order::STATUS_DRAFT, Order::STATUS_SUBMITTED);
        $marking = new Marking([Order::STATUS_SUBMITTED => 1]);

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->method('getName')->willReturn('order');
        $event = new CompletedEvent($order, $marking, $transition, $workflow);

        // Should NOT dispatch message for draft orders
        $this->messageBus
            ->expects($this->never())
            ->method('dispatch');

        // Act
        $this->subscriber->onCompleted($event);
    }

    public function testOnCompletedExtractsOldStatusFromTransition(): void
    {
        // Arrange
        $order = $this->createTestOrder();
        $user = $this->createTestUser();

        $this->security
            ->method('getUser')
            ->willReturn($user);

        // Test transition from confirmed to dispatched
        $transition = new Transition('dispatch', Order::STATUS_CONFIRMED, Order::STATUS_DISPATCHED);
        $marking = new Marking([Order::STATUS_DISPATCHED => 1]);

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->method('getName')->willReturn('order');
        $event = new CompletedEvent($order, $marking, $transition, $workflow);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($message) {
                return $message instanceof OrderStatusChangedMessage
                    && $message->getOldStatus() === Order::STATUS_CONFIRMED
                    && $message->getNewStatus() === Order::STATUS_DISPATCHED;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        // Act
        $this->subscriber->onCompleted($event);
    }

    public function testOnCompletedWithoutAuthenticatedUser(): void
    {
        // Arrange
        $order = $this->createTestOrder();

        $this->security
            ->expects($this->once())
            ->method('getUser')
            ->willReturn(null); // No authenticated user

        $transition = new Transition('confirm', Order::STATUS_SUBMITTED, Order::STATUS_CONFIRMED);
        $marking = new Marking([Order::STATUS_CONFIRMED => 1]);

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->method('getName')->willReturn('order');
        $event = new CompletedEvent($order, $marking, $transition, $workflow);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($message) {
                // Message should still be dispatched, but modifiedBy should be null
                return $message instanceof OrderStatusChangedMessage
                    && $message->getModifiedBy() === null;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        // Act
        $this->subscriber->onCompleted($event);
    }

    public function testOnCompletedLogsTransitionDetails(): void
    {
        // Arrange
        $order = $this->createTestOrder();
        $user = $this->createTestUser();

        $this->security
            ->method('getUser')
            ->willReturn($user);

        $transition = new Transition('complete', Order::STATUS_DISPATCHED, Order::STATUS_COMPLETED);
        $marking = new Marking([Order::STATUS_COMPLETED => 1]);

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->method('getName')->willReturn('order');
        $event = new CompletedEvent($order, $marking, $transition, $workflow);

        $this->logger
            ->expects($this->atLeast(2))
            ->method('info')
            ->willReturnCallback(function ($message, $context) {
                static $callCount = 0;
                $callCount++;

                if ($callCount === 1) {
                    // First log should contain transition details
                    $this->assertArrayHasKey('order_id', $context);
                    $this->assertArrayHasKey('order_number', $context);
                    $this->assertArrayHasKey('transition', $context);
                    $this->assertArrayHasKey('old_status', $context);
                    $this->assertArrayHasKey('new_status', $context);
                    $this->assertArrayHasKey('modified_by', $context);
                    $this->assertEquals('complete', $context['transition']);
                    $this->assertEquals(Order::STATUS_DISPATCHED, $context['old_status']);
                    $this->assertEquals(Order::STATUS_COMPLETED, $context['new_status']);
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
        $order = $this->createTestOrder();
        $user = $this->createTestUser();
        $user->setEmail('john.admin@test.com');
        $user->setFirstName('John');
        $user->setLastName('Admin');

        $this->security
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $transition = new Transition('cancel', Order::STATUS_SUBMITTED, Order::STATUS_CANCELED);
        $marking = new Marking([Order::STATUS_CANCELED => 1]);

        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->method('getName')->willReturn('order');
        $event = new CompletedEvent($order, $marking, $transition, $workflow);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($message) {
                $modifiedBy = $message->getModifiedBy();
                return $message instanceof OrderStatusChangedMessage
                    && $modifiedBy !== null
                    && isset($modifiedBy['id'])
                    && isset($modifiedBy['email'])
                    && $modifiedBy['email'] === 'john.admin@test.com'
                    && $modifiedBy['fullName'] === 'John Admin'
                    && $modifiedBy['firstName'] === 'John'
                    && $modifiedBy['lastName'] === 'Admin';
            }))
            ->willReturn(new Envelope(new \stdClass()));

        // Act
        $this->subscriber->onCompleted($event);
    }

    public function testOnGuardBlocksDispatchWithoutShippingAddress(): void
    {
        // Arrange
        $order = $this->createTestOrder();
        $order->setShippingAddress(null); // No shipping address

        $transition = new Transition('dispatch', Order::STATUS_CONFIRMED, Order::STATUS_DISPATCHED);
        $workflow = $this->createMock(WorkflowInterface::class);
        $event = new GuardEvent($order, new Marking([Order::STATUS_CONFIRMED => 1]), $transition, $workflow);

        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                'Order dispatch blocked - missing shipping address',
                $this->arrayHasKey('order_id')
            );

        // Act
        $this->subscriber->onGuard($event);

        // Assert
        $this->assertTrue($event->isBlocked());
    }

    public function testOnGuardAllowsDispatchWithShippingAddress(): void
    {
        // Arrange
        $order = $this->createTestOrder();
        $order->setShippingAddress('123 Main St, City, Country');

        $transition = new Transition('dispatch', Order::STATUS_CONFIRMED, Order::STATUS_DISPATCHED);
        $workflow = $this->createMock(WorkflowInterface::class);
        $event = new GuardEvent($order, new Marking([Order::STATUS_CONFIRMED => 1]), $transition, $workflow);

        $this->logger
            ->expects($this->never())
            ->method('warning');

        // Act
        $this->subscriber->onGuard($event);

        // Assert
        $this->assertFalse($event->isBlocked());
    }

    public function testOnGuardIgnoresNonDispatchTransitions(): void
    {
        // Arrange
        $order = $this->createTestOrder();
        $order->setShippingAddress(null); // No shipping address, but transition is not 'dispatch'

        $transition = new Transition('confirm', Order::STATUS_SUBMITTED, Order::STATUS_CONFIRMED);
        $workflow = $this->createMock(WorkflowInterface::class);
        $event = new GuardEvent($order, new Marking([Order::STATUS_SUBMITTED => 1]), $transition, $workflow);

        $this->logger
            ->expects($this->never())
            ->method('warning');

        // Act
        $this->subscriber->onGuard($event);

        // Assert - should not block non-dispatch transitions
        $this->assertFalse($event->isBlocked());
    }

    public function testOnCompletedHandlesAllOrderStatuses(): void
    {
        $transitions = [
            ['submit', Order::STATUS_DRAFT, Order::STATUS_SUBMITTED],
            ['confirm', Order::STATUS_SUBMITTED, Order::STATUS_CONFIRMED],
            ['dispatch', Order::STATUS_CONFIRMED, Order::STATUS_DISPATCHED],
            ['complete', Order::STATUS_DISPATCHED, Order::STATUS_COMPLETED],
            ['cancel', Order::STATUS_SUBMITTED, Order::STATUS_CANCELED],
        ];

        foreach ($transitions as [$transitionName, $from, $to]) {
            // Reset mocks
            $this->setUp();

            $order = $this->createTestOrder();
            $order->setStatus($to);
            $user = $this->createTestUser();

            $this->security
                ->method('getUser')
                ->willReturn($user);

            $transition = new Transition($transitionName, $from, $to);
            $marking = new Marking([$to => 1]);

            $workflow = $this->createMock(WorkflowInterface::class);
            $workflow->method('getName')->willReturn('order');
            $event = new CompletedEvent($order, $marking, $transition, $workflow);

            $this->messageBus
                ->expects($this->once())
                ->method('dispatch')
                ->with($this->callback(function ($message) use ($from, $to) {
                    return $message instanceof OrderStatusChangedMessage
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
            ->onlyMethods(['getId'])
            ->getMock();

        $orderId = Uuid::v4();
        $order->method('getId')->willReturn($orderId);
        $order->setOrderNumber('ORD-TEST-001');
        $order->setUser($user);
        $order->setStatus(Order::STATUS_SUBMITTED);
        $order->setTotalAmount(100.00);
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
        $user->setEmail('test@test.com');
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setPhoneNumber('+1234567890');
        $user->setClient($client);
        $user->setPassword('dummy');

        return $user;
    }
}
