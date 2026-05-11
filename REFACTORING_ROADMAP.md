# Deckard Inquiry Tool Backend API - Refactoring Roadmap

**Last Updated:** 2025-11-23
**Application:** Symfony 6.4 + API Platform 4.0
**Status:** Active Development

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Critical Issues (Fix Immediately)](#critical-issues-fix-immediately)
3. [High Priority Issues](#high-priority-issues)
4. [Medium Priority Issues](#medium-priority-issues)
5. [Low Priority Improvements](#low-priority-improvements)
6. [Tracking Progress](#tracking-progress)

---

## Executive Summary

This document tracks all identified issues, refactoring needs, and improvements for the Deckard Inquiry Tool Backend API. Each item includes:

- **Status**: ❌ Not Started | 🔄 In Progress | ✅ Done
- **Priority**: 🚨 Critical | 🔴 High | 🟡 Medium | 🟢 Low
- **Location**: File path and line numbers
- **Effort**: Estimated time to complete
- **Impact**: What fixing this improves

**Overall Application Health: C+ → Target: A**

---

## Critical Issues (Fix Immediately)

### 🚨 SECURITY-001: All API Endpoints Are Public
- **Status**: ❌ Not Started
- **Priority**: 🚨 CRITICAL
- **Location**: `config/packages/security.yaml:55`
- **Effort**: 5 minutes
- **Impact**: Prevents unauthorized access to entire API

**Problem:**
```yaml
access_control:
    - { path: ^/api, roles: PUBLIC_ACCESS }  # ← ALL ENDPOINTS PUBLIC!
```

**Solution:**
```yaml
access_control:
    - { path: ^/api/login, roles: PUBLIC_ACCESS }
    - { path: ^/api/token/refresh, roles: PUBLIC_ACCESS }
    - { path: ^/api/docs, roles: PUBLIC_ACCESS }  # If you have API docs
    - { path: ^/api, roles: IS_AUTHENTICATED_FULLY }
```

**Testing After Fix:**
1. Try to access `/api/orders` without JWT token → Should get 401
2. Login and access with token → Should work
3. Verify all endpoints require authentication

---

### 🚨 DATA-001: Non-Unique Order Numbers
- **Status**: ❌ Not Started
- **Priority**: 🚨 CRITICAL
- **Location**: `src/Entity/Order.php:241`, `src/Entity/Inquiry.php:228`
- **Effort**: 2 hours
- **Impact**: Prevents duplicate order/inquiry numbers

**Problem:**
```php
// Order.php line 241
private function generateOrderNumber(): string
{
    return 'ORD-' . strtoupper(uniqid());  // Not guaranteed unique!
}
```

**Solution Option 1 - Database Sequence (Recommended):**

1. Create migration:
```php
// migrations/VersionXXX.php
public function up(Schema $schema): void
{
    $this->addSql('CREATE SEQUENCE order_number_seq START 1000');
    $this->addSql('CREATE SEQUENCE inquiry_number_seq START 1000');
    $this->addSql('CREATE UNIQUE INDEX UNIQ_order_number ON "order" (order_number)');
    $this->addSql('CREATE UNIQUE INDEX UNIQ_inquiry_number ON inquiry (inquiry_number)');
}
```

2. Update entity:
```php
// Order.php
private function generateOrderNumber(): string
{
    // Let database handle uniqueness via sequence
    // This will be set in a lifecycle callback
    return '';
}

#[ORM\PrePersist]
public function setOrderNumber(): void
{
    if (empty($this->orderNumber)) {
        // Repository will handle this with native query
        $this->orderNumber = 'ORD-PENDING';
    }
}
```

3. Create repository method:
```php
// OrderRepository.php
public function generateOrderNumber(): string
{
    $sql = "SELECT 'ORD-' || LPAD(nextval('order_number_seq')::text, 6, '0')";
    $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
    return $stmt->executeQuery()->fetchOne();
}
```

**Solution Option 2 - UUID v7 (Simpler):**
```php
use Symfony\Component\Uid\UuidV7;

private function generateOrderNumber(): string
{
    return 'ORD-' . UuidV7::generate()->toBase32();
}
```

**Testing:**
- Create 1000 orders rapidly → All should have unique numbers
- Check database for duplicates

---

### 🚨 DATA-002: Price Precision Issues (Float Storage)
- **Status**: ❌ Not Started
- **Priority**: 🚨 CRITICAL
- **Location**: `src/Entity/Product.php`, `OrderItem.php`, `ClientProductPrice.php`
- **Effort**: 4 hours
- **Impact**: Prevents financial calculation errors

**Problem:**
```php
#[ORM\Column(type: 'float')]
private ?float $price = null;  // ❌ Floating point errors!
```

**Solution - Use Integer Cents:**

1. Create migration:
```php
public function up(Schema $schema): void
{
    // Multiply existing prices by 100 and convert to integer
    $this->addSql('ALTER TABLE product ALTER COLUMN price TYPE INTEGER USING (price * 100)::integer');
    $this->addSql('ALTER TABLE order_item ALTER COLUMN unit_price TYPE INTEGER USING (unit_price * 100)::integer');
    $this->addSql('ALTER TABLE order_item ALTER COLUMN subtotal TYPE INTEGER USING (subtotal * 100)::integer');
    $this->addSql('ALTER TABLE "order" ALTER COLUMN total_amount TYPE INTEGER USING (total_amount * 100)::integer');
    $this->addSql('ALTER TABLE client_product_price ALTER COLUMN price TYPE INTEGER USING (price * 100)::integer');
}
```

2. Update entities:
```php
// Product.php
#[ORM\Column(type: 'integer')]
private ?int $priceInCents = null;

public function getPrice(): ?float
{
    return $this->priceInCents ? $this->priceInCents / 100 : null;
}

public function setPrice(?float $price): self
{
    $this->priceInCents = $price ? (int) round($price * 100) : null;
    return $this;
}

public function getPriceInCents(): ?int
{
    return $this->priceInCents;
}
```

3. Create Money Value Object (Optional but recommended):
```php
// src/ValueObject/Money.php
final class Money
{
    public function __construct(
        private readonly int $amountInCents,
        private readonly string $currency = 'EUR'
    ) {}

    public function toFloat(): float
    {
        return $this->amountInCents / 100;
    }

    public function add(Money $other): self
    {
        return new self($this->amountInCents + $other->amountInCents);
    }

    public function multiply(int $quantity): self
    {
        return new self($this->amountInCents * $quantity);
    }
}
```

**Testing:**
- Calculate: (0.1 + 0.2) * 100 → Should equal 30 exactly
- Test order total calculations
- Test client pricing discounts

---

### 🚨 CODE-001: No Test Coverage
- **Status**: ❌ Not Started
- **Priority**: 🚨 CRITICAL
- **Effort**: 40 hours (ongoing)
- **Impact**: Code quality, regression prevention

**Solution:**

1. Install PHPUnit:
```bash
composer require --dev phpunit/phpunit symfony/test-pack symfony/browser-kit
```

2. Create `phpunit.xml.dist`:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true">
    <testsuites>
        <testsuite name="Project Test Suite">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
    <coverage processUncoveredFiles="true">
        <include>
            <directory suffix=".php">src</directory>
        </include>
    </coverage>
</phpunit>
```

3. Create test structure:
```
tests/
├── bootstrap.php
├── Functional/
│   ├── Controller/
│   │   ├── OrderControllerTest.php
│   │   ├── InquiryControllerTest.php
│   │   └── AuthenticationTest.php
│   └── Api/
│       ├── OrderApiTest.php
│       └── InquiryApiTest.php
├── Integration/
│   ├── Repository/
│   │   ├── OrderRepositoryTest.php
│   │   └── ProductRepositoryTest.php
│   └── Service/
│       └── PriceCalculatorTest.php
└── Unit/
    ├── Entity/
    │   ├── OrderTest.php
    │   └── InquiryTest.php
    ├── Validator/
    │   └── ActiveUserLimitValidatorTest.php
    └── Service/
        └── PriceCalculatorTest.php
```

4. Priority test coverage:
   - ✅ Authentication & Authorization
   - ✅ Order creation & pricing
   - ✅ Inquiry creation
   - ✅ Price calculation service
   - ✅ Custom validators
   - ✅ Status transitions

**Minimum Coverage Target: 70%**

---

### 🚨 CONFIG-001: Hardcoded Email Addresses
- **Status**: ❌ Not Started
- **Priority**: 🚨 CRITICAL
- **Location**: `src/MessageHandler/OrderCreatedMessageHandler.php:29-30`
- **Effort**: 30 minutes
- **Impact**: Configurability, environment separation

**Problem:**
```php
public function __construct(
    private readonly string $adminEmail = 'admin@deckard.com',  // ❌ Hardcoded!
    private readonly string $fromEmail = 'noreply@deckard.com',  // ❌ Hardcoded!
) {}
```

**Solution:**

1. Create `config/services.yaml` parameters:
```yaml
parameters:
    app.email.admin: '%env(ADMIN_EMAIL)%'
    app.email.from: '%env(FROM_EMAIL)%'
    app.email.from_name: '%env(FROM_NAME)%'
    app.base_url: '%env(APP_BASE_URL)%'
```

2. Update `.env`:
```env
ADMIN_EMAIL=admin@deckard.com
FROM_EMAIL=noreply@deckard.com
FROM_NAME="Deckard Inquiry Tool"
APP_BASE_URL=https://api.deckard.com
```

3. Update message handlers:
```php
public function __construct(
    #[Autowire(param: 'app.email.admin')]
    private readonly string $adminEmail,
    #[Autowire(param: 'app.email.from')]
    private readonly string $fromEmail,
    #[Autowire(param: 'app.email.from_name')]
    private readonly string $fromName,
    #[Autowire(param: 'app.base_url')]
    private readonly string $baseUrl,
) {}
```

4. Remove `getBaseUrl()` method that uses `$_SERVER` (lines 253-261)

**Files to Update:**
- `OrderCreatedMessageHandler.php`
- `OrderStatusChangedMessageHandler.php`
- `InquiryCreatedMessageHandler.php`
- `InquiryStatusChangedMessageHandler.php`

---

### 🚨 CONFIG-002: Hardcoded File Paths in Commands
- **Status**: ❌ Not Started
- **Priority**: 🚨 CRITICAL
- **Location**: `src/Command/ImportProductsCommand.php:24-25`
- **Effort**: 20 minutes
- **Impact**: Environment portability

**Solution:**

1. Add to `config/services.yaml`:
```yaml
parameters:
    app.import.products_excel: '%kernel.project_dir%/var/import/products.xlsx'
    app.import.products_images: '%kernel.project_dir%/var/import/product-images'
    app.import.machines_excel: '%kernel.project_dir%/var/import/machines.xlsx'
```

2. Update commands:
```php
public function __construct(
    #[Autowire(param: 'app.import.products_excel')]
    private readonly string $excelFilePath,
    #[Autowire(param: 'app.import.products_images')]
    private readonly string $imagesDirectory,
) {
    parent::__construct();
}
```

---

## High Priority Issues

### 🔴 ARCH-001: Fat State Processor (OrderPriceProcessor)
- **Status**: ❌ Not Started
- **Priority**: 🔴 HIGH
- **Location**: `src/State/Processor/OrderPriceProcessor.php`
- **Effort**: 8 hours
- **Impact**: Maintainability, testability, single responsibility

**Problem:**
271-line processor handling:
- Price calculation
- User assignment
- Order submission
- Notification dispatching
- Audit logging
- Draft handling

**Solution - Split into Multiple Processors:**

```php
// 1. src/State/Processor/OrderUserAssignmentProcessor.php
final class OrderUserAssignmentProcessor implements ProcessorInterface
{
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Order && $operation instanceof Post) {
            $user = $this->security->getUser();
            $data->setUser($user);
        }

        return $this->decorated->process($data, $operation, $uriVariables, $context);
    }
}

// 2. src/State/Processor/OrderPricingProcessor.php
final class OrderPricingProcessor implements ProcessorInterface
{
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Order) {
            $this->applyClientPricing($data);
        }

        return $this->decorated->process($data, $operation, $uriVariables, $context);
    }

    private function applyClientPricing(Order $order): void
    {
        foreach ($order->getOrderItems() as $item) {
            $clientPrice = $this->priceCalculator->getClientProductPrice(
                $order->getUser()->getClient(),
                $item->getProduct()
            );

            if ($clientPrice !== null && !$item->getIsCustomPrice()) {
                $item->setUnitPrice($clientPrice);
            }
        }
    }
}

// 3. src/State/Processor/OrderNotificationProcessor.php
final class OrderNotificationProcessor implements ProcessorInterface
{
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $order = $this->decorated->process($data, $operation, $uriVariables, $context);

        if ($order instanceof Order) {
            $this->dispatchNotifications($order, $operation);
        }

        return $order;
    }

    private function dispatchNotifications(Order $order, Operation $operation): void
    {
        if ($operation instanceof Post && !$order->isDraft()) {
            $this->messageBus->dispatch(new OrderCreatedMessage($order->getId()));
        }
        // ... other notification logic
    }
}

