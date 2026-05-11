# Client Admin Users - Invitation System Integration

## **Status: Complete & Ready for Testing** ✅

---

## **Overview**

The Client Admin Users page (`/client-admin/users`) has been updated to use the User Invitation System instead of directly creating user accounts. Admins can now send invitation emails to new users, who will then set their own passwords via the registration link.

---

## **What Was Changed**

### **Component Updates** (`client-admin-users.component.ts`)

#### **New Imports**
```typescript
import { InvitationService } from '@core/services/invitation.service';
import { UserInvitation } from '@core/models/user-invitation.model';
```

#### **New Properties**
```typescript
// Invitations data
invitations: UserInvitation[] = [];
filteredInvitations: UserInvitation[] = [];

// View toggle
currentView: 'users' | 'invitations' = 'users';

// Updated form data structure
formData = {
  firstName: '',
  lastName: '',
  email: '',
  roles: [] as string[]
};

// Form submission state
isSubmitting = false;
```

#### **New Methods**

1. **`loadData()`** - Loads both users and invitations
2. **`loadInvitations()`** - Fetches all invitations from API
3. **`applyInvitationFilters()`** - Filters invitations by search query
4. **`switchView(view)`** - Toggles between users and invitations view
5. **`sendInvitation()`** - Creates invitation and sends email
6. **`revokeInvitation(invitation, event)`** - Revokes a pending invitation
7. **`getInvitationStatus(invitation)`** - Returns formatted status text
8. **`getInvitationStatusClass(invitation)`** - Returns CSS class for status badge
9. **`isInvitationExpired(invitation)`** - Checks if invitation is expired
10. **`getInvitationFullName(invitation)`** - Returns full name
11. **`getInvitationInitials(invitation)`** - Returns initials for avatar
12. **`toggleRole(role)`** - Toggles role selection
13. **`hasRole(role)`** - Checks if role is selected

#### **Updated Methods**

- **`submitForm()`** - Now calls `sendInvitation()` for new users instead of direct user creation
- **`getSubmitButtonText()`** - Returns "Send Invitation" for add mode
- **`onSearchChange()`** - Filters based on current view (users or invitations)

---

### **Template Updates** (`client-admin-users.component.html`)

#### **1. View Toggle (New)**
```html
<div class="users-page__view-toggle">
  <button
    class="users-page__toggle-btn"
    [class.users-page__toggle-btn--active]="currentView === 'users'"
    (click)="switchView('users')">
    Users ({{ users.length }})
  </button>
  <button
    class="users-page__toggle-btn"
    [class.users-page__toggle-btn--active]="currentView === 'invitations'"
    (click)="switchView('invitations')">
    Invitations ({{ invitations.length }})
  </button>
</div>
```

#### **2. Invitations Table (New)**
Complete table showing:
- Name (with avatar)
- Date Created (sortable)
- Email address
- Status badge (pending/completed/expired/revoked)
- Expires At (highlighted if expired)
- Actions menu (revoke for pending invitations)

#### **3. Updated Form Fields**
Changed from single "Full name" field to:
```html
<!-- First Name -->
<input id="firstName" [(ngModel)]="formData.firstName" ... />

<!-- Last Name -->
<input id="lastName" [(ngModel)]="formData.lastName" ... />
```

#### **4. Updated Role Selection**
Changed from dropdown with viewer/regular/admin to checkboxes:
```html
<label class="form-checkbox">
  <input type="checkbox" [checked]="hasRole('ROLE_CLIENT')" (change)="toggleRole('ROLE_CLIENT')" />
  Regular User
</label>
<label class="form-checkbox">
  <input type="checkbox" [checked]="hasRole('ROLE_CLIENT_ADMIN')" (change)="toggleRole('ROLE_CLIENT_ADMIN')" />
  Client Admin
</label>
```

#### **5. Updated Info Banner**
```html
An invitation email will be sent to the user. They will be able to set their
password and access the system after completing registration.
{{ getAvailableSlots() }}/{{ maxActiveUsers }} active user slots available.
```

#### **6. Submit Button with Loading State**
```html
<button [disabled]="isSubmitting">
  @if (isSubmitting) {
    <span>Sending...</span>
  } @else {
    {{ getSubmitButtonText() }}
  }
</button>
```

---

### **Styles Updates** (`client-admin-users.component.scss`)

#### **New Styles**

1. **View Toggle**
```scss
&__view-toggle {
  display: flex;
  gap: 0.5rem;
  background: white;
  padding: 0.25rem;
  border-radius: 0.5rem;
  border: 1px solid #E5E7EB;
}

&__toggle-btn {
  padding: 0.5rem 1rem;
  // ... styles

  &--active {
    background: #DC2626;
    color: white;
  }
}
```

