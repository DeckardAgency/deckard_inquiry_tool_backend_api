# Symfony Workflow Component - Status Transitions

This application uses the **Symfony Workflow Component** to manage and enforce status transitions for Inquiry and Order entities.

## Overview

The Workflow Component provides:
- **Strict transition validation** - Invalid status changes are automatically rejected
- **Event-driven email notifications** - Emails are automatically sent when statuses change
- **Audit trail** - All transitions are logged
- **Clear business logic** - Transition rules are defined in YAML configuration
1
---

## Inquiry Workflow

### Status Flow Diagram

```
draft → submitted → in_review → more_info ↔ information_provided → in_progress → completed
                        ↓           ↓              ↓                    ↓
                    canceled    canceled       canceled             canceled
```

### Available Statuses

| Status | Description |
|--------|-------------|
| `draft` | Initial status - inquiry is being drafted |
| `submitted` | Inquiry has been submitted by customer |
| `in_review` | Inquiry is being reviewed by admin |
| `more_info` | Admin requires more information from customer |
| `information_provided` | Customer has provided requested information |
| `in_progress` | Inquiry is actively being processed |
| `completed` | Inquiry has been completed |
| `canceled` | Inquiry has been canceled |

### Allowed Transitions

| From | To | Transition Name | Description |
|------|----|-----------------| ------------|
| `draft` | `submitted` | `submit` | Submit the inquiry |
| `submitted` | `in_review` | `review` | Start reviewing the inquiry |
| `submitted` | `more_info` | `request_more_info` | Request more information |
| `submitted` | `in_progress` | `start_progress` | Start processing directly |
| `submitted` | `canceled` | `cancel` | Cancel the inquiry |
| `in_review` | `more_info` | `request_more_info` | Request more information |
| `in_review` | `in_progress` | `start_progress` | Start processing |
| `in_review` | `canceled` | `cancel` | Cancel the inquiry |
| `more_info` | `information_provided` | `provide_information` | Customer provides information |
| `more_info` | `canceled` | `cancel` | Cancel the inquiry |
| `information_provided` | `more_info` | `request_more_info` | Request more information again |
| `information_provided` | `in_progress` | `start_progress` | Start processing |
| `information_provided` | `canceled` | `cancel` | Cancel the inquiry |
| `in_progress` | `completed` | `complete` | Complete the inquiry |
| `in_progress` | `canceled` | `cancel` | Cancel the inquiry |

---

## Order Workflow

### Status Flow Diagram

```
draft → submitted → confirmed → dispatched → completed
           ↓            ↓
       canceled     canceled
```

### Available Statuses

| Status | Description |
|--------|-------------|
| `draft` | Initial status - order is being drafted |
| `submitted` | Order has been submitted by customer |
| `confirmed` | Order has been confirmed by admin |
| `dispatched` | Order has been shipped/dispatched |
| `completed` | Order has been delivered and completed |
| `canceled` | Order has been canceled |

### Allowed Transitions

| From | To | Transition Name | Description |
|------|----|-----------------| ------------|
| `draft` | `submitted` | `submit` | Submit the order |
| `submitted` | `confirmed` | `confirm` | Confirm the order |
| `submitted` | `canceled` | `cancel` | Cancel the order |
| `confirmed` | `dispatched` | `dispatch` | Dispatch the order |
| `confirmed` | `canceled` | `cancel` | Cancel the order |
| `dispatched` | `completed` | `complete` | Complete the order |

---

## REST API Usage

### Updating Status via API

To change a status, make a `PATCH` or `PUT` request to update the `status` field:

```http
PATCH /api/inquiries/{id}
Content-Type: application/json

{
  "status": "in_review"
}
```

### Success Response

```json
{
  "id": "uuid",
  "inquiryNumber": "INQ-12345678",
  "status": "in_review",
  ...
}
```

### Error Response (Invalid Transition)

If you try to make an invalid transition, you'll receive a `400 Bad Request` or `500 Internal Server Error`:

```json
{
  "error": "Invalid status transition from 'draft' to 'completed' for inquiry INQ-12345678. Available transitions: submit"
}
```

---

## Email Notifications

Emails are automatically sent when status changes occur. The system sends emails to both administrators and customers.

### Email Sender Information

- **Inquiry Emails**: Sent from `Deckard Orders <orders@deckard.com>`
- **Order Emails**: Sent from `Deckard Orders <orders@deckard.com>`

