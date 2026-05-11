# Support Ticket System - Setup Instructions

## Overview

A complete support ticket system has been created with the following features:
- Create support tickets with attachments
- Status tracking (open, in_progress, resolved, closed)
- Urgency levels (low, medium, high)
- Automatic email notifications to admin on ticket creation
- File upload support for attachments
- User-specific ticket viewing with admin override

## Backend (Symfony API Platform)

### Files Created:

1. **Entity**: `src/Entity/SupportTicket.php`
   - ID (UUID)
   - Subject (required, 3-255 chars)
   - Message (required, min 10 chars)
   - Order/Inquiry ID (optional)
   - Machine/Product (optional)
   - Urgency (low/medium/high)
   - Status (open/in_progress/resolved/closed)
   - Attachment path
   - User relationship
   - Created/Updated timestamps

2. **Repository**: `src/Repository/SupportTicketRepository.php`
   - Custom queries for filtering tickets

3. **State Processor**: `src/State/SupportTicketProcessor.php`
   - Handles file uploads
   - Sends email notifications to admin
   - Sets current user automatically

4. **Migration**: `migrations/Version20251123224744.php`
   - Database schema for support_ticket table

### Setup Steps:

#### 1. Configure Environment Variables

Add to your `.env` file:

```bash
# Admin email for support ticket notifications
ADMIN_EMAIL=admin@deckard.com
```

#### 2. Run Database Migration

```bash
cd /Users/nikolagrdanjski/Code/www/deckard/deckard_inquiry_tool_backend_api
php bin/console doctrine:migrations:migrate
```

This will create the `support_ticket` table with all necessary columns.

**✅ Migration completed successfully** - The `support_ticket` table has been created with MediaItem relationship.

#### 3. Create Upload Directory

```bash
mkdir -p public/uploads/support
chmod 777 public/uploads/support
```

#### 4. Configure Mailer (if not already done)

In `.env`:

```bash
# Example for Gmail SMTP
MAILER_DSN=smtp://username:password@smtp.gmail.com:587

# Or for local testing with Mailpit/MailHog
MAILER_DSN=smtp://localhost:1025
```

For local development, consider using Mailpit:
```bash
# Install Mailpit (macOS)
brew install mailpit

# Run Mailpit
mailpit

# Access web UI at: http://localhost:8025
```

### API Endpoints Created:

#### Create Support Ticket (POST)
```
POST /api/support_tickets
Content-Type: multipart/form-data
Authorization: Bearer {token}

Body (FormData):
- subject: string (required)
- message: string (required)
- urgency: string (low/medium/high)
- orderId: string (optional)
- machine: string (optional)
- attachment: file (optional)
```

#### Get All Support Tickets (GET)
```
GET /api/support_tickets
Authorization: Bearer {token}

Response: Paginated list of user's tickets (admins see all)
```

#### Get Single Support Ticket (GET)
```
GET /api/support_tickets/{id}
Authorization: Bearer {token}
```

#### Update Support Ticket Status (PATCH - Admin Only)
```
PATCH /api/support_tickets/{id}
Authorization: Bearer {token}
Content-Type: application/merge-patch+json

Body:
{
  "status": "in_progress" // or "resolved", "closed"
}
```

### Security:

- **Users**: Can only view their own tickets
- **Admins**: Can view all tickets and update status
- File uploads are validated and sanitized
- Files stored in `/public/uploads/support/` with unique names

### Email Notification:

When a new support ticket is created, an email is automatically sent to `ADMIN_EMAIL` containing:
- Ticket ID
- Subject and message
- Urgency level (with emoji indicators)
- User information
- Order/Machine details if provided
- Attachment status
- Timestamp

## Frontend (Angular)

### Files Created/Updated:

1. **Component Template**: `src/app/shared/components/modals/support-modal/support-modal.component.html`
   - Separated HTML from TypeScript

2. **Component Styles**: `src/app/shared/components/modals/support-modal/support-modal.component.scss`
   - Separated SCSS from TypeScript

3. **Component TypeScript**: `src/app/shared/components/modals/support-modal/support-modal.component.ts`
   - Updated to use external templates
   - Integrated with SupportTicketService
   - Uses LoggerService for debugging

