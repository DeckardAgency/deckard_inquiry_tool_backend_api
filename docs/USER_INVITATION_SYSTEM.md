# User Invitation System

## Overview

The User Invitation System allows administrators to invite new users to the Deckard Inquiry Tool platform via email. Invited users receive a secure link to set their password and activate their account.

## Architecture

### Solution Design: Token-Based Invitation

This implementation uses a secure, token-based invitation flow that separates the invitation process from user creation, providing better security, audit trails, and user experience.

### Flow Diagram

```
┌─────────────┐         ┌──────────────┐         ┌─────────────┐
│   Admin     │         │   Backend    │         │   Email     │
│  Dashboard  │         │   API        │         │   System    │
└──────┬──────┘         └──────┬───────┘         └──────┬──────┘
       │                       │                        │
       │ POST /api/user_invitations                    │
       │ {email, firstName, lastName}                  │
       ├──────────────────────>│                        │
       │                       │                        │
       │                       │ Generate token         │
       │                       │ Create invitation      │
       │                       │ Dispatch message       │
       │                       │                        │
       │                       ├───────────────────────>│
       │                       │ Send invitation email  │
       │                       │                        │
       │<──────────────────────┤                        │
       │ 201 Created           │                        │
       │                       │                        │

┌─────────────┐         ┌──────────────┐         ┌─────────────┐
│   User      │         │   Backend    │         │   Angular   │
│   Email     │         │   API        │         │   App       │
└──────┬──────┘         └──────┬───────┘         └──────┬──────┘
       │                       │                        │
       │ Clicks link with token                         │
       ├───────────────────────────────────────────────>│
       │                       │                        │
       │                       │<───────────────────────┤
       │                       │ GET /api/user_invitations/verify/{token}
       │                       │                        │
       │                       ├───────────────────────>│
       │                       │ {email, firstName, lastName, expiresAt}
       │                       │                        │
       │                       │                        │
       │                       │ User enters password   │
       │                       │                        │
       │                       │<───────────────────────┤
       │                       │ POST /api/user_invitations/complete/{token}
       │                       │ {password, passwordConfirm}
       │                       │                        │
       │                       │ Create User            │
       │                       │ Hash password          │
       │                       │ Mark invitation complete
       │                       │                        │
       │                       ├───────────────────────>│
       │                       │ 200 Success            │
       │                       │                        │
       │                       │                        │
       │                       │ Redirect to login      │
       │                       │                        │
```

## Components

### 1. Database Layer

#### UserInvitation Entity

**File:** `src/Entity/UserInvitation.php`

**Purpose:** Stores invitation data with secure token and status tracking.

**Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | Uuid | Primary key |
| `email` | string | Email address of invited user (unique per active invitation) |
| `firstName` | string | Invited user's first name |
| `lastName` | string | Invited user's last name |
| `token` | string | Secure random token (64 characters, unique, indexed) |
| `status` | string | Invitation status: `pending`, `completed`, `expired`, `revoked` |
| `expiresAt` | DateTime | Token expiration timestamp (default: +7 days) |
| `completedAt` | DateTime\|null | Timestamp when invitation was completed |
| `client` | Client\|null | Optional client assignment |
| `roles` | array | Roles to assign to user (default: `['ROLE_USER']`) |
| `createdBy` | User | Admin who created the invitation |
| `createdAt` | DateTime | Creation timestamp |
| `updatedAt` | DateTime | Last update timestamp |

**Methods:**

```php
public function isExpired(): bool
public function isPending(): bool
public function isCompleted(): bool
public function canBeCompleted(): bool
public function markAsCompleted(): void
public function generateToken(): static
```

**Validations:**
- Email must be valid format
- Email must not already be registered
- Email must not have pending invitation
- Token must be unique
- Status must be valid enum value
- ExpiresAt must be in future

#### UserInvitationRepository

**File:** `src/Repository/UserInvitationRepository.php`

**Methods:**

```php
public function findByToken(string $token): ?UserInvitation
public function findPendingByEmail(string $email): ?UserInvitation
public function findByCreatedBy(User $user): array
public function findExpired(): array
public function findPending(): array
```

### 2. API Layer

#### Endpoints