// 4. src/State/Processor/OrderLoggingProcessor.php
final class OrderLoggingProcessor implements ProcessorInterface
{
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $order = $this->decorated->process($data, $operation, $uriVariables, $context);

        if ($order instanceof Order) {
            $this->logOrderOperation($order, $operation);
        }

        return $order;
    }
}
```

**Configure processor chain in `config/api_platform/resources/Order.yaml`:**
```yaml
App\Entity\Order:
    processor: App\State\Processor\OrderLoggingProcessor
```

**Service configuration:**
```yaml
# config/services.yaml
services:
    App\State\Processor\OrderUserAssignmentProcessor:
        decorates: 'api_platform.doctrine.orm.state.persist_processor'
        arguments:
            $decorated: '@.inner'

    App\State\Processor\OrderPricingProcessor:
        decorates: App\State\Processor\OrderUserAssignmentProcessor
        arguments:
            $decorated: '@.inner'

    App\State\Processor\OrderNotificationProcessor:
        decorates: App\State\Processor\OrderPricingProcessor
        arguments:
            $decorated: '@.inner'

    App\State\Processor\OrderLoggingProcessor:
        decorates: App\State\Processor\OrderNotificationProcessor
        arguments:
            $decorated: '@.inner'
```

**Benefits:**
- Each processor has one responsibility
- Easy to test in isolation
- Can reorder/disable processors easily
- Clear separation of concerns

---

### 🔴 ARCH-002: Duplicate Order/Inquiry Logic
- **Status**: ❌ Not Started
- **Priority**: 🔴 HIGH
- **Location**: `src/Entity/Order.php`, `src/Entity/Inquiry.php`
- **Effort**: 6 hours
- **Impact**: DRY principle, maintainability

**Problem:**
Both entities have nearly identical methods:
- `calculateTotalAmount()`
- `saveDraft()`
- `submitOrder()` / `submitInquiry()`
- `canBeSubmitted()` / `canBeSubmitted()`
- `getSubmissionErrors()`

**Solution - Create Shared Trait:**

```php
// src/Entity/Trait/DraftSubmissionTrait.php
trait DraftSubmissionTrait
{
    #[ORM\Column(type: 'boolean')]
    private bool $isDraft = true;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastSavedAt = null;