### Inquiry Email Notifications

#### Customer Emails

| Status | Template | Subject | Description |
|--------|----------|---------|-------------|
| `submitted` | `emails/customer/inquiry_confirmation.html.twig` | "Your Deckard Inquiry #[ID] Confirmation" | Sent when inquiry is first submitted |
| `in_review` | `emails/customer/inquiry_in_review.html.twig` | "Update on Deckard Inquiry #[ID]: Currently Under Review" | Notifies customer inquiry is being reviewed |
| `more_info` | `emails/customer/inquiry_more_info.html.twig` | "Action Required: More information for Deckard Inquiry #[ID]" | Requests additional information from customer |
| `information_provided` | `emails/customer/inquiry_information_provided.html.twig` | "Update on Deckard Inquiry #[ID]: Information Received" | Confirms receipt of customer information |
| `in_progress` | `emails/customer/inquiry_in_progress.html.twig` | "Update on Deckard Inquiry #[ID]: In Progress" | Notifies customer work has begun |
| `completed` | `emails/customer/inquiry_completed.html.twig` | "Your Deckard Inquiry #[ID] is Now Complete" | Confirms inquiry completion |
| `canceled` | `emails/customer/inquiry_canceled.html.twig` | "Cancellation Confirmation for Deckard Inquiry #[ID]" | Confirms inquiry cancellation |

#### Admin Emails

| Status | Template | Subject | Description |
|--------|----------|---------|-------------|
| `submitted` | `emails/admin/inquiry_created.html.twig` | "New Inquiry Received: [ID]" | Notifies admin of new inquiry |
| `in_review` | `emails/admin/inquiry_in_review.html.twig` | "Inquiry #[ID] is now in review" | Status update for admin |
| `more_info` | `emails/admin/inquiry_more_info.html.twig` | "Inquiry #[ID] requires more information" | Notifies customer was asked for info |
| `information_provided` | `emails/admin/inquiry_information_provided.html.twig` | "Inquiry #[ID] - information has been provided" | Notifies info was received |
| `in_progress` | `emails/admin/inquiry_in_progress.html.twig` | "Inquiry #[ID] is now in progress" | Status update for admin |
| `completed` | `emails/admin/inquiry_completed.html.twig` | "Inquiry #[ID] has been completed" | Confirms completion |
| `canceled` | `emails/admin/inquiry_canceled.html.twig` | "Inquiry #[ID] has been canceled" | Confirms cancellation |

### Order Email Notifications

#### Customer Emails

| Status | Template | Subject | Description |
|--------|----------|---------|-------------|
| `submitted` | `emails/customer/order_confirmation.html.twig` | "Your Deckard Order #[ID] Confirmation" | Sent when order is first submitted |
| `confirmed` | `emails/customer/order_confirmed.html.twig` | "Your Deckard Order #[ID] Confirmed" | Order has been confirmed |
| `dispatched` | `emails/customer/order_dispatched.html.twig` | "Your Deckard Order #[ID] has Been Dispatched" | Order has been shipped |
| `completed` | `emails/customer/order_completed.html.twig` | "Your Deckard Order #[ID] is Now Complete" | Order delivered successfully |
| `canceled` | `emails/customer/order_canceled.html.twig` | "Cancellation Confirmation for Deckard Order #[ID]" | Order cancellation confirmed |

#### Admin Emails

| Status | Template | Subject | Description |
|--------|----------|---------|-------------|
| `submitted` | `emails/admin/order_submitted.html.twig` | "New Order Received: [ID]" | Notifies admin of new order |
| `confirmed` | `emails/admin/order_confirmed.html.twig` | "Order #[ID] has been confirmed" | Order confirmation update |
| `dispatched` | `emails/admin/order_dispatched.html.twig` | "Order #[ID] has been dispatched" | Order dispatch update |
| `completed` | `emails/admin/order_completed.html.twig` | "Order #[ID] has been completed" | Order completion update |
| `canceled` | `emails/admin/order_canceled.html.twig` | "Order #[ID] has been canceled" | Order cancellation update |

### Email Behavior Rules

**Inquiry Emails:**
- Draft status: No emails sent
- Submitted status: Both admin and customer emails sent
- All other status changes: Both admin and customer emails sent

**Order Emails:**
- Draft status: No emails sent
- Submitted status: Both admin and customer emails sent
- All other status changes: Both admin and customer emails sent

---

## Guards & Validation