| Method | Path | Access | Description |
|--------|------|--------|-------------|
| `GET` | `/api/user_invitations` | Admin | List all invitations |
| `POST` | `/api/user_invitations` | Admin | Create new invitation |
| `GET` | `/api/user_invitations/{id}` | Admin | Get invitation details |
| `DELETE` | `/api/user_invitations/{id}` | Admin | Revoke invitation |
| `GET` | `/api/user_invitations/verify/{token}` | Public | Verify token and get invitation data |
| `POST` | `/api/user_invitations/complete/{token}` | Public | Complete invitation (set password) |
| `POST` | `/api/user_invitations/{id}/resend` | Admin | Resend invitation email |

#### API Platform Configuration

**File:** `src/Entity/UserInvitation.php` (annotations)

**Serialization Groups:**

- `user_invitation:read` - For listing/viewing invitations (admin)
- `user_invitation:create` - For creating invitations (admin)
- `user_invitation:verify` - For token verification (public)
- `user_invitation:complete` - For completing invitation (public)

**Example Requests/Responses:**

**Create Invitation:**
```http
POST /api/user_invitations
Content-Type: application/json

{
  "email": "john.doe@example.com",
  "firstName": "John",
  "lastName": "Doe",
  "client": "/api/clients/550e8400-e29b-41d4-a716-446655440000",
  "roles": ["ROLE_USER"]
}
```

**Response:**
```json
{
  "id": "123e4567-e89b-12d3-a456-426614174000",
  "email": "john.doe@example.com",
  "firstName": "John",
  "lastName": "Doe",
  "status": "pending",
  "expiresAt": "2025-12-07T10:30:00+00:00",
  "createdAt": "2025-11-30T10:30:00+00:00",
  "createdBy": {
    "id": "456e7890-e89b-12d3-a456-426614174001",
    "email": "admin@example.com",
    "firstName": "Admin",
    "lastName": "User"
  }
}
```

**Verify Token:**
```http
GET /api/user_invitations/verify/abc123def456...
```

**Response:**
```json
{
  "email": "john.doe@example.com",
  "firstName": "John",
  "lastName": "Doe",
  "expiresAt": "2025-12-07T10:30:00+00:00",
  "isExpired": false
}
```

**Complete Invitation:**
```http
POST /api/user_invitations/complete/abc123def456...
Content-Type: application/json

{
  "password": "SecureP@ssw0rd",
  "passwordConfirm": "SecureP@ssw0rd"
}
```

**Response:**
```json
{
  "message": "Account created successfully. You can now login."
}
```

### 3. Business Logic Layer

#### UserInvitationService

**File:** `src/Service/UserInvitationService.php`

**Purpose:** Encapsulates all business logic for invitation management.

**Methods:**

```php
/**
 * Create a new user invitation
 */
public function createInvitation(
    string $email,
    string $firstName,
    string $lastName,
    ?Client $client = null,
    array $roles = ['ROLE_USER']
): UserInvitation

/**
 * Verify an invitation token
 * @throws InvalidTokenException
 * @throws ExpiredTokenException
 */
public function verifyToken(string $token): UserInvitation

/**
 * Complete an invitation and create user account
 * @throws InvalidTokenException
 * @throws ExpiredTokenException
 * @throws InvitationAlreadyUsedException
 */
public function completeInvitation(string $token, string $password): User

/**
 * Resend invitation email
 */
public function resendInvitation(UserInvitation $invitation): void

/**
 * Revoke an invitation
 */
public function revokeInvitation(UserInvitation $invitation): void
```

### 4. State Processors

#### InvitationCreatedProcessor

**File:** `src/State/Processor/InvitationCreatedProcessor.php`

**Trigger:** `POST /api/user_invitations`

**Process:**
1. Validate email not already registered
2. Validate no pending invitation exists for email
3. Generate unique secure token (64 chars)
4. Set expiration date (configurable, default 7 days)
5. Set `createdBy` from authenticated admin
6. Persist invitation
7. Dispatch `UserInvitedMessage` (async)
8. Return created invitation

#### InvitationCompletedProcessor

**File:** `src/State/Processor/InvitationCompletedProcessor.php`

**Trigger:** `POST /api/user_invitations/complete/{token}`

**Process:**
1. Verify token exists
2. Check invitation not expired
3. Check invitation not already completed
4. Validate password meets requirements
5. Validate password and passwordConfirm match
6. Create User entity with invitation data
7. Hash password using UserPasswordHasher
8. Assign client from invitation
9. Assign roles from invitation
10. Mark invitation as completed
11. Flush changes
12. Return success response

#### InvitationVerifyProvider

**File:** `src/State/Provider/InvitationVerifyProvider.php`

**Trigger:** `GET /api/user_invitations/verify/{token}`