    abstract public function getItems(): Collection;
    abstract public function getStatus(): string;
    abstract public function setStatus(string $status): self;
    abstract protected function getDraftStatus(): string;
    abstract protected function getSubmittedStatus(): string;

    public function isDraft(): bool
    {
        return $this->isDraft;
    }

    public function saveDraft(): self
    {
        $this->isDraft = true;
        $this->lastSavedAt = new \DateTime();
        $this->setStatus($this->getDraftStatus());
        return $this;
    }

    public function submit(): self
    {
        if (!$this->canBeSubmitted()) {
            throw new \RuntimeException('Cannot submit: ' . implode(', ', $this->getSubmissionErrors()));
        }

        $this->isDraft = false;
        $this->lastSavedAt = new \DateTime();
        $this->setStatus($this->getSubmittedStatus());

        return $this;
    }

    public function canBeSubmitted(): bool
    {
        return count($this->getSubmissionErrors()) === 0;
    }

    public function getSubmissionErrors(): array
    {
        $errors = [];

        if ($this->getItems()->isEmpty()) {
            $errors[] = 'Must have at least one item';
        }

        return $errors;
    }
}
```

**Update entities:**
```php
class Order
{
    use DraftSubmissionTrait;

    protected function getDraftStatus(): string
    {
        return self::STATUS_DRAFT;
    }

    protected function getSubmittedStatus(): string
    {
        return self::STATUS_SUBMITTED;
    }

    public function getItems(): Collection
    {
        return $this->orderItems;
    }

    public function getSubmissionErrors(): array
    {
        $errors = parent::getSubmissionErrors();

        // Order-specific validation
        if ($this->getTotalAmount() <= 0) {
            $errors[] = 'Total amount must be greater than zero';
        }

        return $errors;
    }
}
```

---

### 🔴 PERF-001: N+1 Query in PriceCalculator
- **Status**: ❌ Not Started
- **Priority**: 🔴 HIGH
- **Location**: `src/Service/PriceCalculator.php:17`
- **Effort**: 2 hours
- **Impact**: Performance, database load

**Problem:**
```php
// Line 17 - Iterates through collection
foreach ($client->getClientProductPrices() as $clientProductPrice) {
    if ($clientProductPrice->getProduct()->getId()->toRfc4122() === $product->getId()->toRfc4122()) {
        return $clientProductPrice->getPrice();
    }
}
```

**Solution - Use Repository Query:**

```php
// src/Repository/ClientProductPriceRepository.php
public function findPriceForClientAndProduct(Client $client, Product $product): ?float
{
    $result = $this->createQueryBuilder('cpp')
        ->select('cpp.price')
        ->where('cpp.client = :client')
        ->andWhere('cpp.product = :product')
        ->setParameter('client', $client)
        ->setParameter('product', $product)
        ->getQuery()
        ->getOneOrNullResult();

    return $result['price'] ?? null;
}

// src/Service/PriceCalculator.php
public function getClientProductPrice(Client $client, Product $product): ?float
{
    return $this->clientProductPriceRepository
        ->findPriceForClientAndProduct($client, $product);
}
```

**Optimization - Batch Loading:**
```php
// For order items, load all prices at once
public function getClientProductPrices(Client $client, array $productIds): array
{
    $results = $this->createQueryBuilder('cpp')
        ->select('IDENTITY(cpp.product) as product_id', 'cpp.price')
        ->where('cpp.client = :client')
        ->andWhere('cpp.product IN (:products)')
        ->setParameter('client', $client)
        ->setParameter('products', $productIds)
        ->getQuery()
        ->getArrayResult();

    return array_column($results, 'price', 'product_id');
}
```

---

### 🔴 VALID-001: No Status Transition Validation
- **Status**: ❌ Not Started
- **Priority**: 🔴 HIGH
- **Location**: `src/Entity/Order.php`, `src/Entity/Inquiry.php`
- **Effort**: 4 hours
- **Impact**: Data integrity, business logic enforcement

**Problem:**
Can change from any status to any other status without validation.

**Solution - Implement State Machine:**

1. Install Symfony Workflow:
```bash
composer require symfony/workflow
```

2. Configure workflow in `config/packages/workflow.yaml`:
```yaml
framework:
    workflows:
        order:
            type: 'state_machine'
            audit_trail:
                enabled: true
            marking_store:
                type: 'method'
                property: 'status'
            supports:
                - App\Entity\Order
            initial_marking: draft
            places:
                - draft
                - submitted
                - confirmed
                - dispatched
                - completed
                - canceled
            transitions:
                submit:
                    from: draft
                    to: submitted
                confirm:
                    from: submitted
                    to: confirmed
                dispatch:
                    from: confirmed
                    to: dispatched
                complete:
                    from: dispatched
                    to: completed
                cancel:
                    from: [draft, submitted, confirmed]
                    to: canceled

        inquiry:
            type: 'state_machine'
            marking_store:
                type: 'method'
                property: 'status'
            supports:
                - App\Entity\Inquiry
            initial_marking: draft
            places:
                - draft
                - submitted
                - pending
                - processing
                - confirmed
                - dispatched
                - completed
                - canceled
            transitions:
                submit:
                    from: draft
                    to: submitted
                move_to_pending:
                    from: submitted
                    to: pending
                start_processing:
                    from: pending
                    to: processing
                confirm:
                    from: processing
                    to: confirmed
                dispatch:
                    from: confirmed
                    to: dispatched
                complete:
                    from: dispatched
                    to: completed
                cancel:
                    from: [draft, submitted, pending, processing, confirmed]
                    to: canceled