### Inquiry Guards

- **Submit transition**: Validates that inquiry has required fields (machines, parts, contact info)
- Configured in `src/EventSubscriber/InquiryWorkflowSubscriber.php`

### Order Guards

- **Dispatch transition**: Validates shipping address exists
- Configured in `src/EventSubscriber/OrderWorkflowSubscriber.php`

You can add custom guards in:
- `src/EventSubscriber/InquiryWorkflowSubscriber.php`
- `src/EventSubscriber/OrderWorkflowSubscriber.php`

---

## Configuration Files

### Workflow Configuration
- **Location**: `config/packages/workflow.yaml`
- Defines all workflows, places, and transitions
- Uses state machine type for strict one-state-at-a-time enforcement

### Event Subscribers
- **Inquiry**: `src/EventSubscriber/InquiryWorkflowSubscriber.php`
  - Handles workflow events
  - Dispatches email notification messages
  - Implements guard validation
- **Order**: `src/EventSubscriber/OrderWorkflowSubscriber.php`
  - Handles workflow events
  - Dispatches email notification messages
  - Implements guard validation

### State Processors
- **Inquiry**: `src/State/Processor/InquiryStatusChangeProcessor.php`
  - Intercepts API Platform status changes
  - Validates workflow transitions before persistence
  - Throws exceptions for invalid transitions
- **Order**: `src/State/Processor/OrderPriceProcessor.php`
  - Handles price calculation
  - Validates workflow transitions
  - Manages order creation and updates

### Message Handlers
- **InquiryCreatedMessageHandler**: `src/MessageHandler/InquiryCreatedMessageHandler.php`
  - Sends emails when new inquiry is created
- **InquiryStatusChangedMessageHandler**: `src/MessageHandler/InquiryStatusChangedMessageHandler.php`
  - Sends emails when inquiry status changes
- **OrderCreatedMessageHandler**: `src/MessageHandler/OrderCreatedMessageHandler.php`
  - Sends emails when new order is created
- **OrderStatusChangedMessageHandler**: `src/MessageHandler/OrderStatusChangedMessageHandler.php`
  - Sends emails when order status changes

---

## Workflow Events

The following events are fired during transitions:

| Event | When Fired | Use Case |
|-------|------------|----------|
| `workflow.inquiry.guard` | Before transition validation | Add custom validation logic |
| `workflow.inquiry.leave` | When leaving a status | Log or perform cleanup |
| `workflow.inquiry.transition` | During the transition | Track transition attempts |
| `workflow.inquiry.enter` | When entering a new status | Trigger side effects |
| `workflow.inquiry.entered` | After entering a new status | Record transition history |
| `workflow.inquiry.completed` | After transition completes | Send emails, dispatch messages |
| `workflow.inquiry.announce` | Before any transition | Pre-transition hooks |

Replace `inquiry` with `order` for Order workflow events.

---

## How It Works

### Status Change Flow

1. **API Request**: Client sends PATCH/PUT request with new status
2. **State Processor Intercepts**: `InquiryStatusChangeProcessor` or `OrderPriceProcessor` intercepts the request
3. **Workflow Validation**:
   - Processor temporarily resets status to original value
   - Determines appropriate transition based on status change
   - Checks if transition is valid using `workflow->can()`
4. **Transition Application**: If valid, applies the transition using `workflow->apply()`
5. **Workflow Events Fire**:
   - `guard` event (validation)
   - `leave` event
   - `transition` event
   - `enter` event
   - `entered` event
   - `completed` event (triggers email dispatch)
6. **Email Dispatch**: Workflow subscriber dispatches message to queue
7. **Email Processing**: Message handler processes email asynchronously
8. **Persistence**: Entity is persisted with new status

### Email Sending Flow

1. **Workflow Completed Event**: Fires after transition succeeds
2. **Subscriber Dispatches Message**: `InquiryWorkflowSubscriber` or `OrderWorkflowSubscriber` dispatches status change message
3. **Message Queue**: Message goes to Symfony Messenger queue
4. **Message Handler**: `InquiryStatusChangedMessageHandler` or `OrderStatusChangedMessageHandler` processes message
5. **Email Rendering**: Template is rendered with entity data
6. **Email Sending**: Email is sent via configured mailer

---

## Debugging

### Check Available Transitions

```php
$workflow = $container->get('state_machine.inquiry');
$availableTransitions = $workflow->getEnabledTransitions($inquiry);
```

