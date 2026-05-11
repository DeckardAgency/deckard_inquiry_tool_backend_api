<?php

namespace App\Tests\MessageHandler;

use App\Entity\Client;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use App\Message\OrderCreatedMessage;
use App\MessageHandler\OrderCreatedMessageHandler;
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

class OrderCreatedMessageHandlerTest extends TestCase
{
    private OrderRepository $orderRepository;
    private MailerInterface $mailer;
    private LoggerInterface $logger;
    private Environment $twig;
    private PriceCalculator $priceCalculator;
    private OrderLogService $orderLogService;
    private EntityManagerInterface $entityManager;
    private OrderCreatedMessageHandler $handler;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(OrderRepository::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->twig = $this->createMock(Environment::class);
        $this->priceCalculator = $this->createMock(PriceCalculator::class);
        $this->orderLogService = $this->createMock(OrderLogService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->handler = new OrderCreatedMessageHandler(
            $this->orderRepository,
            $this->mailer,
            $this->logger,
            $this->twig,
            $this->priceCalculator,
            $this->orderLogService,
            $this->entityManager,
            'admin@test.com',
            'noreply@test.com'
        );
    }

    public function testHandleOrderCreatedSuccessfully(): void
    {
        // Arrange
        $orderId = Uuid::v4();
        $order = $this->createTestOrder($orderId);
        $message = new OrderCreatedMessage($orderId, null, [
            'operation' => 'create',
            'modifiedBy' => [
                'fullName' => 'John Doe',
                'email' => 'john@test.com'
            ]
        ]);

        $this->orderRepository
            ->expects($this->once())
            ->method('find')
            ->with($orderId)
            ->willReturn($order);

        $this->priceCalculator
            ->expects($this->exactly(2)) // Once for admin, once for customer
            ->method('getOrderItemsDetails')
            ->with($order)
            ->willReturn([
                [
                    'name' => 'Test Product',
                    'quantity' => 2,
                    'unitPrice' => 100.00,
                    'subtotal' => 200.00
                ]
            ]);

        $this->priceCalculator
            ->expects($this->exactly(2))
            ->method('calculateOrderTotal')
            ->with($order)
            ->willReturn(200.00);

        $mockLog = $this->createMock(\App\Entity\OrderLog::class);
        $mockLog->method('getId')->willReturn(Uuid::v4());

        $this->orderLogService
            ->expects($this->once())
            ->method('logOrderSubmission')
            ->willReturn($mockLog);

        $this->entityManager
            ->expects($this->exactly(2)) // Once after logOrderSubmission, once after customer email
            ->method('flush');

        $this->twig
            ->expects($this->exactly(2))
            ->method('render')
            ->willReturnCallback(function ($template, $params) {
                if (str_contains($template, 'admin')) {
                    $this->assertEquals('emails/admin/order_submitted.html.twig', $template);
                    $this->assertArrayHasKey('order', $params);
                    $this->assertArrayHasKey('items', $params);
                    $this->assertArrayHasKey('totalAmount', $params);
                    $this->assertArrayHasKey('base_url', $params);
                    $this->assertArrayHasKey('createdByName', $params);
                    $this->assertEquals('John Doe', $params['createdByName']);
                } else {
                    $this->assertEquals('emails/customer/order_confirmation.html.twig', $template);
                    $this->assertArrayHasKey('order', $params);
                    $this->assertArrayHasKey('user', $params);
                    $this->assertArrayHasKey('supportEmail', $params);
                    $this->assertArrayHasKey('supportPhone', $params);
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

    public function testSkipNotificationsForDraftOrder(): void
    {
        // Arrange
        $orderId = Uuid::v4();
        $order = $this->createTestOrder($orderId);
        $order->setStatus(Order::STATUS_DRAFT);

        $message = new OrderCreatedMessage($orderId);

        $this->orderRepository
            ->expects($this->once())
            ->method('find')
            ->with($orderId)
            ->willReturn($order);

        // Should NOT send any emails for draft orders
        $this->mailer
            ->expects($this->never())
            ->method('send');

        $this->orderLogService
            ->expects($this->never())
            ->method('logOrderSubmission');

        $this->logger
            ->expects($this->atLeast(3))
            ->method('info')
            ->willReturnCallback(function ($message, $context) {
                // The third info call should be about skipping notifications
                static $callCount = 0;
                $callCount++;
                if ($callCount === 3) {
                    $this->assertStringContainsString('Skipping notifications for draft order', $message);
                }
            });

        // Act
        ($this->handler)($message);
    }

    public function testOrderNotFound(): void
    {
        // Arrange
        $orderId = Uuid::v4();
        $message = new OrderCreatedMessage($orderId);

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
        $order->setUser(null); // No user associated

        $message = new OrderCreatedMessage($orderId);

        $this->orderRepository
            ->expects($this->once())
            ->method('find')
            ->with($orderId)
            ->willReturn($order);

        $this->priceCalculator
            ->method('getOrderItemsDetails')
            ->willReturn([]);

        $this->priceCalculator
            ->method('calculateOrderTotal')
            ->willReturn(0.00);

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
                'Cannot send customer notification: no user or email',
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
        $message = new OrderCreatedMessage($orderId);

        $this->orderRepository
            ->expects($this->once())
            ->method('find')
            ->with($orderId)
            ->willReturn($order);

        $this->priceCalculator
            ->method('getOrderItemsDetails')
            ->willReturn([]);

        $this->priceCalculator
            ->method('calculateOrderTotal')
            ->willReturn(0.00);

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

    public function testFirstTimeCustomerDetection(): void
    {
        // Arrange
        $orderId = Uuid::v4();
        $order = $this->createTestOrder($orderId);
        $message = new OrderCreatedMessage($orderId);

        $this->orderRepository
            ->expects($this->once())
            ->method('find')
            ->with($orderId)
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

        $this->priceCalculator
            ->method('getOrderItemsDetails')
            ->willReturn([]);

        $this->priceCalculator
            ->method('calculateOrderTotal')
            ->willReturn(0.00);

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

        // Test with full name
        $message = new OrderCreatedMessage($orderId, null, [
            'modifiedBy' => [
                'fullName' => 'Jane Smith',
                'email' => 'jane@test.com'
            ]
        ]);

        $this->orderRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($order);

        $this->priceCalculator
            ->method('getOrderItemsDetails')
            ->willReturn([]);

        $this->priceCalculator
            ->method('calculateOrderTotal')
            ->willReturn(0.00);

        $this->twig
            ->expects($this->exactly(2))
            ->method('render')
            ->willReturnCallback(function ($template, $params) {
                if (str_contains($template, 'admin')) {
                    $this->assertEquals('Jane Smith', $params['createdByName']);
                }
                return '<html>Test</html>';
            });

        $this->mailer
            ->method('send');

        // Act
        ($this->handler)($message);
    }

    public function testLogsOrderCreationForSubmittedStatus(): void
    {
        // Arrange
        $orderId = Uuid::v4();
        $order = $this->createTestOrder($orderId);
        $order->setStatus(Order::STATUS_SUBMITTED);

        $message = new OrderCreatedMessage($orderId, null, [
            'operation' => 'create',
            'modifiedBy' => ['fullName' => 'Test User']
        ]);

        $this->orderRepository
            ->method('find')
            ->willReturn($order);

        $this->priceCalculator
            ->method('getOrderItemsDetails')
            ->willReturn([]);

        $this->priceCalculator
            ->method('calculateOrderTotal')
            ->willReturn(0.00);

        $this->twig
            ->method('render')
            ->willReturn('<html></html>');

        $mockLog = $this->createMock(\App\Entity\OrderLog::class);
        $mockLog->method('getId')->willReturn(Uuid::v4());

        // Should call logOrderSubmission for submitted orders
        $this->orderLogService
            ->expects($this->once())
            ->method('logOrderSubmission')
            ->with(
                $order,
                $this->stringContains('Order created and submitted'),
                $this->callback(function ($metadata) {
                    return isset($metadata['created_by']) && $metadata['operation'] === 'create';
                })
            )
            ->willReturn($mockLog);

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

        $product = new Product();
        $product->setName('Test Product');
        $product->setPrice(50.00);

        $orderItem = new OrderItem();
        $orderItem->setProduct($product);
        $orderItem->setQuantity(2);
        $orderItem->setUnitPrice(50.00);

        $order->addItem($orderItem);

        return $order;
    }
}