```

3. Update entities:
```php
// Order.php
public function setStatus(string $status): self
{
    // Remove direct setter - use workflow only
    throw new \BadMethodCallException('Use workflow to change status');
}

// Only allow status changes through workflow
private function changeStatus(string $status): void
{
    $this->status = $status;
}
```

4. Use in controllers/processors:
```php
use Symfony\Component\Workflow\WorkflowInterface;

class OrderController
{
    public function __construct(
        private WorkflowInterface $orderStateMachine,
    ) {}

    public function confirmOrder(Order $order): Response
    {
        if (!$this->orderStateMachine->can($order, 'confirm')) {
            throw new BadRequestException('Order cannot be confirmed in current status');
        }

        $this->orderStateMachine->apply($order, 'confirm');
        $this->entityManager->flush();

        return new JsonResponse(['status' => 'confirmed']);
    }
}
```

5. Add event listeners for side effects:
```php
// src/EventSubscriber/OrderWorkflowSubscriber.php
class OrderWorkflowSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.order.completed' => 'onOrderCompleted',
            'workflow.order.transition.confirm' => 'onOrderConfirm',
        ];
    }

    public function onOrderCompleted(Event $event): void
    {
        /** @var Order $order */
        $order = $event->getSubject();

        // Dispatch notification
        $this->messageBus->dispatch(new OrderCompletedMessage($order->getId()));
    }
}
```

---

### 🔴 SEC-001: Add Rate Limiting
- **Status**: ❌ Not Started
- **Priority**: 🔴 HIGH
- **Location**: All controllers, especially export endpoints
- **Effort**: 3 hours
- **Impact**: Security, resource protection

**Solution:**

1. Install rate limiter:
```bash
composer require symfony/rate-limiter
```

2. Configure in `config/packages/rate_limiter.yaml`:
```yaml
framework:
    rate_limiter:
        # General API rate limit
        api:
            policy: 'sliding_window'
            limit: 100
            interval: '1 minute'

        # Strict limit for expensive operations
        exports:
            policy: 'token_bucket'
            limit: 5
            rate: { interval: '1 minute', amount: 1 }

        # Auth endpoints
        login:
            policy: 'fixed_window'
            limit: 5
            interval: '15 minutes'
```

3. Apply to controllers:
```php
use Symfony\Component\RateLimiter\RateLimiterFactory;

class OrderExcelController
{
    public function __construct(
        private RateLimiterFactory $exportsLimiter,
    ) {}

    #[Route('/api/orders/{id}/export/excel', methods: ['GET'])]
    public function export(Order $order, Request $request): Response
    {
        $limiter = $this->exportsLimiter->create($request->getClientIp());

        if (!$limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException('Rate limit exceeded');
        }

        // ... export logic
    }
}
```

4. Add to login endpoint:
```php
// config/packages/security.yaml
security:
    firewalls:
        login:
            pattern: ^/api/login
            stateless: true
            json_login:
                check_path: /api/login
                username_path: email
                password_path: password
            rate_limiter:
                limiter: login
```

---

### 🔴 SEC-002: Password Validation
- **Status**: ❌ Not Started
- **Priority**: 🔴 HIGH
- **Location**: `src/Entity/User.php`
- **Effort**: 1 hour
- **Impact**: Security, password strength

**Solution:**

1. Add validation constraints:
```php
// User.php
#[Assert\NotCompromisedPassword]
#[Assert\Length(
    min: 8,
    minMessage: 'Password must be at least {{ limit }} characters long'
)]
#[Assert\Regex(
    pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/',
    message: 'Password must contain at least one lowercase letter, one uppercase letter, and one number'
)]
#[Groups(['user:write'])]
private ?string $plainPassword = null;
```

2. Configure in `.env`:
```env
# Minimum password strength (0-4, where 4 is strongest)
PASSWORD_MIN_STRENGTH=3
```

3. Remove plainPassword from response groups:
```php
// User.php
#[Groups(['user:write'])] // Only in write, never in read!
private ?string $plainPassword = null;
```

---

### 🔴 PERF-002: Add Database Indexes
- **Status**: ❌ Not Started
- **Priority**: 🔴 HIGH
- **Location**: All entities
- **Effort**: 2 hours
- **Impact**: Query performance

**Solution:**

Create migration:
```php
public function up(Schema $schema): void
{
    // User table
    $this->addSql('CREATE INDEX IDX_user_email ON "user" (email)');
    $this->addSql('CREATE INDEX IDX_user_client_active ON "user" (client_id, is_active)');

    // Order table
    $this->addSql('CREATE INDEX IDX_order_user ON "order" (user_id)');
    $this->addSql('CREATE INDEX IDX_order_status ON "order" (status)');
    $this->addSql('CREATE INDEX IDX_order_created ON "order" (created_at)');
    $this->addSql('CREATE INDEX IDX_order_user_status ON "order" (user_id, status)');

    // Inquiry table
    $this->addSql('CREATE INDEX IDX_inquiry_user ON inquiry (user_id)');
    $this->addSql('CREATE INDEX IDX_inquiry_status ON inquiry (status)');
    $this->addSql('CREATE INDEX IDX_inquiry_created ON inquiry (created_at)');

    // Product table
    $this->addSql('CREATE INDEX IDX_product_slug ON product (slug)');
    $this->addSql('CREATE INDEX IDX_product_part_no ON product (part_no)');

    // Client table
    $this->addSql('CREATE INDEX IDX_client_active ON client (is_active)');

    // OrderItem table
    $this->addSql('CREATE INDEX IDX_order_item_product ON order_item (product_id)');

    // ClientProductPrice table
    $this->addSql('CREATE INDEX IDX_client_product_price_lookup ON client_product_price (client_id, product_id)');
}
```

---

### 🔴 PERF-003: Optimize Eager Loading
- **Status**: ❌ Not Started
- **Priority**: 🔴 HIGH
- **Location**: `config/packages/api_platform.yaml`
- **Effort**: 4 hours
- **Impact**: Performance, memory usage

**Problem:**
```yaml
# api_platform.yaml
api_platform:
    eager_loading:
        enabled: true
        max_joins: 50  # ❌ Way too high!