**Process:**
1. Find invitation by token
2. Check if expired
3. Check if already completed
4. Return invitation data (email, firstName, lastName, expiresAt)

### 5. Messaging System

#### UserInvitedMessage

**File:** `src/Message/UserInvitedMessage.php`

**Purpose:** Async message for sending invitation emails.

**Properties:**
```php
private Uuid $invitationId;
```

#### UserInvitedMessageHandler

**File:** `src/MessageHandler/UserInvitedMessageHandler.php`

**Purpose:** Sends invitation email asynchronously.

**Dependencies:**
- `UserInvitationRepository`
- `MailerInterface`
- `Environment` (Twig)
- `LoggerInterface`
- `string $clientAppUrl` (from config)
- `string $senderEmail`

**Process:**
1. Load invitation from database
2. Validate invitation exists and is pending
3. Build registration URL: `{clientAppUrl}/register?token={token}`
4. Render email template
5. Send email to invited user
6. Log success/failure
7. Handle errors gracefully

### 6. Email Templates

#### User Invitation Email

**File:** `templates/emails/user/invitation.html.twig`

**Subject:** `You've been invited to Deckard Inquiry Tool`

**Variables:**
- `invitation` - UserInvitation entity
- `registrationUrl` - Full URL with token
- `expirationDays` - Calculated from expiresAt

**Content Structure:**
```
- Header with logo
- Personalized greeting
- Invitation message
- Call-to-action button (Set Your Password)
- Registration link (as text fallback)
- Expiration notice
- Support contact information
- Footer
```

### 7. Validation

#### UniqueInvitationEmail

**Files:**
- `src/Validator/UniqueInvitationEmail.php` (Constraint)
- `src/Validator/UniqueInvitationEmailValidator.php` (Validator)

**Validates:**
- Email is not already registered as a User
- Email doesn't have an active pending invitation

#### ValidInvitationToken

**Files:**
- `src/Validator/ValidInvitationToken.php` (Constraint)
- `src/Validator/ValidInvitationTokenValidator.php` (Validator)

**Validates:**
- Token exists in database
- Invitation is not expired
- Invitation is not already completed
- Invitation is not revoked

### 8. Security Configuration

#### security.yaml

**File:** `config/packages/security.yaml`

**Access Control:**
```yaml
access_control:
    # Public endpoints
    - { path: ^/api/user_invitations/verify, roles: PUBLIC_ACCESS }
    - { path: ^/api/user_invitations/complete, roles: PUBLIC_ACCESS }

    # Admin-only endpoints
    - { path: ^/api/user_invitations, roles: ROLE_ADMIN }
```

### 9. Configuration

#### services.yaml

**File:** `config/packages/services.yaml`

**Parameters:**
```yaml
parameters:
    app.client_url: '%env(CLIENT_APP_URL)%'
    app.invitation_expiration_days: 7
    app.sender_email: '%env(MAILER_FROM_EMAIL)%'
```

#### Environment Variables

**File:** `.env`

```env
# Client application URL (where users will register)
CLIENT_APP_URL=http://localhost:4200

# Invitation expiration in days
INVITATION_EXPIRATION_DAYS=7

# Email sender
MAILER_FROM_EMAIL=noreply@deckard.com
```

## Frontend (Angular)

### 10. Models

**File:** `src/app/core/models/user-invitation.model.ts`

```typescript
export interface UserInvitation {
  id: string;
  email: string;
  firstName: string;
  lastName: string;
  status: InvitationStatus;
  expiresAt: string;
  createdAt: string;
  createdBy: {
    id: string;
    email: string;
    firstName: string;
    lastName: string;
  };
}

export type InvitationStatus = 'pending' | 'completed' | 'expired' | 'revoked';

export interface InvitationVerifyResponse {
  email: string;
  firstName: string;
  lastName: string;
  expiresAt: string;
  isExpired: boolean;
}

export interface CompleteInvitationRequest {
  password: string;
  passwordConfirm: string;
}

export interface CreateInvitationRequest {
  email: string;
  firstName: string;
  lastName: string;
  client?: string; // IRI
  roles?: string[];
}
```

### 11. Service

**File:** `src/app/core/services/invitation.service.ts`