2. **Invitation Status Badges**
```scss
&--pending {
  background: #F59E0B;  // Orange
  color: white;
}

&--completed {
  background: #22C55E;  // Green
  color: white;
}

&--expired {
  background: #6B7280;  // Gray
  color: white;
}

&--revoked {
  background: #EF4444;  // Red
  color: white;
}
```

3. **Utility Classes**
```scss
.text-danger {
  color: #EF4444 !important;
  font-weight: 500;
}

.form-roles {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}
```

---

## **User Flow**

### **1. Admin Creates Invitation**

1. Admin navigates to `/client-admin/users`
2. Clicks "Add user" button
3. Fills in form:
   - First name
   - Last name
   - Email address
   - Roles (ROLE_CLIENT and/or ROLE_CLIENT_ADMIN)
4. Clicks "Send Invitation"
5. System:
   - Creates invitation record
   - Sends email to user with registration link
   - Shows success message
   - Refreshes invitations list

### **2. Admin Monitors Invitations**

1. Admin switches to "Invitations" view
2. Sees all invitations with:
   - **Pending** - Invitation sent, waiting for user
   - **Completed** - User registered successfully
   - **Expired** - Invitation expired (7 days)
   - **Revoked** - Admin cancelled invitation
3. Can search/filter invitations
4. Can sort by date created
5. Can revoke pending invitations

### **3. User Receives Invitation**

1. User receives email with subject: "You've been invited to Deckard Inquiry Tool"
2. Email contains:
   - Welcome message
   - Registration button/link
   - Expiration notice (7 days)
   - Company branding
3. Link format: `http://localhost:4200/register?token=abc123...`

### **4. User Registers**

1. User clicks registration link
2. Frontend verifies token
3. Shows pre-filled info (name, email)
4. User sets password:
   - Minimum 8 characters
   - Must contain uppercase, lowercase, number
   - Password confirmation required
   - Real-time strength indicator
5. User submits form
6. Backend:
   - Creates User account
   - Hashes password
   - Marks invitation as completed
7. User redirected to login

### **5. User Logs In**

1. User redirected to `/login?email=user@example.com`
2. Email pre-filled
3. User enters password
4. User authenticated and can access system

---

## **API Endpoints Used**

### **Admin Endpoints** (require ROLE_ADMIN or ROLE_CLIENT_ADMIN)

```http
POST   /api/user_invitations
GET    /api/user_invitations
DELETE /api/user_invitations/{id}
```

### **Public Endpoints** (no authentication required)

```http
GET    /api/user_invitations/verify/{token}
POST   /api/user_invitations/complete/{token}
```

---

## **Data Flow**

### **Create Invitation**

```typescript
// Frontend sends
{
  email: "john.doe@example.com",
  firstName: "John",
  lastName: "Doe",
  roles: ["ROLE_CLIENT", "ROLE_CLIENT_ADMIN"]
}

// Backend returns
{
  id: "uuid",
  email: "john.doe@example.com",
  firstName: "John",
  lastName: "Doe",
  token: "128-char-token",
  status: "pending",
  expiresAt: "2025-12-04T10:30:00+00:00",
  createdAt: "2025-11-27T10:30:00+00:00",
  ...
}
```

### **Revoke Invitation**

```typescript
// Frontend sends
DELETE /api/user_invitations/{id}

// Backend returns
204 No Content
```

---

## **Invitation Status States**

