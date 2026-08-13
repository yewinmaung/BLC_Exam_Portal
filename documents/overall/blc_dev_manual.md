# BLC Developer Manual
## Believe Learning Center — Online Examination System

> **Document Purpose:** Official developer documentation for maintaining and extending the BLC Online Examination System.
> **Generated from:** Live codebase analysis — no assumptions made.
> **Last Updated:** August 2026

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [Application Flow](#2-application-flow)
3. [Database Documentation](#3-database-documentation)
4. [Feature Documentation](#4-feature-documentation)
5. [Code Structure Documentation](#5-code-structure-documentation)
6. [Function and Service Library](#6-function-and-service-library)
7. [Business Rules](#7-business-rules)
8. [Data Flow Diagrams](#8-data-flow-diagrams)
9. [Developer Maintenance Guide](#9-developer-maintenance-guide)
10. [Troubleshooting Guide](#10-troubleshooting-guide)

---

# 1. Project Overview

## Project Name
**BLC Online Examination System** (Believe Learning Center)

## Purpose
A full-featured web-based examination platform for an academic institution. It manages the complete academic lifecycle: student enrollment, online exam delivery, real-time anti-cheat monitoring, automated grading, result reporting, and institutional email communication.

## Main Features
- Role-based access control (Admin / Teacher / Student)
- Academic year and year-level management with student progression records
- Course management with major/semester filtering
- Enrollment system with validation against student year records
- Online exam creation with encrypted questions and answers
- Exam scheduling with configurable open windows and per-student timers
- Live exam session with auto-save, fullscreen enforcement, and violation detection
- 3-tier anti-cheat system with automatic termination at violation 3
- Session recovery for network disconnections (5-minute recovery window)
- Automated grading (MCQ, True/False, Fill-in-the-Blank)
- Result management with PASSED / FAILED / ABSENT / DISQUALIFIED statuses
- Email system: SMTP delivery, queue-based sending, IMAP inbox sync, threaded replies
- Exam timetable email notifications
- Forgot-password OTP flow
- Forced password change for temporary-password accounts
- Login security: account lockout after 3 failed attempts (10-minute lock)
- Profile photo management with OTP verification

## Technology Stack

| Layer | Technology |
|---|---|
| Framework | Laravel (PHP) |
| Frontend | Blade Templates, Bootstrap, Vanilla JS |
| Database | MySQL |
| Queue | Laravel Database Queue (`jobs` table) |
| Email Sending | SMTP via Laravel Mail (queue: `emails`) |
| Email Receiving | IMAP via Webklex Laravel-IMAP 6.2 |
| Encryption | Laravel `Crypt::encryptString()` (AES-256-CBC) |
| Authentication | Laravel built-in `Auth`, Sanctum (API tokens) |
| File Storage | Laravel Storage (public disk) |
| Scheduling | Laravel Console Kernel (Artisan commands) |

## System Architecture Overview

```
Browser (Student / Teacher / Admin)
        │
        ▼
   Routes (web.php)
        │
        ▼
   Middleware Stack
   ┌──────────────────────────────────────┐
   │ auth                                 │
   │ role:{admin|teacher|student}         │
   │ exam.session (EnsureSingleExamSession│
   │ force.password.change                │
   │ exam.active (EnsureExamActive)       │
   └──────────────────────────────────────┘
        │
        ▼
   Controllers (Admin / Teacher / Student / Auth)
        │
        ▼
   Services
   ┌─────────────────────────────────────────────────────────┐
   │ ExamAccessService     — decrypt gate, schedule checks   │
   │ ExamSecurityService   — 3-tier violation enforcement    │
   │ SessionRecoveryService— disconnect/reconnect logic      │
   │ GradingService        — score calculation               │
   │ EmailService          — send / bulk / deliver           │
   │ InboxSyncService      — IMAP fetch + thread resolution  │
   │ AcademicService       — enrollment + history            │
   │ EncryptionService     — encrypt/decrypt wrapper         │
   │ ActivityLogService    — audit trail                     │
   │ NotificationService   — in-app notifications            │
   └─────────────────────────────────────────────────────────┘
        │
        ▼
   Models (Eloquent ORM) ──► MySQL Database
        │
        ▼
   Jobs Queue (emails)
   ┌─────────────────────────────────┐
   │ SendEmailJob                    │
   │ InboxSyncJob                    │
   │ SendExamTimetableNotificationJob│
   │ SendWelcomeAccountJob           │
   │ SendNewTemporaryPasswordJob     │
   │ SendPasswordChangedJob          │
   │ SendProfileOtpJob               │
   └─────────────────────────────────┘
```

---

# 2. Application Flow

## 2.1 Admin Flow

```
Admin Login
    │
    ▼
Admin Dashboard
    │
    ├── User Management ──────────────────────────────────────────────
    │       Create user (admin/teacher/student) → temporary password
    │       generated → SendWelcomeAccountJob dispatched
    │       Terminate account → soft-delete + AccountTerminatedMail
    │       Restore account
    │
    ├── Academic Year Management
    │       Create academic year (name, start_year, end_year, is_current)
    │       Assign students to academic year → creates StudentYearRecord
    │       Remove student from academic year → deletes record
    │
    ├── Major Management
    │       Create / update majors (CS, CT, CST, etc.)
    │       Each major links to courses for Year 2+
    │
    ├── Course Management
    │       Create course (title, code, teacher, year_level, semester, major)
    │       Course tagged with academic_year_id for structural grouping
    │
    ├── Enrollment Management
    │       Select academic year + year level + major + semester
    │       System filters eligible students via StudentYearRecord
    │       Multi-select: course_ids[] + student_ids[]
    │       Validation: year level match, major match, no duplicate enrollment
    │       On success: Enrollment created + NotificationService notifies student
    │
    ├── Exam Management
    │       View all exams grouped: AcademicYear → YearLevel → Semester
    │       Approve pending_approval exams → status: approved
    │       Set schedule (starts_at, ends_at, duration_minutes, attempt_limit)
    │       Publish exam → status: published, is_published on schedule = true
    │       Close exam → status: closed
    │       Reopen exam → status: published
    │       View per-exam results with stats
    │
    ├── Result Management
    │       View results grouped: AcademicYear → YearLevel → Semester → Course → Exam
    │       Drill into individual student result history
    │
    ├── Cheating Logs
    │       View all security violations across all exams
    │       Filter by student name / violation type
    │
    └── Email Management
            Inbox: IMAP sync → threaded view → reply
            Compose: send to individual or recipient group
            Sent / Outbox / Logs with retry
            SMTP settings (runtime, not persisted to .env)
            Exam Timetable Notifications
```

## 2.2 Teacher Flow

```
Teacher Login (forced password change if force_password_change = true)
    │
    ▼
Teacher Dashboard
    │
    ├── Profile
    │       View assigned courses, edit contact info
    │       Course detail: enrolled students list
    │
    ├── Exam Management
    │       Create exam (select course, academic year, total_marks, passing_marks)
    │       Add questions (MCQ, True/False, Fill-in-Blank) — encrypted at save
    │       Import questions from file (txt/pdf/doc/docx)
    │       Submit exam for approval → admins notified
    │       View exam results (after publish)
    │
    └── Results
            View result summary for all their exams
```

## 2.3 Student Flow

```
Student Login (or Register)
    │
    ├── Force Password Change (if force_password_change = true)
    │
    ▼
Student Dashboard
    │
    ├── Courses
    │       View enrolled courses for current academic year
    │
    ├── Exams
    │       List: only published exams for student's academic year + year level
    │       Eligibility check: StudentYearRecord must match exam's AY + course YL
    │       Card states:
    │         "Start Exam"       — within schedule window, attempts remaining
    │         "Continue"         — active in_progress attempt
    │         "Reconnect"        — disconnected attempt within recovery window
    │         "View Result"      — finalized attempt with result
    │         "Terminated/Locked"— security incident
    │
    ├── Exam Attempt Flow
    │       POST /student/exams/{exam}/start
    │           → Validate eligibility (ExamAccessService::studentCanTakeExam)
    │           → Resume active attempt if exists
    │           → Generate question_order (shuffled or natural)
    │           → Compute expires_at = MIN(started_at + duration, schedule.ends_at)
    │           → Create ExamAttempt, set session_token
    │           → Redirect to GET /student/attempt/{attempt}/take
    │
    │       GET /student/attempt/{attempt}/take (EnsureExamActive middleware)
    │           → Session recovery check (disconnected_at set?)
    │           → Timer expiry check (now >= expires_at?)
    │           → Render exam view with decrypted questions
    │
    │       POST /student/attempt/{attempt}/save (auto-save per question)
    │           → StudentAnswer::updateOrCreate
    │
    │       POST /student/attempt/{attempt}/violation
    │           → ExamSecurityService::recordViolation
    │           → Warning 1: warn only
    │           → Warning 2: warn + email teacher/admins
    │           → Warning 3: terminate + grade as DISQUALIFIED
    │
    │       POST /student/attempt/{attempt}/disconnect
    │           → SessionRecoveryService::recordDisconnect
    │           → Sets disconnected_at, last_question_id; status stays in_progress
    │
    │       POST /student/attempt/{attempt}/submit
    │           → status = submitted, GradingService::gradeAttempt
    │
    └── Results
            View own result history across all exams
```

---

# 3. Database Documentation

## 3.1 ERD Relationship Overview

```
roles ──────────────── users (role_id FK)
                         │
          ┌──────────────┼──────────────────────────────────┐
          │              │                                  │
       courses        exam_attempts                 student_year_records
    (teacher_id)      (student_id)                  (student_id)
          │              │                                  │
     enrollments    student_answers              academic_years (AY FK)
  (course_id,       (attempt_id,                year_levels (YL FK)
   student_id)       question_id,
                     answer_id)
       exams ─────────────────────── results
   (course_id,                    (attempt_id,
    teacher_id,                    exam_id,
    academic_year_id)              student_id)
          │
    exam_schedules ───── exam_attempts
    (exam_id)             (schedule_id)
          │
       questions ──── answers
       (exam_id)      (question_id)
          │
    cheating_logs ── exam_attempts
    (attempt_id,      (attempt_id)
     student_id)
```

---

## 3.2 Table: `users`

**Purpose:** Stores all system accounts — Admin, Teacher, Student.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | Auto-increment |
| name | varchar | Full name |
| email | varchar unique | Login identifier |
| password | varchar | Bcrypt hashed |
| role_id | FK → roles | Determines access level |
| is_active | boolean | false = cannot login |
| phone | varchar nullable | Optional contact |
| academic_year | integer nullable | Legacy field (1-5) |
| exam_session_token | varchar nullable | Single-session enforcement token |
| last_login_at | datetime nullable | Updated on successful login |
| profile_photo | varchar nullable | Path in public storage |
| failed_login_attempts | integer default 0 | Counter for lockout |
| locked_until | datetime nullable | Set when lockout triggered |
| temporary_password_expires_at | datetime nullable | 24h expiry for temp passwords |
| temp_password_last_requested_at | datetime nullable | 60s cooldown enforcement |
| force_password_change | boolean default false | Forces change on next login |
| deleted_at | datetime nullable | Soft delete |

**Used by:** Authentication, all role-based access, enrollment, exam attempts, results, email logs.

**Constants (defined in User model):**
- `MAX_FAILED_ATTEMPTS = 3`
- `LOCK_DURATION_MINUTES = 10`
- `TEMP_PASSWORD_EXPIRY_HOURS = 24`
- `TEMP_PASSWORD_REQUEST_COOLDOWN_SECONDS = 60`

---

## 3.3 Table: `roles`

**Purpose:** Defines the three system roles.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | varchar unique | "Admin", "Teacher", "Student" |
| slug | varchar unique | "admin", "teacher", "student" |

**Slugs defined in `App\Enums\RoleSlug`.**

---

## 3.4 Table: `academic_years`

**Purpose:** Represents one academic year (e.g. 2025-2026). Controls which exams and records are "current."

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | varchar | "2025-2026" |
| start_year | year | |
| end_year | year | |
| is_current | boolean | Only one should be true at a time |

**Used by:** `student_year_records`, `courses`, `exams`, enrollment filtering, email recipient resolution.

---

## 3.5 Table: `year_levels`

**Purpose:** Represents academic year levels (Year 1 through Year 5).

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| level | tinyint unsigned | 1–5 |
| name | varchar | "First Year", etc. |
| department | varchar nullable | |
| major | varchar nullable | |

**Used by:** `student_year_records`, `enrollments`, `courses` (via `year_level` integer FK).

---

## 3.6 Table: `student_year_records`

**Purpose:** The authoritative academic record for each student in each academic year and year level. This table drives exam eligibility, enrollment filtering, and result grouping.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| student_id | FK → users | |
| academic_year_id | FK → academic_years | |
| year_level_id | FK → year_levels | |
| semester | varchar default '1' | '1' or '2' |
| department | varchar nullable | |
| major | varchar nullable | Full major name string (e.g. "Computer Technology") |
| gpa | decimal(4,2) nullable | |
| status | enum | active, promoted, failed, withdrawn |
| promoted_at | timestamp nullable | |
| record_type | varchar nullable | NORMAL, TRANSFER, READMISSION (see RecordType enum) |
| remark | text nullable | Admin note on record creation |

**Unique constraint:** `(student_id, academic_year_id, year_level_id, semester)`

**Who creates/updates:** Admin via `AcademicYearController::assignStudents()` using `AcademicService::enrollStudent()`.

**Critical uses:**
- Exam eligibility: `ExamAccessService::studentCanTakeExam()` checks that the student has a matching record for the exam's AY + course year level.
- Enrollment validation: `EnrollmentController::store()` verifies student belongs to the correct year level/major before creating an Enrollment.
- Result grouping: `ResultController::index()` uses this to place results under the correct AY → YL → Semester hierarchy.
- Student exam list: `StudentExamController::index()` fetches only exams whose AY + year level matches the student's records.

---

## 3.7 Table: `majors`

**Purpose:** Defines academic majors (CS, CT, CST, etc.).

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | varchar | "Computer Technology" |
| code | varchar unique | "CT", "CS", "CST" |
| description | text nullable | |
| is_active | boolean | |

**Special rule:** CS and CT students are allowed to enroll in CST courses. This is enforced in `EnrollmentController::store()`.

---

## 3.8 Table: `courses`

**Purpose:** Represents a subject/module taught by a teacher. Tagged with year level, semester, major, and academic year for filtering.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| title | varchar | |
| code | varchar unique | |
| description | text nullable | |
| teacher_id | FK → users nullable | Assigned teacher |
| created_by | FK → users nullable | Admin who created it |
| is_active | boolean | |
| year_level | integer | 0=all years, 1–5=specific year |
| academic_year_id | FK → academic_years nullable | Structural academic year |
| semester | integer | 0=both, 1=Sem1, 2=Sem2 |
| major_id | FK → majors nullable | Required for Year 2+ courses |
| deleted_at | datetime nullable | Soft delete |

**Year level labels (Course model):** `0→All, 1→First Year, 2→Second Year, 3→Third Year, 4→Fourth Year, 5→Fifth Year`

**Semester labels:** `0→Both Semesters, 1→Semester 1, 2→Semester 2`

---

## 3.9 Table: `enrollments`

**Purpose:** Links a student to a course for a specific year level. This is what grants a student access to course exams.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| course_id | FK → courses | |
| student_id | FK → users | |
| enrolled_at | datetime | |
| year | integer nullable | Legacy (stored academic_year_id) |
| year_level_id | FK → year_levels | Proper relational column |
| major_id | FK → majors nullable | |

**Note:** The unique constraint was `(course_id, student_id)` initially. Extended to include `year_level_id` in later migration to allow same student in same course at different year levels.

**Created by:** Admin via `EnrollmentController::store()`.
**Deleted by:** Admin via `EnrollmentController::destroy()`.

---

## 3.10 Table: `exams`

**Purpose:** Represents one exam created by a teacher for a specific course and academic year.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| course_id | FK → courses | |
| academic_year_id | FK → academic_years nullable | Exam's own AY (added in migration 2026_08_07) |
| teacher_id | FK → users | Exam author |
| title | varchar | |
| description | text nullable | |
| status | enum | draft, pending_approval, approved, published, closed |
| total_marks | int unsigned default 100 | Set by teacher |
| passing_marks | int unsigned default 40 | Set by teacher |
| shuffle_questions | boolean | Randomize question order per student |
| submitted_at | datetime nullable | When teacher submitted for approval |
| approved_at | datetime nullable | When admin approved |
| approved_by | FK → users nullable | Admin who approved |
| deleted_at | datetime nullable | Soft delete |

**Status lifecycle:**
```
draft → pending_approval → approved → published → closed
                                  ↗
                  (or directly: approved → published)
                  (closed can be reopened → published)
```

---

## 3.11 Table: `exam_schedules`

**Purpose:** Defines the open window and timing for an exam. One exam has one schedule (immutable after creation).

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| exam_id | FK → exams | |
| starts_at | datetime | When students may begin |
| ends_at | datetime | When the window closes |
| duration_minutes | int unsigned | Per-student countdown |
| attempt_limit | int unsigned default 1 | Max attempts per student |
| target_year | integer nullable | Optional year level filter |
| is_published | boolean | Set true when exam is published |
| published_at | datetime nullable | |
| published_by | FK → users nullable | |

**Key rule:** A schedule is set once and cannot be modified or deleted. `AdminExamController::updateSchedule()` and `deleteSchedule()` both return error responses.

**`expires_at` formula (in ExamController::start):**
```
expires_at = MIN(started_at + duration_minutes, schedule.ends_at)
```
This ensures students starting late cannot exceed the open window.

---

## 3.12 Table: `questions`

**Purpose:** Stores encrypted exam questions.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| exam_id | FK → exams | |
| category_id | FK → question_categories nullable | |
| type | enum | mcq, true_false, essay, fill_blank |
| content_encrypted | longtext | AES-256 encrypted via EncryptionService |
| attachment_path | varchar nullable | File path in public storage |
| attachment_name | varchar nullable | Original filename |
| attachment_mime | varchar nullable | MIME type |
| difficulty | enum | easy, medium, hard |
| marks | int unsigned | Points for this question |
| order | int unsigned | Display order |
| deleted_at | datetime nullable | Soft delete |

**Decryption:** `$question->decrypted_content` accessor calls `EncryptionService::decrypt()`.

---

## 3.13 Table: `answers`

**Purpose:** Stores encrypted answer options for MCQ/True-False and accepted answers for Fill-in-Blank.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| question_id | FK → questions | |
| content_encrypted | longtext | AES-256 encrypted |
| is_correct | boolean | True = correct answer |
| is_blank_answer | boolean | True = accepted fill-in-blank answer |
| order | int unsigned | Display order |

**Fill-in-blank matching:** Case-sensitive exact match. "A" ≠ "a". Teachers must add both if both are acceptable.

---

## 3.14 Table: `exam_attempts`

**Purpose:** Represents one student's session for one exam. Tracks timing, status, warnings, and recovery state.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| exam_id | FK → exams | |
| schedule_id | FK → exam_schedules | |
| student_id | FK → users | |
| attempt_number | int unsigned | 1, 2, … up to attempt_limit |
| status | enum | in_progress, submitted, terminated, suspicious, terminated_pending_review, rejected |
| warning_count | tinyint unsigned | 0–3 (3 = terminated) |
| started_at | datetime nullable | When attempt was created |
| submitted_at | datetime nullable | When submitted or auto-submitted |
| expires_at | datetime nullable | MIN(start + duration, ends_at) |
| session_token | varchar nullable | Matches users.exam_session_token |
| terminated_at | datetime nullable | Set when locked for review |
| approved_by | FK → users nullable | Who approved/rejected |
| approved_at | datetime nullable | |
| approval_comment | text nullable | |
| rejected_by | FK → users nullable | |
| rejected_at | datetime nullable | |
| rejection_comment | text nullable | |
| disconnected_at | datetime nullable | Set on browser close/network drop |
| last_question_id | int nullable | Scroll-resume on reconnect |
| question_order | json nullable | Shuffled question ID array |

**Status semantics:**
- `in_progress` — student is actively taking the exam
- `submitted` — normal or auto-submission
- `terminated` — unconditional security termination (3rd violation)
- `suspicious` — legacy status (not actively used in new flow)
- `terminated_pending_review` — locked pending admin/teacher review
- `rejected` — admin reviewed and rejected the appeal

---

## 3.15 Table: `student_answers`

**Purpose:** Stores each student's answer to each question within an attempt. Updated via auto-save.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| attempt_id | FK → exam_attempts | |
| question_id | FK → questions | |
| answer_id | FK → answers nullable | For MCQ/True-False |
| answer_text | longtext nullable | For fill_blank |
| file_path | varchar nullable | For essay file uploads |
| is_correct | boolean nullable | Set by GradingService |
| marks_awarded | int unsigned nullable | Set by GradingService |

**Unique:** `(attempt_id, question_id)` — one answer per question per attempt.

---

## 3.16 Table: `results`

**Purpose:** Final graded outcome for one exam attempt.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| attempt_id | FK → exam_attempts | |
| exam_id | FK → exams | |
| student_id | FK → users | |
| total_marks | int unsigned | Sum of all question marks |
| obtained_marks | int unsigned | Marks actually earned |
| percentage | decimal(5,2) | (obtained / total) × 100 |
| grade | enum nullable | A/B/C/D/F — removed from active use |
| is_passed | boolean | obtained_marks >= exam.passing_marks |
| is_published | boolean | Visible to student when true |
| exam_result_status | varchar | PASSED, FAILED, ABSENT, DISQUALIFIED |
| violation_reason | text nullable | Set when DISQUALIFIED |
| disqualified_at | datetime nullable | When DISQUALIFIED status was set |
| attendance_status | varchar | attended, absent |
| exam_finished_at | datetime nullable | |

**Constants (Result model):** `STATUS_PASSED`, `STATUS_FAILED`, `STATUS_ABSENT`, `STATUS_DISQUALIFIED`

**DISQUALIFIED rule:** Once set to DISQUALIFIED, `GradingService::gradeAttempt()` will not overwrite it. The guard checks for existing DISQUALIFIED result at the top of the method.

---

## 3.17 Table: `cheating_logs`

**Purpose:** Records each individual security violation during an exam attempt.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| attempt_id | FK → exam_attempts | |
| student_id | FK → users | |
| violation_type | varchar | e.g. fullscreen_exit, tab_switch, devtools_open |
| details | text nullable | Human-readable JS detail string |
| warning_number | tinyint | Per-type count within this attempt |
| user_agent | varchar nullable | Browser string |
| browser | varchar nullable | "Chrome 125" |
| device | varchar nullable | "Desktop" |
| os | varchar nullable | "Windows 11" |
| screen_resolution | varchar nullable | "1920x1080" |
| timezone | varchar nullable | "Asia/Phnom_Penh" |
| ip_address | varchar nullable | |

---

## 3.18 Table: `email_logs`

**Purpose:** Tracks every outbound email sent by the system.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| to_email | varchar | |
| to_name | varchar nullable | |
| from_email | varchar | |
| from_name | varchar nullable | |
| subject | varchar | |
| body_html | longtext nullable | |
| template_slug | varchar nullable | |
| event | varchar nullable | What triggered this email |
| email_type | varchar nullable | welcome, otp, security_warning, etc. |
| status | enum | queued, sent, failed |
| provider | varchar | smtp / log / array |
| error | text nullable | Failure message |
| message_id | varchar nullable | SMTP Message-ID header |
| user_id | FK → users nullable | Recipient user |
| queued_at | timestamp nullable | |
| sent_at | timestamp nullable | |

---

## 3.19 Table: `inbox_emails`

**Purpose:** Stores inbound emails fetched from IMAP. Supports threaded conversations.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| from_email | varchar | Sender email |
| from_name | varchar nullable | |
| sender_type | varchar | 'student' or 'external' |
| user_id | FK → users nullable | If sender is a known user |
| subject | varchar | |
| body_html | text nullable | |
| body_text | text nullable | |
| message_id | varchar | RFC 2822 Message-ID (dedup key) |
| in_reply_to | varchar nullable | Parent message ID |
| references | text nullable | Full References header |
| thread_id | varchar | md5(root_message_id) |
| parent_id | FK → inbox_emails nullable | Direct parent in thread |
| status | varchar | unread, read, archived |
| received_at | datetime | |

---

## 3.20 Table: `jobs` (Queue)

**Purpose:** Laravel database queue table. Stores pending jobs.

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| queue | varchar | Queue name (e.g. "emails") |
| payload | longtext | Serialized job data |
| attempts | tinyint | Attempt counter |
| reserved_at | int nullable | Unix timestamp when picked up |
| available_at | int | When job becomes available |
| created_at | int | Unix timestamp |

## 3.21 Table: `failed_jobs`

**Purpose:** Jobs that exhausted all retries land here for manual inspection.

---

# 4. Feature Documentation

## 4.1 Authentication

### Login Flow

```
POST /login
    │
    ├── 1. Find user by email
    ├── 2. Check isLocked() → if true, show lock countdown
    ├── 3. Auth::attempt(credentials)
    │       if FAIL:
    │         incrementFailedLogins()
    │         if attempts >= 3: set locked_until = now() + 10 min
    │         return remaining attempts message
    │       if SUCCESS:
    ├── 4. Check is_active → if false, logout + error
    ├── 5. Check isTemporaryPasswordExpired() → if true, logout + show resend link
    ├── 6. resetFailedLogins(), update last_login_at
    ├── 7. session()->regenerate()
    └── 8. Redirect to role dashboard
```

### Password Security

- All passwords are hashed with `Hash::make()` (bcrypt).
- Temporary passwords are 12 characters: 3 uppercase + 3 lowercase + 3 digits + 3 symbols, Fisher-Yates shuffled using `random_int()`.
- Temporary passwords expire after 24 hours (`TEMP_PASSWORD_EXPIRY_HOURS = 24`).
- Forced password change: `force_password_change = true` on user record → `ForcePasswordChange` middleware intercepts all requests and redirects to `/password/change`.

### Account Lock System

- After 3 failed attempts: `locked_until = now() + 10 minutes`.
- Lock is checked BEFORE `Auth::attempt()` to prevent timing attacks.
- Lock is cleared automatically when `locked_until` passes (time-based) or when the user successfully logs in after the lock expires.
- `incrementFailedLogins()` and `resetFailedLogins()` are methods on the `User` model.

### Temporary Password Request

- Accessible via `POST /login/request-new-password` (guest middleware).
- Guards: user must have `force_password_change = true`, account must not be locked, 60-second cooldown between requests.
- New temp password generated, hashed, saved. `SendNewTemporaryPasswordJob` dispatched.
- Returns generic success message on all paths to prevent email enumeration.

### OTP-Based Forgot Password

```
GET  /forgot-password            → show email form
POST /forgot-password/send       → generate OTP, send ProfileOtpMail
GET  /forgot-password/verify     → show OTP entry form
POST /forgot-password/check-otp  → validate OTP (stored in profile_otps table)
POST /forgot-password/verify     → if OTP valid, reset password
POST /forgot-password/resend     → resend OTP
```

### Single Session Enforcement (EnsureSingleExamSession middleware)

Applied to all authenticated routes. When a student is taking an exam:
- `users.exam_session_token` is set to a random 60-character string.
- The token is also stored in the PHP session.
- If a second browser/tab attempts to access the system with a different session token, that session is invalidated and the user is logged out with "Another active exam session was detected."
- The token is cleared when the exam is submitted or terminated.

---

## 4.2 Student Academic Records

### Record Types (RecordType enum)

| Value | Meaning |
|---|---|
| `NORMAL` | Standard academic progression (default if NULL) |
| `TRANSFER` | Student transferred from another institution |
| `READMISSION` | Student re-admitted after withdrawal/failure |

All values are in `App\Enums\RecordType::ALL`.

### How Records Are Created

Admin goes to Academic Year → Students → Assign Students. This calls `AcademicService::enrollStudent()` which calls `StudentYearRecord::firstOrCreate()` with `(student_id, academic_year_id, year_level_id, semester)`.

### Validation Rules for Records

- One record per `(student_id, academic_year_id, year_level_id, semester)` combination.
- `record_type` can be NORMAL, TRANSFER, or READMISSION. NULL is treated as NORMAL.
- `remark` field is an optional admin note for context.
- `status` can be: active, promoted, failed, withdrawn.

### How Records Drive the System

Every significant feature reads `student_year_records` to determine where a student belongs:

1. **Exam list:** `StudentExamController::index()` queries `StudentYearRecord` to get the student's `(academic_year_id, year_level_id)` pairs, then filters exams to those matching.
2. **Exam eligibility:** `ExamAccessService::studentCanTakeExam()` verifies a matching record exists for the exam's AY + course year level.
3. **Enrollment filtering:** `EnrollmentController::studentsByYearLevel()` returns only students who have a `StudentYearRecord` for the selected year level + AY.
4. **Enrollment validation:** `EnrollmentController::store()` rejects enrollment if no matching record exists.
5. **Result grouping:** `ResultController::index()` uses records to place results in the correct AY → YL → Semester bucket.
6. **Academic history:** `AcademicService::getStudentHistory()` iterates records to build the history view.

---

## 4.3 Course System

### Course Creation

Admin creates courses via `AdminCourseController`. Key fields:
- `year_level`: 0=all years, 1–5=specific year. Controls which students can be enrolled.
- `semester`: 0=both, 1=Sem1, 2=Sem2. Used to restrict enrollment to a specific semester.
- `major_id`: Required for Year 2+ courses. Year 1 only allows CST major courses.
- `teacher_id`: The teacher assigned to teach and create exams for this course.
- `academic_year_id`: Structural grouping (not used for eligibility filtering — that uses exam's own AY).

### Major Assignment Rules

- Year 1 students are always in CST. Only CST courses can be enrolled for Year 1.
- Year 2+ students belong to CS, CT, or other majors.
- **Special rule:** CS and CT students can enroll in CST courses (enforced in `EnrollmentController::store()`).
- The `student_year_records.major` column stores the **full major name** (e.g. "Computer Technology"), not the code.

### Historical Data Behaviour

Courses use `SoftDeletes`. A deleted course still retains its enrollment and exam history. The soft delete only hides the course from active management views.

Course data on existing exams and results is never destroyed, because `exams.course_id` has `cascadeOnDelete` only in the initial migration — soft deleted courses still resolve via Eloquent.

---

## 4.4 Enrollment System

### How Students Get Courses

1. Admin opens Enrollments page.
2. Admin selects: Academic Year + Year Level + Major + Semester.
3. An AJAX call to `GET /admin/enrollments/students-by-year-level` returns eligible students filtered by their `StudentYearRecord`.
4. Admin selects one or more students + one or more courses.
5. `EnrollmentController::store()` is called.

### Enrollment Validation (in order)

1. Student must have a `StudentYearRecord` matching the selected year level + AY.
2. For Year 2+: student's record major name must match the submitted major.
3. Course `year_level` must match selected year level (or be 0 = all years).
4. Course `semester` must match selected semester (or be 0 = both semesters).
5. Course `major_id` must match (with the CS/CT→CST special rule for Year 2+).
6. No duplicate enrollment for the same `(course_id, student_id, year_level_id)`.

On success: `Enrollment` created + student receives in-app notification.

### Course-to-Student Connection

`Course::students()` returns a `belongsToMany(User, 'enrollments', 'course_id', 'student_id')`.
`ExamAccessService::studentCanTakeExam()` uses `$exam->course->enrollments()->where('student_id', $user->id)->exists()`.

---

## 4.5 Online Exam System

### Exam Creation (Teacher)

1. Teacher selects course + academic year, sets title, total_marks, passing_marks.
2. Exam created with `status = draft`.
3. Teacher adds questions (encrypted on save via `EncryptionService::encrypt()`).
4. Question mark validation: sum of all question marks must exactly equal `total_marks` before submission is allowed.
5. Teacher submits for approval → `status = pending_approval`, admins notified.

### Exam Approval and Scheduling (Admin)

```
pending_approval
    │ Admin::approve()
    ▼
approved
    │ Admin::schedule() — sets exam_schedules record (immutable after creation)
    │ Admin::publish()  — status = published, schedule.is_published = true
    ▼
published  ←──── Admin::open() (reopen from closed)
    │ Admin::close()
    ▼
closed
```

**Schedule is set once.** `updateSchedule()` and `deleteSchedule()` both reject the request.

### Attempt Rules

- Student must be enrolled in the exam's course AND have a `StudentYearRecord` for the exam's AY + year level.
- Student must be within `schedule.starts_at` to `schedule.ends_at`.
- Used attempts (submitted/terminated/suspicious/rejected) must be less than `schedule.attempt_limit`.
- If an `in_progress` attempt already exists, the start redirects to that attempt (resume).

### Timer Logic

```
expires_at = MIN(now() + schedule.duration_minutes, schedule.ends_at)
```

This is calculated at attempt creation. A student who starts 5 minutes before `ends_at` with a 60-minute exam gets only 5 minutes.

On every page load of `/take`, the server checks:
1. `now() >= attempt.expires_at` → auto-submit
2. `now() >= schedule.ends_at` → auto-submit (belt-and-braces for legacy attempts)

### Session Recovery

For temporary disconnections (browser close, network drop):

```
POST /student/attempt/{attempt}/disconnect
    │ SessionRecoveryService::recordDisconnect()
    │ Sets disconnected_at = now(), last_question_id
    │ Status stays in_progress
    ▼
Student returns within 5 minutes AND before expires_at
    │ GET /student/attempt/{attempt}/take
    │ SessionRecoveryService::handleReconnect()
    │ Path A (recoverable):
    │   frozen_seconds = expires_at − disconnected_at
    │   Clear disconnected_at
    │   Render exam with frozen timer
    │
    │ Path B (expired):
    │   status = submitted, GradingService grades with saved answers
    └   Redirect with message
```

**Recovery time limit:** 5 minutes (300 seconds), configurable via `EXAM_RECOVERY_TIME_LIMIT` in `.env`, defined in `config/exam_security.php`.

**Key invariants during recovery:**
- No new attempt is created.
- `expires_at` is never modified.
- `student_answers` are never deleted.
- `warning_count` is not touched.

### Anti-Cheat System (ExamSecurityService)

Violations detected client-side by `exam-anticheat.js`:
- Fullscreen exit
- Tab switch
- Window blur
- Right-click
- Copy (Ctrl+C)
- Paste (Ctrl+V)
- DevTools open
- Keyboard shortcuts (Ctrl+U, F12, etc.)

Each violation is reported via `POST /student/attempt/{attempt}/violation`.

**3-tier sequence (fixed — not configurable):**

| Violation # | Action |
|---|---|
| 1 | Record CheatingLog. `warning_count = 1`. Warn student in UI. Continue. |
| 2 | Record CheatingLog. `warning_count = 2`. Warn student. Email teacher + all admins. Notify admins. Continue. |
| 3 | DB transaction with `lockForUpdate()`. `status = terminated`. `warning_count = 3`. Grade attempt. Set result as DISQUALIFIED, `is_passed = false`, `is_published = false`. Email student + teacher + admins (high priority). |

`MAX_VIOLATIONS = 3` is a hard-coded constant. It cannot be changed from admin UI.

**Concurrent request protection:** Violation 3 uses `ExamAttempt::lockForUpdate()->find()` inside a DB transaction to prevent two simultaneous requests both triggering termination.

### Auto Submission

Auto-submission occurs when:
- Student clicks Submit button.
- Timer hits zero (`expires_at` passed on page load).
- Schedule ends while attempt is active (belt-and-braces check).
- Session recovery window expires (Path B in `SessionRecoveryService`).
- Third security violation (ExamSecurityService handles this separately).

All paths call `GradingService::gradeAttempt()`.

---

## 4.6 Result System

### Result Generation

`GradingService::gradeAttempt(ExamAttempt $attempt): Result`

```
Step 1: Guard — if existing result.exam_result_status == DISQUALIFIED, return unchanged.

Step 2: total_marks = sum of all question marks in the exam.

Step 3: For each StudentAnswer:
    MCQ / True_False:
        is_correct = (answer exists AND answer.is_correct = true)
        marks_awarded = is_correct ? question.marks : 0
    Fill_Blank:
        student_text = trim(answer_text)
        accepted = question.answers where is_blank_answer = true
        is_correct = accepted.contains(student_text)  [case-sensitive exact match]
        marks_awarded = is_correct ? question.marks : 0
    Unanswered questions: no StudentAnswer row = 0 marks automatically.

Step 4: percentage = (obtained_marks / total_marks) * 100, rounded to 2 decimals.

Step 5: is_passed = (obtained_marks >= exam.passing_marks)

Step 6: Result::updateOrCreate with:
    exam_result_status = is_passed ? STATUS_PASSED : STATUS_FAILED
    attendance_status  = ATTENDANCE_ATTENDED
    is_published       = true
```

### Score Calculation Formula

```
percentage = round((obtained_marks / total_marks) * 100, 2)
is_passed  = obtained_marks >= exam.passing_marks
```

Grade column exists in the schema but is no longer populated (removed from GradingService).

### Result Statuses

| Status | Condition |
|---|---|
| PASSED | obtained_marks >= passing_marks |
| FAILED | obtained_marks < passing_marks |
| ABSENT | enrolled but no attempt / no answers (display-only, set by MarkAbsentResults command) |
| DISQUALIFIED | 3rd security violation — set by ExamSecurityService, protected from overwrite |

### ABSENT Status

`ExamAttempt::isDisplayedAsAbsent()` is a display-only helper. It returns true when:
- `status === 'in_progress'` (never submitted)
- Schedule window has ended
- No student answers were saved

The `MarkAbsentResults` Artisan command creates actual ABSENT Result records for enrolled students who have no result after the schedule ends.

### Result Grouping (Admin ResultController)

Results are grouped under `AcademicYear → YearLevel → Semester → Course → Exam` using `StudentYearRecord` as the authoritative source. Matching priority:
1. Exact: course.year_level = record level AND course.semester = record semester
2. Year level matches (course covers both semesters)
3. Semester matches (course covers all year levels)
4. Fallback: most recent record

---

## 4.7 Email System

### Email Architecture

```
Trigger (controller/service/job)
    │
    ▼
EmailService::send()
    │ Creates EmailLog (status = 'queued')
    │ Dispatches SendEmailJob to 'emails' queue
    ▼
Queue Worker processes SendEmailJob
    │
    ▼
EmailService::deliver(EmailLog)
    │ Mail::send() via SMTP
    ├── Success → log.status = 'sent', log.sent_at = now()
    └── Failure → log.status = 'failed', log.error = message
```

### Email Types

| Type | Trigger | Job |
|---|---|---|
| Welcome + temp password | Admin creates user | `SendWelcomeAccountJob` |
| New temporary password | User requests resend | `SendNewTemporaryPasswordJob` |
| Password changed | User changes password | `SendPasswordChangedJob` |
| Profile OTP | User updates email/phone | `SendProfileOtpJob` |
| Exam timetable | Admin sends timetable notification | `SendExamTimetableNotificationJob` |
| Security warning (violation 2) | ExamSecurityService | Inline via `EmailService::send()` |
| Security terminated (violation 3) | ExamSecurityService | Inline via `EmailService::send()` (after commit) |
| Account terminated | Admin terminates user | `AccountTerminatedMail` |
| Cheating detected | Legacy | `CheatingDetectedMail` |

### Queue Configuration

All email jobs use the `emails` queue. The queue worker must be running:
```
php artisan queue:work --queue=emails
```
Or for production with supervisor. `SendEmailJob` has `tries = 3`, `backoff = 30` seconds.

### Inbox Sync (IMAP)

Admin triggers sync via `POST /admin/email/inbox/sync`. This dispatches `InboxSyncJob` to the `emails` queue. `InboxSyncJob` calls `InboxSyncService::sync()`:

```
Connect to IMAP (Webklex, 'default' account)
    │
    ▼
Fetch latest N messages (IMAP_SYNC_LIMIT, default 20, max 200)
  - Envelope only (setFetchBody(false)) for performance
    │
    ▼
For each message:
  - Extract Message-ID → dedup check (inbox_emails table)
  - Fetch body only for new messages
  - Resolve thread: References header → find root message → compute thread_id = md5(root)
  - Identify sender (student or external)
  - Persist to inbox_emails
  - Broadcast NewEmailReceived event
```

**Thread resolution algorithm:**
1. Parse References header into ordered Message-ID list.
2. Walk oldest → newest, find first match in `inbox_emails`.
3. Use that match's `thread_id` as the thread root.
4. Walk newest → oldest for `parent_id`.
5. If no match found, `thread_id = md5(this_message_id)` (new thread).

### SMTP Settings

Admin can configure SMTP via `POST /admin/email/smtp`. This calls `EmailService::applySmtpConfig()` which updates runtime Laravel config with `config([...])`. **These settings are NOT persisted to `.env`** and reset after server restart. To make permanent changes, update `.env` directly.

### Bulk Email

`EmailService::sendBulk()` accepts a `recipientGroup` string:
- `all_students` — all active students
- `all_teachers` — all active teachers
- `first_year` through `final_year` — students at that year level via `StudentYearRecord`
- `all_users` — all active users

Per-recipient variable substitution: `{{student_name}}`, `{{course_name}}`, `{{academic_year}}`, `{{year_level}}`, `{{semester}}`, `{{department}}`, `{{major}}`, etc.

---

# 5. Code Structure Documentation

## 5.1 Folder Responsibilities

```
app/
├── Console/
│   └── Commands/
│       ├── EmailStats.php          — CLI: report email queue statistics
│       └── MarkAbsentResults.php   — CLI: create ABSENT Result records for
│                                    students who didn't take a closed exam
│
├── Enums/
│   ├── RecordType.php  — NORMAL, TRANSFER, READMISSION constants
│   └── RoleSlug.php    — admin, teacher, student constants
│
├── Events/
│   └── NewEmailReceived.php  — Fired when a new inbox email is imported.
│                               Used by frontend polling / broadcast
│
├── Exceptions/
│   └── Handler.php  — Global exception handler (404, 403, validation, etc.)
│
├── Http/
│   ├── Controllers/
│   │   ├── Admin/         — All admin-only actions
│   │   ├── Auth/          — Login, logout, forgot password, force change
│   │   ├── Student/       — Student exam list, exam session, results, courses
│   │   ├── Teacher/       — Teacher exam management, profile, results
│   │   ├── Controller.php         — Base controller
│   │   ├── DashboardController.php— Role-specific dashboard data
│   │   ├── NotificationController.php — In-app notification read/count
│   │   └── ProfileController.php  — Shared profile (photo, password)
│   │
│   ├── Middleware/
│   │   ├── Authenticate.php          — Redirects unauthenticated users
│   │   ├── EnsureExamActive.php      — Rejects requests on non-in_progress attempts
│   │   ├── EnsureSingleExamSession.php — Single-tab enforcement for exam sessions
│   │   ├── ForcePasswordChange.php   — Intercepts requests for temp-password users
│   │   └── RoleMiddleware.php        — Checks user.role.slug against allowed roles
│   │
│   └── Kernel.php  — Middleware registration (exam.session, exam.active, role, force.password.change)
│
├── Jobs/
│   ├── SendEmailJob.php                     — Delivers one email_log via SMTP (queue: emails)
│   ├── InboxSyncJob.php                     — Runs IMAP sync in background (queue: emails)
│   ├── SendExamTimetableNotificationJob.php — Sends timetable emails to target students
│   ├── SendNewTemporaryPasswordJob.php      — Sends new temp password to user
│   ├── SendPasswordChangedJob.php           — Notifies user of password change
│   ├── SendProfileOtpJob.php                — Sends OTP for profile update
│   └── SendWelcomeAccountJob.php            — Sends welcome email + temp password
│
├── Mail/
│   ├── AccountTerminatedMail.php        — Mailable for account termination
│   ├── CheatingDetectedMail.php         — Legacy: cheating notification
│   ├── ExamTimetableNotificationMail.php— Mailable for timetable notification
│   ├── PasswordChangedMail.php          — Mailable for password change confirmation
│   └── ProfileOtpMail.php               — Mailable for profile OTP
│
├── Models/
│   ├── AcademicYear.php      — academic_years table
│   ├── ActivityLog.php       — activity_logs table
│   ├── Answer.php            — answers table (encrypted)
│   ├── CheatingLog.php       — cheating_logs table
│   ├── Course.php            — courses table (soft delete)
│   ├── EmailLog.php          — email_logs table
│   ├── Enrollment.php        — enrollments table
│   ├── Exam.php              — exams table (soft delete)
│   ├── ExamAttempt.php       — exam_attempts table
│   ├── ExamSchedule.php      — exam_schedules table
│   ├── InboxEmail.php        — inbox_emails table
│   ├── Major.php             — majors table
│   ├── Question.php          — questions table (encrypted, soft delete)
│   ├── Result.php            — results table
│   ├── Role.php              — roles table
│   ├── SessionRecoveryLog.php— session_recovery_logs table
│   ├── StudentAnswer.php     — student_answers table
│   ├── StudentYearRecord.php — student_year_records table
│   ├── User.php              — users table (soft delete)
│   ├── UserNotification.php  — user_notifications table
│   └── YearLevel.php         — year_levels table
│
└── Services/
    ├── AcademicService.php       — Student enrollment into year records + history
    ├── ActivityLogService.php    — Writes to activity_logs
    ├── EmailService.php          — Core email sending, bulk, SMTP config, delivery
    ├── EncryptionService.php     — Crypt::encryptString / decryptString wrapper
    ├── ExamAccessService.php     — Decrypt gates, schedule checks, exam eligibility
    ├── ExamSecurityService.php   — 3-tier violation enforcement, approve/reject
    ├── GradingService.php        — Score calculation, Result creation
    ├── InboxSyncService.php      — IMAP fetch, thread resolution, dedup, persist
    ├── NotificationService.php   — Creates UserNotification records
    ├── QuestionImportService.php — Parses txt/doc/pdf files into Question records
    └── SessionRecoveryService.php— Disconnect recording, reconnect evaluation, auto-submit
```

## 5.2 Resources Folder

```
resources/
├── views/
│   ├── admin/
│   │   ├── academic/          — Academic year management views
│   │   ├── cheating-logs/     — Cheating log list view
│   │   ├── courses/           — Course CRUD views
│   │   ├── email/             — Inbox, compose, sent, outbox, logs, SMTP, test, timetable
│   │   ├── enrollments/       — Enrollment management view
│   │   ├── exams/             — Exam list, show, results views
│   │   ├── majors/            — Major management views
│   │   ├── results/           — Result summary + student drill-down
│   │   ├── students/          — Student management views
│   │   ├── teachers/          — Teacher management views
│   │   └── users/             — User CRUD views
│   ├── auth/
│   │   ├── login.blade.php
│   │   ├── forgot-password.blade.php
│   │   ├── forgot-password-verify.blade.php
│   │   └── force-password-change.blade.php
│   ├── dashboard/
│   │   ├── admin.blade.php
│   │   ├── teacher.blade.php
│   │   └── student.blade.php
│   ├── emails/                — HTML email templates (Blade)
│   ├── layouts/
│   │   └── app.blade.php      — Main layout (sidebar, navbar, notification badge)
│   ├── partials/
│   │   ├── admin-sidebar.blade.php
│   │   ├── teacher-sidebar.blade.php
│   │   └── student-sidebar.blade.php
│   ├── profile/
│   │   └── show.blade.php     — Shared profile view (all roles)
│   ├── student/
│   │   ├── courses/index.blade.php
│   │   ├── exam/take.blade.php    — Live exam interface with anti-cheat JS
│   │   ├── exams/index.blade.php
│   │   ├── exams/show.blade.php
│   │   └── results/index.blade.php
│   └── teacher/
│       ├── exams/             — Create, show, results views
│       ├── profile/show.blade.php
│       └── results/index.blade.php
```

## 5.3 Routes

```
routes/web.php  — All web routes

Route groups:
  guest middleware:
    GET/POST /login
    GET/POST /register
    POST     /login/request-new-password
    GET/POST /forgot-password/*

  auth middleware:
    POST /logout
    GET/POST /password/change   (force-change, all roles)

  auth + exam.session + force.password.change:
    /notifications/*
    /profile/*

    prefix /admin (role:admin):
      dashboard, users, courses, majors, enrollments
      exams (approve, schedule, publish, close, open, results)
      academic/years + students
      teachers, students
      results
      email (inbox, compose, sent, outbox, logs, smtp, timetable)
      cheating-logs

    prefix /teacher (role:teacher,admin):
      dashboard, profile
      exams (create, store, show, questions, submit, results, import)
      results

    prefix /student (role:student):
      dashboard, courses, exams
      exams/{exam}/start
      attempt/{attempt}/take    (+ exam.active middleware)
      attempt/{attempt}/save    (+ exam.active middleware)
      attempt/{attempt}/violation (+ exam.active middleware)
      attempt/{attempt}/disconnect
      attempt/{attempt}/submit  (+ exam.active middleware)
      results
```

## 5.4 Database Folder

```
database/
├── migrations/   — 40+ migration files in chronological order
├── seeders/
│   └── DatabaseSeeder.php  — Seeds roles (admin/teacher/student) and default admin user
└── factories/    — (not heavily used in production)
```

---

# 6. Function and Service Library

## 6.1 ExamAccessService

**File:** `app/Services/ExamAccessService.php`

---

**`canDecryptQuestions(User $user, Exam $exam): bool`**
- Purpose: Determines if a user may see decrypted question text.
- Input: User model, Exam model.
- Logic: Admin always true. Teacher if owns exam. Student: exam status approved/published + (active attempt OR within schedule window).
- Output: bool
- Used by: `ExamSessionController::renderExamView()`, `TeacherExamController::show()`

---

**`studentCanTakeExam(User $user, Exam $exam): bool`**
- Purpose: Full eligibility check before creating a new attempt.
- Input: User, Exam.
- Checks (all must pass): user is student, exam is approved/published, student has `student_schedule`, student is enrolled in course, student has matching `StudentYearRecord` for exam's AY + course year level, used attempts < `attempt_limit`, schedule is currently active.
- Output: bool
- Used by: `StudentExamController::start()`, `StudentExamController::show()`

---

**`canViewCorrectAnswers(User $user, Exam $exam): bool`**
- Purpose: Whether the student may see which answers were correct.
- Input: User, Exam.
- Logic: Admin/teacher always true. Student: only after `latestSchedule.ends_at` has passed.
- Output: bool
- Used by: `StudentExamController::show()`

---

**`scheduleHasEnded(Exam $exam): bool`**
- Purpose: Quick check whether the exam's latest schedule is in the past.
- Used by: exam show pages

---

**`decryptContent(User $user, Exam $exam, ?string $encrypted): ?string`**
- Purpose: Decrypt question/answer content only if the user passes access checks.
- Output: decrypted string or null if access denied.
- Used by: `ExamSessionController::renderExamView()`

---

## 6.2 ExamSecurityService

**File:** `app/Services/ExamSecurityService.php`

---

**`recordViolation(ExamAttempt $attempt, string $type, ?string $details, array $client, string $ip): array`**
- Purpose: Main entry point for all client-side violation reports.
- Input: Fresh attempt, violation type string, detail string, client metadata array, IP string.
- Output: JSON array `{warning_count, terminated, locked, message, redirect?}`
- Logic: Routes to `recordWarning()` (violations 1-2) or `recordViolationThree()` (violation 3).
- Used by: `ExamSessionController::violation()`

---

**`approve(ExamAttempt $attempt, User $actor, ?string $comment): void`**
- Purpose: Admin/teacher approves a `terminated_pending_review` attempt, restoring it to `in_progress`.
- Input: Attempt, approving user, optional comment.
- Side effects: Extends `expires_at` by locked duration (capped at `max_resume_extension_minutes`). Notifies student.
- Used by: Admin security incident review page

---

**`reject(ExamAttempt $attempt, User $actor, ?string $comment): void`**
- Purpose: Admin/teacher rejects the appeal. Status → `rejected`.
- Side effects: Notifies student with rejection message.
- Used by: Admin security incident review page

---

**`getRecipients(ExamAttempt $attempt): Collection`**
- Purpose: Returns deduplicated list of responsible teacher + all active admins.
- Used by: Warning 2 and Violation 3 email/notification sending

---

## 6.3 GradingService

**File:** `app/Services/GradingService.php`

---

**`gradeAttempt(ExamAttempt $attempt): Result`**
- Purpose: Calculate score and create/update the Result record.
- Input: ExamAttempt with `studentAnswers.answer` and `studentAnswers.question` eager-loaded.
- Steps: DISQUALIFIED guard → total marks → obtained marks (MCQ/TF/fill_blank) → percentage → pass/fail → `Result::updateOrCreate`.
- Output: Result model.
- Used by: `ExamSessionController::submitAttempt()`, `ExamSecurityService::recordViolationThree()`, `SessionRecoveryService::finalizeExpiredSession()`

---

## 6.4 SessionRecoveryService

**File:** `app/Services/SessionRecoveryService.php`

---

**`recordDisconnect(ExamAttempt $attempt, ?int $questionId, string $reason, array $browserInfo): SessionRecoveryLog`**
- Purpose: Record a temporary disconnect. Sets `disconnected_at` and `last_question_id`. Status stays `in_progress`.
- Used by: `ExamSessionController::disconnect()`

---

**`handleReconnect(ExamAttempt $attempt): array`**
- Purpose: Evaluate whether to restore or finalize a disconnected attempt.
- Returns: `{success: bool, message: string, frozen_seconds?: int}`
- Path A: Clears `disconnected_at`, returns frozen seconds for timer.
- Path B: Calls `finalizeExpiredSession()`, returns `success: false`.
- Used by: `ExamSessionController::take()`

---

**`computeNormalSeconds(ExamAttempt $attempt, ?object $schedule): int`**
- Purpose: Returns remaining seconds for a normal (non-recovery) page load.
- Formula: `MIN(expires_at − now, schedule.ends_at − now)`, minimum 0.
- Used by: `ExamSessionController::take()`

---

## 6.5 EmailService

**File:** `app/Services/EmailService.php`

---

**`send(string $toEmail, string $toName, string $subject, string $bodyHtml, ...): EmailLog`**
- Purpose: Create an `EmailLog` and optionally dispatch `SendEmailJob`.
- Input: Recipient, subject, HTML body, event string, template slug, user ID, queue flag.
- Output: EmailLog model.
- Used by: All email-sending flows throughout the application.

---

**`deliver(EmailLog $log): void`**
- Purpose: Actually send the email via `Mail::send()`. Called by `SendEmailJob`.
- On success: `log->markSent()`
- On failure: `log->markFailed($message)`

---

**`sendBulk(string $recipientGroup, string $subject, string $bodyHtml, ...): int`**
- Purpose: Send personalised emails to a named recipient group.
- Returns: Count of emails dispatched.
- Used by: Email compose bulk send

---

**`resolveAcademicRecipients(array $academicYearIds, array $yearLevelIds, array $majorIds): Collection`**
- Purpose: Find active students matching the given academic filters. Used for timetable notifications.
- Logic: Queries `student_year_records` with `status = active` filtered by provided AY/YL/Major arrays.
- Used by: `SendExamTimetableNotificationJob`

---

**`retry(EmailLog $log): void`**
- Purpose: Reset failed log to `queued` and redispatch `SendEmailJob`.
- Used by: Admin email logs retry button

---

## 6.6 InboxSyncService

**File:** `app/Services/InboxSyncService.php`

---

**`sync(): array`**
- Purpose: Fetch latest messages from IMAP INBOX and import new ones.
- Returns: `{imported: int, skipped: int, errors: int, message: string}`
- Used by: `InboxSyncJob::handle()`

---

## 6.7 AcademicService

**File:** `app/Services/AcademicService.php`

---

**`enrollStudent(User $student, AcademicYear $ay, YearLevel $yl, string $semester, ...): StudentYearRecord`**
- Purpose: Create or retrieve a `StudentYearRecord`. Uses `firstOrCreate`.
- Used by: `AcademicYearController::assignStudents()`

---

**`getStudentHistory(User $student): array`**
- Purpose: Build full academic history across all year records with matching results.
- Returns: Array of `{record: StudentYearRecord, results: Collection<Result>}`.
- Used by: `AdminResultController::student()`, student profile history view

---

## 6.8 EncryptionService

**File:** `app/Services/EncryptionService.php`

---

**`encrypt(?string $value): ?string`**
- Calls `Crypt::encryptString()`. Returns null/empty unchanged.

**`decrypt(?string $value): ?string`**
- Calls `Crypt::decryptString()`. Returns null on `RuntimeException` (prevents crash on bad data).

Used for: Question `content_encrypted`, Answer `content_encrypted`. Both have accessor `decrypted_content` that calls this service.

---

## 6.9 Key Jobs

| Job | Queue | Tries | Backoff | Purpose |
|---|---|---|---|---|
| `SendEmailJob` | emails | 3 | 30s | Deliver one `EmailLog` via SMTP |
| `InboxSyncJob` | emails | 2 | 60s | Run full IMAP sync |
| `SendExamTimetableNotificationJob` | emails | 3 | 30s | Send timetable emails to academic recipients |
| `SendWelcomeAccountJob` | emails | 3 | 30s | Welcome + temp password email |
| `SendNewTemporaryPasswordJob` | emails | 3 | 30s | New temp password email |
| `SendPasswordChangedJob` | emails | 3 | 30s | Password change confirmation |
| `SendProfileOtpJob` | emails | 3 | 30s | OTP for profile update |

---

# 7. Business Rules

## 7.1 Academic Progression Rules

### Normal Progression
- Student receives a `StudentYearRecord` for each academic year they are enrolled in, at a specific year level and semester.
- `record_type = NORMAL` (or NULL, treated as NORMAL).
- Exam eligibility requires a matching record for the exam's AY + course year level.
- No automatic promotion is built in. Admin manually creates records for the next year level in the next academic year.

### Transfer Student
- Admin creates a `StudentYearRecord` with `record_type = TRANSFER`.
- Transfer students can be placed at any year level, regardless of prior enrollment history in the system.
- The system treats TRANSFER records identically to NORMAL for all eligibility and filtering logic.
- `remark` field should document the originating institution/context.

### Re-admission
- Admin creates a `StudentYearRecord` with `record_type = READMISSION`.
- Student was previously withdrawn or failed. They are re-admitted at a specific year level.
- Again treated identically to NORMAL for system logic — the distinction is for administrative record-keeping.

---

## 7.2 Exam Rules

### Attempt Count
- `exam_schedules.attempt_limit` defines the maximum attempts per student (default 1).
- Counted attempts: statuses `submitted`, `terminated`, `suspicious`, `rejected`.
- `in_progress` and `terminated_pending_review` are NOT counted as used — a student with a `terminated_pending_review` attempt must wait for admin review before trying again.
- `ExamAccessService::studentCanTakeExam()` enforces the limit.

### Recovery Window
- Default: 300 seconds (5 minutes). Configurable via `EXAM_RECOVERY_TIME_LIMIT` in `.env`.
- Recovery is only allowed if BOTH conditions are true:
  1. `elapsed since disconnected_at <= recovery_time_limit`
  2. `now() < attempt.expires_at` (the final expiry has not passed)
- During recovery, the disconnect time is NOT consumed from the student's exam duration. The timer is frozen at `expires_at − disconnected_at`.

### Cheating / Violation Limit
- Hard limit: 3 violations. Not configurable via UI.
- Defined as `ExamSecurityService::MAX_VIOLATIONS = 3`.
- Each violation type is counted independently in `cheating_logs` (`warning_number` is per-type).
- `warning_count` on the attempt is the total across all types.
- Third violation always terminates unconditionally, regardless of any settings.

### Exam Timer Precedence
- `expires_at = MIN(started_at + duration_minutes, schedule.ends_at)`
- The student's personal countdown and the exam window end are both enforced.
- A student who starts 2 minutes before `ends_at` with a 60-minute exam gets exactly 2 minutes.

### Question Encryption
- All question `content_encrypted` and answer `content_encrypted` fields are AES-256-CBC encrypted using the application `APP_KEY`.
- Questions are only decrypted server-side when `ExamAccessService::canDecryptQuestions()` returns true.
- Students cannot access question text outside the active exam window.

---

## 7.3 Security Rules

### Login Attempt Limit
- Maximum 3 failed attempts before the account is locked.
- Lock duration: 10 minutes.
- Lock is per-user (tracked in `users.failed_login_attempts` and `users.locked_until`).
- On successful login: `resetFailedLogins()` clears both counters.

### Temporary Password Expiry
- Temporary passwords expire 24 hours from the time they are issued.
- Checked in `AuthController::login()` via `user->isTemporaryPasswordExpired()`.
- If expired, user is logged out and shown a link to request a new temporary password.

### Temporary Password Cooldown
- 60 seconds must pass between consecutive requests for a new temporary password.
- Enforced by `users.temp_password_last_requested_at` and `User::canRequestNewTempPassword()`.

### Force Password Change
- Set `users.force_password_change = true` to force any teacher or student to change their password on next login.
- `ForcePasswordChange` middleware intercepts ALL requests (except `/password/change` and `/logout`) for such users.
- Admin accounts are exempt — the middleware checks `!$user->isAdmin()`.
- On successful change: `force_password_change = false`, `temporary_password_expires_at = null`, `failed_login_attempts = 0`, `locked_until = null`.

### Single Exam Session
- `EnsureSingleExamSession` middleware applies to all authenticated student routes.
- When an exam attempt is started, a 60-character random token is stored in both `users.exam_session_token` and the PHP session.
- If a second session attempts to access the system with a different token, the second session is invalidated and the user is logged out.
- Token is cleared when attempt is submitted or terminated.

### Role-Based Access
- `RoleMiddleware` checks `user.role.slug` against the allowed slugs for the route group.
- Three slugs: `admin`, `teacher`, `student`.
- Teacher routes accept both `role:teacher` and `role:admin` (`middleware('role:teacher,admin')`), allowing admins to access teacher views.
- Student routes are strictly `role:student`.

---

## 7.4 Enrollment Business Rules

1. A student must have a `StudentYearRecord` for the selected academic year + year level before they can be enrolled in any course.
2. Year 1 students can only be enrolled in CST major courses (or courses with no major).
3. Year 2+ students can be enrolled in courses matching their major.
4. CS and CT students can additionally enroll in CST courses.
5. Course year level must match (0 = all years allowed).
6. Course semester must match (0 = both semesters allowed).
7. Duplicate enrollment (same course + student + year level) is rejected silently (counted as skipped).

---

## 7.5 Result Immutability Rule

Once a Result record has `exam_result_status = DISQUALIFIED`, it cannot be overwritten by `GradingService::gradeAttempt()`. The service checks for this at the top of the method and returns the existing record unchanged. This prevents re-grading from restoring a disqualified result.

---

# 8. Data Flow Diagrams

## 8.1 Student Enrollment Flow

```
Admin selects:
  Academic Year + Year Level + Major + Semester
            │
            ▼
AJAX: studentsByYearLevel()
  → Query StudentYearRecord WHERE academic_year_id = X AND year_level_id = Y [AND major = Z]
  → Returns eligible active student list
            │
            ▼
Admin selects students[] + courses[]
            │
            ▼
POST /admin/enrollments
            │
            ▼
EnrollmentController::store()
  ├── Validate all students have matching StudentYearRecord
  ├── For each course:
  │     ├── Year level match check
  │     ├── Semester match check
  │     ├── Major match check (CS/CT→CST rule)
  │     └── For each student:
  │           ├── Duplicate check
  │           ├── Enrollment::create()
  │           └── NotificationService::notify(student, 'enrolled', ...)
  └── Return success/skip summary
```

## 8.2 Student Exam Flow

```
Student opens /student/exams
            │
            ▼
StudentExamController::index()
  → Query StudentYearRecord for student → get (AY_id, YL_id) pairs
  → Query Exams WHERE status=published AND (AY+YL matches) AND enrolled
  → Load active attempts, finalized attempts, used attempt counts
            │
            ▼
Student clicks "Start Exam"
POST /student/exams/{exam}/start
            │
            ▼
StudentExamController::start()
  → ExamAccessService::studentCanTakeExam() — full eligibility
  → Check for existing in_progress attempt → redirect to take
  → Calculate expires_at = MIN(now + duration, ends_at)
  → Set session_token on user + session
  → Generate question_order (shuffled if exam.shuffle_questions)
  → ExamAttempt::create()
            │
            ▼
GET /student/attempt/{attempt}/take
            │
            ▼
ExamSessionController::take()
  → If disconnected_at set → SessionRecoveryService::handleReconnect()
  → If now >= expires_at → auto-submit → redirect
  → If now >= schedule.ends_at → auto-submit → redirect
  → computeNormalSeconds() → effectiveEndsAt
  → canDecryptQuestions() → decrypt questions
  → Apply question_order from attempt
  → Render student.exam.take view with security policy flags
            │
            ▼ (Exam in progress)
            │
  Auto-save: POST /attempt/{id}/save → StudentAnswer::updateOrCreate
  Violations: POST /attempt/{id}/violation → ExamSecurityService::recordViolation
  Disconnect: POST /attempt/{id}/disconnect → SessionRecoveryService::recordDisconnect
            │
            ▼
POST /attempt/{attempt}/submit
            │
            ▼
ExamSessionController::submit()
  → status = submitted, submitted_at = now
  → Clear session_token
  → GradingService::gradeAttempt()
  → Redirect to exam show page
```

## 8.3 Exam Result Flow

```
GradingService::gradeAttempt(attempt)
            │
            ▼
  Guard: existing DISQUALIFIED result? → return unchanged
            │
            ▼
  total_marks = sum(exam.questions.marks)
            │
            ▼
  For each StudentAnswer:
    MCQ/TF:    marks = answer.is_correct ? question.marks : 0
    Fill Blank: marks = acceptedAnswers.contains(student_text) ? q.marks : 0
    (Unanswered = 0, no row exists)
            │
            ▼
  percentage = (obtained / total) * 100
  is_passed  = obtained >= exam.passing_marks
            │
            ▼
  Result::updateOrCreate:
    exam_result_status = PASSED | FAILED
    attendance_status  = attended
    is_published       = true
            │
            ▼
  [If triggered by ExamSecurityService violation 3:]
  Result::where(attempt_id)->update:
    exam_result_status = DISQUALIFIED
    is_passed          = false
    is_published       = false
    violation_reason   = [type/details]
    disqualified_at    = now()
```

## 8.4 Email Sending Flow

```
Trigger (controller / service / event)
            │
            ▼
EmailService::send(to, subject, body, event, queue=true)
            │
            ▼
EmailLog::create(status='queued')
            │
            ▼
SendEmailJob::dispatch(log->id)  → onto 'emails' queue
            │
            ▼  [Queue worker picks up job]
SendEmailJob::handle(EmailService $service)
  → EmailLog::find(id)
  → Guard: already sent? return.
  → EmailService::deliver(log)
            │
            ├── Mail::send() via SMTP
            │       Success → log->markSent()
            └── Exception → log->markFailed($msg)
                            [Job retried up to 3 times with 30s backoff]
                            [After 3 failures → failed_jobs table]
```

## 8.5 Anti-Cheat Violation Flow

```
Browser detects violation (JS)
  fullscreen_exit | tab_switch | blur | devtools | copy | paste | right_click
            │
            ▼
POST /student/attempt/{attempt}/violation
  {type: "fullscreen_exit", details: "..."}
            │
            ▼
ExamSessionController::violation()
  → EnsureExamActive middleware: attempt must be in_progress
  → ExamSecurityService::recordViolation(attempt.fresh(), type, details, [], ip)
            │
            ▼
  attempt.warning_count < 3?
            │
     ┌──────┴──────────┐
     │                 │
  count == 0         count == 1           count == 2
  Warning 1          Warning 2            Warning 3
  CheatingLog        CheatingLog          DB transaction + lockForUpdate
  warning_count++    warning_count++      CheatingLog
  Warn student       Warn student         warning_count = 3
  Continue           Email+Notify         status = terminated
                     teacher+admins       GradingService
                     Continue             Result → DISQUALIFIED
                                          Email student+teacher+admins
                                          session_token cleared
                                          Redirect to exam list
```

---

# 9. Developer Maintenance Guide

## 9.1 How to Add a New Feature

### Step 1: Understand where it fits
- Admin-only → `app/Http/Controllers/Admin/`
- Teacher → `app/Http/Controllers/Teacher/`
- Student → `app/Http/Controllers/Student/`
- Shared business logic → new Service class in `app/Services/`

### Step 2: Create the migration
```bash
php artisan make:migration add_feature_to_table
```
Run `php artisan migrate` after editing.

### Step 3: Update the Model
Add new columns to `$fillable` and `$casts`. Add relationships if needed.

### Step 4: Create the Controller
```bash
php artisan make:controller Admin/FeatureController
```
Inject required services via constructor. Always validate input with `$request->validate()`.

### Step 5: Register routes in `routes/web.php`
Add inside the correct role middleware group:
```php
Route::prefix('admin')->middleware('role:admin')->name('admin.')->group(function () {
    Route::resource('feature', FeatureController::class);
});
```

### Step 6: Create Blade views
Place under `resources/views/admin/feature/` (or teacher/student as appropriate). Extend `layouts.app`.

### Step 7: Add navigation
Edit `resources/views/partials/admin-sidebar.blade.php` (or teacher/student variant).

---

## 9.2 How to Add a New Table

1. Create migration: `php artisan make:migration create_table_name_table`
2. Define schema in `up()` with proper FK constraints.
3. Create a Model: `php artisan make:model ModelName`
4. Add `$fillable`, `$casts`, and relationships.
5. Run `php artisan migrate`.
6. If the table relates to users, ensure `cascadeOnDelete` or `nullOnDelete` is set correctly.

---

## 9.3 How to Add a New Email

1. Create a Blade template in `resources/views/emails/your-email.blade.php`.
2. Create a Job in `app/Jobs/SendYourEmailJob.php`:
   - Implement `ShouldQueue`
   - Use `$this->onQueue('emails')`
   - Set `$tries = 3`, `$backoff = 30`
   - Call `EmailService::send()` in `handle()`
3. Call `SendYourEmailJob::dispatch(...)` from the controller or service.
4. The `EmailService::send()` creates an `EmailLog` and queues `SendEmailJob`.

Alternatively, use `EmailService::send()` directly with a pre-rendered HTML body:
```php
$bodyHtml = view('emails.your-email', ['data' => $data])->render();
$this->emailService->send($user->email, $user->name, 'Subject', $bodyHtml, 'event_slug', null, $user->id, true);
```

---

## 9.4 How to Add a New Exam Rule

### New eligibility condition in `ExamAccessService::studentCanTakeExam()`
Add a check in the method. All checks use early return `false` on failure.

Example — restrict exam to a specific department:
```php
if ($exam->department && $user->studentYearRecords()
    ->where('department', $exam->department)->doesntExist()) {
    return false;
}
```

### Change attempt limit
`attempt_limit` is on `exam_schedules`. It is set by admin at schedule creation. No code change needed — it is already configurable per exam.

### Change recovery window
Set `EXAM_RECOVERY_TIME_LIMIT=600` in `.env`. Default is 300 (5 minutes). Defined in `config/exam_security.php`.

### Change violation limit
`ExamSecurityService::MAX_VIOLATIONS = 3` is a hard constant. To change it, edit the constant directly. Be aware this affects all exams immediately.

---

## 9.5 Where to Modify Specific Behaviours

| Behaviour | Where to change |
|---|---|
| Login attempt limit | `User::MAX_FAILED_ATTEMPTS` |
| Lockout duration | `User::LOCK_DURATION_MINUTES` |
| Temp password expiry | `User::TEMP_PASSWORD_EXPIRY_HOURS` |
| Temp password cooldown | `User::TEMP_PASSWORD_REQUEST_COOLDOWN_SECONDS` |
| Exam recovery window | `config/exam_security.php` → `recovery_time_limit` (or `.env`) |
| Max violation count | `ExamSecurityService::MAX_VIOLATIONS` |
| Grading formula | `GradingService::gradeAttempt()` |
| Fill-blank case sensitivity | `GradingService` fill_blank section (currently case-sensitive) |
| Email queue name | `SendEmailJob::__construct()` → `$this->onQueue('emails')` |
| IMAP fetch limit | `IMAP_SYNC_LIMIT` in `.env` (default 20, max 200) |
| Exam timer formula | `StudentExamController::start()` → `$finalExpiry` calculation |
| Question encryption | `EncryptionService` — uses Laravel's `APP_KEY` |
| Role definitions | `database/seeders/DatabaseSeeder.php` + `app/Enums/RoleSlug.php` |
| Recipient groups for bulk email | `EmailService::resolveRecipients()` match statement |

---

## 9.6 Artisan Commands

```bash
# Mark absent results for closed exams
php artisan exams:mark-absent-results

# Email queue statistics
php artisan email:stats

# Run queue worker (required for all email sending)
php artisan queue:work --queue=emails

# Run queue worker as daemon (production)
php artisan queue:work --queue=emails --daemon --sleep=3 --tries=3

# Retry failed jobs
php artisan queue:retry all

# View failed jobs
php artisan queue:failed

# Clear queue (caution: drops all pending emails)
php artisan queue:clear emails
```

---

## 9.7 Environment Variables Reference

| Variable | Purpose | Default |
|---|---|---|
| `APP_KEY` | Laravel encryption key (also used for question/answer encryption) | Generated |
| `DB_CONNECTION` | Database driver | mysql |
| `MAIL_MAILER` | Mail driver | smtp |
| `MAIL_HOST` | SMTP host | |
| `MAIL_PORT` | SMTP port | |
| `MAIL_USERNAME` | SMTP username | |
| `MAIL_PASSWORD` | SMTP password | |
| `MAIL_ENCRYPTION` | tls or ssl | |
| `MAIL_FROM_ADDRESS` | Sender address | |
| `MAIL_FROM_NAME` | Sender name | |
| `QUEUE_CONNECTION` | Queue driver | database |
| `IMAP_HOST` | IMAP server | |
| `IMAP_PORT` | IMAP port | 993 |
| `IMAP_USERNAME` | IMAP email | |
| `IMAP_PASSWORD` | IMAP password | |
| `IMAP_ENCRYPTION` | ssl or tls | ssl |
| `IMAP_SYNC_LIMIT` | Max messages per sync | 20 |
| `EXAM_RECOVERY_TIME_LIMIT` | Session recovery window in seconds | 300 |

---

# 10. Troubleshooting Guide

## 10.1 Course Not Showing for Student

**Symptom:** Student logs in but sees no courses on `/student/courses`.

**Checklist:**
1. Does the student have a `StudentYearRecord`?
   ```sql
   SELECT * FROM student_year_records WHERE student_id = {id};
   ```
2. Are courses enrolled for the student?
   ```sql
   SELECT * FROM enrollments WHERE student_id = {id};
   ```
3. Is the course `is_active = true`?
4. Is the course soft-deleted (`deleted_at IS NOT NULL`)?

**Fix:** Admin must create a `StudentYearRecord` for the student, then enroll the student in the course.

---

## 10.2 Exam Not Appearing in Student Exam List

**Symptom:** An exam is published but a specific student cannot see it.

**Checklist:**
1. Is the exam `status = published`? Check in admin exam list.
2. Does the student have a `StudentYearRecord` matching the exam's `academic_year_id` + the course's `year_level`?
   ```sql
   SELECT syr.* FROM student_year_records syr
   JOIN year_levels yl ON syr.year_level_id = yl.id
   JOIN exams e ON e.academic_year_id = syr.academic_year_id
   JOIN courses c ON e.course_id = c.id AND c.year_level = yl.level
   WHERE syr.student_id = {student_id} AND e.id = {exam_id};
   ```
3. Is the student enrolled in the exam's course?
   ```sql
   SELECT * FROM enrollments WHERE student_id = {id} AND course_id = {course_id};
   ```
4. Does the exam have a published schedule?
   ```sql
   SELECT * FROM exam_schedules WHERE exam_id = {id} AND is_published = true;
   ```
5. Is the schedule currently active (`starts_at <= NOW() <= ends_at`)?

**Fix sequence:** Create StudentYearRecord → Enroll in course → Publish exam → Set and publish schedule.

---

## 10.3 Student Cannot Start Exam (Sees "Not Available" Error)

**Symptom:** Exam is visible, but clicking Start returns an error.

**Check `ExamAccessService::studentCanTakeExam()` conditions:**

1. Exam status must be `published` (not `approved` — start only works for published).
2. `student_schedule` must return a non-null schedule.
3. Student must be enrolled in the course.
4. Student must have a matching `StudentYearRecord` for exam AY + course year level.
5. Used attempts (`submitted/terminated/suspicious/rejected`) must be < `attempt_limit`.
6. `NOW()` must be between `schedule.starts_at` and `schedule.ends_at`.

**Common causes:**
- Schedule has ended (check `ends_at`).
- Student has reached attempt limit.
- Student has no matching year record.

---

## 10.4 Result Mismatch / Wrong Score

**Symptom:** Student score appears incorrect.

**Checks:**
1. Confirm `total_marks` on the exam matches the sum of all question marks:
   ```sql
   SELECT SUM(marks) FROM questions WHERE exam_id = {id} AND deleted_at IS NULL;
   ```
   If this doesn't match `exams.total_marks`, the teacher submitted with the wrong total.

2. Check `student_answers` for the attempt:
   ```sql
   SELECT sa.*, q.marks, a.is_correct
   FROM student_answers sa
   JOIN questions q ON sa.question_id = q.id
   LEFT JOIN answers a ON sa.answer_id = a.id
   WHERE sa.attempt_id = {attempt_id};
   ```

3. Check fill_blank: answer matching is **case-sensitive exact**. "Computer" ≠ "computer".

4. Check if result is DISQUALIFIED — grading preserves marks but sets `is_passed = false`, `is_published = false`.

5. Verify `results.total_marks` = sum of questions (calculated at time of grading, not from `exams.total_marks`).

---

## 10.5 Email Not Sending

**Symptom:** Emails are created in `email_logs` with `status = queued` but never delivered.

**Checklist:**
1. Is the queue worker running?
   ```bash
   php artisan queue:work --queue=emails
   ```
   Check your process manager (Supervisor, PM2, etc.).

2. Are there failed jobs?
   ```bash
   php artisan queue:failed
   ```
   Check `failed_jobs` table for error messages.

3. Check `email_logs` table for `status = failed` and read the `error` column.

4. Verify SMTP credentials in `.env`. Test via admin SMTP settings page (`/admin/email/smtp`) using the test email form.

5. Check the `jobs` table for stuck jobs:
   ```sql
   SELECT * FROM jobs WHERE queue = 'emails' ORDER BY created_at;
   ```

**Common errors:**
- `Connection refused` → Wrong SMTP host/port.
- `Authentication failed` → Wrong credentials.
- `SSL certificate error` → Try `MAIL_ENCRYPTION=tls` instead of `ssl`.

**Retry a specific failed log:**
Admin → Email → Logs → find the failed log → click Retry.

Or programmatically:
```php
$emailService->retry(EmailLog::find($id));
```

---

## 10.6 IMAP Inbox Not Syncing

**Symptom:** Clicking "Sync Inbox" shows no new emails or returns an error.

**Checklist:**
1. Check `.env` IMAP credentials: `IMAP_HOST`, `IMAP_PORT`, `IMAP_USERNAME`, `IMAP_PASSWORD`, `IMAP_ENCRYPTION`.
2. IMAP sync is now queued (`InboxSyncJob`). The HTTP response returns immediately. Check the queue worker is running.
3. Check `activity_logs` for `inbox_synced` entries to see recent sync results.
4. If sync errors appear, check Laravel logs (`storage/logs/laravel.log`) for `InboxSyncService` entries.
5. Verify the IMAP `INBOX` folder name. Some servers use different folder names.
6. Check `IMAP_SYNC_LIMIT` — if set too high, the job may timeout. Default is 20.

---

## 10.7 Queue Problems

**Symptom:** Jobs are stuck in the `jobs` table or pile up in `failed_jobs`.

**Diagnosis:**
```bash
# Count pending jobs per queue
SELECT queue, COUNT(*) FROM jobs GROUP BY queue;

# Check failed jobs
SELECT id, queue, payload, exception, failed_at FROM failed_jobs ORDER BY failed_at DESC;
```

**Solutions:**

1. Restart the worker:
   ```bash
   php artisan queue:restart
   ```

2. Retry all failed jobs:
   ```bash
   php artisan queue:retry all
   ```

3. Clear the failed jobs table after investigation:
   ```bash
   php artisan queue:flush
   ```

4. If the worker is not running, start it:
   ```bash
   php artisan queue:work --queue=emails --tries=3 --sleep=3
   ```

5. For large backlogs, increase worker concurrency by running multiple workers (different terminals or Supervisor workers).

---

## 10.8 Exam Session Token Error / Forced Logout During Exam

**Symptom:** Student is logged out mid-exam with "Another active exam session was detected."

**Cause:** The student opened the exam in a second browser/tab, or their session was cleared while the `users.exam_session_token` was still set.

**Fix:**
1. Check `users.exam_session_token` for the student — it should match their active attempt's `session_token`.
2. If the student has a legitimate `in_progress` attempt but cannot return, clear the token mismatch:
   ```sql
   UPDATE users SET exam_session_token = NULL WHERE id = {student_id};
   ```
3. The student can then log in again. If the attempt is still `in_progress` and within the schedule window (and `expires_at` hasn't passed), they can resume.

---

## 10.9 Student Locked Out After 3 Failed Login Attempts

**Symptom:** Student sees "Account locked" message.

**Lock duration:** 10 minutes. It clears automatically when `locked_until` passes.

**Manual unlock (admin):**
```sql
UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE email = 'student@email.com';
```

Or via Admin → Users → Edit user → the user management UI should expose this.

---

## 10.10 Force Password Change Not Clearing After Change

**Symptom:** User changed their password but is still redirected to `/password/change`.

**Check:**
```sql
SELECT force_password_change, temporary_password_expires_at FROM users WHERE id = {id};
```

If `force_password_change` is still `1`, the update in `AuthController::updateForcePasswordChange()` may have failed silently.

**Fix:**
```sql
UPDATE users SET force_password_change = 0, temporary_password_expires_at = NULL WHERE id = {id};
```

---

## 10.11 Result Shows ABSENT for a Student Who Took the Exam

**Symptom:** Result list shows ABSENT for a student who actually submitted.

**Cause:** The `MarkAbsentResults` command created an ABSENT result row BEFORE the student's attempt was graded, OR the student's attempt exists but has no `Result` row.

**Check:**
```sql
SELECT * FROM exam_attempts WHERE student_id = {id} AND exam_id = {exam_id};
SELECT * FROM results WHERE student_id = {id} AND exam_id = {exam_id};
```

If the attempt is `submitted` but no result exists, grading may have failed. Check Laravel logs. Re-trigger grading manually if needed.

If there are multiple result rows for the same student/exam, only the one linked via `attempt_id` to the real attempt is authoritative.

---

*End of BLC Developer Manual*

---

> **Document generated from live codebase analysis.**
> All class names, method names, column names, and business rules are sourced directly from the project code.
> No assumptions or generic Laravel documentation were included.
