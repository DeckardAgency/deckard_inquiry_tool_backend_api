# Area Management System - Technical Documentation

## Overview

The Area Management System is an enterprise-grade solution for managing organizational areas and their associated managers. It provides intelligent routing, automated assignment, workload balancing, and comprehensive audit trails for inquiries and orders.

## Table of Contents

1. [System Architecture](#system-architecture)
2. [Database Schema](#database-schema)
3. [Entity Relationships](#entity-relationships)
4. [Assignment Strategies](#assignment-strategies)
5. [API Endpoints](#api-endpoints)
6. [Business Logic Workflows](#business-logic-workflows)
7. [Security & Permissions](#security--permissions)
8. [Performance Optimization](#performance-optimization)
9. [Integration Points](#integration-points)
10. [Development Guidelines](#development-guidelines)

---

## System Architecture

### Core Components

```
┌─────────────────────────────────────────────────────────────┐
│                    Area Management System                    │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │    Area      │  │ AreaManager  │  │ AreaCriteria │      │
│  │   Entity     │  │   Entity     │  │   Entity     │      │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘      │
│         │                  │                  │              │
│         └──────────────────┴──────────────────┘              │
│                            │                                 │
│                  ┌─────────▼─────────┐                       │
│                  │  AreaAssignment   │                       │
│                  │     Entity        │                       │
│                  └─────────┬─────────┘                       │
│                            │                                 │
│         ┌──────────────────┴──────────────────┐             │
│         │                                      │             │
│  ┌──────▼───────┐                    ┌────────▼────────┐    │
│  │   Inquiry    │                    │     Order       │    │
│  │   Entity     │                    │     Entity      │    │
│  └──────────────┘                    └─────────────────┘    │
│                                                               │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│              Assignment Strategy Layer                       │
├─────────────────────────────────────────────────────────────┤
│  • CriteriaMatchStrategy                                     │
│  • RoundRobinStrategy                                        │
│  • LoadBalancedStrategy                                      │
│  • AvailabilityStrategy                                      │
│  • SpecializationStrategy                                    │
│  • HybridStrategy                                            │
└─────────────────────────────────────────────────────────────┘
```

### Key Design Principles

1. **Separation of Concerns**: Area structure, manager assignments, and routing logic are separated
2. **Strategy Pattern**: Pluggable assignment strategies for flexibility
3. **Audit Trail**: Every assignment is tracked with full history
4. **Hierarchical Support**: Areas can have parent-child relationships
5. **Extensibility**: JSON metadata fields allow custom data without schema changes
6. **Performance**: Optimized indexes and caching strategies
7. **Multi-tenancy**: Scoped to clients for security and isolation

---

## Database Schema

### Area Entity

Represents organizational areas (geographic, departmental, product-based, etc.)

```sql
CREATE TABLE area (
    id CHAR(36) PRIMARY KEY,
    client_id CHAR(36) NOT NULL,
    parent_area_id CHAR(36) NULL,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) NOT NULL,
    description TEXT NULL,
    type VARCHAR(50) NOT NULL,
    metadata JSON NULL,
    contact_email VARCHAR(255) NULL,
    contact_phone VARCHAR(50) NULL,
    timezone VARCHAR(50) NOT NULL DEFAULT 'UTC',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    display_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    FOREIGN KEY (client_id) REFERENCES client(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_area_id) REFERENCES area(id) ON DELETE SET NULL,
    UNIQUE KEY unique_area_code_per_client (client_id, code),
    INDEX idx_area_client_active (client_id, is_active),
    INDEX idx_area_parent (parent_area_id),
    INDEX idx_area_type (type)
);
```

**Field Descriptions:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | UUID | Primary key |
| `client_id` | UUID | Reference to owning client |
| `parent_area_id` | UUID | Self-reference for hierarchical structure (nullable) |
| `name` | String | Display name (e.g., "European Sales", "Technical Support EMEA") |
| `code` | String | Unique code per client (e.g., "EU-SALES", "TECH-EMEA") |
| `description` | Text | Detailed description of area purpose |
| `type` | Enum | `geographic`, `departmental`, `product_line`, `custom` |
| `metadata` | JSON | Flexible storage for custom fields |
| `contact_email` | String | General contact email for the area |
| `contact_phone` | String | General contact phone for the area |
| `timezone` | String | Area timezone (e.g., "Europe/Berlin") |
| `is_active` | Boolean | Whether area is currently active |
| `display_order` | Integer | Sort order for display |
| `created_at` | DateTime | Creation timestamp |
| `updated_at` | DateTime | Last update timestamp |

---

### AreaManager Entity

Links users to areas with roles and responsibilities

```sql
CREATE TABLE area_manager (
    id CHAR(36) PRIMARY KEY,
    area_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    role VARCHAR(50) NOT NULL,
    responsibilities JSON NULL,
    specializations JSON NULL,
    languages JSON NULL,
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    availability_schedule JSON NULL,
    max_concurrent_assignments INT NULL,
    current_assignment_count INT NOT NULL DEFAULT 0,
    auto_assign_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    notification_preferences JSON NULL,
    display_order INT NOT NULL DEFAULT 0,
    bio TEXT NULL,
    photo_url VARCHAR(500) NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    FOREIGN KEY (area_id) REFERENCES area(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_area (user_id, area_id),
    INDEX idx_manager_area_active (area_id, is_active),
    INDEX idx_manager_user (user_id),
    INDEX idx_manager_primary (area_id, is_primary),
    INDEX idx_manager_load (current_assignment_count, max_concurrent_assignments),
    INDEX idx_manager_dates (start_date, end_date)
);
```

**Field Descriptions:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | UUID | Primary key |
| `area_id` | UUID | Reference to area |
| `user_id` | UUID | Reference to user account |
| `role` | Enum | `primary`, `secondary`, `specialist`, `backup` |
| `responsibilities` | JSON | Array of responsibilities (e.g., `["sales", "support"]`) |
| `specializations` | JSON | Array of specializations (e.g., `["machine_type_a", "spare_parts"]`) |
| `languages` | JSON | Array of language codes (e.g., `["en", "de", "fr"]`) |
| `is_primary` | Boolean | Is this the primary manager for the area? |
| `is_active` | Boolean | Whether manager is currently active |
| `availability_schedule` | JSON | Work hours and timezone information |
| `max_concurrent_assignments` | Integer | Max number of active assignments (null = unlimited) |
| `current_assignment_count` | Integer | Current number of active assignments |
| `auto_assign_enabled` | Boolean | Whether to include in auto-assignment |
| `notification_preferences` | JSON | Email, SMS, push notification settings |
| `display_order` | Integer | Sort order for display |
| `bio` | Text | Manager biography/description |
| `photo_url` | String | URL to manager's photo |
| `start_date` | Date | When manager started this role |
| `end_date` | Date | When manager ended this role (null = current) |
| `created_at` | DateTime | Creation timestamp |
| `updated_at` | DateTime | Last update timestamp |

---

### AreaCriteria Entity

Defines rules for automatic area assignment

```sql
CREATE TABLE area_criteria (
    id CHAR(36) PRIMARY KEY,
    area_id CHAR(36) NOT NULL,
    criteria_type VARCHAR(50) NOT NULL,
    operator VARCHAR(50) NOT NULL,
    criteria_value TEXT NOT NULL,
    priority INT NOT NULL DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    FOREIGN KEY (area_id) REFERENCES area(id) ON DELETE CASCADE,
    INDEX idx_criteria_area_active (area_id, is_active, priority DESC),
    INDEX idx_criteria_type (criteria_type)
);
```

**Field Descriptions:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | UUID | Primary key |
| `area_id` | UUID | Reference to area |
| `criteria_type` | Enum | `postal_code`, `country`, `department`, `product_type`, `order_value`, `custom` |
| `operator` | Enum | `equals`, `contains`, `starts_with`, `in_range`, `greater_than`, `less_than`, `regex` |
| `criteria_value` | String | Value to match against (e.g., "10000-20000", "DE,AT,CH", "Munich") |
| `priority` | Integer | Higher number = higher priority (checked first) |
| `is_active` | Boolean | Whether criteria is currently active |
| `created_at` | DateTime | Creation timestamp |
| `updated_at` | DateTime | Last update timestamp |

**Example Criteria:**

```json
{
  "criteria_type": "postal_code",
  "operator": "starts_with",
  "criteria_value": "80,81,82,83,84,85",
  "priority": 10
}

{
  "criteria_type": "country",
  "operator": "equals",
  "criteria_value": "DE",
  "priority": 5
}

{
  "criteria_type": "order_value",
  "operator": "greater_than",
  "criteria_value": "50000",
  "priority": 15
}
```

---

### AreaAssignment Entity

Tracks all area and manager assignments with full audit trail

```sql
CREATE TABLE area_assignment (
    id CHAR(36) PRIMARY KEY,
    area_id CHAR(36) NOT NULL,
    manager_id CHAR(36) NULL,
    inquiry_id CHAR(36) NULL,
    order_id CHAR(36) NULL,
    assignment_type VARCHAR(50) NOT NULL,
    assignment_strategy VARCHAR(50) NOT NULL,
    assignment_reason TEXT NULL,
    assigned_by_user_id CHAR(36) NULL,
    assigned_at DATETIME NOT NULL,
    status VARCHAR(50) NOT NULL,
    reassigned_to_id CHAR(36) NULL,
    completed_at DATETIME NULL,
    notes TEXT NULL,
    metadata JSON NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    FOREIGN KEY (area_id) REFERENCES area(id) ON DELETE CASCADE,
    FOREIGN KEY (manager_id) REFERENCES area_manager(id) ON DELETE SET NULL,
    FOREIGN KEY (inquiry_id) REFERENCES inquiry(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES `order`(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by_user_id) REFERENCES user(id) ON DELETE SET NULL,
    FOREIGN KEY (reassigned_to_id) REFERENCES area_assignment(id) ON DELETE SET NULL,
    INDEX idx_assignment_inquiry (inquiry_id),
    INDEX idx_assignment_order (order_id),
    INDEX idx_assignment_manager_status (manager_id, status),
    INDEX idx_assignment_area_status (area_id, status),
    INDEX idx_assignment_created (assigned_at)
);
```

**Field Descriptions:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | UUID | Primary key |
| `area_id` | UUID | Reference to assigned area |
| `manager_id` | UUID | Reference to assigned manager (nullable for area-only assignments) |
| `inquiry_id` | UUID | Reference to inquiry (nullable, one of inquiry/order required) |
| `order_id` | UUID | Reference to order (nullable, one of inquiry/order required) |
| `assignment_type` | Enum | `automatic`, `manual`, `user_selected` |
| `assignment_strategy` | Enum | `criteria_match`, `round_robin`, `load_balanced`, `manual`, `default` |
| `assignment_reason` | Text | Human-readable reason for assignment |
| `assigned_by_user_id` | UUID | Admin who manually assigned (null for automatic) |
| `assigned_at` | DateTime | When assignment was made |
| `status` | Enum | `pending`, `active`, `completed`, `reassigned`, `cancelled` |
| `reassigned_to_id` | UUID | Reference to new assignment if reassigned |
| `completed_at` | DateTime | When assignment was completed |
| `notes` | Text | Additional notes about assignment |
| `metadata` | JSON | Flexible storage for custom data |
| `created_at` | DateTime | Creation timestamp |
| `updated_at` | DateTime | Last update timestamp |

---

### AreaManagerAvailability Entity (Optional Enhancement)

Defines manager availability schedules

```sql
CREATE TABLE area_manager_availability (
    id CHAR(36) PRIMARY KEY,
    area_manager_id CHAR(36) NOT NULL,
    day_of_week TINYINT NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_available BOOLEAN NOT NULL DEFAULT TRUE,
    timezone VARCHAR(50) NOT NULL,
    exceptions JSON NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    FOREIGN KEY (area_manager_id) REFERENCES area_manager(id) ON DELETE CASCADE,
    INDEX idx_availability_manager_day (area_manager_id, day_of_week),
    CHECK (day_of_week BETWEEN 0 AND 6)
);
```

**Field Descriptions:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | UUID | Primary key |
| `area_manager_id` | UUID | Reference to area manager |
| `day_of_week` | Integer | 0=Sunday, 1=Monday, ..., 6=Saturday |
| `start_time` | Time | Start of availability window |
| `end_time` | Time | End of availability window |
| `is_available` | Boolean | Whether manager is available during this period |
| `timezone` | String | Timezone for this availability (e.g., "Europe/Berlin") |
| `exceptions` | JSON | Array of exception dates (holidays, vacation) |
| `created_at` | DateTime | Creation timestamp |
| `updated_at` | DateTime | Last update timestamp |

---

## Entity Relationships

```
Client (1) ────────── (N) Area
                           │
                           ├── (N) AreaManager ───── (1) User
                           │
                           ├── (N) AreaCriteria
                           │
                           ├── (N) AreaAssignment
                           │        │
                           │        ├── (1) Inquiry
                           │        └── (1) Order
                           │
                           └── (1) Area (parent) [Self-referencing]

AreaManager (1) ────── (N) AreaManagerAvailability
```

### Relationship Details

1. **Client → Area**: One-to-Many
   - A client can have multiple areas
   - Each area belongs to exactly one client

2. **Area → Area**: Self-referencing One-to-Many
   - An area can have a parent area (hierarchical structure)
   - An area can have multiple child areas

3. **Area → AreaManager**: One-to-Many
   - An area can have multiple managers
   - Each manager is assigned to exactly one area (but a user can be manager in multiple areas)

4. **User → AreaManager**: One-to-Many
   - A user can be a manager in multiple areas
   - Each area manager record references exactly one user

5. **Area → AreaCriteria**: One-to-Many
   - An area can have multiple assignment criteria
   - Each criterion belongs to exactly one area

6. **Area → AreaAssignment**: One-to-Many
   - An area can have multiple assignments
   - Each assignment belongs to exactly one area

7. **AreaManager → AreaAssignment**: One-to-Many
   - A manager can have multiple assignments
   - Each assignment can have zero or one manager

8. **Inquiry/Order → AreaAssignment**: One-to-Many
   - An inquiry/order can have multiple assignments (history of reassignments)
   - Each assignment belongs to exactly one inquiry or order

9. **AreaManager → AreaManagerAvailability**: One-to-Many
   - A manager can have multiple availability schedules (one per day of week)
   - Each availability record belongs to exactly one manager

---

## Assignment Strategies

### Strategy Interface

```php
interface AreaAssignmentStrategyInterface
{
    /**
     * Assign an area to an inquiry or order
     */
    public function assignArea(Inquiry|Order $entity, ?User $user = null): ?AreaAssignment;

    /**
     * Assign a manager within an area
     */
    public function assignManager(Area $area, Inquiry|Order $entity): ?AreaManager;

    /**
     * Get strategy name
     */
    public function getName(): string;

    /**
     * Get strategy priority (higher = checked first)
     */
    public function getPriority(): int;
}
```

### Available Strategies

#### 1. CriteriaMatchStrategy

**Purpose**: Automatically assign area based on predefined criteria rules

**Algorithm**:
```
1. Fetch all active AreaCriteria for client, sorted by priority DESC
2. For each criterion:
   a. Extract relevant data from inquiry/order (postal code, country, etc.)
   b. Apply operator logic (equals, contains, regex, etc.)
   c. If match found, return that area
3. If no match, return null (fallback to default area)
```

**Example**:
```php
Criteria:
  - Type: postal_code, Operator: starts_with, Value: "80,81,82", Priority: 10
  - Type: country, Operator: equals, Value: "DE", Priority: 5

Inquiry postal code: "80331"
Result: Matches first criterion → Assign to area
```

---

#### 2. RoundRobinStrategy

**Purpose**: Distribute assignments evenly among available managers

**Algorithm**:
```
1. Fetch all active managers in area, sorted by display_order
2. Get last assigned manager from previous assignment
3. Select next manager in rotation
4. If last manager was the end of list, start from beginning
5. Return selected manager
```

**State Management**:
- Stores last assigned manager ID in area metadata
- Thread-safe using database locking

---

#### 3. LoadBalancedStrategy

**Purpose**: Assign to manager with lowest current workload

**Algorithm**:
```
1. Fetch all active managers in area
2. Filter out managers at max capacity (current >= max_concurrent_assignments)
3. Sort by current_assignment_count ASC
4. Return manager with lowest count
5. If tie, use display_order as tiebreaker
```

**Benefits**:
- Prevents manager burnout
- Ensures fair distribution
- Respects capacity limits

---

#### 4. AvailabilityStrategy

**Purpose**: Assign to manager currently available based on timezone and schedule

**Algorithm**:
```
1. Get current datetime in UTC
2. For each manager:
   a. Convert current time to manager's timezone
   b. Check day of week
   c. Check if current time falls within availability window
   d. Check exceptions (holidays, vacation)
3. Filter to only available managers
4. If multiple available, use load-balanced selection
5. Return best available manager
```

**Fallback**: If no manager available now, return manager with soonest availability

---

#### 5. SpecializationStrategy

**Purpose**: Match manager specialization to inquiry/order requirements

**Algorithm**:
```
1. Extract required specializations from inquiry/order
   - Machine types requested
   - Product categories
   - Technical vs sales inquiry
2. For each manager, calculate match score:
   - +10 points for each matching specialization
   - +5 points for each matching responsibility
   - +3 points for matching language
3. Sort by match score DESC
4. Return highest scoring manager
```

**Example**:
```
Inquiry: Machine Type A, Language: German
Manager 1: Specializations: [Machine Type A, B], Languages: [de, en] → Score: 13
Manager 2: Specializations: [Machine Type C], Languages: [en] → Score: 0
Result: Assign Manager 1
```

---

#### 6. HybridStrategy

**Purpose**: Combine multiple strategies for optimal assignment

**Algorithm**:
```
1. Get pool of eligible managers (active, not at capacity)
2. Apply AvailabilityStrategy: Filter to currently available
3. Apply SpecializationStrategy: Score by relevance
4. Apply LoadBalancedStrategy: Prefer managers with lower load
5. Weighted scoring:
   - Availability: 40%
   - Specialization match: 35%
   - Current load (inverted): 25%
6. Return highest scoring manager
```

**Configuration**:
```json
{
  "weights": {
    "availability": 0.4,
    "specialization": 0.35,
    "load": 0.25
  },
  "fallback_strategy": "round_robin"
}
```

---

## API Endpoints

### Area Management

#### List Areas
```http
GET /api/areas

Query Parameters:
- client: UUID (filter by client)
- parent: UUID (filter by parent area)
- type: string (filter by type)
- isActive: boolean
- page: integer
- itemsPerPage: integer

Response: 200 OK
{
  "hydra:member": [
    {
      "id": "uuid",
      "client": "/api/clients/uuid",
      "parentArea": null,
      "name": "European Sales",
      "code": "EU-SALES",
      "type": "geographic",
      "timezone": "Europe/Berlin",
      "isActive": true,
      "managerCount": 3,
      "activeAssignmentCount": 12
    }
  ]
}
```

#### Create Area
```http
POST /api/areas
Content-Type: application/json

{
  "client": "/api/clients/uuid",
  "parentArea": "/api/areas/uuid",
  "name": "German Sales",
  "code": "DE-SALES",
  "type": "geographic",
  "description": "Sales team for Germany",
  "contactEmail": "sales-de@deckard.com",
  "contactPhone": "+49 89 12345678",
  "timezone": "Europe/Berlin",
  "isActive": true,
  "metadata": {
    "region": "DACH",
    "languages": ["de", "en"]
  }
}

Response: 201 Created
{
  "id": "uuid",
  "client": "/api/clients/uuid",
  "name": "German Sales",
  "code": "DE-SALES",
  ...
}
```

#### Get Area
```http
GET /api/areas/{id}

Response: 200 OK
{
  "id": "uuid",
  "client": "/api/clients/uuid",
  "parentArea": "/api/areas/parent-uuid",
  "childAreas": [
    "/api/areas/child-uuid-1",
    "/api/areas/child-uuid-2"
  ],
  "name": "European Sales",
  "code": "EU-SALES",
  "type": "geographic",
  "description": "...",
  "areaManagers": [
    "/api/area_managers/uuid-1",
    "/api/area_managers/uuid-2"
  ],
  "areaCriteria": [
    "/api/area_criteria/uuid-1"
  ],
  "statistics": {
    "totalAssignments": 145,
    "activeAssignments": 12,
    "completedAssignments": 133,
    "averageCompletionTime": "2.5 days"
  }
}
```

#### Update Area
```http
PUT /api/areas/{id}
PATCH /api/areas/{id}
Content-Type: application/json

{
  "name": "European Sales & Support",
  "isActive": false
}

Response: 200 OK
```

#### Delete Area
```http
DELETE /api/areas/{id}

Response: 204 No Content
```

---

### Area Manager Management

#### List Area Managers
```http
GET /api/area_managers

Query Parameters:
- area: UUID (filter by area)
- user: UUID (filter by user)
- role: string (filter by role)
- isActive: boolean
- isPrimary: boolean
- availableNow: boolean (filter to currently available)

Response: 200 OK
{
  "hydra:member": [
    {
      "id": "uuid",
      "area": "/api/areas/uuid",
      "user": "/api/users/uuid",
      "role": "primary",
      "isPrimary": true,
      "isActive": true,
      "currentAssignmentCount": 5,
      "maxConcurrentAssignments": 10,
      "availabilityStatus": "available"
    }
  ]
}
```

#### Create Area Manager
```http
POST /api/area_managers
Content-Type: application/json

{
  "area": "/api/areas/uuid",
  "user": "/api/users/uuid",
  "role": "primary",
  "responsibilities": ["sales", "technical_support"],
  "specializations": ["machine_type_a", "spare_parts"],
  "languages": ["en", "de", "fr"],
  "isPrimary": true,
  "isActive": true,
  "maxConcurrentAssignments": 15,
  "autoAssignEnabled": true,
  "bio": "20 years of experience in industrial machinery sales",
  "startDate": "2024-01-01",
  "notificationPreferences": {
    "email": true,
    "sms": false,
    "push": true
  }
}

Response: 201 Created
```

#### Get Area Manager
```http
GET /api/area_managers/{id}

Response: 200 OK
{
  "id": "uuid",
  "area": "/api/areas/uuid",
  "user": {
    "id": "uuid",
    "email": "manager@example.com",
    "fullName": "John Doe"
  },
  "role": "primary",
  "isPrimary": true,
  "currentAssignmentCount": 5,
  "maxConcurrentAssignments": 10,
  "statistics": {
    "totalAssignments": 234,
    "completedAssignments": 229,
    "averageResponseTime": "2.3 hours",
    "customerSatisfactionScore": 4.8
  }
}
```

---

### Area Criteria Management

#### List Area Criteria
```http
GET /api/area_criteria

Query Parameters:
- area: UUID (filter by area)
- criteriaType: string
- isActive: boolean

Response: 200 OK
```

#### Create Area Criteria
```http
POST /api/area_criteria
Content-Type: application/json

{
  "area": "/api/areas/uuid",
  "criteriaType": "postal_code",
  "operator": "starts_with",
  "criteriaValue": "80,81,82,83,84,85",
  "priority": 10,
  "isActive": true
}

Response: 201 Created
```

---

### Assignment Management

#### List Assignments
```http
GET /api/area_assignments

Query Parameters:
- inquiry: UUID
- order: UUID
- area: UUID
- manager: UUID
- status: string
- assignmentType: string

Response: 200 OK
```

#### Create Manual Assignment
```http
POST /api/area_assignments
Content-Type: application/json

{
  "area": "/api/areas/uuid",
  "manager": "/api/area_managers/uuid",
  "inquiry": "/api/inquiries/uuid",
  "assignmentType": "manual",
  "notes": "High-value customer requires senior manager"
}

Response: 201 Created
```

#### Reassign
```http
POST /api/area_assignments/{id}/reassign
Content-Type: application/json

{
  "newManager": "/api/area_managers/uuid",
  "reason": "Original manager on vacation",
  "notifyCustomer": true
}

Response: 200 OK
{
  "id": "new-assignment-uuid",
  "previousAssignment": "/api/area_assignments/old-uuid",
  "status": "active"
}
```

---

### Custom Operations

#### Get Client's Area Tree
```http
GET /api/clients/{id}/area-tree

Response: 200 OK
{
  "client": "/api/clients/uuid",
  "areas": [
    {
      "id": "uuid",
      "name": "Europe",
      "code": "EU",
      "children": [
        {
          "id": "uuid",
          "name": "Germany",
          "code": "EU-DE",
          "children": [
            {
              "id": "uuid",
              "name": "Munich Office",
              "code": "EU-DE-MUC",
              "children": []
            }
          ]
        }
      ]
    }
  ]
}
```

#### Get Available Managers for Inquiry
```http
GET /api/inquiries/{id}/available-managers

Query Parameters:
- onlyAvailableNow: boolean (default: false)
- includeBackup: boolean (default: true)

Response: 200 OK
{
  "inquiry": "/api/inquiries/uuid",
  "assignedArea": {
    "id": "uuid",
    "name": "European Sales",
    "assignedAt": "2024-01-15T10:30:00Z",
    "assignmentType": "automatic"
  },
  "managers": [
    {
      "id": "uuid",
      "user": {
        "fullName": "John Doe",
        "email": "john@example.com",
        "phone": "+49 89 12345678"
      },
      "role": "primary",
      "isPrimary": true,
      "bio": "20 years experience...",
      "photoUrl": "https://...",
      "specializations": ["machine_type_a"],
      "languages": ["en", "de"],
      "availabilityStatus": "available",
      "availableFrom": null,
      "currentLoad": 5,
      "maxLoad": 10
    }
  ],
  "recommendedManager": {
    "id": "uuid",
    "recommendationScore": 95,
    "recommendationReason": "Best specialization match and currently available"
  }
}
```

#### Get Recommended Manager
```http
GET /api/inquiries/{id}/recommended-manager

Response: 200 OK
{
  "manager": {
    "id": "uuid",
    "user": {...},
    ...
  },
  "score": 95,
  "reasoning": {
    "specializationMatch": 35,
    "availability": 40,
    "workloadBalance": 20
  }
}
```

#### Auto-Assign Area
```http
POST /api/inquiries/{id}/assign-area
Content-Type: application/json

{
  "strategy": "criteria_match"
}

Response: 200 OK
{
  "assignment": {
    "id": "uuid",
    "area": "/api/areas/uuid",
    "assignmentType": "automatic",
    "assignmentStrategy": "criteria_match",
    "assignmentReason": "Matched postal code criteria: starts_with 80*"
  }
}
```

#### Auto-Assign Manager
```http
POST /api/inquiries/{id}/assign-manager
Content-Type: application/json

{
  "strategy": "hybrid",
  "notifyManager": true
}

Response: 200 OK
{
  "assignment": {
    "id": "uuid",
    "area": "/api/areas/uuid",
    "manager": "/api/area_managers/uuid",
    "assignmentType": "automatic",
    "assignmentStrategy": "hybrid",
    "assignmentReason": "Best match based on availability, specialization, and workload"
  }
}
```

#### Get Manager Statistics
```http
GET /api/area_managers/{id}/statistics

Query Parameters:
- dateFrom: date (default: 30 days ago)
- dateTo: date (default: today)

Response: 200 OK
{
  "manager": "/api/area_managers/uuid",
  "period": {
    "from": "2024-01-01",
    "to": "2024-01-31"
  },
  "statistics": {
    "totalAssignments": 45,
    "activeAssignments": 8,
    "completedAssignments": 37,
    "averageCompletionTime": "2.3 days",
    "averageResponseTime": "1.5 hours",
    "customerSatisfactionScore": 4.7,
    "inquiryConversionRate": 0.42,
    "assignmentsByType": {
      "automatic": 30,
      "manual": 15
    },
    "assignmentsByStatus": {
      "active": 8,
      "completed": 37
    }
  }
}
```

---

## Business Logic Workflows

### Workflow 1: Inquiry Submission with Auto-Assignment

```
User submits inquiry
    ↓
[InquiryCreatedEvent fired]
    ↓
AreaAssignmentService::assignAreaToInquiry()
    ↓
1. Check if user has pre-assigned area
    ├─ Yes: Use that area → Go to step 3
    └─ No: Continue to step 2
    ↓
2. Run CriteriaMatchStrategy
    ├─ Get all active AreaCriteria for user's client
    ├─ Sort by priority DESC
    ├─ For each criterion:
    │   ├─ Extract relevant data from inquiry
    │   ├─ Apply operator logic
    │   └─ If match: Use that area → Go to step 3
    └─ No match: Use client's default area (or first area)
    ↓
3. Create AreaAssignment record
    ├─ area_id = selected area
    ├─ inquiry_id = inquiry
    ├─ assignment_type = 'automatic'
    ├─ assignment_strategy = 'criteria_match'
    ├─ assignment_reason = criteria description
    └─ status = 'pending'
    ↓
4. Run manager assignment strategy (if configured)
    ├─ Get configured strategy from area settings
    ├─ Default: HybridStrategy
    ├─ Execute strategy to select manager
    └─ Update AreaAssignment with manager_id
    ↓
5. Update manager's current_assignment_count++
    ↓
6. Dispatch notifications
    ├─ Email to assigned manager
    ├─ Email to customer (with manager info)
    └─ In-app notification to manager
    ↓
7. Update AreaAssignment status = 'active'
    ↓
[Assignment complete]
```

---

### Workflow 2: Manual Manager Reassignment

```
Admin initiates reassignment
    ↓
POST /api/area_assignments/{id}/reassign
{
  "newManager": "uuid",
  "reason": "string",
  "notifyCustomer": true
}
    ↓
AreaAssignmentService::reassignManager()
    ↓
1. Validate current assignment
    ├─ Check assignment exists
    ├─ Check assignment is active
    └─ Check new manager is active and in same area
    ↓
2. Check new manager capacity
    ├─ If at max capacity: Reject with error
    └─ Has capacity: Continue
    ↓
3. Create new AreaAssignment
    ├─ Copy inquiry/order from old assignment
    ├─ area_id = same area
    ├─ manager_id = new manager
    ├─ assignment_type = 'manual'
    ├─ assignment_strategy = 'manual'
    ├─ assignment_reason = provided reason
    ├─ assigned_by_user_id = current admin user
    ├─ status = 'active'
    └─ Save new assignment
    ↓
4. Update old assignment
    ├─ status = 'reassigned'
    ├─ reassigned_to_id = new assignment ID
    ├─ completed_at = now
    └─ Save changes
    ↓
5. Update manager counters
    ├─ Old manager: current_assignment_count--
    └─ New manager: current_assignment_count++
    ↓
6. Dispatch notifications
    ├─ Email to new manager
    ├─ Email to old manager
    └─ Email to customer (if notifyCustomer = true)
    ↓
[Reassignment complete]
```

---

### Workflow 3: Order Confirmation Page - Display Available Managers

```
User views order confirmation page
    ↓
Frontend: GET /api/orders/{id}/available-managers
    ↓
AvailableManagersProvider::getAvailableManagers()
    ↓
1. Get order's area assignment
    ├─ Query AreaAssignment where order_id = {id}
    ├─ Get most recent active assignment
    └─ If no assignment: Run auto-assignment → Go to step 1
    ↓
2. Get area from assignment
    ↓
3. Get all managers for area
    ├─ Query AreaManager where area_id = area AND is_active = true
    └─ Sort by: is_primary DESC, display_order ASC
    ↓
4. Filter managers
    ├─ Remove managers at max capacity
    ├─ If onlyAvailableNow: Check availability schedule
    └─ Result: filtered manager list
    ↓
5. Enhance manager data
    For each manager:
    ├─ Get user details (name, email, phone, photo)
    ├─ Calculate availability status
    │   ├─ Check current time in manager's timezone
    │   ├─ Check day of week schedule
    │   └─ Return: "available", "available_soon", "unavailable"
    ├─ Get specialization match score
    │   └─ Compare manager specializations with order products
    └─ Build manager DTO
    ↓
6. Calculate recommended manager
    ├─ Run HybridStrategy scoring
    ├─ Select highest scoring manager
    └─ Add recommendation reason
    ↓
7. Build response
    ├─ assignedArea: {...}
    ├─ managers: [...]
    └─ recommendedManager: {...}
    ↓
[Return JSON response to frontend]
    ↓
Frontend displays manager cards
    ├─ Highlight recommended manager
    ├─ Show availability status
    ├─ Show contact buttons
    └─ Allow customer to contact manager
```

---

### Workflow 4: Load Balancing with Capacity Management

```
New inquiry/order needs manager assignment
    ↓
LoadBalancedStrategy::assignManager()
    ↓
1. Get all active managers in area
    ├─ WHERE is_active = true
    └─ Result: [Manager A, Manager B, Manager C]
    ↓
2. Filter by capacity
    For each manager:
    ├─ Check: current_assignment_count < max_concurrent_assignments
    ├─ Manager A: 5 < 10 ✓ Keep
    ├─ Manager B: 15 >= 15 ✗ Remove
    └─ Manager C: 8 < 12 ✓ Keep
    Result: [Manager A, Manager C]
    ↓
3. Sort by current load
    ├─ Manager A: 5 assignments
    ├─ Manager C: 8 assignments
    └─ Sorted: [Manager A, Manager C]
    ↓
4. Select first (least loaded)
    └─ Selected: Manager A
    ↓
5. Create assignment
    ├─ manager_id = Manager A
    └─ Save assignment
    ↓
6. Increment counter
    ├─ Manager A: current_assignment_count = 5 + 1 = 6
    └─ UPDATE area_manager SET current_assignment_count = 6
    ↓
[Assignment complete]

---

On assignment completion:
    ↓
AreaAssignmentService::completeAssignment()
    ↓
1. Update assignment
    ├─ status = 'completed'
    └─ completed_at = now
    ↓
2. Decrement counter
    ├─ Manager A: current_assignment_count = 6 - 1 = 5
    └─ UPDATE area_manager SET current_assignment_count = 5
    ↓
[Manager available for new assignments]
```

---

## Security & Permissions

### Role-Based Access Control

#### ROLE_SUPER_ADMIN
```
Areas:
  ✓ Create, read, update, delete any area
  ✓ View all clients' areas
  ✓ Manage area hierarchy

Area Managers:
  ✓ Create, read, update, delete any area manager
  ✓ Assign any user as manager
  ✓ Override capacity limits

Area Criteria:
  ✓ Create, read, update, delete any criteria
  ✓ Change priority and rules

Area Assignments:
  ✓ View all assignments
  ✓ Manually assign/reassign to any manager
  ✓ Override auto-assignment
  ✓ Access full audit trail
```

#### ROLE_CLIENT_ADMIN
```
Areas:
  ✓ Create, read, update, delete areas for own client only
  ✓ Manage area hierarchy within own client
  ✗ Cannot access other clients' areas

Area Managers:
  ✓ Create, read, update, delete managers in own client's areas
  ✓ Assign users from own client as managers
  ✗ Cannot modify managers from other clients

Area Criteria:
  ✓ Create, read, update, delete criteria for own areas
  ✓ Configure auto-assignment rules

Area Assignments:
  ✓ View assignments for own client's inquiries/orders
  ✓ Manually assign/reassign within own areas
  ✓ View assignment statistics for own areas
  ✗ Cannot view other clients' assignments
```

#### ROLE_AREA_MANAGER
```
Areas:
  ✓ Read own assigned area(s)
  ✗ Cannot create, update, or delete areas

Area Managers:
  ✓ Read own area manager profile
  ✓ Update own bio, photo, availability schedule
  ✓ Update own notification preferences
  ✗ Cannot modify role, capacity, or other managers

Area Criteria:
  ✓ Read criteria for own area
  ✗ Cannot create, update, or delete criteria

Area Assignments:
  ✓ View own assignments
  ✓ Accept pending assignments
  ✓ Request reassignment (with reason)
  ✓ Mark assignments as completed
  ✓ Add notes to assignments
  ✗ Cannot view other managers' assignments
  ✗ Cannot manually assign to self
```

#### ROLE_USER (Customer)
```
Areas:
  ✓ Read own assigned area (if any)
  ✗ Cannot modify areas

Area Managers:
  ✓ View available managers in own area
  ✓ View manager contact info and bio
  ✗ Cannot modify managers

Area Criteria:
  ✗ Cannot access criteria

Area Assignments:
  ✓ View assignments for own inquiries/orders
  ✓ View assigned manager details
  ✗ Cannot reassign or modify assignments
```

### API Platform Security Annotations

```php
#[ApiResource(
    security: "is_granted('ROLE_USER')",
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_AREA_MANAGER') or is_granted('ROLE_CLIENT_ADMIN')"
        ),
        new Get(
            security: "is_granted('ROLE_USER') and object.client == user.client"
        ),
        new Post(
            security: "is_granted('ROLE_CLIENT_ADMIN')"
        ),
        new Put(
            security: "is_granted('ROLE_CLIENT_ADMIN') and object.client == user.client"
        ),
        new Delete(
            security: "is_granted('ROLE_CLIENT_ADMIN') and object.client == user.client"
        )
    ]
)]
class Area
{
    // ...
}
```

---

## Performance Optimization

### Database Indexes

**Critical Indexes** (already defined in schema):

```sql
-- Area
CREATE INDEX idx_area_client_active ON area(client_id, is_active);
CREATE INDEX idx_area_parent ON area(parent_area_id);
CREATE INDEX idx_area_code ON area(client_id, code);
CREATE INDEX idx_area_type ON area(type);

-- AreaManager
CREATE INDEX idx_manager_area_active ON area_manager(area_id, is_active);
CREATE INDEX idx_manager_user ON area_manager(user_id);
CREATE INDEX idx_manager_primary ON area_manager(area_id, is_primary);
CREATE INDEX idx_manager_load ON area_manager(current_assignment_count, max_concurrent_assignments);
CREATE INDEX idx_manager_dates ON area_manager(start_date, end_date);

-- AreaCriteria
CREATE INDEX idx_criteria_area_active ON area_criteria(area_id, is_active, priority DESC);
CREATE INDEX idx_criteria_type ON area_criteria(criteria_type);

-- AreaAssignment
CREATE INDEX idx_assignment_inquiry ON area_assignment(inquiry_id);
CREATE INDEX idx_assignment_order ON area_assignment(order_id);
CREATE INDEX idx_assignment_manager_status ON area_assignment(manager_id, status);
CREATE INDEX idx_assignment_area_status ON area_assignment(area_id, status);
CREATE INDEX idx_assignment_created ON area_assignment(assigned_at);

-- AreaManagerAvailability
CREATE INDEX idx_availability_manager_day ON area_manager_availability(area_manager_id, day_of_week);
```

### Caching Strategy

#### 1. Area Hierarchy Cache
```php
// Cache key: area_tree_{client_id}
// TTL: 1 hour
// Invalidate: On area create/update/delete

$cacheKey = "area_tree_{$clientId}";
$tree = $cache->get($cacheKey, function() use ($clientId) {
    return $this->buildAreaTree($clientId);
});
```

#### 2. Available Managers Cache
```php
// Cache key: available_managers_{area_id}_{timestamp_hour}
// TTL: 15 minutes
// Invalidate: On manager update, on hour change

$cacheKey = "available_managers_{$areaId}_" . date('YmdH');
$managers = $cache->get($cacheKey, function() use ($areaId) {
    return $this->getAvailableManagers($areaId);
});
```

#### 3. Criteria Match Cache
```php
// Cache key: area_criteria_{client_id}
// TTL: 30 minutes
// Invalidate: On criteria create/update/delete

$cacheKey = "area_criteria_{$clientId}";
$criteria = $cache->get($cacheKey, function() use ($clientId) {
    return $this->getActiveC riteria($clientId);
});
```

### Query Optimization

#### Eager Loading
```php
// Instead of N+1 queries
$areas = $areaRepository->findBy(['client' => $client]);
foreach ($areas as $area) {
    $managers = $area->getAreaManagers(); // N queries
}

// Use eager loading
$areas = $areaRepository->createQueryBuilder('a')
    ->leftJoin('a.areaManagers', 'm')
    ->addSelect('m')
    ->leftJoin('m.user', 'u')
    ->addSelect('u')
    ->where('a.client = :client')
    ->setParameter('client', $client)
    ->getQuery()
    ->getResult(); // 1 query
```

#### Partial Objects for Lists
```php
// Don't fetch full entities for list views
$areas = $areaRepository->createQueryBuilder('a')
    ->select('a.id', 'a.name', 'a.code', 'a.isActive')
    ->addSelect('COUNT(m.id) as managerCount')
    ->leftJoin('a.areaManagers', 'm')
    ->where('a.client = :client')
    ->groupBy('a.id')
    ->setParameter('client', $client)
    ->getQuery()
    ->getArrayResult(); // Array result, not objects
```

### Batch Operations

```php
// When updating multiple manager counters
public function batchUpdateAssignmentCounts(array $managerDeltas): void
{
    $em = $this->entityManager;

    foreach ($managerDeltas as $managerId => $delta) {
        $em->createQueryBuilder()
            ->update(AreaManager::class, 'am')
            ->set('am.currentAssignmentCount', 'am.currentAssignmentCount + :delta')
            ->where('am.id = :managerId')
            ->setParameter('delta', $delta)
            ->setParameter('managerId', $managerId)
            ->getQuery()
            ->execute();
    }

    // Single flush at the end
    $em->flush();
}
```

---

## Integration Points

### 1. Inquiry Module Integration

**InquiryCreatedEvent Listener**:
```php
#[AsEventListener(event: InquiryCreatedEvent::class)]
class AssignAreaToInquiryListener
{
    public function __invoke(InquiryCreatedEvent $event): void
    {
        $inquiry = $event->getInquiry();

        if ($inquiry->isDraft()) {
            return; // Don't assign to drafts
        }

        // Auto-assign area
        $this->areaAssignmentService->assignAreaToInquiry($inquiry);
    }
}
```

**Inquiry API Response Extension**:
```php
// Add to Inquiry normalization
{
  "id": "uuid",
  "inquiryNumber": "INQ-12345678",
  "status": "submitted",
  // ... other fields
  "areaAssignment": {
    "area": {
      "id": "uuid",
      "name": "European Sales",
      "code": "EU-SALES"
    },
    "manager": {
      "id": "uuid",
      "user": {
        "fullName": "John Doe",
        "email": "john@example.com"
      },
      "role": "primary"
    },
    "assignedAt": "2024-01-15T10:30:00Z"
  }
}
```

---

### 2. Order Module Integration

**Same pattern as Inquiry**:
- OrderCreatedEvent listener
- Auto-assignment on order submission
- Area/manager info in order API response
- Display on order confirmation page

---

### 3. User Module Integration

**UserProfile Extension**:
```php
// Add to User entity
#[ORM\OneToMany(mappedBy: 'user', targetEntity: AreaManager::class)]
private Collection $areaManagerRoles;

// Add to User API response
{
  "id": "uuid",
  "email": "user@example.com",
  "fullName": "John Doe",
  "client": "/api/clients/uuid",
  // ... other fields
  "areaManagerRoles": [
    {
      "id": "uuid",
      "area": {
        "id": "uuid",
        "name": "European Sales"
      },
      "role": "primary",
      "isActive": true
    }
  ],
  "assignedArea": {
    "id": "uuid",
    "name": "German Sales"
  }
}
```

---

### 4. Email Notification Integration

**New Email Templates**:

1. **Manager Assignment Notification** (`emails/manager/assignment_notification.html.twig`)
   - Sent to: Area Manager
   - Trigger: New assignment created
   - Content: Inquiry/order details, customer info, quick action links

2. **Customer Manager Introduction** (`emails/customer/manager_introduction.html.twig`)
   - Sent to: Customer
   - Trigger: Manager assigned to their inquiry/order
   - Content: Manager bio, photo, contact info, availability

3. **Reassignment Notification** (`emails/manager/reassignment_notification.html.twig`)
   - Sent to: Both old and new managers
   - Trigger: Assignment reassigned
   - Content: Reason for reassignment, new/old manager info

---

### 5. Dashboard & Analytics Integration

**New Dashboard Widgets**:

1. **Area Performance Overview**
   - Active assignments by area
   - Average assignment completion time
   - Manager workload distribution

2. **Manager Performance Metrics**
   - Assignments per manager
   - Average response time
   - Customer satisfaction scores

3. **Assignment Analytics**
   - Assignment type breakdown (auto vs manual)
   - Strategy effectiveness
   - Reassignment rate

---

## Development Guidelines

### Adding a New Assignment Strategy

1. **Create Strategy Class**:
```php
namespace App\Service\AreaAssignment\Strategy;

class MyCustomStrategy implements AreaAssignmentStrategyInterface
{
    public function assignArea(Inquiry|Order $entity, ?User $user = null): ?AreaAssignment
    {
        // Your area assignment logic
    }

    public function assignManager(Area $area, Inquiry|Order $entity): ?AreaManager
    {
        // Your manager selection logic
    }

    public function getName(): string
    {
        return 'my_custom';
    }

    public function getPriority(): int
    {
        return 50; // Medium priority
    }
}
```

2. **Register as Service**:
```yaml
# config/services.yaml
services:
    App\Service\AreaAssignment\Strategy\MyCustomStrategy:
        tags:
            - { name: 'area_assignment.strategy' }
```

3. **Use in Configuration**:
```php
$area->setMetadata([
    'assignment_strategy' => 'my_custom'
]);
```

---

### Testing Guidelines

#### Unit Tests

```php
class LoadBalancedStrategyTest extends TestCase
{
    public function testAssignsToManagerWithLowestLoad(): void
    {
        // Arrange
        $area = $this->createArea();
        $manager1 = $this->createManager($area, currentLoad: 5);
        $manager2 = $this->createManager($area, currentLoad: 2);
        $inquiry = $this->createInquiry();

        // Act
        $strategy = new LoadBalancedStrategy();
        $assigned = $strategy->assignManager($area, $inquiry);

        // Assert
        $this->assertSame($manager2->getId(), $assigned->getId());
    }

    public function testRespectsMaxCapacity(): void
    {
        // Arrange
        $area = $this->createArea();
        $manager1 = $this->createManager($area, currentLoad: 10, maxLoad: 10);
        $manager2 = $this->createManager($area, currentLoad: 5, maxLoad: 10);
        $inquiry = $this->createInquiry();

        // Act
        $strategy = new LoadBalancedStrategy();
        $assigned = $strategy->assignManager($area, $inquiry);

        // Assert
        $this->assertSame($manager2->getId(), $assigned->getId());
    }
}
```

#### Integration Tests

```php
class AreaAssignmentApiTest extends ApiTestCase
{
    public function testAutoAssignAreaToInquiry(): void
    {
        // Arrange
        $client = $this->createClient();
        $area = $this->createArea($client, code: 'EU-SALES');
        $this->createAreaCriteria($area, type: 'country', value: 'DE');
        $inquiry = $this->createInquiry($client, country: 'DE');

        // Act
        $response = static::createClient()->request('POST', "/api/inquiries/{$inquiry->getId()}/assign-area");

        // Assert
        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $this->assertEquals($area->getId(), $data['assignment']['area']['id']);
        $this->assertEquals('automatic', $data['assignment']['assignmentType']);
    }
}
```

---

### Migration Guide

**Creating Initial Tables**:

```bash
# Generate migration
php bin/console make:migration

# Review migration file, then execute
php bin/console doctrine:migrations:migrate

# Verify tables created
php bin/console doctrine:schema:validate
```

**Initial Data Seeding**:

```php
// src/DataFixtures/AreaFixtures.php
class AreaFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Create default area for each client
        $clients = $manager->getRepository(Client::class)->findAll();

        foreach ($clients as $client) {
            $area = new Area();
            $area->setClient($client);
            $area->setName('Default Area');
            $area->setCode('DEFAULT');
            $area->setType('geographic');
            $area->setTimezone('UTC');
            $area->setIsActive(true);

            $manager->persist($area);
        }

        $manager->flush();
    }
}
```

---

## Appendix

### Glossary

| Term | Definition |
|------|------------|
| **Area** | Organizational unit (geographic, departmental, etc.) that groups managers |
| **Area Manager** | User assigned to handle inquiries/orders for a specific area |
| **Area Criteria** | Rules that automatically route inquiries/orders to appropriate areas |
| **Area Assignment** | Record tracking which area/manager is assigned to an inquiry/order |
| **Assignment Strategy** | Algorithm used to select which area or manager to assign |
| **Primary Manager** | Main point of contact for an area |
| **Secondary Manager** | Backup manager for an area |
| **Specialist Manager** | Manager with specific expertise (e.g., technical vs sales) |
| **Workload** | Number of active assignments a manager currently has |
| **Capacity** | Maximum number of concurrent assignments a manager can handle |
| **Availability** | Whether a manager is currently available based on schedule/timezone |
| **Reassignment** | Changing the assigned manager for an existing inquiry/order |

### Enum Values Reference

**Area Types**:
- `geographic` - Geographic regions (countries, states, cities)
- `departmental` - Organizational departments (sales, support, technical)
- `product_line` - Product-based divisions (machine types, spare parts)
- `custom` - Custom area type defined by client

**Manager Roles**:
- `primary` - Main contact for area
- `secondary` - Backup contact
- `specialist` - Subject matter expert
- `backup` - Overflow handler

**Criteria Types**:
- `postal_code` - Match by postal/zip code
- `country` - Match by country code
- `department` - Match by customer department
- `product_type` - Match by product/machine type
- `order_value` - Match by order total value
- `custom` - Custom criteria type

**Criteria Operators**:
- `equals` - Exact match
- `contains` - Substring match
- `starts_with` - Prefix match
- `in_range` - Numeric/date range
- `greater_than` - Numeric comparison
- `less_than` - Numeric comparison
- `regex` - Regular expression match

**Assignment Types**:
- `automatic` - Auto-assigned by strategy
- `manual` - Manually assigned by admin
- `user_selected` - Selected by customer

**Assignment Strategies**:
- `criteria_match` - Matched by area criteria rules
- `round_robin` - Rotated among managers
- `load_balanced` - Based on current workload
- `availability` - Based on manager schedule
- `specialization` - Based on expertise match
- `hybrid` - Combination of strategies
- `manual` - Manually selected by admin
- `default` - Default area for client

**Assignment Statuses**:
- `pending` - Assignment created but not yet active
- `active` - Currently active assignment
- `completed` - Assignment successfully completed
- `reassigned` - Assignment was reassigned to another manager
- `cancelled` - Assignment was cancelled

---

## Future Enhancements

### Phase 2 Features (Post-MVP)

1. **AI-Powered Recommendations**
   - Machine learning model to predict best manager
   - Learn from historical assignment success rates
   - Consider customer preferences and feedback

2. **Manager Chat Integration**
   - Direct chat between customer and assigned manager
   - Real-time messaging in application
   - Conversation history tracking

3. **Advanced Analytics Dashboard**
   - Manager performance heatmaps
   - Assignment flow visualization
   - Predictive capacity planning

4. **Multi-Language Support**
   - Auto-match customer language with manager
   - Translated manager bios
   - Localized availability displays

5. **Mobile App Integration**
   - Manager mobile app for assignment notifications
   - Quick accept/reassign from mobile
   - Real-time availability status updates

6. **Escalation Rules**
   - Auto-escalate if manager doesn't respond in X hours
   - Hierarchical escalation paths
   - SLA monitoring and alerts

7. **Customer Feedback Loop**
   - Rate manager after inquiry completion
   - Feedback used to improve assignment algorithm
   - Manager performance scorecards

8. **Capacity Forecasting**
   - Predict future workload based on trends
   - Recommend hiring additional managers
   - Seasonal adjustment suggestions

---

## Conclusion

This Area Management System provides a robust, scalable solution for managing organizational areas and intelligent assignment of inquiries/orders to appropriate managers. The architecture supports:

- **Flexibility**: Multiple assignment strategies, hierarchical areas, custom criteria
- **Automation**: Auto-assignment reduces manual work while allowing override
- **Scalability**: Optimized for performance with caching and indexing
- **Auditability**: Complete tracking of all assignments and changes
- **Extensibility**: Easy to add new strategies, criteria types, and features

The system integrates seamlessly with existing Inquiry and Order modules via API Platform REST endpoints and event-driven architecture.