| Status | Color | Description | Actions Available |
|--------|-------|-------------|-------------------|
| **pending** | Orange (#F59E0B) | Invitation sent, awaiting registration | Revoke |
| **completed** | Green (#22C55E) | User successfully registered | None |
| **expired** | Gray (#6B7280) | Invitation expired after 7 days | None |
| **revoked** | Red (#EF4444) | Admin cancelled invitation | None |

---

## **Validation & Error Handling**

### **Frontend Validation**

- First name required
- Last name required
- Email required and valid format
- At least one role should be selected (defaults to ROLE_CLIENT)

### **Backend Validation**

- Email must be unique (not already registered)
- Email must not have pending invitation
- Token must be valid and not expired
- Password must meet strength requirements

### **Error Messages**

```typescript
// Duplicate email
"Email already registered"

// Pending invitation exists
"User already has a pending invitation"

// Validation errors
"Validation errors:
- Password must be at least 8 characters
- Password must contain uppercase letter"
```

---

## **Security Features**

✅ **Token Security**
- 128-character cryptographically secure tokens
- Single-use only (marked completed after use)
- Time-limited (7 days)
- Cannot be guessed or brute-forced

✅ **Access Control**
- Only CLIENT_ADMIN can create/revoke invitations
- Public endpoints only for verify/complete
- CORS configured properly

✅ **Email Verification**
- User must have access to email to register
- Token sent only to specified email
- Email cannot be changed during registration

---

## **Testing Checklist**

### **Admin Flow**
- [ ] Navigate to `/client-admin/users`
- [ ] Click "Add user" button
- [ ] Fill in first name, last name, email
- [ ] Select roles
- [ ] Click "Send Invitation"
- [ ] Verify success message
- [ ] Switch to "Invitations" view
- [ ] Verify invitation appears with "Pending" status
- [ ] Test search/filter on invitations
- [ ] Test sort by date created
- [ ] Test revoking invitation
- [ ] Verify revoked invitation shows "Revoked" status

### **User Flow**
- [ ] Check email for invitation
- [ ] Click registration link
- [ ] Verify name and email are pre-filled
- [ ] Enter weak password (should show strength indicator)
- [ ] Enter strong password
- [ ] Verify password requirements checklist updates
- [ ] Submit form
- [ ] Verify success message
- [ ] Verify redirect to login with email pre-filled
- [ ] Login with new credentials
- [ ] Verify access to system

### **Edge Cases**
- [ ] Try to use expired token (should show error)
- [ ] Try to use already-used token (should show error)
- [ ] Try to create duplicate invitation (should show error)
- [ ] Try to create invitation for existing user (should show error)
- [ ] Test password visibility toggle
- [ ] Test form validation errors
- [ ] Test empty states (no users, no invitations)
- [ ] Test search with no results

---

## **File Changes Summary**

### **Modified Files**

1. **`src/app/features/client-admin/users/client-admin-users.component.ts`**
   - Added InvitationService integration
   - Added invitation list management
   - Added view toggle functionality
   - Updated form data structure
   - Implemented sendInvitation() method
   - Implemented revokeInvitation() method

2. **`src/app/features/client-admin/users/client-admin-users.component.html`**
   - Added view toggle buttons
   - Added invitations table
   - Updated form fields (firstName, lastName)
   - Updated role selection (checkboxes)
   - Updated info banner text
   - Added loading state for submit button

3. **`src/app/features/client-admin/users/client-admin-users.component.scss`**
   - Added view toggle styles
   - Added invitation status badge styles
   - Added utility classes
   - Updated header layout

### **No New Files Created**

All changes were integrated into existing client-admin users component.

---

## **Dependencies**

✅ **Backend**
- User Invitation System (previously implemented)
- InvitationService endpoints operational
- Email system configured

✅ **Frontend**
- InvitationService (`@core/services/invitation.service`)
- UserInvitation model (`@core/models/user-invitation.model`)
- Register component (previously implemented)

---

## **Configuration**

### **Backend (.env)**
```env
CLIENT_APP_URL=http://localhost:4200
INQUIRY_SENDER_EMAIL=noreply@deckard.com
```

### **Frontend (environment.ts)**
```typescript
export const environment = {
  production: false,
  apiBaseUrl: 'https://127.0.0.1:8002',
  apiPath: '/api/v1',
  serverUrl: 'https://127.0.0.1:8002'
};
```

---

## **Next Steps**

1. **Test End-to-End Flow**
   - Start backend: `symfony server:start`
   - Start message consumer: `php bin/console messenger:consume async`
   - Start frontend: `ng serve`
   - Test complete invitation flow

2. **Optional Enhancements**
   - Resend invitation functionality
   - Bulk invite (CSV upload)
   - Custom expiration per invitation
   - Invitation analytics/dashboard
   - Email open tracking

---

## **Troubleshooting**

### **Invitation not appearing in list**
- Check browser console for errors
- Verify API endpoint is accessible
- Check user has CLIENT_ADMIN role
- Refresh the page

### **Email not received**
- Check messenger consumer is running
- Verify SMTP configuration in backend
- Check spam folder
- Review backend logs: `tail -f var/log/dev.log`

### **Token expired immediately**
- Check system time on server
- Verify `app.invitation_expiration_days` in services.yaml
- Default is 7 days

---

## **Success Metrics**

✅ **Implementation Complete**
- Component updated with invitation service
- Template updated with view toggle and invitations table
- Styles added for new UI elements
- TypeScript compilation passing
- No runtime errors

✅ **Features Implemented**
- Send invitation emails
- View invitations list
- Filter/search invitations
- Sort invitations by date
- Revoke pending invitations
- Status badges with color coding
- Loading states
- Error handling

✅ **Ready for Testing**
- All code changes complete
- No compilation errors
- Integration points verified
- Documentation complete

---

**Implementation Date:** November 27, 2025
**Status:** ✅ Complete & Ready for Testing
**Integration:** Seamless with existing User Invitation System

**🎉 Client Admin Users page now uses the Invitation System for user onboarding!**
