# Product Requirements Document (PRD)
## Lead Management System — Laravel

| Field | Details |
|---|---|
| **Document Version** | 1.0 |
| **Status** | Draft |
| **Author** | Product Team |
| **Last Updated** | March 2026 |
| **Platform** | Laravel (PHP) |

---

## 1. Overview

### 1.1 Purpose

This document defines the requirements for a **Lead Management System (LMS)** built on the Laravel framework. The system enables sales teams to capture, track, assign, and convert leads through a structured pipeline — from initial contact to closed deal — with full call logging and status lifecycle management.

### 1.2 Problem Statement

Sales teams currently manage leads through spreadsheets and manual tracking, causing missed follow-ups, unclear ownership, and no visibility into pipeline health. A centralised LMS will enforce a consistent process, improve team coordination, and increase conversion rates.

### 1.3 Goals

- Provide a single source of truth for all leads
- Enable structured lead lifecycle management with defined statuses
- Allow leads to be assigned to specific team members
- Log and track all calls made against each lead
- Give managers real-time visibility into pipeline health

### 1.4 Out of Scope (v1.0)

- Email automation and drip campaigns
- Integration with external CRMs (Salesforce, HubSpot)
- Mobile native applications
- AI-powered lead scoring
- Two-way telephony integration

---

## 2. Users & Roles

### 2.1 User Roles

| Role | Description | Key Permissions |
|---|---|---|
| **Admin** | Full system access | Manage users, view all leads, configure settings |
| **Sales Manager** | Team oversight | View all leads, assign leads, generate reports |
| **Sales Agent** | Day-to-day lead work | View assigned leads, update status, log calls |

### 2.2 Role-Based Access Control Summary

| Action | Admin | Sales Manager | Sales Agent |
|---|---|---|---|
| Create lead | ✅ | ✅ | ✅ |
| View all leads | ✅ | ✅ | ❌ (own only) |
| Assign lead | ✅ | ✅ | ❌ |
| Update status | ✅ | ✅ | ✅ (own leads) |
| Log call | ✅ | ✅ | ✅ (own leads) |
| Delete lead | ✅ | ❌ | ❌ |
| Manage users | ✅ | ❌ | ❌ |

---

## 3. Functional Requirements

### 3.1 Lead Management

#### 3.1.1 Add / Create Lead

Users with the appropriate role can create a new lead by filling in the following details:

**Required Fields:**
- Company / Organisation Name
- Contact Person (full name)
- Email Address (validated, unique)
- Phone Number

**Optional Fields:**
- Deal Value (numeric, currency)
- Lead Source (Website, Referral, Cold Call, LinkedIn, Email Campaign, Event, Partner)
- Industry (Technology, Finance, Healthcare, Retail, Education, Real Estate, Manufacturing, Other)
- Notes / Description
- Assigned To (team member)

**Behaviour:**
- On submission, the lead is saved with status defaulting to `new`
- A `created_at` timestamp is automatically recorded
- The creator's user ID is stored in `created_by`
- If `assigned_to` is set at creation, an assignment notification is triggered

---

#### 3.1.2 View Leads

**List View:**
- Paginated table (25 leads per page, configurable)
- Sortable columns: Company Name, Deal Value, Status, Created Date, Assigned To
- Searchable by: Company Name, Contact Name, Email
- Filterable by: Status, Assigned Agent, Lead Source, Date Range

**Detail View:**
- Full lead profile showing all fields
- Activity timeline showing status changes, assignments, and call logs
- Inline editing of notes and deal value

---

#### 3.1.3 Edit Lead

- Any authorised user can update lead details
- All edits are timestamped and stored in an `activity_logs` table
- Email uniqueness is validated on update (excluding the current lead)

---

#### 3.1.4 Delete Lead

