# Believe Exam — Comprehensive Architecture Analysis

> **Scope:** Accurate description of the current implementation only.
> No code was modified. Generated: 2026-07-01.

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [Technology Stack & Dependencies](#2-technology-stack--dependencies)
3. [High-Level Architecture](#3-high-level-architecture)
4. [Database Schema & Relationships](#4-database-schema--relationships)
5. [Models Reference](#5-models-reference)
6. [Service Layer](#6-service-layer)
7. [Controllers](#7-controllers)
8. [Routes](#8-routes)
9. [Middleware](#9-middleware)
10. [Console Commands & Scheduler](#10-console-commands--scheduler)
11. [Jobs & Queues](#11-jobs--queues)
12. [Mail Classes](#12-mail-classes)
13. [UI / View Structure](#13-ui--view-structure)
14. [JavaScript Flow](#14-javascript-flow)
15. [Dependency Analysis](#15-dependency-analysis)
16. [Feature Impact Analysis](#16-feature-impact-analysis)
17. [Reusable Components](#17-reusable-components)
18. [Components That Must Not Be Modified](#18-components-that-must-not-be-modified)
19. [Regression Risks](#19-regression-risks)
20. [Implementation Order for Future Work](#20-implementation-order-for-future-work)

---

## 1. Project Overview

**Believe Exam** (`APP_NAME="Believe Exam"`) is a full-stack Laravel 9 web application for online examination management used by BLC (Believe Learning Centre). It supports three user roles — Admin, Teacher, and Student — and covers the complete examination lifecycle: course management, exam authoring, scheduling, live proctoring with anti-cheat, automatic grading, academic records, transcripts, certificates, email communication, and re-attempt workflows.

**Default admin** is seeded automatically on first boot via `EnsureDefaultAdminService` (driven by `.env` keys `DEFAULT_ADMIN_EMAIL`, `DEFAULT_ADMIN_PASSWORD`, `DEFAULT_ADMIN_NAME`).

---

## 2. Technology Stack & Dependencies

### PHP / Laravel
| Package | Version | Purpose |
|---|---|---|
| `laravel/framework` | ^9.0 | Core framework |
| `laravel/sanctum` | ^2.14 | API token auth (Sanctum) |
| `laravel/tinker` | ^2.7 | REPL |
| `barryvdh/laravel-dompdf` | ^3.1 | PDF generation (transcripts, certificates) |
| `maatwebsite/excel` | 3.1.* | Excel import/export |
| `simplesoftwareio/simple-qrcode` | ^4.2 | QR codes on certificates |
| `fruitcake/laravel-cors` | ^2.0.5 | CORS headers |
| `guzzlehttp/guzzle` | ^7.2 | HTTP client |

### Frontend Build
| Package | Purpose |
|---|---|
| `laravel-mix` | Webpack wrapper |
| `axios` | AJAX requests (bundled via Mix) |
| `lodash` | Utility (bundled via Mix) |

### Frontend Runtime (public, non-bundled)
| File | Purpose |
|---|---|
| `public/js/exam-anticheat.js` | Live exam security enforcement |
| `public/js/question-builder.js` | Dynamic question form UI |

### Database
- MySQL (default). `DB_DATABASE=b_exam`.
- Queue driver: `database` (`QUEUE_CONNECTION=database`).
- Cache driver: `file`.

---

## 3. High-Level Architecture

```
Browser
  │
  ├─ GET/POST ──► Laravel Router (routes/web.php)
  │                │
  │                ├─ Middleware Stack
  │                │   ├─ auth / guest
  │                │   ├─ role (RoleMiddleware)
  │                │   ├─ exam.session (EnsureSingleExamSession)
  │                │   └─ exam.active (EnsureExamActive)
  │                │
  │                └─ Controllers
  │                    ├─ Admin/*     (15 controllers)
  │                    ├─ Teacher/*   (3 controllers)
  │                    ├─ Student/*   (5 controllers)
  │                    └─ Shared      (Auth, Chat, Dashboard, Notifications)
  │
  └─ fetch() / XHR ──► JSON endpoints (same web.php routes)

Services (pure PHP, injected via constructor DI):
  ExamSecurityService ←─── ExamSessionController.violation()
  GradingService       ←─── ExamSessionController.submit() + ExamSecurityService
  ExamAccessService    ←─── ExamSessionController.take() + TeacherExamController
  ReAttemptService     ←─── Admin/ReAttemptController + Teacher/ExamController
  EmailService         ←─── Admin/EmailController + ExamSecurityService + others
  NotificationService  ←─── multiple controllers and services
  ActivityLogService   ←─── SecuritySettingsController + ExamSecurityService
  AcademicService      ←─── Admin/AcademicYearController
  CertificateService   ←─── Admin/CertificateController
  TranscriptService    ←─── Admin/TranscriptController
  CourseAssignmentService ← Admin/TeacherController + Admin/StudentController
  EncryptionService    ←─── Question/Answer models (decrypt accessor)
  QuestionImportService ←── Teacher/ExamController.importQuestions()
  EnsureDefaultAdminService ← AppServiceProvider.boot()

Queue:
  SendEmailJob ─── dispatched by EmailService.send() when $queue=true
                └─ processed by: php artisan queue:work --queue=emails

Scheduler (everyMinute):
  email:process-scheduled ─── EmailService::processScheduled()
```

---

## 4. Database Schema & Relationships

### 4.1 Core Tables

#### `users`
Columns (original + extended): `id`, `name`, `email`, `password`, `role_id` (FK→roles), `is_active`, `phone`, `academic_year` (int 1–5, legacy), `exam_session_token`, `last_login_at`, `email_verified_at`, `remember_token`, `deleted_at`, timestamps.

#### `roles`
`id`, `name`, `slug` (admin | teacher | student), timestamps.

#### `courses`
`id`, `title`, `code` (unique), `description`, `teacher_id` (FK→users), `created_by` (FK→users), `is_active`, `year_level` (int 0–5), `academic_year_id` (FK→academic_years), `semester` (0=both, 1, 2), `major_id` (FK→majors), `deleted_at`, timestamps.

#### `enrollments`
`id`, `course_id` (FK→courses), `student_id` (FK→users), `enrolled_at`, `year` (int, legacy), `year_level_id` (FK→year_levels), `major_id` (FK→majors), timestamps. Unique constraint: (`course_id`, `student_id`).

#### `exams`
`id`, `course_id`, `teacher_id`, `title`, `description`, `status` ENUM(draft|pending_approval|approved|published|closed), `total_marks`, `passing_marks`, `shuffle_questions`, `submitted_at`, `approved_at`, `approved_by`, `deleted_at`, timestamps.

#### `exam_schedules`
`id`, `exam_id`, `starts_at`, `ends_at`, `duration_minutes`, `attempt_limit`, `target_year`, `is_published`, `published_at`, `published_by`, timestamps.

#### `questions`
`id`, `exam_id`, `category_id` (FK→question_categories), `type` ENUM(mcq|true_false|essay|fill_blank|document|file_upload), `content_encrypted` (Laravel Crypt), `attachment_path`, `attachment_name`, `attachment_mime`, `difficulty` ENUM(easy|medium|hard), `marks`, `order`, `deleted_at`, timestamps.

#### `answers`
`id`, `question_id`, `content_encrypted`, `is_correct`, `is_blank_answer`, `order`, timestamps.

#### `exam_attempts`
`id`, `exam_id`, `schedule_id`, `student_id`, `attempt_number`, `status` ENUM(in_progress|submitted|terminated|suspicious|terminated_pending_review|rejected), `warning_count`, `started_at`, `submitted_at`, `expires_at`, `session_token`, `terminated_at`, `approved_by`, `approved_at`, `approval_comment`, `rejected_by`, `rejected_at`, `rejection_comment`, timestamps. Index: (`exam_id`, `student_id`).

#### `student_answers`
`id`, `attempt_id`, `question_id`, `answer_id`, `answer_text`, `file_path`, `is_correct`, `marks_awarded`, timestamps. Unique: (`attempt_id`, `question_id`).

#### `results`
`id`, `attempt_id`, `exam_id`, `student_id`, `total_marks`, `obtained_marks`, `percentage`, `grade`, `is_passed`, `is_published`, `exam_result_status` ENUM(PASSED|FAILED|ABSENT|DISQUALIFIED), `violation_reason`, `disqualified_at`, `attendance_status` ENUM(attended|absent), `exam_finished_at`, timestamps.

#### `cheating_logs`
`id`, `attempt_id`, `student_id`, `violation_type`, `details`, `warning_number`, `user_agent`, `browser`, `device`, `os`, `screen_resolution`, `timezone`, `ip_address`, timestamps.

#### `security_settings`
`id`, `key` (unique), `value` (JSON-encoded), `label`, `description`, timestamps.


### 4.2 Academic System Tables

#### `academic_years`
`id`, `name`, `start_year`, `end_year`, `is_current`, timestamps.

#### `year_levels`
`id`, `level` (1–5), `name`, `department`, `major`, timestamps.

#### `majors`
`id`, `name`, `code`, `description`, `is_active`, timestamps.

#### `student_year_records`
`id`, `student_id`, `academic_year_id`, `year_level_id`, `semester`, `department`, `major`, `gpa`, `status` ENUM(active|promoted|failed|withdrawn), `promoted_at`, timestamps. Unique: (`student_id`, `academic_year_id`, `year_level_id`, `semester`).

#### `yearly_exam_results`
`id`, `student_id`, `academic_year_id`, `year_level_id`, `exam_id`, `result_id`, `obtained_marks`, `total_marks`, `percentage`, `grade`, `is_passed`, `semester`, timestamps.

#### `yearly_transcripts`
`id`, `student_id`, `academic_year_id`, `year_level_id`, `semester`, `gpa`, `total_marks`, `obtained_marks`, `percentage`, `grade`, `is_passed`, `status`, `generated_by`, timestamps.

#### `promotion_histories`
`id`, `student_id`, `from_year_level_id`, `to_year_level_id`, `academic_year_id`, `promoted_by`, `notes`, `promoted_at`, timestamps.

#### `certificate_logs`
`id`, `serial_number` (unique CERT-YYYY-NNNN), `student_id`, `academic_year_id`, `year_level_id`, `type` ENUM(transcript|completion|promotion|achievement), `issued_by`, `qr_token` (UUID, unique), `issued_at`, `created_by`, timestamps.

### 4.3 Re-Attempt Tables

#### `re_attempt_requests`
`id`, `student_id`, `teacher_id`, `exam_id`, `reason`, `status` ENUM(pending|approved|rejected), `admin_remark`, `approved_by`, `approved_at`, `sent_to_admin_at`, `re_attempt_start_at`, `re_attempt_end_at`, timestamps.

#### `re_attempt_logs`
`id`, `request_id`, `action`, `actor_id`, `actor_role`, `remarks`, timestamps.

### 4.4 Communication / Support Tables

#### `user_notifications`
`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, timestamps.

#### `chat_messages`
`id`, `sender_id`, `receiver_id`, `message` (Laravel Crypt encrypted), `file_path`, `is_read`, timestamps. Index: (`sender_id`, `receiver_id`).

#### `email_logs`
`id`, `to_email`, `to_name`, `from_email`, `from_name`, `subject`, `body_html`, `template_slug`, `event`, `status` (queued|sent|failed), `provider`, `error`, `message_id`, `user_id`, `queued_at`, `sent_at`, timestamps.

#### `email_templates`
`id`, `name`, `slug` (unique), `subject`, `body_html`, `body_text`, `event`, `is_active`, `created_by`, timestamps.

#### `scheduled_emails`
`id`, `name`, `template_slug`, `subject`, `body_html`, `recipients`, `send_at`, `is_sent`, `sent_at`, `created_by`, timestamps.

#### `activity_logs`
`id`, `user_id`, `action`, `model_type`, `model_id`, `description` (text or JSON), `ip_address`, timestamps.

#### `question_categories`
`id`, `name`, `slug`, `description`, timestamps.

### 4.5 Relationship Map

```
User ──── role_id ──────────────────► Role
User ──── hasMany ──────────────────► Course (as teacher)
User ──── hasMany ──────────────────► Enrollment (as student)
User ──── hasMany ──────────────────► Exam (as teacher)
User ──── hasMany ──────────────────► ExamAttempt (as student)
User ──── hasMany ──────────────────► UserNotification
User ──── hasMany ──────────────────► ChatMessage (sender + receiver)

Course ── belongsTo ────────────────► User (teacher), AcademicYear, YearLevel, Major
Course ── hasMany ──────────────────► Enrollment, Exam
Course ── belongsToMany (via Enrollment) ─► User (students)

Exam ──── belongsTo ────────────────► Course, User (teacher), User (approver)
Exam ──── hasMany ──────────────────► Question, ExamSchedule, ExamAttempt, Result
Exam ──── hasOne (latest) ──────────► ExamSchedule (activeSchedule, latestSchedule)

ExamAttempt ─ belongsTo ────────────► Exam, ExamSchedule, User (student), User (approver), User (rejector)
ExamAttempt ─ hasMany ──────────────► StudentAnswer, CheatingLog
ExamAttempt ─ hasOne ───────────────► Result

Question ── belongsTo ──────────────► Exam, QuestionCategory
Question ── hasMany ────────────────► Answer

Result ──── belongsTo ──────────────► ExamAttempt, Exam, User (student)

ReAttemptRequest ─ belongsTo ───────► User (student, teacher, approver), Exam
ReAttemptRequest ─ hasMany ─────────► ReAttemptLog

StudentYearRecord ─ belongsTo ──────► User, AcademicYear, YearLevel
YearlyExamResult ─ belongsTo ───────► User, AcademicYear, YearLevel, Exam, Result
YearlyTranscript ─ belongsTo ───────► User, AcademicYear, YearLevel, User (generatedBy)
PromotionHistory ─ belongsTo ───────► User, YearLevel (from+to), AcademicYear, User (promotedBy)
CertificateLog ─── belongsTo ───────► User (student+creator), AcademicYear, YearLevel
```

---

## 5. Models Reference

| Model | Table | Soft Deletes | Key Traits / Notes |
|---|---|---|---|
| `User` | `users` | ✅ | `HasApiTokens`, `Notifiable`, `MustVerifyEmail`. Role helpers: `isAdmin()`, `isTeacher()`, `isStudent()`, `hasRole()`. |
| `Role` | `roles` | ❌ | Simple name/slug. Slugs enforced via `RoleSlug` enum constants. |
| `Course` | `courses` | ✅ | Static label maps for year levels and semesters. `requiresMajor()` helper. |
| `Exam` | `exams` | ✅ | `getStudentScheduleAttribute()` — returns active schedule based on status. |
| `ExamSchedule` | `exam_schedules` | ❌ | FK to exam, publisher. |
| `Question` | `questions` | ✅ | `content_encrypted` column; `getDecryptedContentAttribute()` uses `EncryptionService`. `hasAttachment()` / `attachmentUrl()` helpers. |
| `Answer` | `answers` | ❌ | `content_encrypted`; `getDecryptedContentAttribute()`. `is_blank_answer` flag for fill-blank type. |
| `ExamAttempt` | `exam_attempts` | ❌ | Status helpers: `isActive()`, `isTerminatedPendingReview()`, `isRejected()`, `isFinished()`. Phase 2 fields: `terminated_at`, `approved_by/at`, `rejected_by/at` with comments. |
| `StudentAnswer` | `student_answers` | ❌ | `answer_id` or `answer_text` depending on question type. |
| `Result` | `results` | ❌ | Status constants `STATUS_PASSED/FAILED/ABSENT/DISQUALIFIED`. `statusLabel()` / `statusBadgeClass()` helpers. |
| `CheatingLog` | `cheating_logs` | ❌ | Stores client fingerprint (browser, device, OS, resolution, timezone, IP). |
| `SecuritySetting` | `security_settings` | ❌ | Static helpers: `get()`, `set()`, `policy()`, per-flag methods. 60-min cache per key. |
| `ReAttemptRequest` | `re_attempt_requests` | ❌ | Lifecycle: student→teacher→admin. `isPending()`, `isApproved()`, `isRejected()`. |
| `ReAttemptLog` | `re_attempt_logs` | ❌ | Audit trail for re-attempt lifecycle events. |
| `Enrollment` | `enrollments` | ❌ | Dual-column year: legacy `year` int + relational `year_level_id`. |
| `Major` | `majors` | ❌ | `ensureDefaults()` seeds CS and CT. `resolveIdFromLabel()`, `codeFromLabel()`. |
| `AcademicYear` | `academic_years` | ❌ | `current()` scope. |
| `YearLevel` | `year_levels` | ❌ | `ensureDefaults()` seeds levels 1–5. |
| `StudentYearRecord` | `student_year_records` | ❌ | Per-student per-year GPA and status tracking. |
| `YearlyExamResult` | `yearly_exam_results` | ❌ | Permanent archive of exam results per academic year. |
| `YearlyTranscript` | `yearly_transcripts` | ❌ | Aggregated per-year transcript. |
| `PromotionHistory` | `promotion_histories` | ❌ | Permanent promotion audit trail. |
| `CertificateLog` | `certificate_logs` | ❌ | Serial + QR token for certificate verification. |
| `EmailLog` | `email_logs` | ❌ | Delivery tracking per message. `markSent()`, `markFailed()`. |
| `EmailTemplate` | `email_templates` | ❌ | `render(array $vars)` substitutes `{{variable}}` tokens. `findBySlug()`. |
| `ScheduledEmail` | `scheduled_emails` | ❌ | Deferred bulk sends processed by scheduler. |
| `UserNotification` | `user_notifications` | ❌ | In-app notifications per user. |
| `ChatMessage` | `chat_messages` | ❌ | Mutator/accessor encrypt/decrypt via `Crypt::encryptString`. |
| `ActivityLog` | `activity_logs` | ❌ | JSON-encodable description for structured security audit. |
| `QuestionCategory` | `question_categories` | ❌ | Optional tagging for questions. |

---

## 6. Service Layer

All services are resolved via Laravel's DI container (constructor injection). No explicit bindings — autoresolved.

### `ExamSecurityService`
**Dependencies:** `EmailService`, `NotificationService`, `ActivityLogService`, `GradingService`

Implements the **3-tier violation policy**:
- **Tier 1** (warning = 1): record + warn student only. Logs `security_warning_1`.
- **Tier 2** (warning = 2…N-1): record + warn + queue email to teacher+admins + notification. Logs `security_warning_2`.
- **Tier 3** (warning = `max_warnings`): DB transaction with `lockForUpdate()` — sets status `terminated_pending_review`, clears `exam_session_token`, grades attempt, marks result `DISQUALIFIED`, fires after-commit emails + notifications to student + teacher + all admins. Logs `exam_terminated_security`.

**`approve(attempt, actor, comment)`**: Sets status back to `in_progress`, extends `expires_at` by locked duration (capped at `config('exam_security.max_resume_extension_minutes', 120)`), sends `security_approved` notification to student.

**`reject(attempt, actor, comment)`**: Sets status to `rejected`, preserves `terminated_at`, sends `security_rejected` notification to student.

**`persistViolationLog()`**: Creates `CheatingLog` row with client fingerprint data.

**`getRecipients()`**: Teacher of the exam + all active admins, deduplicated by ID.

**`getTerminationRecipients()`**: Student + `getRecipients()` result.

---

### `GradingService`
**Dependencies:** none

`gradeAttempt(ExamAttempt)`: Iterates `studentAnswers`, scores MCQ/true_false and fill_blank. Calls `Result::updateOrCreate()`. Sets `exam_result_status` to PASSED or FAILED.

**Guard**: If result already has `STATUS_DISQUALIFIED`, returns without overwriting.

Grade scale: A≥80%, B≥70%, C≥60%, D≥50%, F<50%.

---

### `ExamAccessService`
**Dependencies:** `EncryptionService`

- `canDecryptQuestions(user, exam)`: Admin/teacher always yes. Student: exam must be approved/published, schedule window active or re-attempt window active.
- `canViewCorrectAnswers(user, exam)`: Admin/teacher always yes. Student: only after schedule ends.
- `studentCanTakeExam(user, exam)`: Checks enrollment, attempt count vs `attempt_limit + approved_reattempts` (capped at 3), and schedule/reattempt window.
- `scheduleHasEnded()`, `isScheduleActive()`, `isScheduleEnded()`.

---

### `EmailService`
**Dependencies:** none (uses `Mail`, `EmailLog`, `EmailTemplate`, `ScheduledEmail`, `SendEmailJob`)

- `send()`: Creates `EmailLog`, dispatches `SendEmailJob` (or calls `deliver()` directly if `$queue=false`).
- `sendTemplate()`: Resolves `EmailTemplate` by slug, renders `{{variables}}`, then calls `send()`.
- `sendBulk()`: Iterates recipient group, substitutes per-user variables, calls `send()`.
- `sendWelcomeEmail()`: Tries template `welcome`, falls back to `emails.welcome` Blade view.
- `deliver()`: Actual `Mail::send()` call; updates `EmailLog` status.
- `retry()`: Re-queues a failed log.
- `processScheduled()`: Finds due `ScheduledEmail` records, calls `sendBulk()`.
- `applySmtpConfig()`: Mutates `config()` at runtime (no `.env` write).
- `resolveRecipients()`: Maps group strings to User collections.

---

### `NotificationService`
Simple: `notify(user, type, title, message, link)` → `UserNotification::create()`.

---

### `ReAttemptService`
**Dependencies:** `NotificationService`

- `createRequest()`: Teacher initiates → immediately sent to admin. Notifies admins + student.
- `createStudentRequest()`: Student initiates → not yet sent to admin. Notifies teacher only.
- `sendToAdmin()`: Teacher forwards student's request. Notifies admins.
- `approve()`: Admin approves with optional re-attempt window. Business rule: does NOT delete previous attempts. Notifies student + teacher.
- `reject()`: Admin rejects. Notifies student + teacher.
- `hasApprovedWithActiveWindow()`: Checks if current datetime falls within approved window.

---

### `AcademicService`
`enrollStudent()`, `archiveResults()`, `recalculateGpa()` (100% = 4.0 GPA scale), `promoteStudent()`, `generateSerial()`, `issueCertificate()`, `getStudentHistory()`.

---

### `CertificateService`
**Dependencies:** `Pdf` (DomPDF), `QrCode` (simplesoftwareio)

- `issue()`: Validates eligibility (completion = Year 4 or 5 passed; others = Year 5 passed). Creates `CertificateLog`.
- `exportPdf()`: Renders `pdf.certificate` Blade view with QR SVG as inline data.
- `verify()`: Looks up `CertificateLog` by `qr_token`.

---

### `TranscriptService`
- `generate()`: Aggregates `YearlyExamResult` data into `YearlyTranscript`.
- `exportPdf()`: Renders `pdf.transcript` Blade view.
- `getHistory()`: Full academic history with results per year.

---

### `CourseAssignmentService`
- `syncCourseStudents()`: Updates enrollments for a course from a student ID array.
- `syncTeacherCourses()`: Sets/clears `teacher_id` on courses.
- `syncStudentCourses()`: Updates enrollments for a student from a course ID array.
- `resolveStudentAcademicContext()`: Derives `[yearInt, yearLevelId, majorId]` from active `StudentYearRecord`, with legacy fallback.

---

### `EncryptionService`
Thin wrapper around `Crypt::encryptString/decryptString`. Returns `null` on decrypt failure.

---

### `QuestionImportService`
**Dependencies:** `EncryptionService`

Parses a Moodle-style plain-text format (`[MCQ]`, `[TRUE_FALSE]`, etc.) from `.txt`, `.docx`, `.pdf`, or `.doc` files. Extracts text from DOCX via `ZipArchive`. Creates `Question` + `Answer` records with encrypted content.

---

### `ActivityLogService`
`log(action, description|array, model)`: Writes to `activity_logs`. Arrays are JSON-encoded for queryability.

---

### `EnsureDefaultAdminService`
Called from `AppServiceProvider::boot()`. Creates roles and default admin user if missing (checks DB schema first to survive `migrate`).

---

## 7. Controllers

### 7.1 Auth
**`Auth\AuthController`**
- `showLogin()` / `login()`: Validates credentials, checks `is_active`, sets `last_login_at`, stores `exam_session_token` in session.
- `showRegister()` / `register()`: Creates user with `role_id` for student.
- `logout()`: Clears session, redirects to login.

---

### 7.2 Shared
**`DashboardController`**
- `admin()`: Counts users, courses, exams, cheating_logs.
- `teacher()`: Counts teacher's courses, exams, pending_approval exams.
- `student()`: Counts enrolled_courses, completed exam attempts.

**`NotificationController`**
- `index()`: All notifications for auth user.
- `markRead(notification)`, `markAllRead()`: Toggle `is_read`.
- `unreadCount()`: JSON count for badge polling.

**`ChatController`**
- `index()`: List of users the auth user has conversations with.
- `conversation(user)`: Load messages between auth user and target.
- `send(user)`: Create `ChatMessage` (message is encrypted by model mutator).
- `poll(user)`: JSON endpoint returning unread messages since last check.

---

### 7.3 Admin Controllers

**`Admin\UserController`** (resource minus `show`)
- `index()`, `create()`, `store()`, `edit()`, `update()`: Standard CRUD for all users.
- `terminate(user)`: Soft-deletes user, sends `AccountTerminatedMail`.

**`Admin\CourseController`** (resource minus `show`)
- `index()`, `create()`, `store()`, `edit()`, `update()`, `destroy()`: Course CRUD.
- `byYearLevel()`: JSON endpoint returning courses filtered by year level — used by enrollment form.

**`Admin\EnrollmentController`**
- `index()`: List all enrollments with filters.
- `store()`: Bulk-enroll students into a course.
- `destroy(enrollment)`: Remove enrollment.
- `studentsByYearLevel()`: JSON endpoint for dynamic student list in enrollment form.

**`Admin\ExamController`**
- `index()`, `show()`: View exams.
- `approve(exam)`: Sets `status=approved`, notifies teacher.
- `schedule(exam)`, `updateSchedule()`, `deleteSchedule()`: Manage `ExamSchedule` records.
- `publish(exam)`: Sets `status=published`, marks schedule `is_published=true`, emails + notifies enrolled students and teacher.
- `close(exam)`: Sets `status=closed`, unpublishes schedule.
- `open(exam)`: Reopens a closed exam.

**`Admin\TeacherController`**
- `index()`, `create()`, `store()`, `show()`, `edit()`, `update()`: Teacher CRUD.
- Uses `CourseAssignmentService::syncTeacherCourses()` to assign courses.

**`Admin\StudentController`**
- `index()`, `create()`, `store()`, `show()`, `edit()`, `update()`, `destroy()`: Student CRUD.
- Uses `CourseAssignmentService::syncStudentCourses()`.

**`Admin\MajorController`** (resource minus `show`)
- CRUD for majors.

**`Admin\ReAttemptController`**
- `index()`: Filtered list (only requests where `sent_to_admin_at IS NOT NULL`).
- `show()`, `approve()`, `reject()`, `updateWindow()`: Delegates to `ReAttemptService`.

**`Admin\CheatingLogController`**
- `index()`: All `CheatingLog` records with student and exam eager-loaded. Read-only view.

**`Admin\SecuritySettingsController`**
- `index()`: Loads all settings from `SecuritySetting::policy()`.
- `update()`: Validates and saves each policy key via `SecuritySetting::set()`. Logs to `ActivityLog`.

**`Admin\EmailController`**
Full email management suite:
- `index()`: Dashboard with stats.
- `smtpSettings()` / `smtpUpdate()`: Runtime SMTP config via `EmailService::applySmtpConfig()`.
- `templates()`, `createTemplate()`, `storeTemplate()`, `editTemplate()`, `updateTemplate()`, `destroyTemplate()`, `previewTemplate()`.
- `logs()`, `showLog()`, `retryLog()`.
- `bulk()`, `sendBulk()`: Bulk email with recipient group selection.
- `scheduled()`, `storeScheduled()`, `destroyScheduled()`.
- `testEmail()`, `sendTestEmail()`.

**`Admin\AcademicYearController`** (resource)
- Standard CRUD for `AcademicYear` records.
- `students()`, `assignStudents()`, `removeStudent()`: Manage `StudentYearRecord` assignments.

**`Admin\TranscriptController`**
- `show(student)`: Display transcript with all year records.
- `generate(student)`: Delegates to `TranscriptService::generate()`.
- `pdf(student)`: Delegates to `TranscriptService::exportPdf()`.

**`Admin\CertificateController`**
- `index()`: List all issued certificates.
- `issue(student)`: Delegates to `CertificateService::issue()`.
- `pdf(cert)`: Delegates to `CertificateService::exportPdf()`.
- `verify(token)` (public route): Renders QR scan verification page.

**`Admin\ResultController`**
- `index()`: All results across all exams.
- `student(student)`: All results for a specific student.

---

### 7.4 Teacher Controllers

**`Teacher\ExamController`**
- `index()`, `create()`, `store()`: Exam CRUD (teacher scope only).
- `show(exam)`: Exam detail with questions.
- `addQuestion()`, `editQuestion()`, `updateQuestion()`, `deleteQuestion()`: Question management. Enforces `status IN (draft, pending_approval)`.
- `submitForApproval()`: Status → `pending_approval`, emails + notifies admins.
- `results(exam)`: View exam results.
- `importQuestions()`: Delegates to `QuestionImportService`.
- `reattemptRequests()`, `reattemptCreate()`, `reattemptStore()`, `reattemptCancel()`, `reattemptSendToAdmin()`: Re-attempt management.

**`Teacher\ProfileController`**
- `show()`, `edit()`, `update()`: Teacher profile management.

**`Teacher\ResultController`**
- `index()`: All results across all exams taught by the auth teacher.

---

### 7.5 Student Controllers

**`Student\ExamController`**
- `index()`: Exams the student is enrolled in, filtered by `approved/published` status. Also loads any `terminated_pending_review` attempts.
- `show(exam)`: Exam detail — shows result only after schedule ends and result is published.
- `start(exam)`: Validates `studentCanTakeExam()`, creates `ExamAttempt`, sets `exam_session_token`, redirects to `take`.

**`Student\ExamSessionController`**
- `take(attempt)`: Decrypts questions via `ExamAccessService`, passes `securityPolicy` from `SecuritySetting::policy()` to view.
- `saveAnswer(attempt)`: `updateOrCreate` on `StudentAnswer`.
- `violation(attempt)`: Delegates entirely to `ExamSecurityService::recordViolation()`.
- `submit(attempt)`: Marks `submitted`, clears session token, calls `GradingService::gradeAttempt()`.

**`Student\CourseController`**
- `index()`: Student's enrolled courses.

**`Student\ReAttemptController`**
- `index()`: Student's re-attempt requests.
- `create(exam)` / `store()`: Delegates to `ReAttemptService::createStudentRequest()`.

**`Student\ResultController`**
- `index()`: Student's all results.

---

## 8. Routes

All routes are in `routes/web.php`. Three role-scoped groups inside `['auth', 'exam.session']`.

### Public Routes
| Method | URI | Name | Handler |
|---|---|---|---|
| GET | `/` | `home` | `welcome` view |
| GET | `certificates/verify/{token}` | `certificates.verify` | `CertificateController@verify` |
| GET | `login` | `login` | `AuthController@showLogin` |
| POST | `login` | — | `AuthController@login` |
| GET | `register` | `register` | `AuthController@showRegister` |
| POST | `register` | — | `AuthController@register` |
| POST | `logout` | `logout` | `AuthController@logout` |

### Authenticated Routes (`auth`, `exam.session`)
**Notifications:** `GET notifications`, `POST notifications/{id}/read`, `POST notifications/read-all`, `GET notifications/unread-count`

**Chat:** `GET chat`, `GET chat/{user}`, `POST chat/{user}`, `GET chat/{user}/poll`

### Admin Group (`/admin`, middleware `role:admin`)
| Area | Routes |
|---|---|
| Dashboard | `GET admin/dashboard` |
| Users | Full resource (index, create, store, edit, update) + `POST users/{user}/terminate` |
| Courses | Full resource (no show) + `GET courses-by-year-level` |
| Majors | Full resource (no show) |
| Enrollments | `GET/POST enrollments`, `GET enrollments/students-by-year-level`, `DELETE enrollments/{id}` |
| Exams | GET index/show; POST approve/schedule/publish/close/open; PUT schedule update; DELETE schedule |
| Cheating Logs | `GET cheating-logs` |
| Email | Full suite: smtp, templates CRUD, logs, bulk, scheduled, test |
| Re-Attempts | GET index/show; POST approve/reject; PUT window |
| Academic Years | Full resource + students assign/remove |
| Teachers | GET index/create/show/edit; POST store; PUT update |
| Students | Full CRUD |
| Results | GET index; GET results/student/{student} |
| Transcripts | GET/POST transcripts/{student}; GET pdf |
| Certificates | GET index; POST issue; GET pdf |
| Security Settings | GET/POST security-settings |

### Teacher Group (`/teacher`, middleware `role:teacher,admin`)
Exams, profile, results, re-attempts (create/cancel/send-to-admin).

### Student Group (`/student`, middleware `role:student`)
Dashboard, courses, exams (index/show/start), exam session (take/save/violation/submit), re-attempts, results.

### API Routes (`routes/api.php`, `auth:sanctum`)
`GET /api/user`, `GET /api/courses`, `GET /api/exams` — minimal, not used by the web frontend.

---

## 9. Middleware

### Global (every request)
- `TrustProxies` — proxy header trust.
- `HandleCors` (fruitcake) — CORS.
- `PreventRequestsDuringMaintenance`.
- `ValidatePostSize`, `TrimStrings`, `ConvertEmptyStringsToNull`.

### Web Group
- `EncryptCookies`, `AddQueuedCookiesToResponse`, `StartSession`.
- `ShareErrorsFromSession`, `VerifyCsrfToken`, `SubstituteBindings`.

### Route Middleware (named)
| Alias | Class | Behaviour |
|---|---|---|
| `auth` | `Authenticate` | Redirects to `login` if unauthenticated. |
| `guest` | `RedirectIfAuthenticated` | Redirects role-appropriately if already logged in. |
| `role` | `RoleMiddleware` | Accepts comma-separated slugs; aborts 403 if user's role.slug not in list. |
| `exam.session` | `EnsureSingleExamSession` | Applied to all auth routes. Students get one session token stored in DB and session. Concurrent session detected → logout + error. Non-students pass through. |
| `exam.active` | `EnsureExamActive` | Applied only to exam session routes. If attempt is not `in_progress` → JSON 403 (for fetch/XHR) or redirect (browser). Returns appropriate message per status. |
| `verified` | `EnsureEmailIsVerified` | Available but not applied to any current routes. |

---

## 10. Console Commands & Scheduler

### Commands

| Signature | Class | Description |
|---|---|---|
| `email:process-scheduled` | `ProcessScheduledEmails` | Runs `EmailService::processScheduled()`. Safe to call repeatedly (idempotent). |
| `results:mark-absent` | `MarkAbsentResults` | Creates `ABSENT` result records for students who never started a closed exam. Supports `--exam=ID` and `--dry-run`. Fully idempotent. |
| `email:stats` | `EmailStats` | Displays sent/queued/failed counts. Shows 5 most recent failures. |

### Scheduler (`app/Console/Kernel.php`)
```
email:process-scheduled  → every minute, withoutOverlapping, runInBackground
```
`results:mark-absent` is **not** scheduled — must be run manually or added by operator.

---

## 11. Jobs & Queues

### `SendEmailJob`
- Queue: `emails`.
- `tries = 3`, `backoff = 30` seconds.
- Constructor: accepts `$logId` (int). Resolves `EmailLog` by ID in `handle()`.
- Idempotent: skips if log already `status=sent`.
- `failed()` callback: calls `EmailLog::markFailed()`.
- Worker command: `php artisan queue:work --queue=emails`.

> **Note:** `QUEUE_CONNECTION=database`. Queue table `jobs` is created by migration `2026_06_11_000002_create_jobs_table.php`.

---

## 12. Mail Classes

These are legacy Mailable classes. They are **no longer dispatched directly** by controllers — `EmailService` now handles all sending via `EmailLog` + `SendEmailJob`. The classes remain compilable for backward compatibility.

| Class | Template | Status |
|---|---|---|
| `ExamSubmittedMail` | `emails.exam-submitted` | Legacy |
| `ExamPublishedMail` | `emails.exam-published` | Legacy |
| `CheatingDetectedMail` | `emails.cheating-detected` | Legacy (used by legacy `CheatingDetectionService`) |
| `AccountTerminatedMail` | `emails.account-terminated` | Used by `UserController::terminate()` |

### Email Templates (Blade views in `resources/views/emails/`)
- `layout.blade.php` — base HTML email layout.
- `welcome.blade.php` — fallback welcome email.
- `exam-submitted.blade.php`, `exam-published.blade.php`.
- `cheating-detected.blade.php` — legacy cheating alert (still used by `CheatingDetectionService`).
- `security-warning.blade.php` — Tier 2 violation email.
- `security-terminated.blade.php` — Tier 3 termination email.
- `account-terminated.blade.php`.

---

## 13. UI / View Structure

Layout: `resources/views/layouts/app.blade.php` — single master layout with Bootstrap 5 + Bootstrap Icons.

### Partials (`resources/views/partials/`)
| File | Purpose |
|---|---|
| `admin-sidebar.blade.php` | Admin navigation sidebar |
| `teacher-sidebar.blade.php` | Teacher navigation sidebar |
| `student-sidebar.blade.php` | Student navigation sidebar |
| `sidebar-signout.blade.php` | Sign-out button partial |
| `breadcrumbs.blade.php` | Page breadcrumb component |
| `teacher-form.blade.php` | Shared teacher create/edit form (reused by admin) |
| `assign-courses-form.blade.php` | Course multi-select for teacher/student assignment |
| `course-assignment-checkboxes.blade.php` | Checkbox list of courses |

### View Directories
```
auth/           login.blade.php, register.blade.php
dashboard/      admin.blade.php, teacher.blade.php, student.blade.php
chat/           index.blade.php, conversation.blade.php
notifications/  index.blade.php
certificates/   verify.blade.php (public QR scan page)
pdf/            transcript.blade.php, certificate.blade.php (DomPDF)

admin/
  users/          index, create, edit
  courses/        index, create, edit
  majors/         index, create, edit
  enrollments/    index
  exams/          index, show
  cheating-logs/  index
  security-settings/ index, _toggle.blade.php (partial)
  reattempts/     index, show
  results/        index, student
  students/       index, create, edit, show
  teachers/       index, create, edit, show
  email/          index, smtp, bulk, logs, log-show, scheduled, test
                  templates/ index, create, edit, preview, _form.blade.php
  academic/
    years/        index, create, edit, show, students
    transcripts/  show
    certificates/ index

teacher/
  exams/          index, create, show, edit-question, results
  profile/        show, edit
  reattempts/     index, create
  results/        index

student/
  courses/        index
  exams/          index, show
  exam/           take.blade.php  ← the live exam interface
  reattempts/     index, create
  results/        index
```

---

## 14. JavaScript Flow

### `resources/js/app.js` + `bootstrap.js` (Laravel Mix bundled → `public/js/app.js`)
Provides `window.axios` (with `X-Requested-With: XMLHttpRequest` header) and `window._` (lodash). Pusher/Echo is commented out — real-time broadcasting is not active.

---

### `public/js/exam-anticheat.js` (loaded directly in `student/exam/take.blade.php`)
Self-executing IIFE. Reads configuration from `data-*` attributes on `#examBody`:
- `data-save-url`, `data-violation-url`, `data-submit-url` — API endpoints.
- `data-ends-at` — Unix timestamp for countdown.
- `data-policy-*` — 8 boolean flags from `SecuritySetting::policy()` passed via Blade.

**Policy flags gate listener registration:**
```
policy.fullscreen  → document.fullscreenchange
policy.blur        → window.blur
policy.tabSwitch   → document.visibilitychange
policy.rightClick  → document.contextmenu (preventDefault)
policy.copy        → document.copy + cut (preventDefault)
policy.paste       → document.paste (preventDefault)
policy.keyboard    → document.keydown (blocks F12, Ctrl+Shift+I/J/C, Ctrl+U)
policy.devtools    → (same keydown handler)
```

**Violation flow:**
1. Listener fires → `reportViolation(type, details)`.
2. `fetch(violationUrl, ...)` with JSON body.
3. Server responds: `{warning_count, terminated, locked, message, redirect?}`.
4. `handleViolationResponse(data)`:
   - Shows `#warningBox` for 5 seconds for Tier 1/2.
   - If `data.terminated = true` → calls `lockExamInterface(message)` → redirects after 3s.

**`lockExamInterface(message)` — irreversible shutdown:**
1. Clears all tracked `intervals` and `timeouts`.
2. Removes all detection event listeners.
3. Disables all answer inputs and nav buttons.
4. Exits fullscreen.
5. Appends `#examLockedOverlay` div over the entire viewport.

**Answer saving:**
- MCQ/TF: `click` on `.mcq-option` → `saveAnswer(qId, answerId, null)` via `fetch`.
- Fill-blank: `input` with 800ms debounce.
- Essay: `input` with 1500ms debounce.
- Periodic auto-save: all checked MCQ radios every 10 seconds.

**Timer:** `setInterval` every 1s. Auto-submits form on expiry via native `form.submit()`.

**Submit button `#submitBtn`:** Confirms with unanswered count → stops all intervals → sets `form.action = submitUrl` → `form.submit()`.

**Navigation:** `showQuestion(index)` toggles `.active` class on `.question-block` elements. `refreshNav()` updates sidebar `.q-nav-btn` classes (`active`, `answered`) and progress bar.

---

### `public/js/question-builder.js` (loaded in teacher exam `show` and `edit-question` views)
Self-executing IIFE. Manages the dynamic question form:
- Reads `#qType` select and shows/hides answer blocks accordingly.
- `resetAnswersForType(type)`: Populates default answer rows per type (MCQ=4, TF=2, fill_blank=1 blank).
- `createAnswerRow(label, value, isCorrect)`: Builds input-group HTML with A/B/C/D labels, correct radio, remove button.
- `createBlankAnswerRow(value)`: Builds accepted-answer input for fill-blank.
- `syncCorrectToHidden()`: On form submit, converts `correct_choice` radio selection to `answers[N][is_correct]` hidden fields.
- Edit mode: checks `window.editMode === true` and `window.existingAnswers` to pre-populate via `populateExisting()`.

---

## 15. Dependency Analysis

### Service → Service Dependencies
```
ExamSecurityService
  ├── EmailService
  ├── NotificationService
  ├── ActivityLogService
  └── GradingService

GradingService          (no service deps)
ExamAccessService
  └── EncryptionService
EmailService            (no service deps — uses Eloquent models + Mail facade)
NotificationService     (no service deps)
ReAttemptService
  └── NotificationService
AcademicService         (no service deps)
CertificateService      (no service deps — uses Pdf + QrCode facades)
TranscriptService       (no service deps — uses Pdf facade)
CourseAssignmentService (no service deps)
EncryptionService       (no deps — wraps Crypt facade)
QuestionImportService
  └── EncryptionService
ActivityLogService      (no deps — uses Request facade)
EnsureDefaultAdminService (no deps — uses Schema + Hash facades)
```

### Controller → Service Dependencies
```
ExamSessionController       → ExamAccessService, GradingService, ExamSecurityService
Student\ExamController      → ExamAccessService, GradingService
Teacher\ExamController      → EncryptionService, ExamAccessService, NotificationService,
                               EmailService, QuestionImportService
Admin\ExamController        → ActivityLogService, NotificationService, EmailService
Admin\SecuritySettingsController → ActivityLogService
Admin\ReAttemptController   → ReAttemptService
Admin\AcademicYearController → AcademicService
Admin\TranscriptController  → TranscriptService, AcademicService
Admin\CertificateController → CertificateService
Admin\EmailController       → EmailService
Admin\TeacherController     → CourseAssignmentService, EmailService
Admin\StudentController     → CourseAssignmentService, EmailService
```

### Model → Service Dependencies (via accessor)
```
Question::getDecryptedContentAttribute() → EncryptionService (via app() resolution)
Answer::getDecryptedContentAttribute()   → EncryptionService (via app() resolution)
```

### Critical Chain: Exam Violation → Termination
```
Browser JS
  → POST /student/attempt/{id}/violation
  → EnsureExamActive middleware
  → ExamSessionController::violation()
  → ExamSecurityService::recordViolation()
  → [Tier 3] DB::transaction + lockForUpdate()
  → attempt.status = terminated_pending_review
  → User.exam_session_token = null
  → GradingService::gradeAttempt() [auto-grades]
  → Result::update(DISQUALIFIED)
  → DB::afterCommit()
      → EmailService::send() → SendEmailJob → queue
      → NotificationService::notify() → UserNotification
  → JSON response {terminated: true, redirect: ...}
  → JS: lockExamInterface() → redirect after 3s
```

---

## 16. Feature Impact Analysis

### Feature: Exam Security System
**Touches:** `ExamSecurityService`, `ExamSessionController`, `SecuritySetting`, `ExamAttempt` (status enum + new columns), `CheatingLog` (fingerprint columns), `Result` (DISQUALIFIED status), `exam-anticheat.js`, `admin/security-settings` views, `EnsureExamActive` middleware.

**Impact radius:** Changes to the warning threshold, violation types, or attempt status values will cascade into grading logic, result display, and the admin cheating/security views.

---

### Feature: Re-Attempt System
**Touches:** `ReAttemptRequest`, `ReAttemptLog`, `ReAttemptService`, `ExamAccessService` (window check), `Student\ExamController::start()`, `Admin\ReAttemptController`, `Teacher\ExamController` (reattempt* methods), `Student\ReAttemptController`.

**Impact radius:** Attempt counting logic in `ExamAccessService::studentCanTakeExam()` is directly coupled to re-attempt approval count. Changes to max attempts (currently hardcoded 3) must be updated in both `ExamAccessService` and relevant views.

---

### Feature: Academic System (Transcripts / Certificates)
**Touches:** `AcademicYear`, `YearLevel`, `StudentYearRecord`, `YearlyExamResult`, `YearlyTranscript`, `PromotionHistory`, `CertificateLog`, `AcademicService`, `TranscriptService`, `CertificateService`, all admin academic views, PDF views.

**Impact radius:** Entirely self-contained. No other feature reads `yearly_exam_results` or `yearly_transcripts` directly except the transcript/certificate views.

---

### Feature: Email System
**Touches:** `EmailLog`, `EmailTemplate`, `ScheduledEmail`, `EmailService`, `SendEmailJob`, `ProcessScheduledEmails`, `EmailStats`, `Admin\EmailController`, all `admin/email/*` views.

**Impact radius:** `EmailService::send()` is called by `ExamSecurityService`, `Admin\ExamController`, `Teacher\ExamController`, `Admin\TeacherController`, `Admin\StudentController`. Any breaking change to `EmailService::send()` signature would break all callers.

---

### Feature: Course / Enrollment Management
**Touches:** `Course`, `Enrollment`, `Major`, `CourseAssignmentService`, `Admin\CourseController`, `Admin\EnrollmentController`, `Admin\MajorController`.

**Impact radius:** `Enrollment` records drive `ExamAccessService::studentCanTakeExam()` (enrollment check) and `Admin\ExamController::publish()` (notifies enrolled students). Deleting enrollments can block students from accessing exams they are expected to take.

---

### Feature: Grading
**Touches:** `GradingService`, `StudentAnswer`, `Result`, `ExamAttempt`.

**Impact radius:** Called by `ExamSessionController::submit()` and `ExamSecurityService` (Tier 3 auto-grade). Grade scale (A/B/C/D/F) is hardcoded in `GradingService::calculateGrade()`. Result status is set here for normal submissions; security service overrides with DISQUALIFIED after this call.

---

### Feature: Chat
**Touches:** `ChatMessage` (encrypted), `ChatController`, `chat/*` views.

**Impact radius:** Isolated. No other feature depends on chat. Encryption key rotation would invalidate all stored messages.

---

## 17. Reusable Components

### Blade Partials (safe to include anywhere)
| Partial | Used By |
|---|---|
| `partials/teacher-form.blade.php` | `admin/teachers/create.blade.php`, `admin/teachers/edit.blade.php` |
| `partials/assign-courses-form.blade.php` | Teacher and student CRUD pages |
| `partials/course-assignment-checkboxes.blade.php` | Enrollment form |
| `partials/breadcrumbs.blade.php` | All pages that need breadcrumbs |
| `partials/admin-sidebar.blade.php` | All admin-role pages via `layouts/app.blade.php` |
| `partials/teacher-sidebar.blade.php` | All teacher-role pages |
| `partials/student-sidebar.blade.php` | All student-role pages |
| `admin/security-settings/_toggle.blade.php` | Security settings index page |
| `admin/email/templates/_form.blade.php` | Email template create/edit |

### Services (injectable anywhere)
- `NotificationService::notify()` — single method, zero dependencies, safe to call from any service or controller.
- `ActivityLogService::log()` — same pattern; accepts plain string or array.
- `EncryptionService::encrypt/decrypt()` — pure wrapper, no side effects.
- `EmailService::send()` / `sendTemplate()` — creates a log record and dispatches a job; does not throw unless DB is unavailable.

### Static Model Helpers
- `SecuritySetting::policy()` — returns full config array; safe to call in any Blade view via `@php`.
- `SecuritySetting::get(key, default)` — cached 60min; safe for repeated calls.
- `Major::ensureDefaults()` / `YearLevel::ensureDefaults()` — idempotent seeders.
- `AcademicYear::current()` — convenience scope.
- `EmailTemplate::findBySlug(slug)` — nullable return; safe.

---

## 18. Components That Must Not Be Modified

These components are load-bearing. Changes require extensive regression testing and coordinated updates across dependent files.

### 1. `ExamAttempt::status` ENUM values
`in_progress`, `submitted`, `terminated`, `suspicious`, `terminated_pending_review`, `rejected`.
- Read by: `EnsureExamActive::message()`, `ExamSessionController`, `ExamSecurityService`, `ExamAccessService`, `Result` creation logic.
- MySQL requires raw ALTER to add values — cannot use a simple migration column change.
- **Never remove or rename existing values without a data migration.**

### 2. `Result::exam_result_status` ENUM + constants
`PASSED`, `FAILED`, `ABSENT`, `DISQUALIFIED`.
- Constants defined on the model: `STATUS_PASSED`, `STATUS_FAILED`, `STATUS_ABSENT`, `STATUS_DISQUALIFIED`.
- Read by: `GradingService` (guard), `ExamSecurityService` (Tier 3 override), `MarkAbsentResults` command, all result views, `Result::statusLabel()`, `Result::statusBadgeClass()`.
- **Never change string values without updating all callers.**

### 3. `ExamSecurityService::recordViolation()` return shape
`{warning_count, terminated, locked, message, redirect?}`.
- Consumed directly by `exam-anticheat.js` `handleViolationResponse()`.
- **Changing the JSON key names will silently break the JS without a compile error.**

### 4. `SecuritySetting::policy()` return keys
8 boolean keys + `max_warnings`. These exact keys are read by:
- `ExamSessionController::take()` → passed as `securityPolicy` to view.
- `student/exam/take.blade.php` → written as `data-policy-*` attributes.
- `exam-anticheat.js` → reads `body.dataset.policy*` attributes.
- **The kebab-case mapping in Blade (`policyFullscreen` → `data-policy-fullscreen`) is coupled to the camelCase names in JS. Any rename must update all three files simultaneously.**

### 5. `exam-anticheat.js` — `lockExamInterface()` function
Locks the exam UI irreversibly. This is the terminal safety mechanism. Any change that allows `examLocked = false` to be reset or that removes the overlay would compromise exam integrity.

### 6. `EncryptionService` + `APP_KEY`
All question and answer content is encrypted with Laravel's `APP_KEY`. Rotating `APP_KEY` without re-encrypting all `content_encrypted` columns will make all questions unreadable. The models gracefully return `null` on decrypt failure but the exam will be blank for students.

### 7. `EnsureSingleExamSession` middleware
The `exam.session` middleware alias is applied to **all authenticated routes**, not just exam routes. Removing it would allow concurrent exam sessions. The token comparison logic is fragile — any change must preserve the three-way sync: `users.exam_session_token`, `session['exam_session_token']`, and the attempt's `session_token`.

### 8. `GradingService::gradeAttempt()` DISQUALIFIED guard
```php
if ($existing && $existing->exam_result_status === Result::STATUS_DISQUALIFIED) {
    return $existing;
}
```
This guard prevents grading from overwriting a DISQUALIFIED result. It must not be removed.

### 9. `DB::afterCommit()` callbacks in `ExamSecurityService`
These callbacks fire emails and notifications only **after** the database transaction commits. If converted to direct calls inside the transaction, email delivery could happen before the DB row is committed, causing inconsistent state on failure.

### 10. `CourseAssignmentService` unique constraint handling
`Enrollment` has a unique constraint on (`course_id`, `student_id`). `CourseAssignmentService` uses `updateOrCreate` with a 3-column key (`course_id`, `student_id`, `year`). The `year` column being part of this key is a legacy quirk — if the legacy `year` column is removed, the `updateOrCreate` key must be updated to avoid duplicate inserts.

---

## 19. Regression Risks

### High Risk

**R1 — Exam Session Token Synchronisation**
The three-way token sync (DB column, session, attempt record) in `EnsureSingleExamSession`, `Student\ExamController::start()`, and `ExamSecurityService::recordTierThree()` must remain consistent. Any of the three clearing mechanisms failing leaves a student locked out or able to bypass the single-session guard.

**R2 — Concurrent Termination Race**
`ExamSecurityService::recordTierThree()` uses `lockForUpdate()` to prevent double-termination. Any refactor that moves this logic outside a transaction (e.g., to a queued job) would re-introduce the race condition.

**R3 — Auto-Grade on Termination**
`ExamSecurityService` calls `GradingService::gradeAttempt()` inside the Tier-3 transaction, then immediately overrides `exam_result_status` to DISQUALIFIED. If the order is reversed (override first, then grade), the DISQUALIFIED guard in `GradingService` would prevent grading and `obtained_marks` would remain 0. This is currently the intended behavior but the ordering is not obvious.

**R4 — Question Decryption Access Control**
`ExamAccessService::canDecryptQuestions()` is the only gate. If bypassed (e.g., direct model access in a new controller), question content is accessible outside the exam window.

**R5 — Re-Attempt Window Bypass**
`ExamAccessService::studentCanTakeExam()` checks both the main schedule window and re-attempt windows. If the re-attempt window check is removed, students could bypass main-window restrictions.

---

### Medium Risk

**R6 — Email Queue Worker Not Running**
All emails are queued (`QUEUE_CONNECTION=database`). If `php artisan queue:work` is not running, no emails are delivered. The `email:stats` command will show queued=high, sent=0. This is a deployment configuration issue, not a code bug, but it silently breaks all email notifications.

**R7 — `MarkAbsentResults` Not Scheduled**
The command for marking absent students is not in the scheduler. If not run after each exam closes, result pages will show incomplete data (missing ABSENT records).

**R8 — Enrollment Cascade on Course Delete**
`enrollments` has `cascadeOnDelete` on `course_id`. Deleting a course removes all enrollments, which would block students from future exam access silently (no error, just empty exam list).

**R9 — Legacy `CheatingDetectionService`**
This service is marked `@deprecated` but remains in the codebase. If any new code accidentally injects or calls it (e.g., copy-paste from old commit), it will use the legacy 3-warning hard-coded path instead of the configurable `ExamSecurityService`. The legacy service sets status to `suspicious` (not `terminated_pending_review`), bypassing the review workflow.

**R10 — `applySmtpConfig()` Runtime-Only**
SMTP changes via the admin UI apply only to the current PHP process. A server restart or new worker process will revert to `.env`/config values. This is documented in the service but easy to misunderstand operationally.

---

### Low Risk

**R11 — `SecuritySetting` Cache TTL**
Settings are cached for 3600 seconds. Admin changes take up to 1 hour to propagate to running workers. `SecuritySetting::set()` calls `Cache::forget()` for the specific key, so changes via the UI are immediate for subsequent requests — but the exam-anticheat JS policy is baked into the HTML at page-load time and is not live-updated.

**R12 — `QrCode` SVG in PDF**
Certificates use inline SVG QR codes (no Imagick dependency). The `$qrBase64` variable is always `null` in the PDF view. If the view template references `$qrBase64` instead of `$qrSvg`, the QR code will be missing silently.

**R13 — Legacy `academic_year` Integer on User**
`users.academic_year` (int 1–5) is a legacy column. `CourseAssignmentService` falls back to it when no `StudentYearRecord` exists. New features should use `StudentYearRecord` as the source of truth, not `users.academic_year`.

---