4. **Service**: `src/app/core/services/http/support-ticket.service.ts`
   - Extends BaseHttpService
   - Methods:
     - `createSupportTicket(formData: FormData)`
     - `getUserSupportTickets()`
     - `getSupportTicket(id)`
     - `updateSupportTicketStatus(id, status)`

### Frontend Setup:

No additional setup required! The component is ready to use.

### Usage Example:

```typescript
// In your component
import { SupportModalComponent } from '@shared/components/modals/support-modal/support-modal.component';

@Component({
  // ...
  imports: [SupportModalComponent]
})
export class YourComponent {
  isSupportModalOpen = false;

  openSupportModal() {
    this.isSupportModalOpen = true;
  }
}
```

```html
<!-- In your template -->
<button (click)="openSupportModal()">Contact Support</button>

<app-support-modal
  [(isOpen)]="isSupportModalOpen">
</app-support-modal>
```

## Testing

### 1. Test Backend API

```bash
# Create a support ticket using curl
curl -X POST http://localhost:8000/api/support_tickets \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "subject=Test Support Ticket" \
  -F "message=This is a test message for support" \
  -F "urgency=high" \
  -F "orderId=ORD-123" \
  -F "machine=Machine XYZ" \
  -F "attachment=@/path/to/file.pdf"

# Get all tickets
curl http://localhost:8000/api/support_tickets \
  -H "Authorization: Bearer YOUR_TOKEN"

# Update ticket status (admin only)
curl -X PATCH http://localhost:8000/api/support_tickets/{id} \
  -H "Authorization: Bearer ADMIN_TOKEN" \
  -H "Content-Type: application/merge-patch+json" \
  -d '{"status":"in_progress"}'
```

### 2. Test Frontend Component

1. Start the Angular dev server
2. Navigate to a page with the support modal
3. Click to open the support modal
4. Fill in the form with:
   - Subject (required)
   - Message (required)
   - Optional fields
   - File attachment (optional)
5. Submit the form
6. Check that:
   - API request is successful
   - Success message is shown
   - Modal closes
   - Admin receives email notification

### 3. Check Email Delivery

- If using Mailpit: http://localhost:8025
- Check admin inbox for notification email

## Database Schema

```sql
CREATE TABLE support_ticket (
    id CHAR(36) NOT NULL,
    attachment_id BINARY(16) DEFAULT NULL,
    user_id BINARY(16) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message LONGTEXT NOT NULL,
    order_id VARCHAR(50) DEFAULT NULL,
    machine VARCHAR(255) DEFAULT NULL,
    urgency VARCHAR(20) NOT NULL DEFAULT 'medium',
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY(id),
    INDEX IDX_1F5A4D53464E68B (attachment_id),
    INDEX IDX_1F5A4D53A76ED395 (user_id),
    CONSTRAINT FK_1F5A4D53464E68B FOREIGN KEY (attachment_id) REFERENCES media_item (id) ON DELETE SET NULL,
    CONSTRAINT FK_1F5A4D53A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)
);
```

## Status Flow

```
open → in_progress → resolved → closed
  ↑                      ↓
  └──────────────────────┘
  (can reopen if needed)
```

## Urgency Levels

- **Low** 🟢: General questions, non-urgent requests
- **Medium** 🟡: Standard support requests (default)
- **High** 🔴: Urgent issues requiring immediate attention

## Future Enhancements

Potential improvements:
1. Add comments/replies to tickets
2. Ticket assignment to specific support staff
3. SLA tracking and automatic escalation
4. Email notifications to users on status changes
5. Dashboard for admin to manage all tickets
6. File preview in admin panel
7. Multiple file attachments
8. Ticket categories/tags

## Troubleshooting

### File Upload Issues

If file uploads fail:
1. Check upload directory permissions: `chmod 777 public/uploads/support`
2. Check PHP upload limits in `php.ini`:
   ```
   upload_max_filesize = 10M
   post_max_size = 10M
   ```

### Email Not Sending

1. Check `MAILER_DSN` in `.env`
2. Verify SMTP credentials
3. Check logs: `var/log/dev.log` or `var/log/prod.log`
4. For local testing, use Mailpit/MailHog

### Permission Errors

1. Ensure user is authenticated
2. Check JWT token is valid
3. Verify user has ROLE_USER
4. For status updates, user must have ROLE_ADMIN

## Support

For issues or questions, please contact the development team.