```

**Solution:**

1. Update config:
```yaml
api_platform:
    eager_loading:
        enabled: true
        max_joins: 10  # More reasonable default
        fetch_partial: true  # Only fetch needed fields
```

2. Disable eager loading for specific resources:
```yaml
# config/api_platform/resources/Order.yaml
App\Entity\Order:
    operations:
        get:
            normalizationContext:
                enable_max_depth: true
                groups: ['order:read']
        getCollection:
            normalizationContext:
                enable_max_depth: true
                groups: ['order:list']  # Limited fields for lists
            paginationClientEnabled: true
            paginationClientItemsPerPage: true
            paginationMaximumItemsPerPage: 100
```

3. Use custom state providers for complex queries:
```php
// src/State/Provider/OrderListProvider.php
final class OrderListProvider implements ProviderInterface
{
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // Custom DQL with specific joins
        return $this->orderRepository->createQueryBuilder('o')
            ->select('o', 'u', 'c')
            ->leftJoin('o.user', 'u')
            ->leftJoin('u.client', 'c')
            ->addSelect('COUNT(oi) as itemCount')
            ->leftJoin('o.orderItems', 'oi')
            ->groupBy('o.id')
            ->getQuery()
            ->getResult();
    }
}
```

---

## Medium Priority Issues

### 🟡 CODE-002: MediaItem Hardcoded Defaults
- **Status**: ❌ Not Started
- **Priority**: 🟡 MEDIUM
- **Location**: `src/Entity/MediaItem.php:146-148`
- **Effort**: 1 hour
- **Impact**: Code smell, configurability

**Problem:**
```php
public function __construct()
{
    $this->filePath = '/uploads/placeholder.jpg';
    $this->fileName = 'placeholder.jpg';
    $this->mimeType = 'image/jpeg';
}
```

**Solution:**

1. Remove from constructor, use factory instead:
```php
// src/Factory/MediaItemFactory.php
class MediaItemFactory
{
    public function __construct(
        #[Autowire(param: 'app.media.placeholder_path')]
        private readonly string $placeholderPath,
    ) {}

    public function createPlaceholder(): MediaItem
    {
        $item = new MediaItem();
        $item->setFilePath($this->placeholderPath);
        $item->setFileName('placeholder.jpg');
        $item->setMimeType('image/jpeg');
        return $item;
    }

    public function createFromUpload(UploadedFile $file): MediaItem
    {
        $item = new MediaItem();
        // ... handle upload
        return $item;
    }
}
```

2. Add to config:
```yaml
# config/services.yaml
parameters:
    app.media.placeholder_path: '/uploads/placeholder.jpg'
```

---

### 🟡 MAINT-001: Add File Cleanup for Orphaned Media
- **Status**: ❌ Not Started
- **Priority**: 🟡 MEDIUM
- **Location**: `src/Entity/MediaItem.php`
- **Effort**: 3 hours
- **Impact**: Disk space management

**Solution:**

1. Create event listener:
```php
// src/EventListener/MediaItemRemovalListener.php
#[AsDoctrineListener(event: Events::preRemove)]
class MediaItemRemovalListener
{
    public function __construct(
        private readonly string $uploadDirectory,
    ) {}

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof MediaItem) {
            return;
        }

        $filePath = $this->uploadDirectory . '/' . $entity->getFilePath();

        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}
```

2. Create cleanup command for orphans:
```php
// src/Command/CleanupOrphanedMediaCommand.php
#[AsCommand(
    name: 'app:media:cleanup-orphans',
    description: 'Remove media files not referenced in database'
)]
class CleanupOrphanedMediaCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Get all file paths from database
        $dbFiles = $this->mediaItemRepository->createQueryBuilder('m')
            ->select('m.filePath')
            ->getQuery()
            ->getSingleColumnResult();

        // Get all files from disk
        $diskFiles = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->uploadDirectory)
        );

        $removed = 0;
        foreach ($diskFiles as $file) {
            if ($file->isFile() && !in_array($file->getPathname(), $dbFiles)) {
                unlink($file->getPathname());
                $removed++;
            }
        }

        $output->writeln("Removed $removed orphaned files");
        return Command::SUCCESS;
    }
}
```

3. Schedule in cron:
```bash
# Run weekly
0 2 * * 0 cd /path/to/app && php bin/console app:media:cleanup-orphans
```

---

### 🟡 VALID-002: Add Validation Constraints
- **Status**: ❌ Not Started
- **Priority**: 🟡 MEDIUM
- **Location**: Multiple entities
- **Effort**: 3 hours
- **Impact**: Data integrity

**Solution:**

```php
// OrderItem.php
#[Assert\Positive(message: 'Quantity must be at least 1')]
private ?int $quantity = null;

#[Assert\PositiveOrZero(message: 'Price cannot be negative')]
private ?float $unitPrice = null;

// Product.php
#[Assert\PositiveOrZero(message: 'Price cannot be negative')]
private ?float $price = null;

#[Assert\Positive(message: 'Weight must be positive')]
private ?float $weight = null;

// Machine.php
#[Assert\Callback]
public function validateWarrantyDates(ExecutionContextInterface $context): void
{
    if ($this->warrantyEndDate && $this->warrantyEndDate < new \DateTime()) {
        $context->buildViolation('Warranty end date cannot be in the past')
            ->atPath('warrantyEndDate')
            ->addViolation();
    }
}

// Client.php - Enforce mutual exclusion
#[Assert\Callback]
public function validateActiveArchived(ExecutionContextInterface $context): void
{
    if ($this->isActive && $this->isArchived) {
        $context->buildViolation('Client cannot be both active and archived')
            ->atPath('isActive')
            ->addViolation();
    }
}
```

Add database constraints:
```php
public function up(Schema $schema): void
{
    // PostgreSQL check constraints
    $this->addSql('ALTER TABLE order_item ADD CONSTRAINT check_quantity_positive CHECK (quantity > 0)');
    $this->addSql('ALTER TABLE product ADD CONSTRAINT check_price_not_negative CHECK (price >= 0)');
    $this->addSql('ALTER TABLE client ADD CONSTRAINT check_active_archived_exclusive CHECK (NOT (is_active AND is_archived))');
}
```

---

### 🟡 CODE-003: Extract Slugify to Service
- **Status**: ❌ Not Started
- **Priority**: 🟡 MEDIUM
- **Location**: `src/Command/ImportProductsCommand.php:414-432`
- **Effort**: 30 minutes
- **Impact**: DRY, code reuse

**Solution:**

Replace custom method with Symfony's AsciiSlugger:
```php
// ImportProductsCommand.php
use Symfony\Component\String\Slugger\SluggerInterface;