### Check If Transition Is Allowed

```php
if ($workflow->can($inquiry, 'submit')) {
    // Transition is allowed
}
```

### View Workflow Diagram

Generate a visual diagram of your workflows:

```bash
php bin/console workflow:dump inquiry | dot -Tpng -o inquiry_workflow.png
php bin/console workflow:dump order | dot -Tpng -o order_workflow.png
```

### Check Registered Subscribers

```bash
php bin/console debug:event-subscriber
```

### Monitor Message Queue

```bash
php bin/console messenger:consume async -vv
```

### Check Logs

```bash
tail -f var/log/dev.log
```

---

## Development Guidelines

### Adding a New Status

1. Add the status constant to the entity (e.g., `Inquiry::STATUS_NEW_STATUS = 'new_status'`)
2. Update `config/packages/workflow.yaml` - add to `places`
3. Add transitions to/from the new status
4. Create customer email template: `templates/emails/customer/inquiry_new_status.html.twig`
5. Create admin email template: `templates/emails/admin/inquiry_new_status.html.twig`
6. Update `InquiryStatusChangedMessageHandler` - add subject line and template mapping
7. Update state processor's `determineTransition()` method
8. Test the transition via API

### Adding Custom Validation

Edit the `onGuard` method in the appropriate workflow subscriber:

```php
public function onGuard(GuardEvent $event): void
{
    $inquiry = $event->getSubject();
    $transition = $event->getTransition()->getName();

    if ($transition === 'submit') {
        if (!$inquiry->canBeSubmitted()) {
            $event->setBlocked(true, 'Inquiry cannot be submitted - missing required fields');

            $this->logger->warning('Inquiry submission blocked', [
                'inquiry_id' => $inquiry->getId()->toRfc4122(),
                'errors' => $inquiry->getSubmissionErrors()
            ]);
        }
    }
}
```

### Creating Email Templates

All email templates use a simple, clean text-based design. Example structure:

```twig
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <p>Dear {{ user ? user.fullName : 'Customer' }},</p>

    <p>Email content here...</p>

    <p>Sincerely, The Deckard Team</p>
</body>
</html>
```

**Important Notes:**
- Customer templates use `{{ user.fullName }}` or fallback to 'Customer'
- Admin templates use "Dear Admin,"
- Include inquiry/order number in all messages
- Keep design simple - no fancy CSS or images
- Maintain consistent tone and structure

---

## Troubleshooting

### "Invalid status transition" Error

**Symptoms**: API returns 500 error with message like "Invalid status transition from 'submitted' to 'completed'"

**Solutions**:
- Check that the transition exists in `workflow.yaml`
- Verify current status in the database
- Ensure the transition is allowed from the current status
- Check available transitions in error message

### Emails Not Sending

**Symptoms**: Status changes work but no emails arrive

**Solutions**:
- Check workflow subscriber is registered: `php bin/console debug:event-subscriber`
- Verify messenger is processing messages: `php bin/console messenger:consume async`
- Check logs for errors: `tail -f var/log/dev.log`
- Verify email templates exist
- Check mailer configuration in `.env`

### Duplicate Emails

**Symptoms**: Multiple identical emails sent for single status change

**Solutions**:
- Ensure only workflow subscriber dispatches messages (not state processor)
- Check that message handlers don't dispatch additional messages
- Verify no duplicate event listeners registered

### Workflow Not Applying

**Symptoms**: Status changes don't validate or workflow seems ignored

**Solutions**:
- Clear cache: `php bin/console cache:clear`
- Check entity has workflow support in `config/packages/workflow.yaml`
- Verify state processor is being called
- Check that workflow is injected correctly in processor

### Template Rendering Errors

**Symptoms**: Email fails with Twig error about missing properties

**Solutions**:
- For `inquiry.machines`, iterate as `inquiryMachine` and access `inquiryMachine.machine.articleDescription`
- Check entity relationships are properly loaded
- Verify template uses correct property names from entity
- Use fallbacks for optional properties: `{{ property ?: 'N/A' }}`

---

## References

- [Symfony Workflow Documentation](https://symfony.com/doc/current/workflow.html)
- [Workflow State Machines](https://symfony.com/doc/current/workflow/state-machines.html)
- [Workflow Events](https://symfony.com/doc/current/workflow.html#using-events)
- [Symfony Messenger](https://symfony.com/doc/current/messenger.html)