```typescript
@Injectable({
  providedIn: 'root'
})
export class InvitationService {
  private apiUrl = '/api/user_invitations';

  constructor(private http: HttpClient) {}

  /**
   * Verify an invitation token
   */
  verifyToken(token: string): Observable<InvitationVerifyResponse> {
    return this.http.get<InvitationVerifyResponse>(
      `${this.apiUrl}/verify/${token}`
    );
  }

  /**
   * Complete an invitation (set password)
   */
  completeInvitation(
    token: string,
    data: CompleteInvitationRequest
  ): Observable<void> {
    return this.http.post<void>(
      `${this.apiUrl}/complete/${token}`,
      data
    );
  }

  /**
   * Create a new invitation (admin only)
   */
  createInvitation(data: CreateInvitationRequest): Observable<UserInvitation> {
    return this.http.post<UserInvitation>(this.apiUrl, data);
  }

  /**
   * Get all invitations (admin only)
   */
  getInvitations(): Observable<UserInvitation[]> {
    return this.http.get<UserInvitation[]>(this.apiUrl);
  }

  /**
   * Resend invitation (admin only)
   */
  resendInvitation(id: string): Observable<void> {
    return this.http.post<void>(`${this.apiUrl}/${id}/resend`, {});
  }

  /**
   * Revoke invitation (admin only)
   */
  revokeInvitation(id: string): Observable<void> {
    return this.http.delete<void>(`${this.apiUrl}/${id}`);
  }
}
```

### 12. Registration Component

**Files:**
- `src/app/features/register/register.component.ts`
- `src/app/features/register/register.component.html`
- `src/app/features/register/register.component.scss`

**Features:**
- Reads token from query parameter (`?token=...`)
- Calls `verifyToken()` on component initialization
- Displays user information from invitation
- Password form with validation:
  - Minimum 8 characters
  - At least one uppercase letter
  - At least one lowercase letter
  - At least one number
  - At least one special character
- Password strength indicator
- Password confirmation validation (must match)
- Submit button disabled until form is valid
- Loading states during verification and submission
- Error handling:
  - Token expired
  - Token invalid
  - Token already used
  - Network errors
- Success redirect to login page

**Component Logic:**
```typescript
export class RegisterComponent implements OnInit {
  token: string | null = null;
  invitation: InvitationVerifyResponse | null = null;
  loading = false;
  error: string | null = null;

  registerForm = this.fb.group({
    password: ['', [
      Validators.required,
      Validators.minLength(8),
      this.passwordStrengthValidator()
    ]],
    passwordConfirm: ['', Validators.required]
  }, {
    validators: this.passwordMatchValidator()
  });

  ngOnInit() {
    // Get token from query params
    this.route.queryParams.subscribe(params => {
      this.token = params['token'];
      if (this.token) {
        this.verifyToken();
      } else {
        this.error = 'Invalid invitation link';
      }
    });
  }

  verifyToken() {
    // Verify token and load invitation data
  }

  onSubmit() {
    // Complete invitation
  }
}
```

### 13. Route Configuration

**File:** `src/app/app.routes.ts`

```typescript
{
  path: 'register',
  component: RegisterComponent,
  // Public route - no auth guard
}
```

## Testing

### Unit Tests

#### Backend

1. **UserInvitationTest.php**
   - Test entity methods (isExpired, isPending, etc.)
   - Test token generation
   - Test status transitions

2. **UserInvitationServiceTest.php**
   - Test createInvitation()
   - Test verifyToken() with valid/invalid/expired tokens
   - Test completeInvitation()
   - Test resendInvitation()
   - Test revokeInvitation()

3. **UserInvitedMessageHandlerTest.php**
   - Test email sending
   - Test error handling
   - Test logging

4. **InvitationCreatedProcessorTest.php**
   - Test invitation creation flow
   - Test validation (duplicate email, existing user)
   - Test message dispatching

5. **InvitationCompletedProcessorTest.php**
   - Test user creation
   - Test password hashing
   - Test invitation completion
   - Test error scenarios

6. **InvitationVerifyProviderTest.php**
   - Test token verification
   - Test expired token handling
   - Test completed token handling

7. **UniqueInvitationEmailValidatorTest.php**
   - Test duplicate email detection
   - Test existing user detection

8. **ValidInvitationTokenValidatorTest.php**
   - Test token validation
   - Test expiration checking
   - Test completion checking

#### Frontend

1. **invitation.service.spec.ts**
   - Test API calls
   - Test error handling
   - Test response parsing

2. **register.component.spec.ts**
   - Test token verification
   - Test form validation
   - Test password strength
   - Test password confirmation
   - Test submission
   - Test error states

### Integration Tests

**UserInvitationApiTest.php**

