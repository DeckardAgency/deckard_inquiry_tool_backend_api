<?php

namespace App\Tests\MessageHandler;

use App\Entity\Client;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\OrderLog;
use App\Entity\Product;
use App\Entity\User;
use App\Message\OrderStatusChangedMessage;
use App\MessageHandler\OrderStatusChangedMessageHandler;
use App\Repository\OrderRepository;
use App\Service\OrderLogService;
use App\Service\PriceCalculator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;
use Twig\Loader\LoaderInterface;

class OrderStatusChangedMessageHandlerTest extends TestCase
{
    private OrderRepository $orderRepository;
    private MailerInterface $mailer;
    private Environment $twig;
    private LoggerInterface $logger;
    private PriceCalculator $priceCalculator;
    private OrderLogService $orderLogService;
    private EntityManagerInterface $entityManager;
    private LoaderInterface $twigLoader;
    private OrderStatusChangedMessageHandler $handler;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(OrderRepository::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->priceCalculator = $this->createMock(PriceCalculator::class);
        $this->orderLogService = $this->createMock(OrderLogService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->twigLoader = $this->createMock(LoaderInterface::class);
        $this->twig = $this->createMock(Environment::class);

        $this->twig->method('getLoader')->willReturn($this->twigLoader);

        $this->handler = new OrderStatusChangedMessageHandler(
            $this->orderRepository,
            $this->mailer,
            $this->twig,
            $this->logger,
            $this->priceCalculator,
            $this->orderLogService,
            $this->entityManager,
            'admin@test.com',
            'noreply@test.com'
        );
    }

    public function testHandleOrderStatusChangedSuccessfully(): void
    {
        // Arrange
        $orderId = Uuid::v4();
        $order = $this->createTestOrder($orderId);
        $message = new OrderStatusChangedMessage(
            $orderId,
            Order::STATUS_SUBMITTED,
            Order::STATUS_CONFIRMED
        );
        $message->setModifiedBy([
            'fullName' => 'John Admin',
            'email' => 'john@test.com'
        ]);

        $this->orderRepository
            ->expects($this->once())
            ->method('find')
            ->with($orderId)
            ->willReturn($order);

        $mockLog = $this->createMock(OrderLog::class);
        $mockLog->method('getId')->willReturn(Uuid::v4());
        $mockLog->method('getTransitionDescription')->willReturn('submitted → confirmed');

        $this->orderLogService
            ->expects($this->exactly(2)) // Once for status change, once for email sent
            ->method('logStatusChange')
            ->willReturn($mockLog);

        $this->orderLogService
            ->method('getOrderHistory')
            ->willReturn([]);

        $this->entityManager
            ->expects($this->exactly(2))
            ->method('flush');

        $this->twigLoader
            ->method('exists')
            ->willReturn(true);

        $this->priceCalculator
            ->expects($this->exactly(2))
            ->method('getOrderItemsDetails')
            ->willReturn([]);

        $this->priceCalculator
            ->expects($this->exactly(2))
            ->method('calculateOrderTotal')
            ->willReturn(100.00);

        $this->twig
            ->expects($this->exactly(2))
            ->method('render')
            ->willReturnCallback(function ($template, $params) use ($order) {
                if (str_contains($template, 'admin')) {
                    $this->assertEquals('emails/admin/order_confirmed.html.twig', $template);
                    $this->assertArrayHasKey('order', $params);
                    $this->assertArrayHasKey('status', $params);
                    $this->assertArrayHasKey('previousStatus', $params);
                    $this->assertArrayHasKey('modifiedBy', $params);
                    $this->assertArrayHasKey('modifiedByName', $params);
                } else {
                    $this->assertEquals('emails/customer/order_confirmed.html.twig', $template);
                    $this->assertArrayHasKey('order', $params);
                    $this->assertArrayHasKey('user', $params);
                    $this->assertArrayHasKey('status', $params);
                    $this->assertArrayHasKey('isFirstTimeCustomer', $params);
                }
                return '<html>Test Email</html>';
            });

        $this->mailer
            ->expects($this->exactly(2))
            ->method('send')
            ->with($this->isInstanceOf(Email::class));

        // Act
        ($this->handler)($message);
    }

    public function testSkipNotificationsForDraftStatus(): void
    {
        // Arrange
        $orderId = Uuid::v4();
        $order = $this->createTestOrder($orderId);
        $message = new OrderStatusChangedMessage(
            $orderId,
            Order::STATUS_SUBMITTED,
            Order::STATUS_DRAFT
        );

        $this->orderRepository
            ->expects($this->once())
            ->method('find')
            ->with($orderId)
            ->willReturn($order);

        $mockLog = $this->createMock(OrderLog::class);
        $mockLog->method('getId')->willReturn(Uuid::v4());

        $this->orderLogService
            ->expects($this->once())
            ->method('logStatusChange')
            ->willReturn($mockLog);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Should NOT send any emails for draft status
        $this->mailer
            ->expects($this->never())
            ->method('send');

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info')
            ->willReturnCallback(function ($message, $context) {
                static $found = false;
                if (str_contains($message, 'Skipping notifications for draft status')) {
                    $found = true;
                }
            });

        // Act
        ($this->handler)($message);
    }

    public function testOrderNotFound(): void
    {
        // Arrange
        $orderId = Uuid::v4();
        $message = new OrderStatusChangedMessage(
            $orderId,
            Order::STATUS_SUBMITTED,
            Order::STATUS_CONFIRMED
        );

        $this->orderRepository
            ->expects($this->once())
            ->method('find')
            ->with($orderId)
            ->willReturn(null);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Order not found in database',
                ['order_id' => $orderId->toRfc4122()]
            );

        $this->mailer
            ->expects($this->never())
            ->method('send');

        // Act
        ($this->handler)($message);
    }

