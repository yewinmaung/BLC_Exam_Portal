# Believe Exam Portal — Technical Architecture Documentation

**Project:** Believe Learning Center (BLC) Exam Portal
**Framework:** Laravel 9 · PHP 8.0+
**Generated:** 2026-07-22
**Status:** Active Development

---

## TABLE OF CONTENTS

1. [System Overview](#part-1--system-overview)
2. [Folder Analysis](#part-2--complete-folder-analysis)
3. [Database Documentation](#part-3--database-documentation)
4. [Model Analysis](#part-4--model-analysis)
5. [Controller Flow](#part-5--controller-flow)
6. [Service Layer](#part-6--service-layer)
7. [Job & Queue System](#part-7--job-and-queue-system)
8. [Exam System Complete Flow](#part-8--exam-system-complete-flow)
9. [Anti-Cheat System](#part-9--anti-cheat-system)
10. [Email System Architecture](#part-10--email-system-architecture)
11. [Frontend Analysis](#part-11--frontend-analysis)
12. [Route Map](#part-12--route-map)
13. [Code Quality Review](#part-13--code-quality-review)
14. [Final Summary](#part-14--final-summary)

---

---

## PART 1 — SYSTEM OVERVIEW

### Project Purpose

Believe Exam Portal is a multi-role web application for **Believe Learning Center (BLC)** that manages the complete academic examination lifecycle — from teacher question authoring through student exam-taking to result publishing — with built-in anti-cheat enforcement, a queued email system, and an IMAP-connected inbox for admin communication.

### Main Modules

| Module | Description |
|--------|-------------|
| **Authentication** | Role-based login, OTP password reset, single-session enforcement |
| **Academic Structure** | Academic years, year levels, majors, courses, enrollments |
| **Exam Management** | Draft → approval → schedule → publish → close lifecycle |
| **Question Bank** | MCQ, True/False, Fill-in-the-Blank with encryption and attachments |
| **Exam Taking** | Timed sessions, answer auto-save, question randomization, session recovery |
| **Anti-Cheat** | 3-strike violation system, tab-switch detection, copy-paste blocking |
| **Auto-Grading** | Immediate scoring on submit (MCQ/TF/Fill-blank), case-sensitive matching |
| **Results** | PASSED / FAILED / ABSENT / DISQUALIFIED per attempt |
| **Email System** | SMTP queue, DB templates, compose, bulk, inbox IMAP sync |
| **Admin Panel** | Full CRUD for users, courses, majors, exams, results, email |

### Technology Stack

| Layer | Technology |
|-------|-----------|
| Backend Framework | Laravel 9 |
| Language | PHP 8.0+ |
| Database | MySQL (migrations-based) |
| Queue | Database-backed (jobs table) |
| Mail | SMTP via Laravel Mail + Webklex IMAP |
| Frontend | Blade templates + Bootstrap 5 + Vanilla JS |
| File Storage | Laravel public disk (profile photos, attachments) |
| Encryption | Laravel Crypt (question/answer content) |
| PDF | barryvdh/laravel-dompdf |
| Excel | maatwebsite/excel |
| QR Code | simplesoftwareio/simple-qrcode |
| Auth | Session-based (Sanctum installed, not actively used) |

### High-Level Architecture

```
Browser (Blade + Bootstrap 5)
        │
        ▼
  Web Routes (routes/web.php)
        │
  Middleware Stack
  ├── auth
  ├── role:admin|teacher|student
  ├── exam.session  (single-session guard)
  └── exam.active   (attempt state guard)
        │
        ▼
  Controllers (Admin / Teacher / Student / Auth)
        │
        ▼
  Service Layer
  ├── ExamSecurityService    ← violation enforcement
  ├── GradingService         ← auto-score on submit
  ├── SessionRecoveryService ← disconnect/reconnect
  ├── EmailService           ← queue & deliver emails
  ├── InboxSyncService       ← IMAP fetch
  ├── CheatingDetectionService (legacy)
  ├── QuestionImportService
  ├── CourseAssignmentService
  ├── AcademicService
  └── EncryptionService
        │
        ▼
  Models (Eloquent ORM)
        │
        ▼
  MySQL Database (32 migrations)
        │
  Background Jobs (database queue)
  ├── SendEmailJob
  ├── SendPasswordChangedJob
  └── SendProfileOtpJob
```

### Current Development Status

| Area | Status |
|------|--------|
| Authentication + OTP reset | ✅ Complete |
| Role system (3 roles) | ✅ Complete |
| Academic structure | ✅ Complete |
| Course / Enrollment management | ✅ Complete |
| Exam lifecycle (draft→close) | ✅ Complete |
| Question types (MCQ/TF/Fill) | ✅ Complete |
| Question encryption | ✅ Complete |
| Question import (txt/docx) | ✅ Complete |
| Exam taking + auto-save | ✅ Complete |
| Session recovery | ✅ Complete |
| Question randomization | ✅ Complete |
| Anti-cheat (3-strike) | ✅ Complete |
| Auto-grading | ✅ Complete |
| Results (PASS/FAIL/ABSENT/DISQ) | ✅ Complete |
| Email queue + templates | ✅ Complete |
| IMAP inbox sync | ✅ Complete |
| Admin compose (custom+template) | ✅ Complete |
| Profile photo upload | ✅ Complete |
| Notifications | ✅ Complete |
| Teacher analytics view | ✅ Complete |
| Grade/GPA system | ❌ Removed (intentionally) |
| PDF transcript/certificate | ⚠️ Tables dropped, views present |
| Real-time (Pusher/Echo) | ❌ Not implemented |
| API (REST/JSON) | ❌ Not implemented |

---

---

## PART 2 — COMPLETE FOLDER ANALYSIS

### `app/Models/` — 25 files
Eloquent ORM models. Each maps to a database table and defines relationships, fillable fields, casts, accessors, and business-level helper methods. Encryption-sensitive models (Question, Answer) delegate to `EncryptionService` via accessor.

### `app/Http/Controllers/` — 20 files across 4 subdirs

| Subdir | Files | Responsibility |
|--------|-------|---------------|
| `Admin/` | 11 | Full CRUD for all administrative entities |
| `Teacher/` | 3 | Exam authoring, profile, result viewing |
| `Student/` | 4 | Course browsing, exam taking, result viewing |
| `Auth/` | 2 | Login/register/logout, OTP password reset |
| Root | 3 | Dashboard, Notifications, Profile |

### `app/Services/` — 14 files
Business logic extracted from controllers. Each service is injected via Laravel's service container (constructor DI in controllers).

| Service | Responsibility |
|---------|---------------|
| `AcademicService` | Student enrollment history, yearly results |
| `ActivityLogService` | Writes activity_logs records with IP |
| `CheatingDetectionService` | **Legacy** — kept for admin log view only |
| `CourseAssignmentService` | Sync teacher/student course assignments |
| `EmailService` | Complete email pipeline (compose, queue, deliver, bulk) |
| `EncryptionService` | Thin Laravel Crypt wrapper for question content |
| `EnsureDefaultAdminService` | Seeds default admin on first boot |
| `ExamAccessService` | Gate checks for exam content decryption |
| `ExamSecurityService` | Violation enforcement, approve/reject workflow |
| `GradingService` | Auto-score exam attempt on submit |
| `InboxSyncService` | IMAP fetch via Webklex, deduplication |
| `NotificationService` | Creates UserNotification records |
| `QuestionImportService` | Parse txt/docx/pdf → questions |
| `SessionRecoveryService` | Disconnect/reconnect timer logic |

### `app/Jobs/` — 3 files
All implement `ShouldQueue`. Use `emails` queue, 3 retries, 15–30s backoff.

| Job | Trigger | Action |
|-----|---------|--------|
| `SendEmailJob` | `EmailService::send()` | Calls `deliver($log)` via SMTP |
| `SendPasswordChangedJob` | Profile password change | Sends `PasswordChangedMail` |
| `SendProfileOtpJob` | OTP request | Sends `ProfileOtpMail` with code |

### `app/Mail/` — 6 files
Laravel Mailable classes. Each references a blade view under `resources/views/emails/`.

| Mailable | View | Trigger |
|----------|------|---------|
| `AccountTerminatedMail` | emails.account-terminated | Admin terminates user |
| `CheatingDetectedMail` | emails.cheating-detected | 3rd violation recorded |
| `ExamPublishedMail` | emails.exam-published | Admin publishes exam |
| `ExamSubmittedMail` | emails.exam-submitted | Teacher submits for approval |
| `PasswordChangedMail` | emails.password-changed | Password changed |
| `ProfileOtpMail` | emails.profile-otp | OTP request |

### `app/Http/Middleware/` — 11 files

| Middleware | Alias | Purpose |
|-----------|-------|---------|
| `Authenticate` | `auth` | Standard + blocks `is_active=false` |
| `RedirectIfAuthenticated` | `guest` | Role-aware redirect on login |
| `RoleMiddleware` | `role` | Slug-based role gating |
| `EnsureExamActive` | `exam.active` | Blocks inactive attempt access |
| `EnsureSingleExamSession` | `exam.session` | Token-based concurrent session guard |
| `EncryptCookies` | — | Standard cookie encryption |
| `VerifyCsrfToken` | — | Standard CSRF |
| `TrustProxies` | — | Standard proxy trust |
| `TrustHosts` | — | Subdomain trust |
| `TrimStrings` | — | Trims all input except passwords |
| `PreventRequestsDuringMaintenance` | — | Standard maintenance mode |

### `app/Console/Commands/` — 3 files

| Command | Schedule | Purpose |
|---------|----------|---------|
| `email:process-scheduled` | Every minute | Process due scheduled_emails |
| `results:mark-absent` | Manual/Artisan | Create ABSENT results for no-show students |
| `email:stats` | Manual | Print email delivery stats table |

### `app/Enums/`
`RoleSlug.php` — PHP class constants: `ADMIN`, `TEACHER`, `STUDENT`. Used throughout for string-safe role comparisons.

### Missing Folders (not present)
- `app/Http/Requests/` — No FormRequest classes. Validation is inline in controllers.
- `app/Policies/` — No Policy classes. Authorization via `RoleMiddleware` only.
- `app/Events/` / `app/Listeners/` — No custom events. No observer pattern.
- `app/Providers/EventServiceProvider` — present but empty (no listeners registered).

---

---

## PART 3 — DATABASE DOCUMENTATION

### Complete Table List (32 migrations → ~35 active tables)

---

### `users`
**Purpose:** All system users (admin, teacher, student) unified in one table with role_id discriminator.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | varchar | |
| email | varchar | unique |
| password | varchar | bcrypt hashed |
| role_id | FK → roles | |
| is_active | boolean | default true; false = suspended |
| phone | varchar nullable | |
| profile_photo | varchar nullable | Path to WebP avatar |
| academic_year | varchar nullable | Legacy field (superseded by student_year_records) |
| exam_session_token | varchar nullable | Single-session enforcement token |
| last_login_at | timestamp nullable | |
| email_verified_at | timestamp nullable | |
| deleted_at | timestamp nullable | SoftDeletes |

---

### `roles`
**Purpose:** Simple role registry.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | varchar | display name |
| slug | varchar | `admin`, `teacher`, `student` |

---

### `courses`
**Purpose:** Academic courses taught by teachers and enrolled by students.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| title | varchar | |
| code | varchar | |
| description | text nullable | |
| teacher_id | FK → users | nullable |
| year_level_id | FK → year_levels | nullable |
| academic_year_id | FK → academic_years | nullable |
| major_id | FK → majors | nullable |
| semester | tinyint | 1 or 2 |
| deleted_at | timestamp | SoftDeletes |

---

### `enrollments`
**Purpose:** Many-to-many junction between students and courses, with academic context.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| student_id | FK → users | |
| course_id | FK → courses | |
| year | varchar nullable | Legacy academic year label |
| year_level_id | FK → year_levels | nullable |
| major_id | FK → majors | nullable |
| Unique | student_id + course_id | prevent double-enroll |

---

### `exams`
**Purpose:** Exam header — title, marks, status, teacher ownership.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| title | varchar | |
| course_id | FK → courses | |
| teacher_id | FK → users | |
| total_marks | int | |
| passing_marks | int | |
| duration_minutes | int | |
| status | enum | draft / pending_approval / approved / published / closed |
| instructions | text nullable | |
| deleted_at | timestamp | SoftDeletes |

---

### `exam_schedules`
**Purpose:** When an exam is available to students (one-to-one active schedule per exam).

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| exam_id | FK → exams | |
| starts_at | datetime | |
| ends_at | datetime | |
| duration_minutes | int | |
| target_year | varchar nullable | Year level label |
| is_published | boolean | |
| published_at | datetime nullable | |
| published_by | FK → users nullable | |

---

### `question_categories`
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | varchar | |
| slug | varchar | unique |
| description | text nullable | |

---

### `questions`
**Purpose:** Individual exam questions with encrypted content.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| exam_id | FK → exams | |
| category_id | FK → question_categories nullable | |
| type | enum | mcq / true_false / fill_blank / document |
| content_encrypted | text | Crypt::encryptString() |
| attachment_path | varchar nullable | |
| attachment_name | varchar nullable | |
| attachment_mime | varchar nullable | |
| difficulty | enum | easy / medium / hard nullable |
| marks | int | |
| order | int | |
| deleted_at | timestamp | SoftDeletes |

---

### `answers`
**Purpose:** Answer options for questions. MCQ has multiple rows; fill_blank has `is_blank_answer=true` rows as accepted answers.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| question_id | FK → questions | |
| content_encrypted | text | Crypt::encryptString() |
| is_correct | boolean | MCQ/TF correct flag |
| is_blank_answer | boolean | fill_blank accepted value flag |
| order | int | display order |

---

### `exam_attempts`
**Purpose:** One record per student per exam attempt. Tracks session state and security status.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| exam_id | FK → exams | |
| schedule_id | FK → exam_schedules nullable | |
| student_id | FK → users | |
| attempt_number | int | default 1 |
| status | enum | in_progress / submitted / terminated / suspicious / absent / terminated_pending_review / rejected |
| warning_count | int | 0–3 |
| started_at | datetime | |
| submitted_at | datetime nullable | |
| expires_at | datetime | started_at + duration |
| session_token | varchar | per-attempt token |
| terminated_at | datetime nullable | Phase 2 |
| approved_by | FK → users nullable | Phase 2 |
| approved_at | datetime nullable | Phase 2 |
| approval_comment | text nullable | Phase 2 |
| rejected_by | FK → users nullable | Phase 2 |
| rejected_at | datetime nullable | Phase 2 |
| rejection_comment | text nullable | Phase 2 |
| disconnected_at | datetime nullable | Phase 3 recovery |
| last_question_id | FK → questions nullable | Phase 3 recovery |
| question_order | JSON nullable | Phase 4 randomization |

---

### `student_answers`
**Purpose:** One row per question per attempt — records what the student selected/typed.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| attempt_id | FK → exam_attempts | |
| question_id | FK → questions | |
| answer_id | FK → answers nullable | MCQ/TF selection |
| answer_text | text nullable | fill_blank typed value |
| file_path | varchar nullable | unused |
| is_correct | boolean nullable | set by GradingService |
| marks_awarded | decimal nullable | set by GradingService |

---

### `results`
**Purpose:** Final score record for an attempt. Created/updated by GradingService on submission.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| attempt_id | FK → exam_attempts | unique |
| exam_id | FK → exams | |
| student_id | FK → users | |
| total_marks | decimal | |
| obtained_marks | decimal | |
| percentage | decimal | |
| is_passed | boolean | |
| is_published | boolean | |
| exam_result_status | enum | passed / failed / absent / disqualified |
| attendance_status | enum | attended / absent |
| violation_reason | text nullable | |
| disqualified_at | datetime nullable | |
| exam_finished_at | datetime nullable | |

---

### `cheating_logs`
**Purpose:** Records every violation event per attempt with browser fingerprint data.

| Column | Type | Notes |
|--------|------|-------|
| attempt_id | FK → exam_attempts | |
| student_id | FK → users | |
| violation_type | varchar | tab_switch / copy_paste / dev_tools / etc. |
| details | text nullable | |
| warning_number | int | 1 / 2 / 3 |
| user_agent | text nullable | |
| browser | varchar nullable | |
| device | varchar nullable | |
| os | varchar nullable | |
| screen_resolution | varchar nullable | |
| timezone | varchar nullable | |
| ip_address | varchar nullable | |

---

### Email Tables

**`email_templates`** — DB-stored HTML templates with `{{variable}}` placeholders.
Columns: id, name, slug (unique), subject, body_html, body_text, event, is_active, created_by.

**`email_logs`** — Audit log of every email send attempt.
Columns: id, to_email, to_name, from_email, from_name, subject, body_html, template_slug, event, email_type, status (queued/sent/failed), provider, error, user_id, campaign_id, cc_email, cc_name, queued_at, sent_at.

**`scheduled_emails`** — Future-dated bulk emails.
Columns: id, name, subject, body_html, recipients, send_at, is_sent, sent_at, template_slug, created_by.

**`inbox_emails`** — Synced inbound messages from IMAP.
Columns: id, from_email, from_name, sender_type (student/external), user_id nullable, subject, body_html, body_text, message_id (dedup key), in_reply_to, thread_id, status (unread/read/replied/archived), replied_by, replied_at, received_at.

---

### OTP Table

**`profile_otps`** — Password-change OTP codes.
Columns: id, user_id (FK), code (bcrypt hashed 6-digit), expires_at (5 min), used_at, attempt_count (max 5).

---

### Academic Structure Tables

**`academic_years`** — name, start_year, end_year, is_current.
**`year_levels`** — name (Year 1–5), level (int), ensureDefaults() seeds all 5.
**`majors`** — name, code (CST/CS/CT), description, ensureDefaults() seeds 3.
**`student_year_records`** — Per-academic-year enrollment snapshot: student_id, academic_year_id, year_level_id, department, major, semester, status (active/inactive/completed).

---

### ERD Relationships Summary

```
roles ←── users ──→ role_id
                  ──→ enrollments.student_id
                  ──→ exam_attempts.student_id
                  ──→ results.student_id
                  ──→ cheating_logs.student_id
                  ──→ courses.teacher_id

courses ──→ enrollments.course_id
        ──→ exams.course_id
        ──→ year_levels (year_level_id)
        ──→ academic_years (academic_year_id)
        ──→ majors (major_id)

exams ──→ exam_schedules (exam_id)
      ──→ questions (exam_id)
      ──→ exam_attempts (exam_id)
      ──→ results (exam_id)

questions ──→ answers (question_id)
          ──→ student_answers (question_id)

exam_attempts ──→ student_answers (attempt_id)
              ──→ results (attempt_id) [unique]
              ──→ cheating_logs (attempt_id)
              ──→ session_recovery_logs (attempt_id)

email_logs ──→ users (user_id, nullable)
           ──→ email_campaigns (campaign_id, nullable)
```

---

---

## PART 4 — MODEL ANALYSIS

### `User`
**Table:** `users` | **Traits:** SoftDeletes, HasApiTokens, Notifiable
**Purpose:** Unified user record for all three roles. Role discriminated by `role_id`.
**Key Methods:**
- `isAdmin()`, `isTeacher()`, `isStudent()` — role helpers
- `profilePhotoUrl()` — returns storage URL or null
- `role()` — BelongsTo Role
- `enrollments()` — HasMany Enrollment
- `attempts()` — HasMany ExamAttempt
- `notifications()` — HasMany UserNotification

---

### `Exam`
**Table:** `exams` | **Traits:** SoftDeletes
**Purpose:** Exam header and lifecycle state machine.
**Status flow:** `draft → pending_approval → approved → published → closed`
**Key Relations:**
- `course()` BelongsTo, `teacher()` BelongsTo
- `questions()` HasMany, `attempts()` HasMany, `results()` HasMany
- `activeSchedule()` HasOne (is_published=true), `latestSchedule()` HasOne, `anySchedule()` HasOne
- `getStudentScheduleAttribute()` — returns the schedule relevant to the authenticated student's year level

---

### `Question`
**Table:** `questions` | **Traits:** SoftDeletes
**Purpose:** Stores encrypted question content. Type determines answer behaviour.
**Types:** `mcq`, `true_false`, `fill_blank`, `document`
**Key Methods:**
- `getDecryptedContentAttribute()` — decrypts via EncryptionService
- `isFillBlank()` — type check helper
- `attachmentUrl()` — Storage::disk('public') URL
- `answers()` HasMany (ordered by `order`)

---

### `Answer`
**Table:** `answers`
**Purpose:** Answer options. MCQ/TF use `is_correct`. Fill-blank uses `is_blank_answer` to mark accepted values.
**Key Accessor:** `getDecryptedContentAttribute()` — decrypts answer text
**Note:** Multiple rows per question for MCQ. For fill_blank, `is_blank_answer=true` rows are the accepted correct values.

---

### `ExamAttempt`
**Table:** `exam_attempts`
**Purpose:** Session record for one student's exam sitting. Central state machine for the exam-taking subsystem.
**Key Methods:**
- `isActive()` — status === 'in_progress'
- `isFinished()` — any terminal status
- `isTerminatedPendingReview()` — Phase 2 security hold
- `canAutoRecover()` — disconnect within 10-min window, exam not expired
- `approver()`, `rejector()` — BelongsTo User (Phase 2)
- `question_order` cast to array — randomized question ID sequence (Phase 4)

---

### `StudentAnswer`
**Table:** `student_answers`
**Purpose:** Records the student's answer for each question in an attempt.
- `answer_id` — set for MCQ/TF (FK to answers)
- `answer_text` — set for fill_blank
- `is_correct`, `marks_awarded` — written by GradingService post-submit

---

### `Result`
**Table:** `results`
**Purpose:** Final scored record for an attempt.
**Status Constants:** `STATUS_PASSED`, `STATUS_FAILED`, `STATUS_ABSENT`, `STATUS_DISQUALIFIED`
**Attendance Constants:** `ATTENDANCE_ATTENDED`, `ATTENDANCE_ABSENT`
**Key Methods:** `statusLabel()`, `statusBadgeClass()` — UI helpers

---

### `CheatingLog`
**Table:** `cheating_logs`
**Purpose:** Immutable audit record of every security violation event.
Contains device fingerprint: browser, OS, screen resolution, timezone, IP.

---

### `EmailTemplate`
**Table:** `email_templates`
**Purpose:** DB-stored reusable email templates with `{{variable}}` placeholders.
**Key Methods:**
- `render(array $vars)` — substitutes all `{{key}}` tokens in subject + body_html + body_text. HTML values are `e()` escaped.
- `findBySlug(string $slug)` — static finder, only returns active templates.

---

### `EmailLog`
**Table:** `email_logs`
**Purpose:** Audit trail for every outbound email. Created before dispatch, updated on delivery.
**Key Methods:** `markSent()`, `markFailed($error)` — update status + timestamps.

---

### `InboxEmail`
**Table:** `inbox_emails`
**Purpose:** Inbound emails fetched from IMAP.
**Status flow:** `unread → read → replied | archived`
**Key Accessors:** `displayName()` — from_name or from_email fallback.
**Relations:** `replier()` BelongsTo User, `user()` BelongsTo User (linked sender if in system).

---

### `ProfileOtp`
**Table:** `profile_otps`
**Purpose:** One-time codes for password-change verification.
**Key Methods:**
- `generate(User $user)` — creates 6-digit code, bcrypt-hashes it, 5-min expiry
- `latestForUser(int $userId)` — finds most recent unexpired, unused OTP
- `checkCode(string $plain)` — bcrypt_check, increments attempt_count (max 5)
- `isValid()` — not expired, not used, attempt_count < 5

---

### `AcademicYear`
**Purpose:** Academic year registry. `is_current` flag marks the active year.
**Key Method:** `current()` — static finder for current year.

---

### `Major`
**Purpose:** Academic major (CST, CS, CT). CS and CT students can enroll in CST courses.
**Key Methods:** `ensureDefaults()`, `resolveIdFromLabel()`, `codeFromLabel()`

---

### `UserNotification`
**Table:** `user_notifications`
**Purpose:** In-app notification system. Categorized by `CATEGORY_MAP`.
**Key Methods:** `categoryFor(string $event)`, `markCategoryRead()`, `unreadCountsByCategory()`, `scopeForCategory()`

---

---

## PART 5 — CONTROLLER FLOW

### Auth Controllers

#### `AuthController`
| Method | Route | Description |
|--------|-------|-------------|
| `showLogin()` | GET /login | Display login form |
| `login()` | POST /login | Validate, check is_active, authenticate, update last_login_at, set exam_session_token |
| `showRegister()` | GET /register | Display student-only registration form |
| `register()` | POST /register | Create student account, assign student role |
| `logout()` | POST /logout | Invalidate session, clear exam_session_token |

Flow: `POST /login → AuthController::login() → Auth::attempt() → exam_session_token update → redirect by role`

#### `ForgotPasswordController`
| Method | Route | Description |
|--------|-------|-------------|
| `showEmailForm()` | GET /forgot-password | Email input form |
| `sendOtp()` | POST /forgot-password/send | Find user, `ProfileOtp::generate()`, `SendProfileOtpJob::dispatch()` |
| `showVerifyForm()` | GET /forgot-password/verify | OTP entry form |
| `checkOtp()` | POST /forgot-password/check-otp | `ProfileOtp::checkCode()`, set fp_otp_verified session |
| `resetPassword()` | POST /forgot-password/verify | Require fp_otp_verified, Hash::make(), `SendPasswordChangedJob::dispatch()` |
| `resendOtp()` | POST /forgot-password/resend | 60-second cooldown, regenerate |

---

### Admin Controllers

#### `Admin/ExamController`
```
GET  /admin/exams              → index()    → view with paginated+filtered exams
GET  /admin/exams/{exam}       → show()     → exam detail view
GET  /admin/exams/{exam}/results → results() → per-exam results with stats
POST /admin/exams/{exam}/approve  → approve() → status: approved
POST /admin/exams/{exam}/schedule → schedule() → create ExamSchedule (once only)
PUT  /admin/exams/{exam}/schedule/{schedule} → updateSchedule() → blocked after creation
POST /admin/exams/{exam}/publish  → publish() → status: published, notify+email students
POST /admin/exams/{exam}/close    → close()   → status: closed
POST /admin/exams/{exam}/open     → open()    → status: published (re-open)
```
**Services used:** `NotificationService`, `EmailService`, `ActivityLogService`

#### `Admin/StudentController`
```
index()   → paginated list with search/year/major filters
create()  → form with year_levels, majors, academic_years, courses
store()   → create User (student role) + StudentYearRecord + Enrollment sync
show()    → student detail with results, attempts, records
edit()    → edit form
update()  → update user + record + enrollment sync (CourseAssignmentService)
destroy() → SoftDelete user
```

#### `Admin/EmailController`
```
GET  /admin/email/compose         → compose()       → template list + group labels
POST /admin/email/compose         → sendCompose()   → single(EmailService::send) or group(per-recipient render)
POST /admin/email/compose/custom  → sendCustom()    → render manual-message blade → EmailService::send()
POST /admin/email/compose/preview → composePreview()→ JSON {subject, body_html, recipient_info}
GET  /admin/email/inbox           → inbox()         → paginated InboxEmail
GET  /admin/email/inbox/{email}   → showInbox()     → mark read + show
POST /admin/email/inbox/sync      → syncInbox()     → InboxSyncService::sync()
POST /admin/email/inbox/{email}/reply   → replyInbox()    → EmailService::send()
POST /admin/email/inbox/{email}/read    → markInboxRead()
DELETE /admin/email/inbox/{email}       → archiveInbox()
GET  /admin/email/logs            → logs()          → paginated EmailLog
GET  /admin/email/logs/{log}      → showLog()
POST /admin/email/logs/{log}/retry → retryLog()     → EmailService::retry()
```

---

### Teacher Controllers

#### `Teacher/ExamController`
```
index()              → teacher's own exams with search
create()             → exam creation form
store()              → create Exam (draft) + redirect to show
show()               → exam detail with questions, schedule status
addQuestion()        → encrypt content → create Question + Answers
editQuestion()       → decrypt + populate edit form
updateQuestion()     → re-encrypt + update
deleteQuestion()     → SoftDelete Question
submitForApproval()  → validate ≥1 question, all questions have ≥1 answer with is_correct,
                        status→pending_approval, ExamSubmittedMail to admin
results()            → paginated student results for teacher's exam
importQuestions()    → QuestionImportService::importFromFile()
```

---

### Student Controllers

#### `Student/ExamController`
```
index()  → published exams for student's course, attempt map, terminated alert
show()   → exam detail with schedule, instructions
start()  → validate: published, active schedule, not already attempted,
           create ExamAttempt (status=in_progress, question_order=shuffle),
           set session_token, redirect to take
```

#### `Student/ExamSessionController`
```
take()        → recovery path (canAutoRecover) or normal path
              → renderExamView() builds security policy for JS
saveAnswer()  → upsert StudentAnswer (answer_id or answer_text)
violation()   → ExamSecurityService::recordViolation()
submit()      → check not expired, mark submitted,
               GradingService::gradeAttempt(), send ExamSubmittedMail (teacher)
disconnect()  → SessionRecoveryService::recordDisconnect()
```

**Flow:**
```
POST /student/exams/{exam}/start
    → ExamAttempt created (question_order JSON shuffled)
    → redirect GET /student/attempt/{attempt}/take
    → student answers rendered in randomized order
    → POST /student/attempt/{attempt}/save (per answer, debounced)
    → POST /student/attempt/{attempt}/violation (on tab-switch etc.)
    → POST /student/attempt/{attempt}/submit
        → GradingService::gradeAttempt()
        → Result created/updated
        → ExamSubmittedMail dispatched
```

---

---

## PART 6 — SERVICE LAYER

### `ExamSecurityService`
**Purpose:** Central anti-cheat enforcement. Manages the 3-strike violation lifecycle.
**`MAX_VIOLATIONS = 3`**

| Method | Description |
|--------|-------------|
| `recordViolation($attempt, $type, $details, $metadata)` | Dispatches to `recordWarning()` (violations 1–2) or `recordViolationThree()` (violation 3) |
| `recordWarning()` | Increments `warning_count`, saves `CheatingLog`, sends warning notification + email |
| `recordViolationThree()` | Sets status=`terminated_pending_review`, saves log, sends admin+teacher alerts via `CheatingDetectedMail` |
| `approve($attempt, $adminUser, $comment)` | DB::lockForUpdate(), sets status back to `in_progress`, resets warning_count, notifies student |
| `reject($attempt, $adminUser, $comment)` | Sets status=`rejected`, creates DISQUALIFIED Result via GradingService path |
| `persistViolationLog()` | Writes CheatingLog with full browser fingerprint |
| `getRecipients()` | Returns warning recipient (student) |
| `getTerminationRecipients()` | Returns [admin, teacher] for 3rd violation alert |
| `sendSecurityEmail()` | Dispatches CheatingDetectedMail |
| `sendSecurityNotification()` | Calls NotificationService |

**Called from:** `Student/ExamSessionController::violation()`

---

### `GradingService`
**Purpose:** Calculates scores for a completed attempt. Called once on submit.

**Algorithm:**
1. Guard: if existing Result is DISQUALIFIED, return unchanged.
2. `totalMarks` = sum of ALL questions in exam (not just answered ones).
3. For each `StudentAnswer`:
   - MCQ/TF: `is_correct = answer.is_correct`
   - fill_blank: `trim(studentText)` exact match against `trim(acceptedAnswer)` — **case-sensitive**
4. `percentage = (obtainedMarks / totalMarks) × 100`
5. `isPassed = obtainedMarks >= exam.passing_marks`
6. `updateOrCreate` Result with status PASSED/FAILED, `attendance_status=attended`

**Called from:** `Student/ExamSessionController::submit()`

---

### `EmailService`
**Purpose:** Complete outbound email pipeline. Does NOT handle IMAP.

| Method | Description |
|--------|-------------|
| `send()` | Creates EmailLog (status=queued), dispatches SendEmailJob |
| `sendTemplate()` | Loads EmailTemplate by slug, calls `render()`, calls `send()` |
| `sendBulk()` | Resolves recipient group, personalizes per-user via `resolveUserVars()` |
| `sendWelcomeEmail()` | Template slug `welcome`, silent-fail if missing |
| `deliver($log)` | Called by SendEmailJob — actual `Mail::send()` via SMTP |
| `retry($log)` | Resets status to queued, re-dispatches SendEmailJob |
| `processScheduled()` | Processes due ScheduledEmail rows |
| `resolveUserVars($user)` | Builds variable map: student_name, app_name, course_name, year_level etc. |
| `substituteVars($text, $vars)` | `str_replace('{{key}}', value)` loop |
| `applySmtpConfig($settings)` | Runtime-only config override from admin UI |
| `resolveRecipients($group)` | Returns User collection by group label |

**Called from:** Many controllers, ExamSecurityService, Console commands.

---

### `InboxSyncService`
**Purpose:** Fetches new emails from Gmail via IMAP and persists to `inbox_emails`.

**Key Design Decisions:**
- `setFetchBody(false)` + `setFetchFlags(false)` on listing = envelope-only (fast)
- `limit(FETCH_LIMIT)` = server-side cap (default 20, env: `IMAP_SYNC_LIMIT`)
- `setFetchOrder('desc')` = newest first
- Dedup check on `message_id` BEFORE calling `parseBody()` (saves bandwidth)
- `parseBody()` called only for new messages
- Fallback dedup key: `md5(from_email|subject|date)` for messages without Message-ID

**Called from:** `Admin/EmailController::syncInbox()` (POST button)

---

### `SessionRecoveryService`
**Purpose:** Handles exam session disconnects while keeping the attempt `in_progress`.

| Method | Description |
|--------|-------------|
| `recordDisconnect($attempt)` | Sets `disconnected_at = now()` |
| `handleReconnect($attempt)` | Creates `SessionRecoveryLog`, clears `disconnected_at` |
| `computeNormalSeconds()` | Wall-clock time minus frozen (disconnect) time |
| `computeFrozenSeconds()` | Total time spent disconnected |
| `finalizeExpiredSession()` | Marks attempt expired if reconnect window passed |

**Recovery window:** `EXAM_RECOVERY_TIME_LIMIT` env var (default 600s / 10 min).

---

### `QuestionImportService`
**Purpose:** Parses Moodle-style plain-text or DOCX/PDF files into Question+Answer records.

**Supported formats:** `.txt`, `.docx`, `.pdf`, `.doc`
**Parsing:** Regex-based — detects question blocks, answer options (A/B/C/D), correct answer markers, fill-blank syntax.
**Storage:** Calls `EncryptionService::encrypt()` for each question/answer before DB insert.

---

### `CheatingDetectionService` (Legacy)
**Status:** Deprecated. `recordViolation()` and `terminateExam()` methods present but `ExamSessionController` now delegates entirely to `ExamSecurityService`. Retained only because `Admin/CheatingLogController` still reads `cheating_logs` table.

---

---

## PART 7 — JOB AND QUEUE SYSTEM

### Queue Configuration
- **Driver:** Database (jobs table, created by migration 2026_06_11_000002)
- **Queue name:** `emails` (all three jobs use this queue)
- **Worker command:** `php artisan queue:work --queue=emails`
- **Scheduler:** `email:process-scheduled` runs every minute via `app/Console/Kernel.php`

---

### `SendEmailJob`
| Property | Value |
|----------|-------|
| Queue | `emails` |
| Tries | 3 |
| Backoff | 30 seconds |
| Constructor | `int $logId` |

**`handle()`:**
1. Load `EmailLog` by `$logId`
2. If not found or already sent → return early (idempotent)
3. Call `EmailService::deliver($log)`
4. `deliver()` calls `Mail::send()` with raw HTML body

**`failed()`:** Calls `$log->markFailed($exception->getMessage())`

**Trigger:** `EmailService::send()` when `$queue=true` (default)

---

### `SendPasswordChangedJob`
| Property | Value |
|----------|-------|
| Queue | `emails` |
| Tries | 3 |
| Backoff | 15 seconds |
| Constructor | `User $user` |

**`handle()`:** `Mail::to($user)->send(new PasswordChangedMail($user))`

**Trigger:** `ProfileController::changePassword()` on successful password update.

---

### `SendProfileOtpJob`
| Property | Value |
|----------|-------|
| Queue | `emails` |
| Tries | 3 |
| Backoff | 15 seconds |
| Constructor | `User $user`, `string $plainCode` |

**`handle()`:** `Mail::to($user)->send(new ProfileOtpMail($user, $plainCode))`

**Note:** Passes the **plaintext** code to the mail class (hashed version stored in DB; plain used only for display in email).

**Trigger:** `ForgotPasswordController::sendOtp()` and `resendOtp()`

---

### Scheduled Email Processing
`ProcessScheduledEmails` artisan command → `EmailService::processScheduled()`:
1. Query `scheduled_emails` where `is_sent=false` AND `send_at <= now()`
2. For each: call `sendBulk()` with recipient group
3. Mark `is_sent=true`, `sent_at=now()`

---

## PART 8 — EXAM SYSTEM COMPLETE FLOW

### Phase 1: Exam Creation (Teacher)

```
Teacher: POST /teacher/exams
    → Exam created (status: draft)
    → course_id, teacher_id, total_marks, passing_marks, duration_minutes

Teacher: POST /teacher/exams/{exam}/questions
    → Question content → EncryptionService::encrypt() → stored as content_encrypted
    → For MCQ: N Answer rows (one with is_correct=true)
    → For fill_blank: N Answer rows with is_blank_answer=true (accepted values)

Teacher: POST /teacher/exams/{exam}/submit
    → Validate: ≥1 question, each MCQ/TF has ≥1 correct answer
    → status → pending_approval
    → ExamSubmittedMail dispatched to admin
```

### Phase 2: Approval & Scheduling (Admin)

```
Admin: POST /admin/exams/{exam}/approve
    → status → approved

Admin: POST /admin/exams/{exam}/schedule
    → ExamSchedule created (starts_at, ends_at, duration_minutes, target_year)
    → One-time only — updateSchedule() blocked

Admin: POST /admin/exams/{exam}/publish
    → status → published
    → ExamSchedule.is_published = true
    → For each enrolled student: UserNotification + EmailService::sendTemplate('exam_published')
    → Teacher: UserNotification
```

### Phase 3: Student Exam Taking

```
Student: GET /student/exams
    → Published exams for student's enrolled courses
    → Checks for existing terminated_pending_review attempts (security alert banner)

Student: POST /student/exams/{exam}/start
    → Validate: exam published, schedule active, no existing in_progress attempt
    → Create ExamAttempt:
        status = in_progress
        started_at = now()
        expires_at = now() + duration_minutes
        question_order = shuffle(question IDs)  ← Phase 4 randomization
        session_token = unique token
    → Redirect to take page

Student: GET /student/attempt/{attempt}/take [middleware: exam.active]
    → EnsureExamActive checks attempt.isActive()
    → If disconnected + canAutoRecover() → SessionRecoveryService::handleReconnect()
    → Build securityPolicy array for JS anti-cheat initialization
    → Decrypt questions/answers via ExamAccessService
    → Render in question_order sequence
```

### Phase 4: Answer Auto-Save

```
Student: POST /student/attempt/{attempt}/save [AJAX, every answer change]
    → Upsert StudentAnswer (attempt_id + question_id unique)
    → For MCQ/TF: set answer_id, null answer_text
    → For fill_blank: set answer_text, null answer_id
    → Returns JSON {success: true}
```

### Phase 5: Violation Handling

```
JS detects: tab-switch / copy-paste / devtools / focus-loss
    → POST /student/attempt/{attempt}/violation
    → ExamSecurityService::recordViolation()
        Violation 1 or 2:
            → warning_count++
            → CheatingLog created
            → Student notification + email (warning)
            → JSON {action: 'warn', warning_count: N}
        Violation 3:
            → status → terminated_pending_review
            → CheatingLog created
            → Admin + Teacher: CheatingDetectedMail
            → JSON {action: 'terminate'}
            → JS redirects student to dashboard
```

### Phase 6: Submission & Grading

```
Student: POST /student/attempt/{attempt}/submit
    → Validate: attempt not expired
    → attempt.status → submitted, submitted_at = now()
    → GradingService::gradeAttempt():
        1. Skip if DISQUALIFIED result exists
        2. totalMarks = exam.questions.sum('marks')
        3. For each StudentAnswer:
           MCQ/TF: marks_awarded = answer.is_correct ? question.marks : 0
           fill_blank: marks_awarded = trim(answer_text) in acceptedAnswers ? marks : 0
        4. percentage = (obtainedMarks / totalMarks) × 100
        5. isPassed = obtainedMarks >= exam.passing_marks
        6. Result::updateOrCreate → exam_result_status = PASSED|FAILED
    → ExamSubmittedMail → teacher (notification of new submission)
    → Redirect to results page
```

### Attempt Rules
- One active attempt per student per exam (enforced in `start()`)
- Attempt expires at `expires_at` — checked in `EnsureExamActive`
- `exam.session` middleware prevents concurrent browser sessions
- Recovery window: 10 minutes from disconnect (configurable)
- Warning count persists across reconnects

### Session Recovery (Phase 3)
```
Student browser crashes/closes:
    → POST /student/attempt/{attempt}/disconnect
    → SessionRecoveryService::recordDisconnect() → disconnected_at = now()
    → Status stays in_progress

Student reopens exam URL (within 10 min, exam not expired):
    → EnsureExamActive passes (status still in_progress)
    → take() detects canAutoRecover() = true
    → SessionRecoveryService::handleReconnect()
    → SessionRecoveryLog created
    → disconnected_at cleared
    → Exam resumes exactly where student left off
```

---

---

## PART 9 — ANTI-CHEAT SYSTEM

### Frontend Detection (JS)

The exam-taking view (`student/exam/take.blade.php`) loads an inline JS anti-cheat system initialized with a `securityPolicy` object built server-side in `renderExamView()`.

**Detected violation types:**
| Type | Detection Method |
|------|-----------------|
| `tab_switch` | `document.visibilitychange` event |
| `window_blur` | `window.blur` event |
| `copy_attempt` | `copy`, `cut` events on document |
| `paste_attempt` | `paste` event on document |
| `right_click` | `contextmenu` event |
| `dev_tools` | Window size differential heuristic |
| `focus_loss` | `window.focus`/`blur` cycling |
| `keyboard_shortcut` | Blocked: F12, Ctrl+Shift+I/J/C, Ctrl+U/S/P/A |

**JS Behavior on Violation:**
1. Sends `POST /student/attempt/{attempt}/violation` with `{type, details, metadata{browser, os, screenResolution, timezone}}`
2. On response `action: 'warn'` → overlay warning shown, count displayed
3. On response `action: 'terminate'` → full-screen overlay, 3s countdown, redirect to dashboard

**Additional Frontend Protections:**
- Text selection disabled via CSS
- Right-click context menu suppressed
- Copy/paste events cancelled
- F5/Ctrl+R refresh warned
- `beforeunload` event triggers disconnect POST

---

### Backend Enforcement

**`ExamSecurityService::recordViolation()`**

```
Violation arrives with: attempt_id, type, details, metadata
    ↓
DB::lockForUpdate() on attempt (prevent race conditions)
    ↓
attempt.warning_count < MAX_VIOLATIONS (3)?
    ├── YES (warning 1 or 2):
    │       → persistViolationLog() → CheatingLog record with full fingerprint
    │       → attempt.warning_count++
    │       → attempt.save()
    │       → sendSecurityNotification() → student warning
    │       → sendSecurityEmail() → (optional, for early warnings)
    │       → return {action: 'warn', warning_count: N}
    │
    └── NO (violation 3):
            → persistViolationLog()
            → attempt.status = 'terminated_pending_review'
            → attempt.terminated_at = now()
            → attempt.save()
            → sendSecurityEmail() → CheatingDetectedMail to [admin, teacher]
            → sendSecurityNotification() to [admin, teacher]
            → return {action: 'terminate'}
```

**Admin Review Workflow (Phase 2):**
```
Admin sees terminated_pending_review attempt in exam results
    ↓
POST /admin/exams/{exam}/approve OR reject (via modal in results view)
    ↓
ExamSecurityService::approve():
    → DB::lockForUpdate()
    → status → in_progress
    → warning_count → 0
    → approved_by, approved_at, approval_comment saved
    → StudentNotification: "Your exam has been restored"
    → Student can re-enter exam
    
ExamSecurityService::reject():
    → status → rejected
    → rejected_by, rejected_at, rejection_comment saved
    → GradingService marks result DISQUALIFIED
    → Student notification: "Exam rejected"
```

**Database Fingerprint Stored Per Violation:**
```
browser, os, device, screen_resolution, timezone, ip_address,
user_agent, violation_type, details, warning_number
```

---

## PART 10 — EMAIL SYSTEM ARCHITECTURE

### Outbound Email System

```
EmailService::send()
    ↓
EmailLog::create() [status=queued]
    ↓
SendEmailJob::dispatch($log->id)
    ↓ (queue worker picks up)
SendEmailJob::handle()
    ↓
EmailService::deliver($log)
    ↓
Mail::send() [Laravel Mailer via SMTP]
    ↓
EmailLog::markSent() OR markFailed()
```

**SMTP Configuration:**
- Stored in `.env` (MAIL_HOST, MAIL_PORT, MAIL_USERNAME etc.)
- Admin can update via `/admin/email/smtp` — writes directly to `.env` file, calls `config:clear`
- Runtime override: `applySmtpConfig()` affects current request only

**Template System:**
- `email_templates` table stores slug + HTML body with `{{variable}}` placeholders
- `EmailTemplate::render($vars)` substitutes variables. HTML values are `e()` escaped.
- System auto-vars (app_name, student_name etc.) resolved by `resolveUserVars()`
- Admin can create/edit/preview templates at `/admin/email/templates`
- 4 seeded templates: `welcome`, `exam_submitted`, `exam_published`, `account_terminated`

**Compose Flow (Admin):**
```
Template-based (single/group):
    Step 1: Select template + enter email/group
    Step 2: Fill manual variables ({{exam_name}} etc.)
    Step 3: Preview (AJAX → composePreview()) → Send

Custom Message (single recipient only):
    Enter: to_email + subject + body (plain text)
    → manual-message.blade.php wraps in branded HTML
    → EmailService::send() [email_type = 'manual']
```

**Bulk Email:**
```
Admin selects: recipient_group + subject + body (or template)
    → EmailService::sendBulk()
    → resolveRecipients() → User collection
    → Per user: resolveUserVars() + substituteVars()
    → EmailService::send() for each → queued individually
```

---

### Inbox Communication System (IMAP)

```
Gmail IMAP Server (imap.gmail.com:993 SSL)
    ↓
InboxSyncService::sync()  [triggered by admin clicking "Sync Inbox"]
    ↓
Webklex Client::account('default')::connect()
    ↓
getFolderByName('INBOX')
    ↓
messages()->all()
    .setFetchBody(false)     ← envelope only
    .setFetchFlags(false)
    .setFetchOrder('desc')   ← newest first
    .limit(20)               ← configurable via IMAP_SYNC_LIMIT
    .get()
    ↓
For each message:
    extractMessageId() → dedup check → already in inbox_emails? SKIP
    parseBody()             ← fetch full body only for new messages
    InboxEmail::create()    ← persist with status=unread
    ↓
Admin sees emails at /admin/email/inbox
```

**Inbox Actions:**
- View: marks `unread → read`
- Reply: `EmailService::send()` to `from_email`, marks `replied`
- Archive: marks `archived` (no delete)
- Sync: POST `/admin/email/inbox/sync`

**FT_PEEK:** Configured in `config/imap.php` — fetching never marks messages as read on the mail server.

---

---

## PART 11 — FRONTEND ANALYSIS

### Blade Template Structure

**Layout:** Single master layout `resources/views/layouts/app.blade.php`
- Bootstrap 5 (CDN)
- Bootstrap Icons (CDN)
- Custom CSS variables (`--blc-royal: #2d27a0`)
- `@stack('styles')` / `@stack('scripts')` for per-page assets
- Notification bell with AJAX unread count polling
- Three sidebar partials: `admin-sidebar`, `teacher-sidebar`, `student-sidebar`

**Key View Groups:**

| Group | Views | Notes |
|-------|-------|-------|
| `admin/email/` | 10+ | Full email management UI |
| `admin/exams/` | 3 | List, show, results |
| `admin/students/` | 4 | Full CRUD |
| `teacher/exams/` | 6 | Create, show, results, analytics, import |
| `student/exam/` | 1 | `take.blade.php` — most complex view |
| `emails/` | 7 | Outbound HTML email templates |

### JavaScript Files

**`resources/js/app.js`** — Single line: `require('./bootstrap')`. No custom JS bundled via Webpack/Vite.

**`resources/js/bootstrap.js`** — Axios with CSRF header. Lodash imported. Echo/Pusher commented out (not implemented).

**All custom JavaScript is inline in Blade files** — not extracted to separate files.

### Key Inline JavaScript Modules

**`student/exam/take.blade.php`** — The anti-cheat engine:
- Event listeners for tab-switch, copy, paste, right-click, keyboard shortcuts
- Auto-save debouncer (500ms) — `fetch()` POST per answer change
- Timer countdown from `expires_at`
- Violation reporter — `fetch()` POST to violation endpoint
- Disconnect handler — `beforeunload` → `fetch()` POST to disconnect endpoint
- Full-screen warning overlay on termination

**`admin/email/compose.blade.php`** — 3-step compose wizard:
- Template data embedded as `<script type="application/json">`
- Step navigation state machine (showStep 1/2/3)
- Template variable chip display
- AJAX preview via `fetch()` POST to `compose/preview`
- Custom-path submit (no template) → dynamic form submit to `compose/custom`

**`admin/exams/results.blade.php`** — Approve/reject modals:
- Bootstrap modal for security incident review
- POST forms for approve/reject with comment

**`teacher/exams/analytics.blade.php`** — Chart.js (CDN) charts for exam performance analytics.

### AJAX Communication Pattern

All AJAX uses `fetch()` API (no jQuery). Pattern:
```javascript
fetch(url, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json'
    },
    body: JSON.stringify(payload)
})
```

CSRF token read from `<meta name="csrf-token">` in layout head.

---

## PART 12 — ROUTE MAP

### Guest Routes
| Method | URL | Controller | Method | Purpose |
|--------|-----|-----------|--------|---------|
| GET | / | closure | — | Welcome page |
| GET | /login | AuthController | showLogin | Login form |
| POST | /login | AuthController | login | Authenticate |
| GET | /register | AuthController | showRegister | Register form |
| POST | /register | AuthController | register | Create student |
| GET | /forgot-password | ForgotPasswordController | showEmailForm | OTP step 1 |
| POST | /forgot-password/send | ForgotPasswordController | sendOtp | Send code |
| GET | /forgot-password/verify | ForgotPasswordController | showVerifyForm | Enter code |
| POST | /forgot-password/check-otp | ForgotPasswordController | checkOtp | Verify code |
| POST | /forgot-password/verify | ForgotPasswordController | resetPassword | Set password |
| POST | /forgot-password/resend | ForgotPasswordController | resendOtp | Resend code |

### Shared Auth Routes (`auth` + `exam.session`)
| Method | URL | Purpose |
|--------|-----|---------|
| POST | /logout | Logout |
| GET | /profile | Profile view |
| POST | /profile/photo | Update avatar |
| POST | /profile/password | Change password |
| GET | /notifications | Notification list |
| POST | /notifications/{n}/read | Mark read |
| POST | /notifications/read-all | Mark all read |
| GET | /notifications/unread-count | AJAX count |
| GET | /notifications/unread-by-category | AJAX category counts |

### Admin Routes (`role:admin`)
| Method | URL | Key Action |
|--------|-----|-----------|
| GET | /admin/dashboard | Admin stats dashboard |
| CRUD | /admin/users | User management |
| POST | /admin/users/{user}/terminate | Suspend account |
| CRUD | /admin/courses | Course management |
| GET | /admin/courses-by-year-level | AJAX course filter |
| CRUD | /admin/majors | Major management |
| GET | /admin/enrollments | Enrollment list |
| POST | /admin/enrollments | Create enrollment |
| DELETE | /admin/enrollments/{e} | Remove enrollment |
| GET | /admin/exams | Exam list |
| POST | /admin/exams/{e}/approve | Approve exam |
| POST | /admin/exams/{e}/schedule | Schedule exam |
| POST | /admin/exams/{e}/publish | Publish + notify |
| POST | /admin/exams/{e}/close | Close exam |
| GET | /admin/email/inbox | Inbox list |
| POST | /admin/email/inbox/sync | IMAP sync |
| GET | /admin/email/compose | Compose form |
| POST | /admin/email/compose/custom | Send custom email |
| POST | /admin/email/compose/preview | AJAX preview |
| GET | /admin/email/logs | Email audit log |
| POST | /admin/email/logs/{log}/retry | Retry failed |
| CRUD | /admin/email/templates | Template management |
| GET | /admin/email/smtp | SMTP settings |
| CRUD | /admin/academic/years | Academic year management |
| CRUD | /admin/students | Student management |
| CRUD | /admin/teachers | Teacher management |
| GET | /admin/results | All results |
| GET | /admin/cheating-logs | Cheating log list |

### Teacher Routes (`role:teacher,admin`)
| Method | URL | Key Action |
|--------|-----|-----------|
| GET | /teacher/dashboard | Teacher stats |
| CRUD | /teacher/exams | Exam management |
| POST | /teacher/exams/{e}/submit | Submit for approval |
| POST | /teacher/exams/{e}/import | Import questions |
| GET | /teacher/results | Results for teacher's exams |

### Student Routes (`role:student`)
| Method | URL | Key Action |
|--------|-----|-----------|
| GET | /student/dashboard | Student stats |
| GET | /student/courses | Enrolled courses |
| GET | /student/exams | Available exams |
| POST | /student/exams/{e}/start | Begin attempt |
| GET | /student/attempt/{a}/take | Exam taking view |
| POST | /student/attempt/{a}/save | Auto-save answer |
| POST | /student/attempt/{a}/violation | Report violation |
| POST | /student/attempt/{a}/disconnect | Record disconnect |
| POST | /student/attempt/{a}/submit | Submit exam |
| GET | /student/results | My results |

---

---

## PART 13 — CODE QUALITY REVIEW

### ✅ Good Architecture Decisions

**1. Service Layer Extraction**
Business logic is properly separated from controllers. `ExamSecurityService`, `GradingService`, `SessionRecoveryService` are all testable in isolation. Constructor DI is used consistently.

**2. Question Encryption**
All question and answer content is encrypted at rest via `EncryptionService` (Laravel Crypt). Even DB admins cannot read exam content without the app key. Accessors make this transparent to consuming code.

**3. Dedup-First IMAP Sync**
`InboxSyncService` checks Message-ID deduplication before calling `parseBody()`, making syncs bandwidth-efficient and idempotent.

**4. DISQUALIFIED Guard in GradingService**
The guard at the top of `gradeAttempt()` prevents overwriting a security-team decision with auto-grading results. This is a correct protection.

**5. Case-Sensitive Fill-Blank Grading**
Intentional design: teachers can add both "A" and "a" as accepted answers independently. The comparison uses `trim()` + exact match, which is correct for an exam context.

**6. Single-Session Enforcement**
`EnsureSingleExamSession` middleware + `exam_session_token` on users table prevents students from taking an exam on two devices simultaneously.

**7. DB::lockForUpdate() in Security Service**
Race condition protection in `ExamSecurityService::recordViolation()` prevents double-termination when rapid violations fire concurrently.

**8. Artisan Command: mark-absent**
`results:mark-absent` is idempotent, supports `--dry-run`, and handles the absent case separately from the grading flow — clean separation of concerns.

**9. Phase-Tagged Code**
Comments explicitly label Phase 2/3/4 additions in `ExamAttempt`, making the incremental development history readable directly in code.

**10. EmailLog Before Dispatch**
Email log is created with `status=queued` BEFORE the job is dispatched. This means no silent failures — even if the queue worker never runs, the log exists.

---

### ⚠️ Technical Debt

**1. Inline Validation (No FormRequests)**
All validation is done inline in controllers with `$request->validate()`. For complex entities (Student, Enrollment, Exam), this makes controller methods very long (50–80 lines of validation logic). `app/Http/Requests/` folder does not exist.

**2. No Policy Classes**
Authorization uses `RoleMiddleware` at the route level only. There are no `Gate` or `Policy` checks inside controllers or models. This means any admin-role user can manipulate any resource regardless of ownership (e.g., teacher editing another teacher's exam if they have admin role).

**3. CheatingDetectionService is Dead Code**
The service exists, has methods, but is never injected anywhere in active code. `ExamSessionController` uses `ExamSecurityService` instead. Risk of confusion if future developers find it.

**4. .env File Written at Runtime**
`Admin/EmailController::smtpUpdate()` writes directly to the `.env` file using `file_get_contents/file_put_contents`. This is fragile on file-permission-restricted servers and not the recommended approach (use a config table + runtime override instead).

**5. All JS Inline in Blade**
No Vite/Webpack build pipeline for custom JS. The `app.js` file is essentially empty. All page logic is in `@push('scripts')` blocks inside blade files. This makes code reuse, linting, and testing impossible for JS.

**6. Profile Photo as Base64 in Request**
The profile photo is sent as a Base64-encoded string in the POST body. For large images this inflates request size significantly. A standard `multipart/form-data` file upload would be more efficient.

**7. EmailController is Monolithic**
`Admin/EmailController.php` handles 20+ methods across inbox, compose, templates, logs, SMTP, bulk, scheduled, and test. It should be split into at least 4 separate controllers.

**8. Hardcoded Major Business Logic**
The CS/CT → CST enrollment cross-referencing rule is encoded in `Admin/EnrollmentController::store()` as a special-case string check. This would break silently if major codes change.

**9. Missing Soft-Delete Cascade**
`Question` uses SoftDeletes but `Answer` does not. Soft-deleting a question leaves orphaned answer rows in the database (still joinable via Eloquent but logically orphaned).

---

### 🐛 Potential Bugs

**1. Timer Desync on Recovery**
`SessionRecoveryService` computes "frozen seconds" (time spent disconnected) but there is no evidence the exam `expires_at` is adjusted to add frozen time back. A student who disconnects for 5 minutes effectively loses 5 minutes of exam time.

**2. `varSummary` display uses `currentTmpl.all_vars`**
In the original compose JS, `varSummary.style.display` referenced `currentTmpl.all_vars.length` — but `all_vars` is not included in the JSON payload from the server (only `manual_vars` and `auto_vars` are). After the refactor this was corrected to `.concat()`, but worth verifying the fix is complete.

**3. Enrollment Unique Constraint**
The `enrollments` table has a unique constraint on `(student_id, course_id)`. The `store()` method catches DB exceptions, but if a constraint violation occurs for a reason other than duplicate enrollment, the error message may be misleading.

**4. ExamPublishedMail Double-Build**
`ExamPublishedMail::build()` attempts to load an `email_published` template for the subject, but the actual email body is rendered from `emails.exam-published` Blade view. These two systems are disconnected — the template subject is used but the DB template body is ignored.

**5. `question_order` shuffle at start()**
The question IDs are shuffled when the attempt is created. If a question is later soft-deleted before the student takes the exam, the shuffle array contains a deleted question ID. The take view should filter soft-deleted questions from the stored order.

---

### 🔒 Security Risks

**1. No Rate Limiting on Login**
`POST /login` has no rate limiting or throttle middleware. Brute-force attacks against the login endpoint are not mitigated.

**2. No Rate Limiting on OTP**
`POST /forgot-password/send` has a 60s client-side cooldown checked via session, but no server-side throttle. Multiple browser sessions could bypass this.

**3. SMTP Password in .env via Admin**
The SMTP settings admin page stores the password in `.env` in plaintext. If `.env` is accidentally exposed (misconfigured server), credentials are leaked.

**4. Email Body HTML Injection**
`EmailController::replyInbox()` wraps the reply body with `nl2br(e())` which is correct. However `sendCustom()` passes `body` to the blade as raw variable and uses `{!! nl2br(e($body)) !!}` inside the template — this is safe as long as the template is not modified to use `{!! $body !!}`.

**5. Admin Can Write Arbitrary HTML to Email Templates**
The template body_html field is a textarea with no HTML sanitization. An admin could inject scripts. This is an insider threat consideration only since only admins can edit templates.

---

### ⚡ Performance Concerns

**1. N+1 on Exam Results View**
`Admin/ExamController::results()` loads attempts with `with(['student', 'result'])` but the `buildResultStats()` helper then iterates all enrolled students separately. On large exams this could produce many queries.

**2. No Pagination on Question Import**
`QuestionImportService::importParsed()` inserts all questions in a loop without chunking. Importing 500 questions in one request could time out.

**3. IMAP Sync is Synchronous**
`syncInbox()` runs the full IMAP connection synchronously in a web request. Even with the `limit(20)` optimization, IMAP connections can be slow. This should be a queued job.

**4. No Caching**
No `Cache::remember()` usage anywhere. Dashboard statistics (`DashboardController`) re-queries counts on every page load. Academic structure (year levels, majors) never changes but is queried fresh on every enrollment form load.

**5. Email Log Grows Unbounded**
`email_logs` has no archival or pruning mechanism. High-volume installations will accumulate millions of rows over time.

---

---

## PART 14 — FINAL SUMMARY

### 1. Completed Features

| Feature | Notes |
|---------|-------|
| Role-based authentication | Admin / Teacher / Student with session enforcement |
| OTP password reset | 6-digit, 5-min expiry, bcrypt-hashed, queued delivery |
| Profile photo upload | Base64 WebP, stored on public disk |
| Academic structure | AcademicYear → YearLevel → Major → Course hierarchy |
| Course management | Year/semester/major scoped, teacher assignment |
| Enrollment management | With CS/CT → CST cross-enrollment rule |
| Exam lifecycle | draft → pending_approval → approved → published → closed |
| Question types | MCQ, True/False, Fill-in-the-Blank |
| Question encryption | All content encrypted at rest (Laravel Crypt) |
| Question attachments | Image/document file uploads |
| Question import | txt/docx/pdf Moodle-style format |
| Question randomization | Per-attempt shuffle stored in `question_order` JSON |
| Exam scheduling | One-time schedule with starts_at/ends_at |
| Session recovery | 10-min reconnect window, frozen-time tracking |
| Single-session enforcement | Token-based concurrent session prevention |
| Anti-cheat (3-strike) | Tab-switch, copy-paste, dev-tools detection |
| Security incident review | Admin approve/reject with comment |
| Auto-grading | MCQ/TF/fill-blank, case-sensitive, DISQUALIFIED guard |
| Result statuses | PASSED / FAILED / ABSENT (command) / DISQUALIFIED |
| ABSENT result command | `results:mark-absent` Artisan command |
| Notification system | In-app, categorized, per-role |
| Email queue | SendEmailJob, 3 retries, `emails` queue |
| Email templates | DB-stored, `{{variable}}` substitution |
| Email logs + retry | Full audit trail, admin retry button |
| Admin compose (template) | 3-step wizard with preview |
| Admin compose (custom) | Direct to/subject/body → branded HTML wrapper |
| Bulk email | Per-recipient personalization |
| Scheduled email | Future-dated bulk sends, every-minute cron |
| IMAP inbox sync | Webklex, envelope-first dedup, configurable limit |
| Inbox reply/archive | Reply via SMTP queue, archive flag |
| SMTP settings admin | Runtime + .env write |
| Teacher analytics | Chart.js performance charts |
| Student result history | Per-semester grouping with academic history |
| Activity logging | All admin/teacher actions logged with IP |

---

### 2. In-Progress / Partial Features

| Feature | Status | Notes |
|---------|--------|-------|
| Session recovery timer adjustment | ⚠️ Partial | Frozen time tracked but not added back to expires_at |
| Email template for ExamPublishedMail | ⚠️ Partial | DB template subject used, Blade body used — not unified |
| Compose group send | ⚠️ Partial | Works but no per-recipient preview capability |
| Question import (PDF) | ⚠️ Partial | Extraction may be limited depending on PDF structure |

---

### 3. Missing / Removed Features

| Feature | Notes |
|---------|-------|
| Grade/GPA calculation | Intentionally removed — tables cleaned up |
| Transcripts / Certificates | Views exist, DB tables dropped (2026_07_08) |
| Real-time notifications (Pusher/Echo) | Echo commented out, not implemented |
| REST API | No API routes — web-only application |
| Re-attempt support | `attempt_number` field exists but always = 1; no re-attempt flow |
| Rate limiting (login/OTP) | No throttle middleware on auth routes |
| FormRequest classes | No validation objects — all inline |
| Policy / Gate authorization | Role-based middleware only |
| Test suite | PHPUnit installed but no test files written |
| Email campaign system | `email_campaigns` table created but not used in UI |
| Thread-based inbox | `thread_id` column exists but threading not implemented |

---

### 4. Recommended Next Steps

**Priority 1 — Security Hardening**
- Add `throttle:5,1` to `POST /login` and `POST /forgot-password/send`
- Move SMTP password to an encrypted config table instead of `.env`
- Add ownership checks (`Gate::authorize`) in teacher exam routes

**Priority 2 — Bug Fixes**
- Adjust `expires_at` when student reconnects after a disconnect (add frozen seconds back)
- Filter soft-deleted questions from `question_order` array during exam take
- Move `syncInbox()` to a queued job to prevent HTTP timeout on slow IMAP

**Priority 3 — Code Structure**
- Split `EmailController` into: `InboxController`, `ComposeController`, `EmailTemplateController`, `EmailLogController`
- Extract inline `$request->validate()` blocks into `FormRequest` classes
- Extract dead `CheatingDetectionService` or formally deprecate/remove it

**Priority 4 — Frontend**
- Introduce Vite build pipeline — move inline JS to `resources/js/` modules
- Add `CSP` headers to restrict script sources on the exam-taking page
- Make profile photo upload use standard `<input type="file">` instead of Base64

**Priority 5 — Performance**
- Add `Cache::remember()` for dashboard stats (TTL 5 minutes)
- Cache `YearLevel::all()`, `Major::all()` (they never change at runtime)
- Add `email_logs` pruning command (keep last 90 days)
- Consider moving GradingService to a queued job for large exams

**Priority 6 — Testing**
- Write Feature tests for the full exam submission → grading → result flow
- Write Unit tests for `GradingService::gradeAttempt()` (all three question types)
- Write Unit tests for `ExamSecurityService` violation escalation logic
- Write Unit tests for `InboxSyncService` deduplication

---

*End of Believe Exam Portal Architecture Documentation*
*Total tables: ~35 | Total migrations: 32 | Total PHP classes: ~75 | Generated: 2026-07-22*