public function __construct(
    private readonly SluggerInterface $slugger,
) {
    parent::__construct();
}

// Replace slugify() calls with:
$slug = $this->slugger->slug($productName)->lower()->toString();

// Remove slugify() method entirely (lines 414-432)
```

---

### 🟡 CONFIG-003: Move Faker to dev Dependencies
- **Status**: ❌ Not Started
- **Priority**: 🟡 MEDIUM
- **Location**: `composer.json`
- **Effort**: 5 minutes
- **Impact**: Production security, package size

**Solution:**

```bash
composer remove fakerphp/faker
composer require --dev fakerphp/faker
```

---

### 🟡 MAINT-002: Add Static Analysis Tools
- **Status**: ❌ Not Started
- **Priority**: 🟡 MEDIUM
- **Location**: Project root
- **Effort**: 2 hours
- **Impact**: Code quality, bug prevention

**Solution:**

1. Install PHPStan:
```bash
composer require --dev phpstan/phpstan phpstan/phpstan-symfony phpstan/phpstan-doctrine
```

2. Create `phpstan.neon`:
```neon
parameters:
    level: 8
    paths:
        - src
        - tests
    excludePaths:
        - src/Kernel.php
    symfony:
        container_xml_path: var/cache/dev/App_KernelDevDebugContainer.xml
    doctrine:
        objectManagerLoader: tests/object-manager.php
    ignoreErrors:
        - '#Access to an undefined property Doctrine\\ORM\\QueryBuilder::\$expr#'
```

3. Install PHP-CS-Fixer:
```bash
composer require --dev friendsofphp/php-cs-fixer
```

4. Create `.php-cs-fixer.php`:
```php
<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests');

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => true,
        'no_unused_imports' => true,
        'strict_comparison' => true,
        'strict_param' => true,
    ])
    ->setFinder($finder);
```

5. Add to composer scripts:
```json
{
    "scripts": {
        "phpstan": "phpstan analyse",
        "cs-fix": "php-cs-fixer fix",
        "cs-check": "php-cs-fixer fix --dry-run --diff",
        "quality": [
            "@phpstan",
            "@cs-check"
        ]
    }
}
```

---

### 🟡 PERF-004: Add Cached Count Updates
- **Status**: ❌ Not Started
- **Priority**: 🟡 MEDIUM
- **Location**: `src/Entity/Client.php`, `src/Entity/Machine.php`
- **Effort**: 2 hours
- **Impact**: Data consistency

**Solution:**

1. Create event listener:
```php
// src/EventListener/ClientMachineCountListener.php
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
class ClientMachineCountListener
{
    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->updateCount($args->getObject(), $args->getObjectManager());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->updateCount($args->getObject(), $args->getObjectManager());
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->updateCount($args->getObject(), $args->getObjectManager());
    }

    private function updateCount(object $entity, ObjectManager $om): void
    {
        if (!$entity instanceof ClientMachineInstalledBase) {
            return;
        }

        $client = $entity->getClient();

        // Update machines count
        $count = $om->getRepository(ClientMachineInstalledBase::class)
            ->count(['client' => $client]);

        $client->setMachinesCount($count);
        $om->flush();
    }
}
```

2. Add documentation:
```php
// Client.php
/**
 * Cached count of machines.
 * Automatically updated by ClientMachineCountListener.
 */
#[ORM\Column(type: 'integer', nullable: true)]
private ?int $machinesCount = null;
```

---

### 🟡 SEC-003: Add Email Verification
- **Status**: ❌ Not Started
- **Priority**: 🟡 MEDIUM
- **Location**: `src/Entity/User.php`
- **Effort**: 4 hours
- **Impact**: Security, user management

**Solution:**

1. Install SymfonyCasts verify-email-bundle:
```bash
composer require symfonycasts/verify-email-bundle
```

2. Add fields to User entity:
```php
#[ORM\Column(type: 'boolean')]
private bool $isVerified = false;

public function isVerified(): bool
{
    return $this->isVerified;
}

public function setIsVerified(bool $isVerified): self
{
    $this->isVerified = $isVerified;
    return $this;
}
```

3. Create migration:
```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

4. Create verification controller:
```php
// src/Controller/VerifyEmailController.php
#[Route('/api/verify/email')]
class VerifyEmailController extends AbstractController
{
    public function __construct(
        private VerifyEmailHelperInterface $verifyEmailHelper,
    ) {}

    public function verify(Request $request, UserRepository $userRepository): JsonResponse
    {
        $id = $request->query->get('id');
        $user = $userRepository->find($id);

        if (!$user) {
            throw $this->createNotFoundException();
        }

        try {
            $this->verifyEmailHelper->validateEmailConfirmation(
                $request->getUri(),
                $user->getId(),
                $user->getEmail()
            );
        } catch (VerifyEmailExceptionInterface $e) {
            return new JsonResponse(['error' => $e->getReason()], 400);
        }

        $user->setIsVerified(true);
        $userRepository->save($user, true);

        return new JsonResponse(['message' => 'Email verified successfully']);
    }
}
```

5. Add check in authentication:
```php
// config/packages/security.yaml
security:
    access_control:
        - { path: ^/api, roles: IS_AUTHENTICATED_FULLY }

# Create voter to check verification
class VerifiedUserVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === 'IS_VERIFIED';
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        return $user->isVerified();
    }
}
```

---

## Low Priority Improvements

### 🟢 TECH-001: Upgrade to PHP 8.2+ for Enums
- **Status**: ❌ Not Started
- **Priority**: 🟢 LOW
- **Effort**: 6 hours
- **Impact**: Type safety, modern code

**Solution:**

1. Update composer.json:
```json
{
    "require": {
        "php": ">=8.2"
    }
}
```

2. Convert status constants to enums:
```php
// src/Enum/OrderStatus.php
enum OrderStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case CONFIRMED = 'confirmed';
    case DISPATCHED = 'dispatched';
    case COMPLETED = 'completed';
    case CANCELED = 'canceled';

    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Submitted',
            self::CONFIRMED => 'Confirmed',
            self::DISPATCHED => 'Dispatched',
            self::COMPLETED => 'Completed',
            self::CANCELED => 'Canceled',
        };
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return match($this) {
            self::DRAFT => in_array($newStatus, [self::SUBMITTED, self::CANCELED]),
            self::SUBMITTED => in_array($newStatus, [self::CONFIRMED, self::CANCELED]),
            self::CONFIRMED => in_array($newStatus, [self::DISPATCHED, self::CANCELED]),
            self::DISPATCHED => $newStatus === self::COMPLETED,
            self::COMPLETED => false,
            self::CANCELED => false,
        };
    }
}

// Update Order entity
#[ORM\Column(type: 'string', enumType: OrderStatus::class)]
private OrderStatus $status = OrderStatus::DRAFT;

public function getStatus(): OrderStatus
{
    return $this->status;
}

public function setStatus(OrderStatus $status): self
{
    if (!$this->status->canTransitionTo($status)) {
        throw new \InvalidArgumentException(
            "Cannot transition from {$this->status->value} to {$status->value}"
        );
    }

    $this->status = $status;
    return $this;
}
```