- Only Admin users can soft-delete a lead (`deleted_at` timestamp via Laravel's `SoftDeletes` trait)
- Deleted leads are hidden from all views but remain in the database for audit purposes
- A restore option is available in the Admin panel

---

### 3.2 Lead Status Lifecycle

#### 3.2.1 Status Definitions

| Status | Description |
|---|---|
| `new` | Lead just entered the system, no contact made |
| `contacted` | Initial outreach has been made |
| `qualified` | Lead meets the criteria to pursue |
| `proposal` | A proposal or quote has been sent |
| `negotiation` | In active negotiation with the lead |
| `won` | Deal successfully closed |
| `lost` | Lead did not convert; no further action |

#### 3.2.2 Status Transition Rules

- Any authorised user may move a lead **forward** through the pipeline
- Moving a lead **backwards** (e.g., from `qualified` back to `new`) is restricted to Admin and Sales Manager roles
- Transitioning to `won` or `lost` is a terminal action; further changes require Admin approval
- Every status change is logged in `activity_logs` with: previous status, new status, changed by, timestamp

#### 3.2.3 Status Update UI

- Status is displayed as a colour-coded badge on both the list and detail views
- A dropdown or segmented control allows authorised users to change status inline from the list view
- A confirmation prompt appears before setting status to `won` or `lost`

---

### 3.3 Lead Assignment

#### 3.3.1 Assigning a Lead

- Admin and Sales Manager roles can assign a lead to any active Sales Agent
- Assignment can be done from the list view (inline) or the detail view
- A lead may be unassigned; unassigned leads appear in a dedicated queue
- Only one agent may be assigned to a lead at a time (single owner model)

#### 3.3.2 Reassignment

- A lead can be reassigned to a different agent at any time by authorised users
- On reassignment, the previous agent receives a notification (in-app and optional email)
- Reassignment history is stored in `activity_logs`

#### 3.3.3 Assignment Notifications

- Newly assigned agents receive an in-app notification
- Notification payload includes: lead name, assigning manager's name, lead status, and a direct link to the lead
- Email notification is optional per user preference

---

### 3.4 Call Management

#### 3.4.1 Log a Call

Any authorised user can log a call against a lead by providing:

**Required:**
- Call Notes (free text, minimum 10 characters)
- Logged By (auto-populated with current user, editable by Admin)

**Optional:**
- Call Duration (format: `mm:ss` or free text, e.g., "32 min")
- Call Outcome (Interested, Not Interested, Callback Requested, No Answer, Voicemail)
- Follow-Up Date (date picker; triggers a reminder if set)
- Call Direction (Inbound / Outbound)

**Behaviour:**
- Call log is timestamped with current date/time on save
- The lead's `last_contacted_at` field is updated on every new call log
- A call count badge is shown on the lead list view

#### 3.4.2 View Call History

- The lead detail page shows a full chronological list of all calls
- Each entry shows: date, duration, outcome, notes, and logged-by agent
- Calls can be edited (within 24 hours of creation) by the user who created them or by Admin

#### 3.4.3 Call Reminders

- If a follow-up date is set during call logging, a reminder is queued
- Reminders appear in the assigned agent's notification centre on the due date
- Laravel's task scheduler (`php artisan schedule:run`) processes reminders daily at 08:00

---

## 4. Non-Functional Requirements

### 4.1 Performance

- Lead list view must load within **2 seconds** for up to 10,000 records with pagination
- Status updates and call log saves must respond within **500ms**
- Database queries must use eager loading (`with()`) to prevent N+1 issues

### 4.2 Security

- All routes are protected by Laravel's built-in `auth` middleware
- Role permissions are enforced using **Laravel Policies** and **Gates**
- All user inputs are validated using Laravel **Form Request** classes
- SQL injection protection via Eloquent ORM and parameterised queries
- CSRF protection on all forms via Laravel's `@csrf` blade directive
- Passwords stored using `bcrypt` hashing

### 4.3 Scalability

- The system must support up to **50 concurrent users** without performance degradation
- Architecture must allow horizontal scaling (stateless application layer, shared cache)
- Queue system (Laravel Queue with Redis or database driver) for notifications and email dispatch

### 4.4 Auditability

- All create, update, delete, status change, and assignment actions are logged in `activity_logs`
- Logs are immutable; no user can delete activity log entries
- Logs are visible to Admin via a dedicated audit trail view

---

## 5. Technical Architecture

### 5.1 Tech Stack

| Layer | Technology |
|---|---|
| **Framework** | Laravel 11.x |
| **Language** | PHP 8.2+ |
| **Database** | MySQL 8.0 / PostgreSQL 15 |
| **Frontend** | Blade + Alpine.js + Tailwind CSS |
| **Authentication** | Laravel Breeze / Fortify |
| **Authorisation** | Laravel Policies & Gates |
| **Queue** | Laravel Queues (Redis or DB driver) |
| **Notifications** | Laravel Notifications (database + mail channels) |
| **Testing** | PHPUnit + Laravel Dusk |

---

### 5.2 Database Schema

#### `users`
| Column | Type | Notes |
|---|---|---|
| id | bigint (PK) | |
| name | varchar(255) | |
| email | varchar(255) | unique |
| password | varchar(255) | bcrypt |
| role | enum | `admin`, `manager`, `agent` |
| is_active | boolean | default true |
| created_at | timestamp | |
| updated_at | timestamp | |

#### `leads`
| Column | Type | Notes |
|---|---|---|
| id | bigint (PK) | |
| company_name | varchar(255) | required |
| contact_name | varchar(255) | required |
| email | varchar(255) | required, unique |
| phone | varchar(50) | required |
| deal_value | decimal(15,2) | nullable |
| source | varchar(100) | nullable |
| industry | varchar(100) | nullable |
| status | enum | `new`, `contacted`, `qualified`, `proposal`, `negotiation`, `won`, `lost` |
| notes | text | nullable |
| assigned_to | bigint (FK → users.id) | nullable |
| created_by | bigint (FK → users.id) | |
| last_contacted_at | timestamp | nullable |
| deleted_at | timestamp | nullable (soft delete) |
| created_at | timestamp | |
| updated_at | timestamp | |

#### `call_logs`
| Column | Type | Notes |
|---|---|---|
| id | bigint (PK) | |
| lead_id | bigint (FK → leads.id) | required |
| logged_by | bigint (FK → users.id) | required |
| notes | text | required |
| duration | varchar(20) | nullable |
| outcome | enum | `interested`, `not_interested`, `callback`, `no_answer`, `voicemail`, nullable |
| direction | enum | `inbound`, `outbound` |
| follow_up_date | date | nullable |
| called_at | timestamp | default now() |
| created_at | timestamp | |
| updated_at | timestamp | |

#### `activity_logs`
| Column | Type | Notes |
|---|---|---|
| id | bigint (PK) | |
| lead_id | bigint (FK → leads.id) | |
| user_id | bigint (FK → users.id) | |
| action | varchar(100) | e.g., `status_changed`, `assigned`, `call_logged` |
| old_value | json | nullable |
| new_value | json | nullable |
| description | text | human-readable summary |
| created_at | timestamp | |

#### `notifications` (Laravel default table)
- Uses Laravel's built-in `notifications` table for database channel notifications

---

### 5.3 Key Routes (RESTful)

```
GET    /leads                  → LeadController@index    (list view)
GET    /leads/create           → LeadController@create   (add form)
POST   /leads                  → LeadController@store    (save new lead)
GET    /leads/{id}             → LeadController@show     (detail view)
GET    /leads/{id}/edit        → LeadController@edit     (edit form)
PUT    /leads/{id}             → LeadController@update   (save changes)
DELETE /leads/{id}             → LeadController@destroy  (soft delete)

PATCH  /leads/{id}/status      → LeadController@updateStatus
PATCH  /leads/{id}/assign      → LeadController@assign

POST   /leads/{id}/calls       → CallLogController@store
GET    /leads/{id}/calls       → CallLogController@index
PUT    /calls/{id}             → CallLogController@update

GET    /admin/activity-logs    → ActivityLogController@index
```

---

### 5.4 Models & Relationships

```php
// Lead model relationships
Lead belongsTo User (assigned_to)
Lead belongsTo User (created_by)
Lead hasMany CallLog
Lead hasMany ActivityLog

// CallLog model relationships
CallLog belongsTo Lead
CallLog belongsTo User (logged_by)

// User model relationships
User hasMany Lead (assigned)
User hasMany CallLog
```

---

## 6. UI/UX Requirements

### 6.1 Lead List Page

- Searchable, sortable, paginated table
- Inline status dropdown (updates via AJAX without full page reload)
- Inline assignment dropdown
- Call count badge per lead
- Colour-coded status badges
- Quick action buttons: View, Edit, Log Call

### 6.2 Lead Detail Page

- Full information card at the top
- Tabbed sections: Overview | Call History | Activity Log
- Status update control prominent at the top of the page
- Assign/Reassign button with agent picker modal
- "Log a Call" button opens a slide-over panel or modal
- Activity timeline at the bottom showing all changes chronologically

### 6.3 Navigation

- Left sidebar with links to: Dashboard, All Leads, My Leads, Unassigned, Reports (Manager+), Admin (Admin only)
- Top bar with notification bell and user menu
- Dashboard shows KPI cards: Total Leads, My Open Leads, Calls Today, Won This Month

---

## 7. Notifications

| Trigger | Recipients | Channel |
|---|---|---|
| Lead assigned to agent | Assigned agent | In-app + Email |
| Lead reassigned | Old agent, new agent | In-app + Email |
| Lead status changed to `won`/`lost` | Assigned manager | In-app |
| Follow-up date due | Assigned agent | In-app + Email |
| New call logged on a lead | Assigned manager | In-app |

---

## 8. Reporting (Sales Manager & Admin)

- **Pipeline Summary:** Count and total value of leads per status
- **Agent Performance:** Number of leads assigned, calls logged, and deals won per agent
- **Lead Source Analysis:** Conversion rate by source
- **Activity Report:** All actions taken within a date range
- Export to CSV available for all report views

---

## 9. Development Milestones

| Phase | Scope | Estimated Duration |
|---|---|---|
| **Phase 1** | Auth, user roles, lead CRUD, basic status updates | 2 weeks |
| **Phase 2** | Assignment system, call log module, notifications | 2 weeks |
| **Phase 3** | Activity log, audit trail, reports, dashboard KPIs | 1.5 weeks |
| **Phase 4** | Testing (unit + feature + browser), bug fixes, deployment | 1 week |

---

## 10. Acceptance Criteria

- [ ] An Admin can create, view, edit, and soft-delete any lead
- [ ] A Sales Agent can only view and update leads assigned to them
- [ ] A lead's status can be changed inline from the list view without a full page reload
- [ ] A Sales Manager can assign or reassign a lead to any active agent
- [ ] The assigned agent receives a notification when a lead is assigned to them
- [ ] A Sales Agent can log a call against a lead with notes, duration, outcome, and optional follow-up date
- [ ] All call logs are visible in a timeline on the lead's detail page
- [ ] All status changes and assignments are recorded in the activity log
- [ ] The pipeline summary report displays correct counts and values per status
- [ ] All routes are inaccessible to unauthenticated users
- [ ] Role-based permissions are enforced — agents cannot assign leads or access admin features

---

## 11. Assumptions & Dependencies

- Laravel 11.x will be used as the base framework
- The team has access to a MySQL or PostgreSQL database server
- Redis or a database-backed queue driver is available for notification dispatch
- Laravel Breeze will be used for scaffolding authentication
- A mail server (SMTP or service like Mailgun) is available for email notifications
- Development follows Laravel conventions: Eloquent ORM, Blade templating, Artisan commands

---

## 12. Open Questions

1. Should leads support **multiple contacts** per company, or one contact per lead (v1.0)?
2. Is **bulk assignment** (select many leads → assign to one agent) required in v1.0?
3. What is the expected **data retention policy** for soft-deleted leads and activity logs?
4. Should the **follow-up reminder** also trigger an SMS notification, or email/in-app only?
5. Is there a requirement for **lead import via CSV** in the initial release?

---

*This document is a living specification. All changes should be versioned and reviewed by the product owner before implementation begins.*