Test scenarios:
- Create invitation (POST /api/user_invitations)
- List invitations (GET /api/user_invitations)
- Get invitation details (GET /api/user_invitations/{id})
- Verify token (GET /api/user_invitations/verify/{token})
- Complete invitation (POST /api/user_invitations/complete/{token})
- Resend invitation (POST /api/user_invitations/{id}/resend)
- Revoke invitation (DELETE /api/user_invitations/{id})
- Test authorization (admin-only endpoints)
- Test public access (verify and complete endpoints)

### End-to-End Tests

Complete user journey:
1. Admin creates invitation
2. Email is sent (verify email queue)
3. User receives email with token
4. User clicks link (opens registration page)
5. Frontend verifies token
6. User enters password
7. User submits form
8. Account is created
9. User can login with credentials
10. Invitation marked as completed

Edge cases:
- Expired token
- Already used token
- Invalid token
- Duplicate email
- Network errors
- Validation errors

## Security Considerations

### Token Security

- **Token Length:** 64 characters (hexadecimal)
- **Token Generation:** Cryptographically secure random bytes
- **Token Storage:** Indexed for fast lookup, unique constraint
- **Token Lifetime:** Configurable, default 7 days
- **Token Usage:** Single-use only (marked completed after use)

### Email Security

- **No Sensitive Data:** Email contains only registration link
- **HTTPS Only:** Registration links use HTTPS in production
- **Token in URL:** Safe for email (not in POST body)

### Password Security

- **Hashing:** Symfony's PasswordHasher with bcrypt
- **Validation:** Minimum complexity requirements
- **Confirmation:** Password must be entered twice
- **No Plain Text:** Never stored or transmitted unencrypted

### Access Control

- **Admin Only:** Create, list, resend, revoke endpoints
- **Public Access:** Verify and complete endpoints only
- **JWT Protected:** Standard API endpoints require authentication

### Rate Limiting

Consider implementing rate limiting for:
- Invitation creation (prevent spam)
- Token verification (prevent brute force)
- Invitation completion (prevent abuse)

## Monitoring & Logging

### Logs

**Success Events:**
```
[info] User invitation created: {email}
[info] Invitation email sent: {invitationId}
[info] Invitation token verified: {token}
[info] Invitation completed: {invitationId}, User created: {userId}
```

**Error Events:**
```
[error] Failed to send invitation email: {error}
[warning] Expired token accessed: {token}
[warning] Invalid token accessed: {token}
[error] Duplicate invitation attempt: {email}
```

### Metrics to Track

- Total invitations sent
- Invitations completed (conversion rate)
- Invitations expired
- Average time to completion
- Failed email deliveries
- Invalid token attempts

## Maintenance

### Cleanup Tasks

**Expired Invitations:**
- Recommended: Daily cleanup job
- Delete invitations older than 30 days
- Or move to archive table

**Command:**
```php
// src/Command/CleanupExpiredInvitationsCommand.php
bin/console app:cleanup-expired-invitations
```

### Database Indexes

Ensure proper indexing for performance:
```sql
CREATE INDEX idx_user_invitation_token ON user_invitation(token);
CREATE INDEX idx_user_invitation_email ON user_invitation(email);
CREATE INDEX idx_user_invitation_status ON user_invitation(status);
CREATE INDEX idx_user_invitation_expires_at ON user_invitation(expires_at);
```

## Troubleshooting

### Common Issues

**Issue: Email not received**
- Check email queue (messenger:consume email)
- Verify SMTP configuration
- Check spam folder
- Verify sender email is configured

**Issue: Token expired**
- Check expiration configuration
- Resend invitation (creates new token)
- Adjust expiration days if needed

**Issue: "Email already registered"**
- User account already exists
- Delete old account or use different email
- Check for pending invitations

**Issue: Token not found**
- Verify token in URL is complete
- Check if invitation was deleted
- Check database for invitation record

## API Reference

See [API Documentation](./API_DOCUMENTATION.md) for complete endpoint reference.

## Related Documentation

- [User Management](./USER_MANAGEMENT.md)
- [Email System](./EMAIL_SYSTEM.md)
- [Security Guide](./SECURITY.md)
- [Testing Guide](./TESTING.md)

## Changelog

### Version 1.0.0 (2025-11-30)
- Initial implementation
- Token-based invitation system
- Email notifications
- Angular registration component
- Comprehensive test coverage

---

**Last Updated:** 2025-11-30
**Author:** Development Team
**Status:** In Development
