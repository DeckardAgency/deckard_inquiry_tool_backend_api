<?php

namespace App\Tests\Email;

use App\Entity\Inquiry;
use App\Entity\InquiryMachine;
use App\Entity\Machine;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use App\Entity\Client;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;

class EmailTemplateTest extends KernelTestCase
{
    private Environment $twig;
    private User $testUser;
    private Client $testClient;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->twig = self::getContainer()->get('twig');

        // Create test fixtures
        $this->testClient = $this->createTestClient();
        $this->testUser = $this->createTestUser();
    }

    private function createTestClient(): Client
    {
        $client = new Client();
        $client->setName('Test Company Ltd');
        $client->setCode('TESTCO');
        $client->setEmail('test@company.com');
        $client->setAddress('123 Test Street, Test City');
        $client->setPhoneNumber('+1234567890');
        return $client;
    }

    private function createTestUser(): User
    {
        $user = new User();
        $user->setEmail('john.doe@test.com');
        $user->setFirstName('John');
        $user->setLastName('Doe');
        $user->setPhoneNumber('+1234567890');
        $user->setClient($this->testClient);
        $user->setPassword('dummy_hash');
        return $user;
    }

    private function createTestInquiry(): Inquiry
    {
        $inquiry = new Inquiry();
        $inquiry->setInquiryNumber('INQ-12345678');
        $inquiry->setUser($this->testUser);
        $inquiry->setStatus(Inquiry::STATUS_SUBMITTED);
        $inquiry->setContactEmail('john.doe@test.com');
        $inquiry->setContactPhone('+1234567890');
        $inquiry->setNotes('This is a test inquiry for machine parts');
        $inquiry->setCreatedAt(new \DateTime());
        $inquiry->setUpdatedAt(new \DateTime());

        // Add a machine
        $machine = new Machine();
        $machine->setArticleNumber('ART-001');
        $machine->setArticleDescription('Test Machine Type A');
        $machine->setIbStationNumber(123);
        $machine->setIbSerialNumber(456);

        $inquiryMachine = new InquiryMachine();
        $inquiryMachine->setInquiry($inquiry);
        $inquiryMachine->setMachine($machine);
        $inquiryMachine->setNotes('Urgent request');
        $inquiryMachine->setCreatedAt(new \DateTime());
        $inquiryMachine->setUpdatedAt(new \DateTime());

        $inquiry->addMachine($inquiryMachine);

        return $inquiry;
    }

    private function createTestOrder(): Order
    {
        $order = new Order();
        $order->setOrderNumber('ORD-12345678');
        $order->setUser($this->testUser);
        $order->setStatus(Order::STATUS_SUBMITTED);
        $order->setTotalAmount(1500.00);
        $order->setCreatedAt(new \DateTime());
        $order->setUpdatedAt(new \DateTime());

        // Add order item
        $product = new Product();
        $product->setName('Test Product');
        $product->setPartNo('PROD-001');
        $product->setShortDescription('Test Product Description');
        $product->setPrice(500.00);
        $product->setCreatedAt(new \DateTime());
        $product->setUpdatedAt(new \DateTime());

        $orderItem = new OrderItem();
        $orderItem->setOrderRef($order);
        $orderItem->setProduct($product);
        $orderItem->setQuantity(3);
        $orderItem->setUnitPrice(500.00);
        $orderItem->setCreatedAt(new \DateTime());
        $orderItem->setUpdatedAt(new \DateTime());

        $order->addItem($orderItem);

        return $order;
    }

    // ==================== INQUIRY EMAIL TESTS ====================

    public function testInquiryConfirmationEmailCustomer(): void
    {
        $inquiry = $this->createTestInquiry();

        try {
            $html = $this->twig->render('emails/customer/inquiry_confirmation.html.twig', [
                'inquiry' => $inquiry,
                'user' => $this->testUser,
            ]);

            $this->assertStringContainsString('INQ-12345678', $html);
            $this->assertStringContainsString('John Doe', $html);
            $this->assertStringContainsString('Dear John Doe', $html);
        } catch (\Exception $e) {
            $this->fail('inquiry_confirmation.html.twig failed: ' . $e->getMessage());
        }
    }

    public function testInquiryInReviewEmailCustomer(): void
    {
        $inquiry = $this->createTestInquiry();
        $inquiry->setStatus(Inquiry::STATUS_IN_REVIEW);

        try {
            $html = $this->twig->render('emails/customer/inquiry_in_review.html.twig', [
                'inquiry' => $inquiry,
                'user' => $this->testUser,
            ]);

            $this->assertStringContainsString('INQ-12345678', $html);
            $this->assertStringContainsString('John Doe', $html);
        } catch (\Exception $e) {
            $this->fail('inquiry_in_review.html.twig failed: ' . $e->getMessage());
        }
    }

    public function testInquiryMoreInfoEmailCustomer(): void
    {
        $inquiry = $this->createTestInquiry();
        $inquiry->setStatus(Inquiry::STATUS_MORE_INFO);

        try {
            $html = $this->twig->render('emails/customer/inquiry_more_info.html.twig', [
                'inquiry' => $inquiry,
                'user' => $this->testUser,
            ]);

            $this->assertStringContainsString('INQ-12345678', $html);
            $this->assertStringContainsString('John Doe', $html);
        } catch (\Exception $e) {
            $this->fail('inquiry_more_info.html.twig failed: ' . $e->getMessage());
        }
    }

    public function testInquiryInformationProvidedEmailCustomer(): void
    {
        $inquiry = $this->createTestInquiry();
        $inquiry->setStatus(Inquiry::STATUS_INFORMATION_PROVIDED);

        try {
            $html = $this->twig->render('emails/customer/inquiry_information_provided.html.twig', [
                'inquiry' => $inquiry,
                'user' => $this->testUser,
            ]);

            $this->assertStringContainsString('INQ-12345678', $html);
            $this->assertStringContainsString('John Doe', $html);
        } catch (\Exception $e) {
            $this->fail('inquiry_information_provided.html.twig failed: ' . $e->getMessage());
        }
    }

    public function testInquiryInProgressEmailCustomer(): void
    {
        $inquiry = $this->createTestInquiry();
        $inquiry->setStatus(Inquiry::STATUS_IN_PROGRESS);

        try {
            $html = $this->twig->render('emails/customer/inquiry_in_progress.html.twig', [
                'inquiry' => $inquiry,
                'user' => $this->testUser,
            ]);

            $this->assertStringContainsString('INQ-12345678', $html);
            $this->assertStringContainsString('John Doe', $html);
        } catch (\Exception $e) {
            $this->fail('inquiry_in_progress.html.twig failed: ' . $e->getMessage());
        }
    }

    public function testInquiryCompletedEmailCustomer(): void
    {
        $inquiry = $this->createTestInquiry();
        $inquiry->setStatus(Inquiry::STATUS_COMPLETED);

        try {
            $html = $this->twig->render('emails/customer/inquiry_completed.html.twig', [
                'inquiry' => $inquiry,
                'user' => $this->testUser,
            ]);

            $this->assertStringContainsString('INQ-12345678', $html);
            $this->assertStringContainsString('John Doe', $html);
        } catch (\Exception $e) {
            $this->fail('inquiry_completed.html.twig failed: ' . $e->getMessage());
        }
    }

    public function testInquiryCanceledEmailCustomer(): void
    {
        $inquiry = $this->createTestInquiry();
        $inquiry->setStatus(Inquiry::STATUS_CANCELED);

        try {
            $html = $this->twig->render('emails/customer/inquiry_canceled.html.twig', [
                'inquiry' => $inquiry,
                'user' => $this->testUser,
            ]);

            $this->assertStringContainsString('INQ-12345678', $html);
            $this->assertStringContainsString('John Doe', $html);
        } catch (\Exception $e) {
            $this->fail('inquiry_canceled.html.twig failed: ' . $e->getMessage());
        }
    }

    // ==================== INQUIRY ADMIN EMAIL TESTS ====================

    public function testInquiryCreatedEmailAdmin(): void
    {
        $inquiry = $this->createTestInquiry();

        try {
            $html = $this->twig->render('emails/admin/inquiry_created.html.twig', [
                'inquiry' => $inquiry,
            ]);

            $this->assertStringContainsString('INQ-12345678', $html);
            $this->assertStringContainsString('Dear Admin', $html);
            $this->assertStringContainsString('Test Machine Type A', $html);
        } catch (\Exception $e) {
            $this->fail('inquiry_created.html.twig failed: ' . $e->getMessage());
        }
    }

    public function testInquiryInReviewEmailAdmin(): void
    {
        $inquiry = $this->createTestInquiry();
        $inquiry->setStatus(Inquiry::STATUS_IN_REVIEW);

        try {
            $html = $this->twig->render('emails/admin/inquiry_in_review.html.twig', [
                'inquiry' => $inquiry,
                'status' => 'in_review',
            ]);

            $this->assertStringContainsString('INQ-12345678', $html);
            $this->assertStringContainsString('in_review', $html);
        } catch (\Exception $e) {
            $this->fail('inquiry_in_review.html.twig failed: ' . $e->getMessage());
        }
    }

    public function testInquiryMoreInfoEmailAdmin(): void
    {
        $inquiry = $this->createTestInquiry();
        $inquiry->setStatus(Inquiry::STATUS_MORE_INFO);

        try {
            $html = $this->twig->render('emails/admin/inquiry_more_info.html.twig', [
                'inquiry' => $inquiry,
                'status' => 'more_info',
            ]);

            $this->assertStringContainsString('INQ-12345678', $html);
        } catch (\Exception $e) {
            $this->fail('inquiry_more_info.html.twig failed: ' . $e->getMessage());
        }
    }

    public function testInquiryInformationProvidedEmailAdmin(): void
    {
        $inquiry = $this->createTestInquiry();
        $inquiry->setStatus(Inquiry::STATUS_INFORMATION_PROVIDED);

        try {
            $html = $this->twig->render('emails/admin/inquiry_information_provided.html.twig', [
                'inquiry' => $inquiry,
                'status' => 'information_provided',
            ]);

            $this->assertStringContainsString('INQ-12345678', $html);
        } catch (\Exception $e) {
            $this->fail('inquiry_information_provided.html.twig failed: ' . $e->getMessage());
        }
    }

    public function testInquiryInProgressEmailAdmin(): void
    {
        $inquiry = $this->createTestInquiry();
        $inquiry->setStatus(Inquiry::STATUS_IN_PROGRESS);

        try {
            $html = $this->twig->render('emails/admin/inquiry_in_progress.html.twig', [
                'inquiry' => $inquiry,
                'status' => 'in_progress',
            ]);

            $this->assertStringContainsString('INQ-12345678', $html);
        } catch (\Exception $e) {
            $this->fail('inquiry_in_progress.html.twig failed: ' . $e->getMessage());
        }
    }

    public function testInquiryCompletedEmailAdmin(): void
    {
        $inquiry = $this->createTestInquiry();
        $inquiry->setStatus(Inquiry::STATUS_COMPLETED);

        try {
            $html = $this->twig->render('emails/admin/inquiry_completed.html.twig', [
                'inquiry' => $inquiry,
                'status' => 'completed',
            ]);

            $this->assertStringContainsString('INQ-12345678', $html);
        } catch (\Exception $e) {
            $this->fail('inquiry_completed.html.twig failed: ' . $e->getMessage());
        }
    }

    public function testInquiryCanceledEmailAdmin(): void
    {
        $inquiry = $this->createTestInquiry();
        $inquiry->setStatus(Inquiry::STATUS_CANCELED);

        try {
            $html = $this->twig->render('emails/admin/inquiry_canceled.html.twig', [
                'inquiry' => $inquiry,
                'status' => 'canceled',
            ]);

            $this->assertStringContainsString('INQ-12345678', $html);
        } catch (\Exception $e) {
            $this->fail('inquiry_canceled.html.twig failed: ' . $e->getMessage());
        }
    }

    // ==================== ORDER EMAIL TESTS ====================

    public function testOrderConfirmationEmailCustomer(): void
    {
        $order = $this->createTestOrder();

        try {
            $html = $this->twig->render('emails/customer/order_confirmation.html.twig', [
                'order' => $order,
                'user' => $this->testUser,
                'base_url' => 'https://example.com',
                'supportEmail' => 'support@deckard.com',
                'supportPhone' => '+43 1 234 5678',
            ]);

            $this->assertStringContainsString('ORD-12345678', $html);
            $this->assertStringContainsString('John Doe', $html);
        } catch (\Exception $e) {
            $this->fail('order_confirmation.html.twig failed: ' . $e->getMessage());
        }
    }

    public function testOrderConfirmedEmailCustomer(): void
    {
        $order = $this->createTestOrder();
        $order->setStatus(Order::STATUS_CONFIRMED);

        try {
            $html = $this->twig->render('emails/customer/order_confirmed.html.twig', [
                'order' => $order,
                'user' => $this->testUser,
                'base_url' => 'https://example.com',
            ]);

            $this->assertStringContainsString('ORD-12345678', $html);
        } catch (\Exception $e) {
            $this->fail('order_confirmed.html.twig failed: ' . $e->getMessage());
        }
    }

    public function testOrderDispatchedEmailCustomer(): void
    {
        $order = $this->createTestOrder();
        $order->setStatus(Order::STATUS_DISPATCHED);

        try {
            $html = $this->twig->render('emails/customer/order_dispatched.html.twig', [
                'order' => $order,
                'user' => $this->testUser,
                'base_url' => 'https://example.com',
            ]);

            $this->assertStringContainsString('ORD-12345678', $html);
        } catch (\Exception $e) {
            $this->fail('order_dispatched.html.twig failed: ' . $e->getMessage());
        }
    }

    public function testOrderCompletedEmailCustomer(): void
    {
        $order = $this->createTestOrder();
        $order->setStatus(Order::STATUS_COMPLETED);

        try {
            $html = $this->twig->render('emails/customer/order_completed.html.twig', [
                'order' => $order,
                'user' => $this->testUser,
                'base_url' => 'https://example.com',
            ]);

            $this->assertStringContainsString('ORD-12345678', $html);
        } catch (\Exception $e) {
            $this->fail('order_completed.html.twig failed: ' . $e->getMessage());
        }
    }

    public function testOrderCanceledEmailCustomer(): void
    {
        $order = $this->createTestOrder();
        $order->setStatus(Order::STATUS_CANCELED);

        try {
            $html = $this->twig->render('emails/customer/order_canceled.html.twig', [
                'order' => $order,
                'user' => $this->testUser,
                'base_url' => 'https://example.com',
                'supportEmail' => 'support@deckard.com',
                'supportPhone' => '+43 1 234 5678',
            ]);

            $this->assertStringContainsString('ORD-12345678', $html);
        } catch (\Exception $e) {
            $this->fail('order_canceled.html.twig failed: ' . $e->getMessage());
        }
    }

    // ==================== ORDER ADMIN EMAIL TESTS ====================

    public function testOrderSubmittedEmailAdmin(): void
    {
        $order = $this->createTestOrder();

        try {
            $html = $this->twig->render('emails/admin/order_submitted.html.twig', [
                'order' => $order,
                'base_url' => 'https://example.com',
            ]);

            $this->assertStringContainsString('ORD-12345678', $html);
            $this->assertStringContainsString('Test Product', $html);
        } catch (\Exception $e) {
            $this->fail('order_submitted.html.twig failed: ' . $e->getMessage());
        }
    }

    public function testOrderConfirmedEmailAdmin(): void
    {
        $order = $this->createTestOrder();
        $order->setStatus(Order::STATUS_CONFIRMED);

        try {
            $html = $this->twig->render('emails/admin/order_confirmed.html.twig', [
                'order' => $order,
                'base_url' => 'https://example.com',
            ]);

            $this->assertStringContainsString('ORD-12345678', $html);
        } catch (\Exception $e) {
            $this->fail('order_confirmed.html.twig failed: ' . $e->getMessage());
        }
    }

    public function testOrderDispatchedEmailAdmin(): void
    {
        $order = $this->createTestOrder();
        $order->setStatus(Order::STATUS_DISPATCHED);

        try {
            $html = $this->twig->render('emails/admin/order_dispatched.html.twig', [
                'order' => $order,
                'base_url' => 'https://example.com',
            ]);

            $this->assertStringContainsString('ORD-12345678', $html);
        } catch (\Exception $e) {
            $this->fail('order_dispatched.html.twig failed: ' . $e->getMessage());
        }
    }

    public function testOrderCompletedEmailAdmin(): void
    {
        $order = $this->createTestOrder();
        $order->setStatus(Order::STATUS_COMPLETED);

        try {
            $html = $this->twig->render('emails/admin/order_completed.html.twig', [
                'order' => $order,
            ]);

            $this->assertStringContainsString('ORD-12345678', $html);
        } catch (\Exception $e) {
            $this->fail('order_completed.html.twig failed: ' . $e->getMessage());
        }
    }

    public function testOrderCanceledEmailAdmin(): void
    {
        $order = $this->createTestOrder();
        $order->setStatus(Order::STATUS_CANCELED);

        try {
            $html = $this->twig->render('emails/admin/order_canceled.html.twig', [
                'order' => $order,
                'base_url' => 'https://example.com',
            ]);

            $this->assertStringContainsString('ORD-12345678', $html);
        } catch (\Exception $e) {
            $this->fail('order_canceled.html.twig failed: ' . $e->getMessage());
        }
    }

    // ==================== GENERIC STATUS CHANGE TEMPLATES ====================

    public function testInquiryStatusChangedEmailCustomer(): void
    {
        $inquiry = $this->createTestInquiry();
        $inquiry->setStatus('custom_status'); // Use a non-standard status to trigger generic template

        try {
            $html = $this->twig->render('emails/customer/inquiry_status_changed.html.twig', [
                'inquiry' => $inquiry,
                'user' => $this->testUser,
                'status' => 'custom_status',
                'previousStatus' => Inquiry::STATUS_SUBMITTED,
                'base_url' => 'https://example.com',
            ]);

            $this->assertStringContainsString('INQ-12345678', $html);
            $this->assertStringContainsString('John Doe', $html);
        } catch (\Exception $e) {
            $this->fail('inquiry_status_changed.html.twig failed: ' . $e->getMessage());
        }
    }

    public function testInquiryStatusChangedEmailAdmin(): void
    {
        $inquiry = $this->createTestInquiry();
        $inquiry->setStatus('custom_status');

        try {
            $html = $this->twig->render('emails/admin/inquiry_status_changed.html.twig', [
                'inquiry' => $inquiry,
                'status' => 'custom_status',
                'previousStatus' => Inquiry::STATUS_SUBMITTED,
                'base_url' => 'https://example.com',
            ]);

            $this->assertStringContainsString('INQ-12345678', $html);
        } catch (\Exception $e) {
            $this->fail('inquiry_status_changed.html.twig failed: ' . $e->getMessage());
        }
    }

    public function testOrderStatusChangedEmailCustomer(): void
    {
        $order = $this->createTestOrder();
        $order->setStatus('custom_status');

        try {
            $html = $this->twig->render('emails/customer/order_status_changed.html.twig', [
                'order' => $order,
                'user' => $this->testUser,
                'status' => 'custom_status',
                'previousStatus' => Order::STATUS_SUBMITTED,
                'base_url' => 'https://example.com',
                'supportEmail' => 'support@deckard.com',
                'supportPhone' => '+43 1 234 5678',
            ]);

            $this->assertStringContainsString('ORD-12345678', $html);
            $this->assertStringContainsString('John Doe', $html);
        } catch (\Exception $e) {
            $this->fail('order_status_changed.html.twig failed: ' . $e->getMessage());
        }
    }

    public function testOrderStatusChangedEmailAdmin(): void
    {
        $order = $this->createTestOrder();
        $order->setStatus('custom_status');

        try {
            $html = $this->twig->render('emails/admin/order_status_changed.html.twig', [
                'order' => $order,
                'status' => 'custom_status',
                'previousStatus' => Order::STATUS_SUBMITTED,
                'base_url' => 'https://example.com',
            ]);

            $this->assertStringContainsString('ORD-12345678', $html);
        } catch (\Exception $e) {
            $this->fail('order_status_changed.html.twig failed: ' . $e->getMessage());
        }
    }

    // ==================== EDGE CASES ====================

    public function testInquiryWithoutUser(): void
    {
        $inquiry = $this->createTestInquiry();
        $inquiry->setUser(null);

        try {
            $html = $this->twig->render('emails/customer/inquiry_confirmation.html.twig', [
                'inquiry' => $inquiry,
                'user' => null,
            ]);

            $this->assertStringContainsString('Customer', $html);
            $this->assertStringContainsString('INQ-12345678', $html);
        } catch (\Exception $e) {
            $this->fail('inquiry_confirmation.html.twig failed with null user: ' . $e->getMessage());
        }
    }

    public function testInquiryWithoutMachines(): void
    {
        $inquiry = new Inquiry();
        $inquiry->setInquiryNumber('INQ-99999999');
        $inquiry->setUser($this->testUser);
        $inquiry->setStatus(Inquiry::STATUS_SUBMITTED);
        $inquiry->setCreatedAt(new \DateTimeImmutable());
        $inquiry->setUpdatedAt(new \DateTimeImmutable());

        try {
            $html = $this->twig->render('emails/admin/inquiry_created.html.twig', [
                'inquiry' => $inquiry,
            ]);

            $this->assertStringContainsString('INQ-99999999', $html);
        } catch (\Exception $e) {
            $this->fail('inquiry_created.html.twig failed without machines: ' . $e->getMessage());
        }
    }

    public function testOrderWithoutItems(): void
    {
        $order = new Order();
        $order->setOrderNumber('ORD-99999999');
        $order->setUser($this->testUser);
        $order->setStatus(Order::STATUS_SUBMITTED);
        $order->setTotalAmount(0.00);
        $order->setCreatedAt(new \DateTimeImmutable());
        $order->setUpdatedAt(new \DateTimeImmutable());

        try {
            $html = $this->twig->render('emails/admin/order_submitted.html.twig', [
                'order' => $order,
                'base_url' => 'https://example.com',
            ]);

            $this->assertStringContainsString('ORD-99999999', $html);
        } catch (\Exception $e) {
            $this->fail('order_submitted.html.twig failed without items: ' . $e->getMessage());
        }
    }
}