---

### 🟢 DOC-001: Create Comprehensive Documentation
- **Status**: ❌ Not Started
- **Priority**: 🟢 LOW
- **Effort**: 8 hours
- **Impact**: Developer onboarding, maintenance

**Solution:**

Create the following documents:

1. **README.md** - Project overview, installation
2. **CONTRIBUTING.md** - Contribution guidelines
3. **CHANGELOG.md** - Version history
4. **docs/ARCHITECTURE.md** - System architecture
5. **docs/API.md** - API documentation
6. **docs/DEPLOYMENT.md** - Deployment guide
7. **docs/DEVELOPMENT.md** - Local setup guide

Example README structure:
```markdown
# Deckard Inquiry Tool Backend API

## Overview
REST API for managing product inquiries and orders for Deckard clients.

## Tech Stack
- PHP 8.1
- Symfony 6.4 LTS
- API Platform 4.0
- PostgreSQL
- JWT Authentication

## Installation
See [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md)

## API Documentation
See [docs/API.md](docs/API.md)

## Deployment
See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)

## Contributing
See [CONTRIBUTING.md](CONTRIBUTING.md)
```

---

### 🟢 PERF-005: Add Redis Caching
- **Status**: ❌ Not Started
- **Priority**: 🟢 LOW
- **Location**: N/A
- **Effort**: 4 hours
- **Impact**: Performance for read-heavy operations

**Solution:**

1. Install Redis bundle:
```bash
composer require symfony/cache
```

2. Configure Redis:
```yaml
# config/packages/cache.yaml
framework:
    cache:
        app: cache.adapter.redis
        default_redis_provider: redis://localhost:6379
        pools:
            cache.products:
                adapter: cache.adapter.redis
                default_lifetime: 3600
            cache.prices:
                adapter: cache.adapter.redis
                default_lifetime: 1800
```

3. Use in repositories:
```php
// src/Repository/ProductRepository.php
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

public function __construct(
    private CacheInterface $productsCache,
) {
    parent::__construct($registry, Product::class);
}

public function findAllCached(): array
{
    return $this->productsCache->get('all_products', function (ItemInterface $item) {
        $item->expiresAfter(3600);
        return $this->findAll();
    });
}

public function findBySlugCached(string $slug): ?Product
{
    return $this->productsCache->get("product_$slug", function (ItemInterface $item) use ($slug) {
        $item->expiresAfter(3600);
        return $this->findOneBy(['slug' => $slug]);
    });
}
```

4. Invalidate cache on changes:
```php
// src/EventListener/ProductCacheInvalidator.php
#[AsDoctrineListener(event: Events::postUpdate, entity: Product::class)]
#[AsDoctrineListener(event: Events::postPersist, entity: Product::class)]
#[AsDoctrineListener(event: Events::postRemove, entity: Product::class)]
class ProductCacheInvalidator
{
    public function __construct(
        private CacheInterface $productsCache,
    ) {}

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->clearCache($args->getObject());
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->clearCache($args->getObject());
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->clearCache($args->getObject());
    }

    private function clearCache(object $entity): void
    {
        if (!$entity instanceof Product) {
            return;
        }

        $this->productsCache->delete('all_products');
        $this->productsCache->delete("product_{$entity->getSlug()}");
    }
}
```

---

### 🟢 FEAT-001: Add API Versioning
- **Status**: ❌ Not Started
- **Priority**: 🟢 LOW
- **Location**: API Platform configuration
- **Effort**: 3 hours
- **Impact**: API evolution, backwards compatibility

**Solution:**

1. Configure versioning:
```yaml
# config/api_platform/resources/Order.yaml
App\Entity\Order:
    uriTemplate: '/v1/orders'
    shortName: Order
    # v1 operations...

# Future versions
App\Entity\Order:
    uriTemplate: '/v2/orders'
    shortName: OrderV2
    # v2 operations with breaking changes...
```

2. Version-specific DTOs:
```php
// src/ApiResource/V1/OrderResource.php
#[ApiResource(
    uriTemplate: '/v1/orders/{id}',
    operations: [new Get(), new Put(), new Delete()],
    normalizationContext: ['groups' => ['order:v1:read']],
)]
class OrderResourceV1
{
    // v1 structure
}

// src/ApiResource/V2/OrderResource.php
#[ApiResource(
    uriTemplate: '/v2/orders/{id}',
    operations: [new Get(), new Put(), new Delete()],
    normalizationContext: ['groups' => ['order:v2:read']],
)]
class OrderResourceV2
{
    // v2 structure with breaking changes
}
```

3. Header-based versioning (alternative):
```php
// src/EventSubscriber/ApiVersionSubscriber.php
class ApiVersionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $version = $request->headers->get('X-API-Version', 'v1');
        $request->attributes->set('_api_version', $version);
    }
}
```

---

### 🟢 OPT-001: Add APM Monitoring
- **Status**: ❌ Not Started
- **Priority**: 🟢 LOW
- **Location**: N/A
- **Effort**: 3 hours
- **Impact**: Performance monitoring, debugging

**Solution:**

Choose one APM solution:

**Option 1 - Blackfire (Symfony optimized):**
```bash
composer require blackfire/php-sdk
```

**Option 2 - New Relic:**
```bash
# Install New Relic PHP agent
curl -L https://download.newrelic.com/php_agent/release/newrelic-php5-*.tar.gz | tar -C /tmp -zx
cd /tmp/newrelic-php5-*
./newrelic-install

# Add to php.ini
newrelic.appname = "Deckard API"
newrelic.license = "YOUR_LICENSE_KEY"
```

**Option 3 - Datadog:**
```bash
composer require datadog/dd-trace
```

Add monitoring to critical paths:
```php
// src/EventSubscriber/PerformanceMonitoringSubscriber.php
class PerformanceMonitoringSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 255],
            KernelEvents::RESPONSE => ['onKernelResponse', -255],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $request->attributes->set('_request_start_time', microtime(true));
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        $duration = microtime(true) - $request->attributes->get('_request_start_time');

        // Send to APM
        $this->logger->info('Request completed', [
            'duration_ms' => round($duration * 1000, 2),
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'status' => $response->getStatusCode(),
        ]);
    }
}
```

---