    public function testSkipCustomerNotificationWhenNoUser(): void
    {
        // Arrange
        $orderId = Uuid::v4();
        $order = $this->createTestOrder($orderId);
        $order->setUser(null);

        $message = new OrderStatusChangedMessage(
            $orderId,
            Order::STATUS_SUBMITTED,
            Order::STATUS_CONFIRMED
        );

        $this->orderRepository
            ->expects($this->once())
            ->method('find')
            ->with($orderId)
            ->willReturn($order);

        $mockLog = $this->createMock(OrderLog::class);
        $mockLog->method('getId')->willReturn(Uuid::v4());

        $this->orderLogService
            ->method('logStatusChange')
            ->willReturn($mockLog);

        $this->orderLogService
            ->method('getOrderHistory')
            ->willReturn([]);

        $this->twigLoader
            ->method('exists')
            ->willReturn(true);

        $this->priceCalculator
            ->method('getOrderItemsDetails')
            ->willReturn([]);

        $this->priceCalculator
            ->method('calculateOrderTotal')
            ->willReturn(100.00);

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
                'Cannot send customer status notification: no user or email',
                $this->anything()
            );

        // Act
        ($this->handler)($message);
    }

    public function testHandleEmailSendingFailureGracefully(): void
    {
        // Arrange
        $orderId = Uuid::v4();
        $order = $this->createTestOrder($orderId);
        $message = new OrderStatusChangedMessage(
            $orderId,
            Order::STATUS_SUBMITTED,
            Order::STATUS_CONFIRMED
        );

        $this->orderRepository
            ->expects($this->once())
            ->method('find')
            ->with($orderId)
            ->willReturn($order);

        $mockLog = $this->createMock(OrderLog::class);
        $mockLog->method('getId')->willReturn(Uuid::v4());

        $this->orderLogService
            ->method('logStatusChange')
            ->willReturn($mockLog);

        $this->orderLogService
            ->method('getOrderHistory')
            ->willReturn([]);

        $this->twigLoader
            ->method('exists')
            ->willReturn(true);

        $this->priceCalculator
            ->method('getOrderItemsDetails')
            ->willReturn([]);

        $this->priceCalculator
            ->method('calculateOrderTotal')
            ->willReturn(100.00);

        $this->twig
            ->method('render')
            ->willReturn('<html>Test Email</html>');

        // Mailer throws exception
        $this->mailer
            ->expects($this->atLeastOnce())
            ->method('send')
            ->willThrowException(new \Exception('SMTP connection failed'));

        // Should log the error
        $this->logger
            ->expects($this->atLeastOnce())
            ->method('error')
            ->with(
                $this->stringContains('Failed to send'),
                $this->anything()
            );

        // Act - should not throw exception (email failures are caught)
        ($this->handler)($message);

        // Assert
        $this->assertTrue(true);
    }

    public function testStatusChangeToDispatched(): void
    {
        // Arrange
        $orderId = Uuid::v4();
        $order = $this->createTestOrder($orderId);
        $message = new OrderStatusChangedMessage(
            $orderId,
            Order::STATUS_CONFIRMED,
            Order::STATUS_DISPATCHED
        );

        $this->orderRepository
            ->method('find')
            ->willReturn($order);

        $mockLog = $this->createMock(OrderLog::class);
        $mockLog->method('getId')->willReturn(Uuid::v4());

        $this->orderLogService
            ->method('logStatusChange')
            ->willReturn($mockLog);

        $this->orderLogService
            ->method('getOrderHistory')
            ->willReturn([]);

        $this->twigLoader
            ->method('exists')
            ->willReturn(true);

        $this->priceCalculator
            ->method('getOrderItemsDetails')
            ->willReturn([]);

        $this->priceCalculator
            ->method('calculateOrderTotal')
            ->willReturn(100.00);

        $this->twig
            ->expects($this->exactly(2))
            ->method('render')
            ->willReturnCallback(function ($template) {
                if (str_contains($template, 'admin')) {
                    $this->assertEquals('emails/admin/order_dispatched.html.twig', $template);
                } else {
                    $this->assertEquals('emails/customer/order_dispatched.html.twig', $template);
                }
                return '<html>Test Email</html>';
            });

        $this->mailer
            ->expects($this->exactly(2))
            ->method('send');

        // Act
        ($this->handler)($message);
    }

    public function testStatusChangeToCompleted(): void
    {
        // Arrange
        $orderId = Uuid::v4();
        $order = $this->createTestOrder($orderId);
        $message = new OrderStatusChangedMessage(
            $orderId,
            Order::STATUS_DISPATCHED,
            Order::STATUS_COMPLETED
        );

        $this->orderRepository
            ->method('find')
            ->willReturn($order);

        $mockLog = $this->createMock(OrderLog::class);
        $mockLog->method('getId')->willReturn(Uuid::v4());

        $this->orderLogService
            ->method('logStatusChange')
            ->willReturn($mockLog);

        $this->orderLogService
            ->method('getOrderHistory')
            ->willReturn([]);

        $this->twigLoader
            ->method('exists')
            ->willReturn(true);

        $this->priceCalculator
            ->method('getOrderItemsDetails')
            ->willReturn([]);

        $this->priceCalculator
            ->method('calculateOrderTotal')
            ->willReturn(100.00);

        $this->twig
            ->expects($this->exactly(2))
            ->method('render')
            ->willReturnCallback(function ($template) {
                if (str_contains($template, 'admin')) {
                    $this->assertEquals('emails/admin/order_completed.html.twig', $template);
                } else {
                    $this->assertEquals('emails/customer/order_completed.html.twig', $template);
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
        $orderId = Uuid::v4();
        $order = $this->createTestOrder($orderId);
        $message = new OrderStatusChangedMessage(
            $orderId,
            Order::STATUS_SUBMITTED,
            Order::STATUS_CANCELED
        );

        $this->orderRepository
            ->method('find')
            ->willReturn($order);

        $mockLog = $this->createMock(OrderLog::class);
        $mockLog->method('getId')->willReturn(Uuid::v4());

        $this->orderLogService
            ->method('logStatusChange')
            ->willReturn($mockLog);

        $this->orderLogService
            ->method('getOrderHistory')
            ->willReturn([]);

        $this->twigLoader
            ->method('exists')
            ->willReturn(true);

        $this->priceCalculator
            ->method('getOrderItemsDetails')
            ->willReturn([]);

        $this->priceCalculator
            ->method('calculateOrderTotal')
            ->willReturn(100.00);

        $this->twig
            ->expects($this->exactly(2))
            ->method('render')
            ->willReturnCallback(function ($template) {
                if (str_contains($template, 'admin')) {
                    $this->assertEquals('emails/admin/order_canceled.html.twig', $template);
                } else {
                    $this->assertEquals('emails/customer/order_canceled.html.twig', $template);
                }
                return '<html>Test Email</html>';
            });

        $this->mailer
            ->expects($this->exactly(2))
            ->method('send');

        // Act
        ($this->handler)($message);
    }

    public function testFirstTimeCustomerDetection(): void
    {
        // Arrange
        $orderId = Uuid::v4();
        $order = $this->createTestOrder($orderId);
        $message = new OrderStatusChangedMessage(
            $orderId,
            Order::STATUS_SUBMITTED,
            Order::STATUS_CONFIRMED
        );

        $this->orderRepository
            ->method('find')
            ->willReturn($order);

        // Mock that customer has 0 completed orders (first time customer)
        $this->orderRepository
            ->expects($this->once())
            ->method('count')
            ->with([
                'user' => $order->getUser(),
                'status' => [
                    Order::STATUS_COMPLETED,
                    Order::STATUS_DISPATCHED,
                    Order::STATUS_CONFIRMED
                ]
            ])
            ->willReturn(0);

        $mockLog = $this->createMock(OrderLog::class);
        $mockLog->method('getId')->willReturn(Uuid::v4());

        $this->orderLogService
            ->method('logStatusChange')
            ->willReturn($mockLog);

        $this->orderLogService
            ->method('getOrderHistory')
            ->willReturn([]);

        $this->twigLoader
            ->method('exists')
            ->willReturn(true);

        $this->priceCalculator
            ->method('getOrderItemsDetails')
            ->willReturn([]);

        $this->priceCalculator
            ->method('calculateOrderTotal')
            ->willReturn(100.00);

        $this->twig
            ->expects($this->exactly(2))
            ->method('render')
            ->willReturnCallback(function ($template, $params) {
                if (str_contains($template, 'customer')) {
                    $this->assertArrayHasKey('isFirstTimeCustomer', $params);
                    $this->assertTrue($params['isFirstTimeCustomer']);
                }
                return '<html>Test Email</html>';
            });

        $this->mailer
            ->expects($this->exactly(2))
            ->method('send');

        // Act
        ($this->handler)($message);
    }

    public function testModifiedByNameExtraction(): void
    {
        // Arrange
        $orderId = Uuid::v4();
        $order = $this->createTestOrder($orderId);
        $message = new OrderStatusChangedMessage(
            $orderId,
            Order::STATUS_SUBMITTED,
            Order::STATUS_CONFIRMED
        );
        $message->setModifiedBy([
            'fullName' => 'Jane Admin',
            'email' => 'jane@test.com'
        ]);

        $this->orderRepository
            ->method('find')
            ->willReturn($order);

        $mockLog = $this->createMock(OrderLog::class);
        $mockLog->method('getId')->willReturn(Uuid::v4());

        $this->orderLogService
            ->method('logStatusChange')
            ->willReturn($mockLog);

        $this->orderLogService
            ->method('getOrderHistory')
            ->willReturn([]);

        $this->twigLoader
            ->method('exists')
            ->willReturn(true);

        $this->priceCalculator
            ->method('getOrderItemsDetails')
            ->willReturn([]);

        $this->priceCalculator
            ->method('calculateOrderTotal')
            ->willReturn(100.00);

        $this->twig
            ->expects($this->exactly(2))
            ->method('render')
            ->willReturnCallback(function ($template, $params) {
                if (str_contains($template, 'admin')) {
                    $this->assertEquals('Jane Admin', $params['modifiedByName']);
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
        $orderId = Uuid::v4();
        $order = $this->createTestOrder($orderId);
        $message = new OrderStatusChangedMessage(
            $orderId,
            Order::STATUS_SUBMITTED,
            'custom_status'
        );

        $this->orderRepository
            ->method('find')
            ->willReturn($order);

        $mockLog = $this->createMock(OrderLog::class);
        $mockLog->method('getId')->willReturn(Uuid::v4());

        $this->orderLogService
            ->method('logStatusChange')
            ->willReturn($mockLog);

        $this->orderLogService
            ->method('getOrderHistory')
            ->willReturn([]);

        // For custom status, match expression defaults to generic template
        $this->twigLoader
            ->method('exists')
            ->willReturn(true);

        $this->priceCalculator
            ->method('getOrderItemsDetails')
            ->willReturn([]);

        $this->priceCalculator
            ->method('calculateOrderTotal')
            ->willReturn(100.00);

        $this->twig
            ->expects($this->exactly(2))
            ->method('render')
            ->willReturnCallback(function ($template) {
                // Should use generic fallback template for unknown status
                $this->assertStringContainsString('order_status_changed.html.twig', $template);
                return '<html>Test Email</html>';
            });

        $this->mailer
            ->expects($this->exactly(2))
            ->method('send');

        // Act
        ($this->handler)($message);
    }

    public function testLogsStatusChangeWithMetadata(): void
    {
        // Arrange
        $orderId = Uuid::v4();
        $order = $this->createTestOrder($orderId);
        $message = new OrderStatusChangedMessage(
            $orderId,
            Order::STATUS_SUBMITTED,
            Order::STATUS_CONFIRMED
        );
        $message->setModifiedBy([
            'fullName' => 'Test Admin',
            'email' => 'admin@test.com'
        ]);

        $this->orderRepository
            ->method('find')
            ->willReturn($order);

        $mockLog = $this->createMock(OrderLog::class);
        $mockLog->method('getId')->willReturn(Uuid::v4());
        $mockLog->method('getTransitionDescription')->willReturn('submitted → confirmed');

        $this->orderLogService
            ->expects($this->exactly(2))
            ->method('logStatusChange')
            ->willReturnCallback(function ($order, $oldStatus, $newStatus, $description, $metadata) use ($mockLog) {
                // First call is for status change
                static $callCount = 0;
                $callCount++;
                if ($callCount === 1) {
                    $this->assertEquals(Order::STATUS_SUBMITTED, $oldStatus);
                    $this->assertEquals(Order::STATUS_CONFIRMED, $newStatus);
                    $this->assertArrayHasKey('modified_by', $metadata);
                    $this->assertStringContainsString('via API', $description);
                }
                return $mockLog;
            });

        $this->orderLogService
            ->method('getOrderHistory')
            ->willReturn([]);

        $this->twigLoader
            ->method('exists')
            ->willReturn(true);

        $this->priceCalculator
            ->method('getOrderItemsDetails')
            ->willReturn([]);

        $this->priceCalculator
            ->method('calculateOrderTotal')
            ->willReturn(100.00);

        $this->twig
            ->method('render')
            ->willReturn('<html>Test Email</html>');

        $this->mailer
            ->method('send');

        // Act
        ($this->handler)($message);
    }

    private function createTestOrder(Uuid $orderId): Order
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

        $order->method('getId')->willReturn($orderId);
        $order->setOrderNumber('ORD-TEST-001');
        $order->setUser($user);
        $order->setStatus(Order::STATUS_SUBMITTED);
        $order->setTotalAmount(100.00);
        $order->setCreatedAt(new \DateTime());
        $order->setUpdatedAt(new \DateTime());

        return $order;
    }
}