### 🟢 OPT-002: Add Image Optimization
- **Status**: ❌ Not Started
- **Priority**: 🟢 LOW
- **Location**: `src/Entity/MediaItem.php`
- **Effort**: 4 hours
- **Impact**: Performance, storage

**Solution:**

1. LiipImagineBundle is already installed, configure it:
```yaml
# config/packages/liip_imagine.yaml
liip_imagine:
    driver: "gd"
    cache: default
    data_loader: default
    default_image: null
    controller:
        filter_action: liip_imagine.controller:filterAction
        filter_runtime_action: liip_imagine.controller:filterRuntimeAction

    filter_sets:
        # Product thumbnails
        product_thumb:
            quality: 85
            filters:
                thumbnail: { size: [300, 300], mode: outbound }
                auto_rotate: ~

        # Product gallery
        product_gallery:
            quality: 90
            filters:
                thumbnail: { size: [800, 800], mode: inset }
                auto_rotate: ~

        # High resolution
        product_full:
            quality: 95
            filters:
                thumbnail: { size: [1920, 1920], mode: inset }
                auto_rotate: ~

        # Machine images
        machine_thumb:
            quality: 85
            filters:
                thumbnail: { size: [400, 300], mode: outbound }
                auto_rotate: ~
```

2. Create WebP versions automatically:
```php
// src/EventListener/ImageOptimizationListener.php
#[AsDoctrineListener(event: Events::postPersist, entity: MediaItem::class)]
class ImageOptimizationListener
{
    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof MediaItem) {
            return;
        }

        if (!str_starts_with($entity->getMimeType(), 'image/')) {
            return;
        }

        // Dispatch async job to create WebP version
        $this->messageBus->dispatch(new CreateWebPVersionMessage($entity->getId()));
    }
}

// src/MessageHandler/CreateWebPVersionMessageHandler.php
class CreateWebPVersionMessageHandler implements MessageHandlerInterface
{
    public function __invoke(CreateWebPVersionMessage $message): void
    {
        $mediaItem = $this->mediaItemRepository->find($message->getMediaItemId());

        if (!$mediaItem) {
            return;
        }

        $originalPath = $this->uploadDirectory . '/' . $mediaItem->getFilePath();
        $webpPath = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $originalPath);

        // Convert to WebP
        $image = imagecreatefromstring(file_get_contents($originalPath));
        imagewebp($image, $webpPath, 85);
        imagedestroy($image);

        // Store WebP path in database
        $mediaItem->setWebpPath(str_replace($this->uploadDirectory, '', $webpPath));
        $this->entityManager->flush();
    }
}
```

3. Serve WebP with fallback:
```php
// Add to MediaItem.php
#[ORM\Column(type: 'string', nullable: true)]
private ?string $webpPath = null;

public function getOptimizedPath(bool $supportsWebP = false): string
{
    if ($supportsWebP && $this->webpPath) {
        return $this->webpPath;
    }

    return $this->filePath;
}
```

---

### 🟢 INFRA-001: Add Docker Configuration
- **Status**: ❌ Not Started
- **Priority**: 🟢 LOW
- **Location**: Project root
- **Effort**: 4 hours
- **Impact**: Environment consistency

**Solution:**

Create `docker-compose.yml`:
```yaml
version: '3.8'

services:
    php:
        build:
            context: .
            dockerfile: docker/php/Dockerfile
        volumes:
            - .:/var/www/html
        environment:
            DATABASE_URL: postgresql://app:secret@postgres:5432/deckard?serverVersion=15&charset=utf8
            MESSENGER_TRANSPORT_DSN: doctrine://default
        depends_on:
            - postgres
            - redis

    nginx:
        image: nginx:alpine
        ports:
            - "8080:80"
        volumes:
            - .:/var/www/html
            - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
        depends_on:
            - php

    postgres:
        image: postgres:15-alpine
        environment:
            POSTGRES_DB: deckard
            POSTGRES_USER: app
            POSTGRES_PASSWORD: secret
        volumes:
            - postgres_data:/var/lib/postgresql/data
        ports:
            - "5432:5432"

    redis:
        image: redis:7-alpine
        ports:
            - "6379:6379"

    messenger-worker:
        build:
            context: .
            dockerfile: docker/php/Dockerfile
        command: php bin/console messenger:consume async --limit=100
        volumes:
            - .:/var/www/html
        depends_on:
            - postgres
            - redis
        restart: unless-stopped

volumes:
    postgres_data:
```

Create `docker/php/Dockerfile`:
```dockerfile
FROM php:8.1-fpm-alpine

# Install dependencies
RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    zip \
    git \
    && docker-php-ext-install pdo_pgsql zip opcache

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configure PHP
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/html

CMD ["php-fpm"]
```

---

## Tracking Progress

### How to Use This Document

1. **Before starting work:**
   - Find the issue you want to work on
   - Change status from ❌ to 🔄
   - Read the entire solution section
   - Test the proposed solution in a feature branch

2. **While working:**
   - Follow the solution steps exactly
   - Add your own notes if you deviate
   - Document any issues encountered

3. **After completion:**
   - Change status from 🔄 to ✅
   - Add completion date
   - Document any differences from proposed solution
   - Link to related PRs/commits

4. **Regular review:**
   - Monthly: Review all 🔴 HIGH priority items
   - Quarterly: Review all 🟡 MEDIUM priority items
   - Annually: Review all 🟢 LOW priority items

### Progress Tracking Template

When marking items as complete, add:

```markdown
### ✅ COMPLETED: [ISSUE-ID] - [Title]
- **Completed Date:** YYYY-MM-DD
- **Completed By:** Name
- **Solution Used:** [As proposed / Custom]
- **Notes:** Any deviations or lessons learned
- **Related:** PR #123, Commit abc123
```

### Priority for Next Sprint

Based on risk and impact, tackle in this order:

1. 🚨 SECURITY-001 (5 min) - **DO THIS FIRST**
2. 🚨 DATA-001 (2 hours)
3. 🚨 DATA-002 (4 hours)
4. 🚨 CONFIG-001 (30 min)
5. 🚨 CONFIG-002 (20 min)
6. 🚨 CODE-001 (Ongoing - start with critical paths)
7. 🔴 ARCH-001 (8 hours)
8. 🔴 PERF-001 (2 hours)
9. 🔴 VALID-001 (4 hours)
10. 🔴 SEC-001 (3 hours)

**Total estimated time for critical issues: ~27 hours (about 1 week of focused work)**

---

## Notes Section

Use this section to add notes, discoveries, or updates:

### [Date] - Discovery Notes
- Found that...
- Need to also update...
- Alternative approach...

---

**Last Updated:** 2025-11-23
**Next Review:** 2025-12-23
**Document Version:** 1.0
