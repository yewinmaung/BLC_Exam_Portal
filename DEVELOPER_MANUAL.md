# BLC_Complete_Final — Developer Manual
> Laravel 10 · PHP 8.1 · MySQL · Queue-based Email System

---

## Table of Contents
1. [Project Overview](#1-project-overview)
2. [Directory Structure](#2-directory-structure)
3. [Database Schema & Foreign Keys](#3-database-schema--foreign-keys)
4. [Models & Relationships](#4-models--relationships)
5. [Services Layer](#5-services-layer)
6. [Controllers](#6-controllers)
7. [Middleware](#7-middleware)
8. [Routes](#8-routes)
9. [Jobs & Queue](#9-jobs--queue)
10. [Artisan Commands & Scheduler](#10-artisan-commands--scheduler)
11. [Anti-Cheating System](#11-anti-cheating-system)
12. [Session Recovery System](#12-session-recovery-system)
13. [Email System](#13-email-system)
14. [Enums & Constants](#14-enums--constants)
15. [Key Business Rules](#15-key-business-rules)

---

## 1. Project Overview

**BLC (Believe Learning Center)** သည် University/College level online examination management system ဖြစ်သည်။

| Item | Value |
|------|-------|
| Framework | Laravel 10 |
| Auth | Session-based (MustVerifyEmail) |
| Roles | admin / teacher / student |
| Encryption | Laravel Crypt (AES-256-CBC) |
| Queue | Database queue (`emails` channel) |
| Scheduler | Every-minute cron |


---

## 2. Directory Structure

```
app/
├── Console/
│   ├── Kernel.php                    # Scheduler definition
│   └── Commands/
│       ├── EmailStats.php            # Email statistics report
│       ├── MarkAbsentResults.php     # ABSENT result creator
│       ├── NotifyStudentResults.php  # Result notification sender
│       └── SyncInbox.php             # IMAP inbox sync trigger
├── Enums/
│   ├── RecordType.php                # student_year_records.record_type values
│   └── RoleSlug.php                  # 'admin' | 'teacher' | 'student'
├── Events/
│   └── NewEmailReceived.php          # Fired when IMAP syncs new email
├── Http/
│   ├── Controllers/
│   │   ├── Admin/                    # Admin-only controllers
│   │   ├── Auth/                     # Login, ForgotPassword
│   │   ├── Student/                  # Student-only controllers
│   │   ├── Teacher/                  # Teacher-only controllers
│   │   ├── DashboardController.php   # Role-branched dashboard
│   │   ├── NotificationController.php
│   │   └── ProfileController.php
│   ├── Kernel.php                    # Middleware registration
│   └── Middleware/                   # See §7
├── Jobs/                             # See §9
├── Mail/                             # Mailable classes
├── Models/                           # See §4
├── Providers/
│   ├── AppServiceProvider.php
│   ├── AuthServiceProvider.php
│   ├── EventServiceProvider.php
│   └── RouteServiceProvider.php
├── Services/                         # See §5
└── Support/
    └── AcademicYear.php              # OPTIONS constant array for year choices
```


---

## 3. Database Schema & Foreign Keys

### 3.1 Auth & User Tables

#### `users`
| Column | Type | Description | FK |
|--------|------|-------------|----|
| id | bigint PK | | |
| role_id | bigint NULL | User's role | → `roles.id` nullOnDelete |
| name | string | | |
| email | string unique | | |
| password | string | bcrypt hash | |
| is_active | boolean def=true | Account enabled? | |
| force_password_change | boolean | Must change pw on login | |
| phone | string NULL | | |
| academic_year | int NULL | Legacy year integer (1-5) | |
| exam_session_token | string NULL | Single-session enforcement token | |
| last_login_at | datetime NULL | | |
| profile_photo | string NULL | Storage path | |
| failed_login_attempts | int def=0 | Lockout counter | |
| locked_until | datetime NULL | Account locked till this time | |
| temporary_password_expires_at | datetime NULL | Temp pw expiry | |
| temp_password_last_requested_at | datetime NULL | Cooldown tracker | |
| deleted_at | datetime NULL | SoftDeletes | |

#### `roles`
| Column | Type | Description |
|--------|------|-------------|
| id | bigint PK | |
| name | string unique | "Admin", "Teacher", "Student" |
| slug | string unique | "admin", "teacher", "student" |

#### `profile_otps`
| Column | Type | Description | FK |
|--------|------|-------------|----|
| id | bigint PK | | |
| user_id | bigint | | → `users.id` cascadeOnDelete |
| code_hash | string | bcrypt of 6-digit OTP | |
| new_password_hash | string | bcrypt of new password | |
| attempts | tinyint def=0 | Wrong attempt count (max 5) | |
| expires_at | timestamp NULL | Valid 5 minutes | |
| used_at | timestamp NULL | One-time use marker | |


### 3.2 Academic Structure Tables

#### `academic_years`
| Column | Type | Description |
|--------|------|-------------|
| id | bigint PK | |
| name | string | e.g. "2025-2026" |
| start_year | year | |
| end_year | year | |
| is_current | boolean def=false | Active academic year flag |

#### `year_levels`
| Column | Type | Description |
|--------|------|-------------|
| id | bigint PK | |
| level | tinyint | 1-5 |
| name | string | "First Year" … "Fifth Year" |
| department | string NULL | |
| major | string NULL | |

#### `majors`
| Column | Type | Description |
|--------|------|-------------|
| id | bigint PK | |
| name | string | e.g. "Computer Science" |
| code | string unique | e.g. "CS", "CT", "CST" |
| description | text NULL | |
| is_active | boolean def=true | |

#### `student_year_records`
| Column | Type | Description | FK |
|--------|------|-------------|----|
| id | bigint PK | | |
| student_id | bigint | | → `users.id` cascadeOnDelete |
| academic_year_id | bigint | | → `academic_years.id` cascadeOnDelete |
| year_level_id | bigint | | → `year_levels.id` cascadeOnDelete |
| semester | string def='1' | '1' or '2' | |
| department | string NULL | | |
| major | string NULL | Major name (text, not FK) | |
| gpa | decimal(4,2) NULL | | |
| status | enum | active/promoted/failed/withdrawn | |
| promoted_at | timestamp NULL | | |
| record_type | string NULL | See RecordType enum | |
| remark | text NULL | Admin remark | |
| UNIQUE | (student_id, academic_year_id, year_level_id, semester) | | |


### 3.3 Course & Enrollment Tables

#### `courses`
| Column | Type | Description | FK |
|--------|------|-------------|----|
| id | bigint PK | | |
| title | string | Course name | |
| code | string unique | e.g. "CS101" | |
| description | text NULL | | |
| teacher_id | bigint NULL | Assigned teacher | → `users.id` nullOnDelete |
| created_by | bigint NULL | | → `users.id` nullOnDelete |
| is_active | boolean def=true | | |
| year_level | int NULL | 0=all, 1-5 | |
| semester | int NULL | 0=both, 1, 2 | |
| academic_year | int NULL | Legacy year integer | |
| academic_year_id | bigint NULL | | → `academic_years.id` |
| major_id | bigint NULL | null = Year 1 / all majors | → `majors.id` nullOnDelete |
| deleted_at | datetime NULL | SoftDeletes | |

#### `enrollments`
| Column | Type | Description | FK |
|--------|------|-------------|----|
| id | bigint PK | | |
| course_id | bigint | | → `courses.id` cascadeOnDelete |
| student_id | bigint | | → `users.id` cascadeOnDelete |
| year | int NULL | Legacy year integer | |
| year_level_id | bigint NULL | | → `year_levels.id` nullOnDelete |
| major_id | bigint NULL | | → `majors.id` nullOnDelete |
| enrolled_at | datetime | | |
| UNIQUE | (course_id, student_id, year) | | |


### 3.4 Exam Core Tables

#### `exams`
| Column | Type | Description | FK |
|--------|------|-------------|----|
| id | bigint PK | | |
| course_id | bigint | | → `courses.id` cascadeOnDelete |
| academic_year_id | bigint NULL | | → `academic_years.id` nullOnDelete |
| teacher_id | bigint | Snapshot at creation | → `users.id` cascadeOnDelete |
| title | string | | |
| description | text NULL | | |
| status | enum | draft→pending_approval→approved→published→closed | |
| total_marks | uint def=100 | Sum of all question marks | |
| passing_marks | uint def=40 | Minimum to pass | |
| shuffle_questions | boolean def=false | Randomise per-student | |
| submitted_at | datetime NULL | When teacher submitted | |
| approved_at | datetime NULL | When admin approved | |
| approved_by | bigint NULL | | → `users.id` nullOnDelete |
| deleted_at | datetime NULL | SoftDeletes | |

#### `exam_schedules`
| Column | Type | Description | FK |
|--------|------|-------------|----|
| id | bigint PK | | |
| exam_id | bigint | | → `exams.id` cascadeOnDelete |
| starts_at | datetime | Exam window open | |
| ends_at | datetime | Exam window close | |
| duration_minutes | uint | Per-student timer | |
| attempt_limit | uint def=1 | Max attempts per student | |
| is_published | boolean def=false | Visible to students | |
| published_at | datetime NULL | | |
| published_by | bigint NULL | | → `users.id` nullOnDelete |

#### `questions`
| Column | Type | Description | FK |
|--------|------|-------------|----|
| id | bigint PK | | |
| exam_id | bigint | | → `exams.id` cascadeOnDelete |
| type | enum | mcq / true_false / essay / file_upload / fill_blank | |
| content_encrypted | longText | AES-256 encrypted question text | |
| attachment_path | string NULL | Storage path for image | |
| attachment_name | string NULL | Original filename | |
| attachment_mime | string NULL | MIME type | |
| marks | uint def=1 | Points for this question | |
| order | uint def=0 | Display order | |
| deleted_at | datetime NULL | SoftDeletes | |

#### `answers`
| Column | Type | Description | FK |
|--------|------|-------------|----|
| id | bigint PK | | |
| question_id | bigint | | → `questions.id` cascadeOnDelete |
| content_encrypted | longText | AES-256 encrypted answer text | |
| is_correct | boolean def=false | Correct answer flag | |
| is_blank_answer | boolean NULL | fill_blank accepted answer | |
| decrypted_content | virtual | Accessor via EncryptionService | |
| order | uint def=0 | Display order | |


### 3.5 Exam Attempt & Result Tables

#### `exam_attempts`
| Column | Type | Description | FK |
|--------|------|-------------|----|
| id | bigint PK | | |
| exam_id | bigint | | → `exams.id` cascadeOnDelete |
| schedule_id | bigint | | → `exam_schedules.id` cascadeOnDelete |
| student_id | bigint | | → `users.id` cascadeOnDelete |
| attempt_number | uint def=1 | Retry count | |
| status | enum | in_progress / submitted / terminated / suspicious / terminated_pending_review / rejected | |
| warning_count | tinyint def=0 | Violation counter (0-3) | |
| started_at | datetime NULL | Exam begin time | |
| submitted_at | datetime NULL | Exam submit time | |
| expires_at | datetime NULL | MIN(started_at+duration, schedule.ends_at) | |
| terminated_at | datetime NULL | When locked for review | |
| disconnected_at | datetime NULL | Network disconnect timestamp | |
| last_question_id | bigint NULL | Question at disconnect | |
| question_order | json NULL | Per-student shuffled question ID array | |
| session_token | string NULL | Per-attempt token | |
| approved_by | bigint NULL | Who approved security incident | → `users.id` nullOnDelete |
| approved_at | datetime NULL | Approval timestamp | |
| approval_comment | text NULL | | |
| rejected_by | bigint NULL | Who rejected security incident | → `users.id` nullOnDelete |
| rejected_at | datetime NULL | Rejection timestamp | |
| rejection_comment | text NULL | | |
| INDEX | (exam_id, student_id) | | |

#### `student_answers`
| Column | Type | Description | FK |
|--------|------|-------------|----|
| id | bigint PK | | |
| attempt_id | bigint | | → `exam_attempts.id` cascadeOnDelete |
| question_id | bigint | | → `questions.id` cascadeOnDelete |
| answer_id | bigint NULL | Selected MCQ answer | → `answers.id` nullOnDelete |
| answer_text | longText NULL | Essay / fill_blank text | |
| file_path | string NULL | Uploaded file path | |
| is_correct | boolean NULL | Set by GradingService | |
| marks_awarded | uint NULL | Set by GradingService | |
| UNIQUE | (attempt_id, question_id) | | |

#### `results`
| Column | Type | Description | FK |
|--------|------|-------------|----|
| id | bigint PK | | |
| attempt_id | bigint NULL | NULL = ABSENT | → `exam_attempts.id` cascadeOnDelete |
| exam_id | bigint | | → `exams.id` cascadeOnDelete |
| student_id | bigint | | → `users.id` cascadeOnDelete |
| total_marks | uint | Sum of all exam questions | |
| obtained_marks | uint | Student's earned marks | |
| percentage | decimal(5,2) | obtained/total × 100 | |
| grade | enum NULL | A/B/C/D/F (legacy, unused) | |
| is_passed | boolean | obtained_marks >= passing_marks | |
| is_published | boolean def=false | Visible to student | |
| exam_result_status | string NULL | PASSED/FAILED/ABSENT/DISQUALIFIED | |
| attendance_status | string NULL | attended / absent | |
| violation_reason | text NULL | Disqualification reason | |
| disqualified_at | datetime NULL | | |
| exam_finished_at | datetime NULL | | |


### 3.6 Security & Audit Tables

#### `cheating_logs`
| Column | Type | Description | FK |
|--------|------|-------------|----|
| id | bigint PK | | |
| attempt_id | bigint | | → `exam_attempts.id` cascadeOnDelete |
| student_id | bigint | | → `users.id` cascadeOnDelete |
| violation_type | string | e.g. fullscreen_exit, tab_switch | |
| details | text NULL | Human-readable detail from JS | |
| warning_number | tinyint def=1 | Nth time this type occurred | |
| user_agent | text NULL | Raw navigator.userAgent | |
| browser | string NULL | Parsed: "Chrome 125" | |
| device | string NULL | Desktop / Mobile / Tablet | |
| os | string NULL | "Windows 11" | |
| screen_resolution | string NULL | "1920x1080" | |
| timezone | string NULL | "Asia/Yangon" | |
| ip_address | string NULL | Request IP at violation time | |

#### `session_recovery_logs`
| Column | Type | Description | FK |
|--------|------|-------------|----|
| id | bigint PK | | |
| attempt_id | bigint | | → `exam_attempts.id` cascadeOnDelete |
| student_id | bigint | | → `users.id` cascadeOnDelete |
| exam_id | bigint | | → `exams.id` cascadeOnDelete |
| disconnected_at | timestamp | When disconnected | |
| reconnected_at | timestamp NULL | When reconnected | |
| disconnected_duration_seconds | uint NULL | Duration of disconnect | |
| disconnect_reason | string(100) NULL | browser_close / network_error | |
| last_question_id | bigint NULL | Question at disconnect | |
| browser_info | json NULL | Structured browser metadata | |
| user_agent | string(500) NULL | | |
| ip_address | string(45) NULL | IPv4/IPv6 | |
| recovery_status | enum | pending / recovered / expired | |
| INDEX | (attempt_id, disconnected_at) | | |

#### `activity_logs`
| Column | Type | Description | FK |
|--------|------|-------------|----|
| id | bigint PK | | |
| user_id | bigint NULL | Actor | → `users.id` nullOnDelete |
| action | string | Machine key e.g. exam_terminated_security | |
| model_type | string NULL | e.g. "App\Models\ExamAttempt" | |
| model_id | bigint NULL | Subject record ID | |
| description | text NULL | JSON metadata or text | |
| ip_address | string NULL | | |


### 3.7 Email System Tables

#### `email_logs`
| Column | Type | Description | FK |
|--------|------|-------------|----|
| id | bigint PK | | |
| to_email | string | Recipient | |
| to_name | string NULL | | |
| from_email | string | | |
| from_name | string NULL | | |
| subject | string | | |
| body_html | longText NULL | Rendered HTML | |
| template_slug | string NULL | Template used | |
| event | string NULL | Event that triggered it | |
| email_type | string NULL | welcome / security_warning / etc. | |
| status | enum | queued / sent / failed | |
| provider | string def='smtp' | | |
| error | text NULL | Failure reason | |
| message_id | string NULL | SMTP Message-ID header | |
| user_id | bigint NULL | | → `users.id` nullOnDelete |
| queued_at | timestamp NULL | | |
| sent_at | timestamp NULL | | |
| INDEX | (status, created_at), (to_email) | | |

#### `inbox_emails`
| Column | Type | Description | FK |
|--------|------|-------------|----|
| id | bigint PK | | |
| from_email | string | | |
| from_name | string NULL | | |
| sender_type | enum | student / external | |
| user_id | bigint NULL | Linked user if student | → `users.id` nullOnDelete |
| subject | string | | |
| body_html | longText NULL | | |
| body_text | text NULL | | |
| message_id | string NULL | SMTP Message-ID | |
| in_reply_to | string NULL | Threading header | |
| thread_id | string NULL | Grouped thread key | |
| status | enum | unread / read / replied / archived | |
| category | string NULL | Admin label | |
| replied_by | bigint NULL | | → `users.id` nullOnDelete |
| replied_at | timestamp NULL | | |
| received_at | timestamp | | |

#### `exam_timetable_notifications`
| Column | Type | Description | FK |
|--------|------|-------------|----|
| id | bigint PK | | |
| sent_by | bigint | Admin who sent it | → `users.id` cascadeOnDelete |
| academic_year_id | bigint | | → `academic_years.id` cascadeOnDelete |
| year_level_id | bigint | | → `year_levels.id` cascadeOnDelete |
| major_id | bigint NULL | | → `majors.id` nullOnDelete |
| semester | tinyint | 1 or 2 | |
| exam_schedule_ids | json | Array of schedule IDs included | |
| exam_policy | text NULL | Admin-typed policy text | |
| additional_instructions | text NULL | | |
| recipient_count | uint def=0 | How many emails sent | |
| status | enum | queued / sent / partial / failed | |
| sent_at | timestamp NULL | | |


### 3.8 Notification & Supporting Tables

#### `user_notifications`
| Column | FK | Description |
|--------|----|-------------|
| user_id | → `users.id` cascadeOnDelete | |
| type | | security_warning / exam_result / cheating / etc. |
| title | | |
| message | | |
| link | | Route URL |
| is_read | boolean def=false | |

#### `attempt_reset_requests`
| Column | FK | Description |
|--------|----|-------------|
| exam_id | → `exams.id` cascadeOnDelete | |
| student_id | → `users.id` cascadeOnDelete | |
| requested_by | → `users.id` cascadeOnDelete | |
| teacher_status | enum | pending/approved/rejected |
| admin_status | enum | pending/approved/rejected |
| reason | text NULL | |
| approved_by | → `users.id` nullOnDelete | |

#### `yearly_exam_results` (Archive)
| Column | FK |
|--------|----|
| student_id | → `users.id` |
| academic_year_id | → `academic_years.id` |
| year_level_id | → `year_levels.id` |
| exam_id | → `exams.id` |
| result_id | → `results.id` nullOnDelete |

#### `certificate_logs`
| Column | FK | Description |
|--------|----|-------------|
| serial_number | unique | |
| student_id | → `users.id` cascadeOnDelete | |
| academic_year_id | → `academic_years.id` cascadeOnDelete | |
| year_level_id | → `year_levels.id` cascadeOnDelete | |
| type | enum | transcript/completion/promotion/achievement |
| qr_token | unique | For QR code verification |
| created_by | → `users.id` cascadeOnDelete | |

---

## 4. Models & Relationships

### User
**File:** `app/Models/User.php`

```
User ──hasMany──► Enrollment        (student_id)
User ──hasMany──► ExamAttempt       (student_id)
User ──hasMany──► Exam              (teacher_id) [taughtExams]
User ──hasMany──► Course            (teacher_id) [taughtCourses]
User ──hasMany──► UserNotification  (user_id)
User ──hasMany──► StudentYearRecord (student_id)
User ──belongsTo► Role              (role_id)
```

**Constants:**
- `MAX_FAILED_ATTEMPTS = 3` → lockout threshold
- `LOCK_DURATION_MINUTES = 10` → lockout duration
- `TEMP_PASSWORD_EXPIRY_HOURS = 24`
- `TEMP_PASSWORD_REQUEST_COOLDOWN_SECONDS = 60`

**Key Methods:**
| Method | Returns | Description |
|--------|---------|-------------|
| `isAdmin()` | bool | role.slug === 'admin' |
| `isTeacher()` | bool | role.slug === 'teacher' |
| `isStudent()` | bool | role.slug === 'student' |
| `isLocked()` | bool | locked_until is future |
| `isTemporaryPasswordExpired()` | bool | force_change + expiry past |
| `incrementFailedLogins()` | void | +1 counter, set lock if ≥3 |
| `resetFailedLogins()` | void | Clear counter + lock |
| `remainingLoginAttempts()` | int | 3 - used |
| `canRequestNewTempPassword()` | bool | Cooldown check |
| `tempPasswordCooldownSecondsRemaining()` | int | Seconds left |
| `profilePhotoUrl()` | ?string | Storage URL or null |


### Exam
**File:** `app/Models/Exam.php`

```
Exam ──belongsTo──► Course         (course_id)
Exam ──belongsTo──► AcademicYear   (academic_year_id)
Exam ──belongsTo──► User           (teacher_id)
Exam ──belongsTo──► User           (approved_by)  [approver]
Exam ──hasMany────► Question       (exam_id)
Exam ──hasMany────► ExamSchedule   (exam_id)
Exam ──hasOne─────► ExamSchedule   (is_published=true) [activeSchedule]
Exam ──hasOne─────► ExamSchedule   (latest) [latestSchedule]
Exam ──hasMany────► ExamAttempt    (exam_id)
Exam ──hasMany────► Result         (exam_id)
```

**Status Flow:** `draft → pending_approval → approved → published → closed`

**Key Accessor:** `getStudentScheduleAttribute()` — returns activeSchedule if published, latestSchedule if approved

### ExamAttempt
**File:** `app/Models/ExamAttempt.php`

```
ExamAttempt ──belongsTo──► Exam            (exam_id)
ExamAttempt ──belongsTo──► ExamSchedule    (schedule_id)
ExamAttempt ──belongsTo──► User            (student_id)
ExamAttempt ──belongsTo──► User            (approved_by) [approver]
ExamAttempt ──belongsTo──► User            (rejected_by) [rejector]
ExamAttempt ──hasMany────► StudentAnswer   (attempt_id)
ExamAttempt ──hasOne─────► Result          (attempt_id)
ExamAttempt ──hasMany────► CheatingLog     (attempt_id)
ExamAttempt ──hasMany────► SessionRecoveryLog (attempt_id)
```

**Status Values:** `in_progress / submitted / terminated / suspicious / terminated_pending_review / rejected`

**Key Methods:**
| Method | Returns | Description |
|--------|---------|-------------|
| `isActive()` | bool | status === 'in_progress' |
| `isFinished()` | bool | submitted/terminated/suspicious/rejected |
| `isTerminatedPendingReview()` | bool | Locked for human review |
| `isRejected()` | bool | Admin rejected the incident |
| `canAutoRecover()` | bool | Disconnect within 5-min window & before expires_at |
| `isDisplayedAsAbsent(schedule)` | bool | DISPLAY-ONLY: no answers + window ended |

**Casts:** `started_at, submitted_at, expires_at, terminated_at, approved_at, rejected_at, disconnected_at` → datetime; `question_order` → array

### Result
**File:** `app/Models/Result.php`

**Status Constants:**
- `STATUS_PASSED = 'PASSED'`
- `STATUS_FAILED = 'FAILED'`
- `STATUS_ABSENT = 'ABSENT'`
- `STATUS_DISQUALIFIED = 'DISQUALIFIED'`
- `ATTENDANCE_ATTENDED = 'attended'`
- `ATTENDANCE_ABSENT = 'absent'`

**Key Methods:** `isPassed()`, `isFailed()`, `isAbsent()`, `isDisqualified()`, `statusLabel()`, `statusBadgeClass()`


### Question
**File:** `app/Models/Question.php`

- `content_encrypted` — Crypt::encryptString() ဖြင့် encrypt လုပ်ထားသည်
- `getDecryptedContentAttribute()` — EncryptionService inject ဖြင့် decrypt
- `type` enum: `mcq / true_false / essay / file_upload / fill_blank`
- `hasAttachment()`, `attachmentUrl()` — image attachment helpers

### Other Models Summary

| Model | File | Key Relations |
|-------|------|---------------|
| Course | Models/Course.php | belongsTo Teacher, Major; hasMany Enrollments, Exams |
| Enrollment | Models/Enrollment.php | belongsTo Course, Student, YearLevel, Major |
| ExamSchedule | Models/ExamSchedule.php | belongsTo Exam; hasMany ExamAttempts |
| StudentAnswer | Models/StudentAnswer.php | belongsTo Attempt, Question, Answer |
| CheatingLog | Models/CheatingLog.php | belongsTo ExamAttempt, Student |
| SessionRecoveryLog | Models/SessionRecoveryLog.php | belongsTo ExamAttempt, Student, Exam |
| AcademicYear | Models/AcademicYear.php | hasMany StudentYearRecords |
| YearLevel | Models/YearLevel.php | hasMany StudentYearRecords |
| Major | Models/Major.php | hasMany Courses, Enrollments |
| StudentYearRecord | Models/StudentYearRecord.php | belongsTo Student, AcademicYear, YearLevel |
| UserNotification | Models/UserNotification.php | belongsTo User |
| ActivityLog | Models/ActivityLog.php | belongsTo User |
| EmailLog | Models/EmailLog.php | belongsTo User |
| InboxEmail | Models/InboxEmail.php | belongsTo User (sender) |
| ProfileOtp | Models/ProfileOtp.php | belongsTo User |
| Role | Models/Role.php | hasMany Users |

---

## 5. Services Layer

### 5.1 ExamSecurityService
**File:** `app/Services/ExamSecurityService.php`
**Purpose:** 3-violation anti-cheat system

**Constructor Injects:** EmailService, NotificationService, ActivityLogService, GradingService

| Method | Visibility | Description |
|--------|-----------|-------------|
| `recordViolation(attempt, type, details, client, ip)` | public | Main entry — routes to tier 1/2/3 |
| `approve(attempt, actor, comment)` | public | Restore in_progress + extend timer |
| `reject(attempt, actor, comment)` | public | Set status=rejected |
| `persistViolationLog(...)` | public | Write CheatingLog row |
| `getRecipients(attempt)` | public | Teacher + all admins (deduplicated) |
| `getTerminationRecipients(attempt)` | public | Student + Teacher + Admins |
| `sendSecurityEmail(attempt, recipient, template, priority)` | public | Queue email via EmailService |
| `sendSecurityNotification(attempt, recipient, priority)` | public | Create UserNotification |
| `maxWarnings()` | public static | Returns 3 (fixed constant) |
| `maxResumeExtensionMinutes()` | public static | From config, default 120 |
| `recordWarning(...)` | private | Handle violation 1 or 2 |
| `recordViolationThree(...)` | private | Unconditional termination with DB lock |
| `handleWarningOne(attempt, type)` | private | Audit log + return response |
| `handleWarningTwo(attempt, type)` | private | Notify teacher/admins + return response |
| `buildAuditMeta(attempt, extra)` | private | JSON metadata for ActivityLog |
| `lockedResponse()` | private | Standard "already terminated" JSON |

**Violation 3 flow uses `DB::transaction` + `lockForUpdate()` + `DB::afterCommit()`**


### 5.2 GradingService
**File:** `app/Services/GradingService.php`

| Method | Description |
|--------|-------------|
| `gradeAttempt(ExamAttempt $attempt): Result` | Auto-grade and create/update Result record |

**Grading Logic:**
1. DISQUALIFIED guard — if result already = DISQUALIFIED, return unchanged
2. `total_marks` = SUM of all exam question marks
3. `obtained_marks` = marks from answered questions only (unanswered = 0)
4. MCQ/true_false: compare `answer.is_correct`
5. fill_blank: case-sensitive exact match against `is_blank_answer=true` answers
6. essay/file_upload: NOT auto-graded (marks_awarded remains null)
7. `percentage` = (obtained / total) × 100
8. `is_passed` = obtained_marks >= exam.passing_marks
9. `exam_result_status` = PASSED or FAILED (DISQUALIFIED set by ExamSecurityService)

### 5.3 EncryptionService
**File:** `app/Services/EncryptionService.php`

| Method | Description |
|--------|-------------|
| `encrypt(?string $value): ?string` | `Crypt::encryptString()` wrapper |
| `decrypt(?string $value): ?string` | `Crypt::decryptString()` — returns null on failure |

Used for: `questions.content_encrypted` and `answers.content_encrypted`

### 5.4 ExamAccessService
**File:** `app/Services/ExamAccessService.php`

| Method | Description |
|--------|-------------|
| `canDecryptQuestions(user, exam)` | Admin/owner teacher = yes; Student = only during schedule window OR has active attempt |
| `canViewCorrectAnswers(user, exam)` | After schedule ends; admin/teacher always |
| `studentCanTakeExam(user, exam)` | Enrolled + matching year record + within attempt_limit + schedule active |
| `decryptContent(user, exam, encrypted)` | Calls EncryptionService if access granted |
| `isScheduleActive(schedule)` | now() between starts_at and ends_at |
| `scheduleHasEnded(exam)` | now() > latestSchedule.ends_at |

### 5.5 SessionRecoveryService
**File:** `app/Services/SessionRecoveryService.php`

| Method | Description |
|--------|-------------|
| `recordDisconnect(attempt, questionId, reason, browserInfo)` | Set disconnected_at + last_question_id; status stays in_progress |
| `handleReconnect(attempt)` | Path A: restore; Path B: finalize expired session |
| `computeNormalSeconds(attempt, schedule)` | MIN(expires_at-now, schedule.ends_at-now) |
| `computeFrozenSeconds(attempt, schedule)` | private — expires_at − disconnected_at |
| `finalizeExpiredSession(attempt, message)` | private — auto-submit + grade |

**Recovery window:** 5 minutes (config `exam_security.recovery_time_limit`, default 300s)

### 5.6 EmailService
**File:** `app/Services/EmailService.php`

| Method | Description |
|--------|-------------|
| `sendWelcomeEmail(user, password)` | Render welcome-account blade, queue |
| `send(toEmail, toName, subject, html, event, slug, userId, queue)` | Create EmailLog + dispatch SendEmailJob |
| `sendBulk(recipientGroup, subject, html, event, slug)` | Resolve group → per-user substitution → send |
| `deliver(EmailLog)` | Actual SMTP send; called by SendEmailJob |
| `retry(EmailLog)` | Re-queue failed log |
| `resolveRecipients(group)` | 'all_students', 'all_teachers', 'first_year'…'final_year', 'all_users' |
| `resolveAcademicRecipients(yearIds, levelIds, majorIds)` | Filter by StudentYearRecord |
| `resolveUserVars(user)` | Build {{variable}} map for templates |
| `substituteVars(text, vars)` | Replace {{key}} placeholders |
| `applySmtpConfig(settings)` | Runtime SMTP config (current request only) |

**Template variables:** `{{student_name}}`, `{{email}}`, `{{year_level}}`, `{{academic_year}}`, `{{course_name}}`, `{{semester}}`, `{{app_name}}`, `{{app_url}}`


### 5.7 Other Services

#### NotificationService (`app/Services/NotificationService.php`)
```php
notify(User $user, string $type, string $title, string $message, ?string $link): UserNotification
```
UserNotification row တစ်ခု create လုပ်သည်။

#### ActivityLogService (`app/Services/ActivityLogService.php`)
```php
log(string $action, string|array|null $description, ?Model $model): void
```
- Array ဖြင့် ပေးလျှင် JSON encode ဖြင့် သိမ်းသည် (queryable via JSON_EXTRACT)
- auth()->id(), Request::ip() auto-capture

#### AcademicService (`app/Services/AcademicService.php`)
| Method | Description |
|--------|-------------|
| `enrollStudent(student, academicYear, yearLevel, semester, dept, major)` | StudentYearRecord firstOrCreate |
| `getStudentHistory(student)` | All year records + matching results |

**History match logic (priority):**
1. course.year_level == record_level AND course.semester == record_semester
2. course.year_level == record_level only
3. course.semester == record_semester only
4. Any enrolled course (last resort)

#### CourseAssignmentService (`app/Services/CourseAssignmentService.php`)
| Method | Description |
|--------|-------------|
| `syncCourseStudents(course, studentIds[])` | Remove old enrollments, add new with year context |
| `syncTeacherCourses(teacher, courseIds[])` | Assign/unassign courses; preserves historical exams teacher_id |
| `syncStudentCourses(student, courseIds[])` | Enrollment sync from student edit side |

**Safety rule:** Courses with published/approved/closed exams are NOT unassigned from teachers (historical preservation).

#### EnsureDefaultAdminService (`app/Services/EnsureDefaultAdminService.php`)
Default admin account ရှိ/မရှိ စစ်ပြီး မရှိရင် create လုပ်သည်

#### YearLevelProgressionValidator (`app/Services/YearLevelProgressionValidator.php`)
Year level promotion rules validation

#### QuestionImportService (`app/Services/QuestionImportService.php`)
Excel/CSV file မှ question bulk import

#### InboxSyncService (`app/Services/InboxSyncService.php`)
IMAP server မှ inbox email sync လုပ်သည်

#### CheatingDetectionService (`app/Services/CheatingDetectionService.php`)
> **DEPRECATED — Legacy only.** Admin cheating-logs view display ကိုသာ support လုပ်သည်။
> New violation handling = ExamSecurityService သာ သုံးပါ။

---

## 6. Controllers

### 6.1 Admin Controllers

#### `Admin/UserController.php`
| Route | Method | Action |
|-------|--------|--------|
| GET admin/users | index | All users list |
| GET admin/users/create | create | Create form |
| POST admin/users | store | Create + sendWelcomeEmail |
| GET admin/users/{user}/edit | edit | Edit form |
| PUT admin/users/{user} | update | Update user |
| DELETE admin/users/{user} | destroy | SoftDelete |
| POST admin/users/{user}/terminate | terminate | Deactivate |
| POST admin/users/{user}/restore | restore | Restore soft-deleted |

#### `Admin/ExamController.php`
| Route | Action |
|-------|--------|
| GET admin/exams | index — list all exams |
| GET admin/exams/{exam} | show — exam detail |
| GET admin/exams/{exam}/results | results — view results |
| POST admin/exams/{exam}/approve | approve — approve exam |
| POST admin/exams/{exam}/schedule | schedule — set exam schedule |
| PUT admin/exams/{exam}/schedule/{schedule} | updateSchedule |
| DELETE admin/exams/{exam}/schedule/{schedule} | deleteSchedule |
| POST admin/exams/{exam}/publish | publish |
| POST admin/exams/{exam}/close | close |
| POST admin/exams/{exam}/open | open (reopen closed) |

#### `Admin/EmailController.php`
| Route Group | Description |
|-------------|-------------|
| email/inbox | IMAP inbox — list, show, reply, read, archive |
| email/compose | Bulk compose — preview, send custom, send timetable |
| email/sent | Sent emails view |
| email/outbox | Queued emails view |
| email/logs | Email log CRUD + retry |
| email/smtp | SMTP settings form |
| email/timetable | Exam timetable notification batch |

#### `Admin/StudentController.php` / `Admin/TeacherController.php`
Standard CRUD + show profile + edit/update

#### `Admin/AcademicYearController.php`
Resource CRUD + `students()` + `assignStudents()` + `removeStudent()`


### 6.2 Teacher Controllers

#### `Teacher/ExamController.php`
| Method | Action |
|--------|--------|
| index | My exams list |
| create/store | Create exam |
| show | Exam detail + questions |
| addQuestion | Add question + answers (encrypted) |
| editQuestion/updateQuestion | Edit question |
| deleteQuestion | Delete question |
| submitForApproval | Change status → pending_approval |
| destroy | Delete draft exam |
| results | View exam results |
| importQuestions | Bulk import via QuestionImportService |

### 6.3 Student Controllers

#### `Student/ExamController.php`
| Method | Action |
|--------|--------|
| index | Available exams list |
| show | Exam detail + schedule info |
| start | Create ExamAttempt + set question_order (shuffle if enabled) |

#### `Student/ExamSessionController.php`
Main exam-taking controller. See §11 for Anti-Cheat and §12 for Session Recovery.

| Method | Route | Middleware |
|--------|-------|-----------|
| take | GET attempt/{attempt}/take | exam.active |
| saveAnswer | POST attempt/{attempt}/save | exam.active |
| violation | POST attempt/{attempt}/violation | exam.active |
| disconnect | POST attempt/{attempt}/disconnect | (none — must work on page-unload) |
| submit | POST attempt/{attempt}/submit | exam.active |

### 6.4 Auth Controllers

#### `Auth/AuthController.php`
| Method | Description |
|--------|-------------|
| showLogin/login | Login with lockout check + force_password_change redirect |
| showRegister/register | Registration |
| logout | Clear exam_session_token + logout |
| showForcePasswordChange | Force change form |
| updateForcePasswordChange | Update pw, clear force flag |
| requestNewTemporaryPassword | Cooldown-gated temp pw resend |

#### `Auth/ForgotPasswordController.php`
| Method | Description |
|--------|-------------|
| showEmailForm | Enter email |
| sendOtp | Generate OTP → SendProfileOtpJob |
| showVerifyForm | Enter 6-digit OTP |
| checkOtp | Verify OTP (max 5 attempts, 5 min expiry) |
| resetPassword | Set new password |
| resendOtp | 60s cooldown gated resend |

### 6.5 Shared Controllers

#### `DashboardController.php`
- `admin()` — stats: users, exams, recent activity
- `teacher()` — my courses, pending exams, recent results
- `student()` — my exams, results, notifications

#### `ProfileController.php`
- `show()` — profile view
- `updatePhoto()` — upload profile_photo to storage/public
- `deletePhoto()` — remove photo
- `changePassword()` — OTP verification flow via ProfileOtp

#### `NotificationController.php`
- `index()`, `markRead()`, `markAllRead()`, `unreadCount()`, `unreadCountsByCategory()`

---

## 7. Middleware

| Middleware | Class | Description |
|-----------|-------|-------------|
| `auth` | Authenticate.php | Laravel default — must be logged in |
| `guest` | RedirectIfAuthenticated.php | Redirect authenticated users |
| `role:admin` | RoleMiddleware.php | Check role.slug === 'admin' |
| `role:teacher,admin` | RoleMiddleware.php | Teacher or admin |
| `role:student` | RoleMiddleware.php | Student only |
| `exam.session` | EnsureSingleExamSession.php | Single-device exam session |
| `exam.active` | EnsureExamActive.php | Block finished/terminated attempts |
| `force.password.change` | ForcePasswordChange.php | Redirect to change form |

### EnsureSingleExamSession
1. Student မဟုတ်ရင် skip
2. `exam_session_token` မရှိရင် generate (60 chars random)
3. Session ထဲ token မတူ → logout + "Another active session detected"

### EnsureExamActive
- `attempt->isActive()` ဆိုမှ pass
- ကျန်ရင် status မူတည်ပြီး error message ပြ
- AJAX/JSON request → 403 JSON response
- Web request → redirect to exams index

### ForcePasswordChange
- `force_password_change = true` + route မဟုတ် `password.force-change*` ဆိုရင် → redirect to change form
- `isTemporaryPasswordExpired()` ဆိုရင် same redirect


---

## 8. Routes

### Route Groups Overview

```
/ (home)                      → welcome view
/login, /register             → guest middleware
/forgot-password/*            → guest middleware
/logout                       → auth middleware
/password/change              → auth middleware

/admin/*   → auth + exam.session + force.password.change + role:admin
/teacher/* → auth + exam.session + force.password.change + role:teacher,admin
/student/* → auth + exam.session + force.password.change + role:student
```

### Named Route Reference

#### Auth
| Name | URL | Description |
|------|-----|-------------|
| login | GET /login | |
| register | GET /register | |
| logout | POST /logout | |
| forgot-password | GET /forgot-password | |
| forgot-password.send | POST /forgot-password/send | |
| forgot-password.verify | GET /forgot-password/verify | |
| forgot-password.check-otp | POST /forgot-password/check-otp | |
| forgot-password.reset | POST /forgot-password/verify | |
| forgot-password.resend | POST /forgot-password/resend | |
| password.force-change | GET /password/change | |
| password.force-change.update | POST /password/change | |
| login.request-new-password | POST /login/request-new-password | |

#### Admin
| Name | URL | Description |
|------|-----|-------------|
| admin.dashboard | GET /admin/dashboard | |
| admin.users.* | /admin/users | CRUD |
| admin.users.terminate | POST /admin/users/{user}/terminate | |
| admin.users.restore | POST /admin/users/{user}/restore | |
| admin.courses.* | /admin/courses | CRUD |
| admin.majors.* | /admin/majors | CRUD |
| admin.enrollments.* | /admin/enrollments | |
| admin.exams.* | /admin/exams | |
| admin.students.* | /admin/students | CRUD |
| admin.teachers.* | /admin/teachers | CRUD |
| admin.results.index | GET /admin/results | |
| admin.cheating-logs | GET /admin/cheating-logs | |
| admin.academic.years.* | /admin/academic/years | CRUD |
| admin.email.* | /admin/email/... | Email management |

#### Teacher
| Name | URL | Description |
|------|-----|-------------|
| teacher.dashboard | GET /teacher/dashboard | |
| teacher.exams.* | /teacher/exams | Exam CRUD |
| teacher.exams.questions.store | POST /teacher/exams/{exam}/questions | |
| teacher.exams.submit | POST /teacher/exams/{exam}/submit | Submit for approval |
| teacher.results.index | GET /teacher/results | |

#### Student
| Name | URL | Description |
|------|-----|-------------|
| student.dashboard | GET /student/dashboard | |
| student.exams.index | GET /student/exams | |
| student.exams.show | GET /student/exams/{exam} | |
| student.exams.start | POST /student/exams/{exam}/start | Create attempt |
| student.exam.take | GET /student/attempt/{attempt}/take | Live exam page |
| student.exam.save | POST /student/attempt/{attempt}/save | Auto-save answer |
| student.exam.violation | POST /student/attempt/{attempt}/violation | Report violation |
| student.exam.disconnect | POST /student/attempt/{attempt}/disconnect | Browser close signal |
| student.exam.submit | POST /student/attempt/{attempt}/submit | Final submit |
| student.results.index | GET /student/results | |


---

## 9. Jobs & Queue

Queue channel: `emails` (database driver)

| Job | File | Queue | Description |
|-----|------|-------|-------------|
| SendEmailJob | Jobs/SendEmailJob.php | emails | Deliver one EmailLog via SMTP; 3 retries, 30s backoff |
| InboxSyncJob | Jobs/InboxSyncJob.php | default | IMAP inbox sync |
| SendExamTimetableNotificationJob | Jobs/SendExamTimetableNotificationJob.php | emails | Batch timetable email |
| SendNewTemporaryPasswordJob | Jobs/SendNewTemporaryPasswordJob.php | emails | Temp password email |
| SendPasswordChangedJob | Jobs/SendPasswordChangedJob.php | emails | Password changed notification |
| SendProfileOtpJob | Jobs/SendProfileOtpJob.php | emails | OTP email |
| SendWelcomeAccountJob | Jobs/SendWelcomeAccountJob.php | emails | Welcome email |

### SendEmailJob Detail
```php
public int $tries   = 3;
public int $backoff = 30;  // seconds between retries
```
- `handle()`: EmailLog ရှာ → already sent ဆိုရင် skip → `emailService->deliver($log)`
- `failed()`: `markFailed('Job failed after max retries: ...')`

---

## 10. Artisan Commands & Scheduler

### Commands

#### `results:notify-students`
**File:** `app/Console/Commands/NotifyStudentResults.php`
```
php artisan results:notify-students [--exam=ID] [--dry-run]
```
**Purpose:** Exam schedule ends_at ပြီးပြီဆိုမှ students ကို result notification ပို့သည်

**Eligibility conditions:**
1. `exam_schedule.ends_at < now()`
2. Result record exists
3. `attempt.status = 'submitted'`
4. `exam_result_status` ≠ DISQUALIFIED and ≠ ABSENT
5. Deduplication: `(user_id, type='exam_result', link)` triple မတူဖူး

---

#### `results:mark-absent`
**File:** `app/Console/Commands/MarkAbsentResults.php`
```
php artisan results:mark-absent [--exam=ID] [--dry-run]
```
**Purpose:** Exam window ကုန်ပြီး `started_at` မပါတဲ့ enrolled students ကို ABSENT result ဖန်တီးသည်

**Absent condition:**
- `ends_at < now()` + enrolled + NO attempt with started_at NOT NULL + NO existing result

---

#### `inbox:sync`
**File:** `app/Console/Commands/SyncInbox.php`
IMAP inbox ကို manual trigger ဖြင့် sync လုပ်သည်

---

#### `email:stats`
**File:** `app/Console/Commands/EmailStats.php`
Email statistics report ထုတ်သည်

---

### Scheduler (`Console/Kernel.php`)

| Command | Frequency | withoutOverlapping |
|---------|-----------|-------------------|
| `inbox:sync` | every minute | 5 min lock |
| `results:notify-students` | every minute | 5 min lock |

> **Note:** `results:mark-absent` is NOT in the scheduler — run manually or add as needed.

---

## 11. Anti-Cheating System

### Client-Side Detection (JavaScript)
`exam-anticheat.js` ဖြင့် detect လုပ်ပြီး `student.exam.violation` endpoint ကို POST လုပ်သည်

| Violation Type | Trigger |
|---------------|---------|
| `fullscreen_exit` | Fullscreen မှ ထွက်သည် |
| `tab_switch` | Browser tab ပြောင်းသည် |
| `window_blur` | Window focus ဆုံးသည် |
| `right_click` | Right-click ကြိုးစားသည် |
| `copy` | Ctrl+C / Copy ကြိုးစားသည် |
| `paste` | Ctrl+V / Paste ကြိုးစားသည် |
| `devtools_open` | DevTools ဖွင့်သည် |
| `keyboard_shortcut` | F12, Ctrl+Shift+I ကဲ့သို့ |

### Server-Side Enforcement (ExamSecurityService)

```
Violation 1 (warning_count = 1):
  ├─ CheatingLog row create
  ├─ exam_attempts.warning_count +1
  ├─ ActivityLog: security_warning_1
  └─ Response: "Warning 1 of 3" — exam continues

Violation 2 (warning_count = 2):
  ├─ CheatingLog row create
  ├─ exam_attempts.warning_count +1
  ├─ Notify teacher + all admins (email + notification)
  ├─ ActivityLog: security_warning_2
  └─ Response: "Warning 2 of 3" — exam continues

Violation 3 (warning_count = 3):
  ├─ DB::transaction + lockForUpdate() (prevents race condition)
  ├─ CheatingLog row create
  ├─ exam_attempts.status = 'terminated'
  ├─ exam_attempts.terminated_at = now()
  ├─ users.exam_session_token = null (session revoked)
  ├─ GradingService::gradeAttempt() (grade with saved answers)
  ├─ results.exam_result_status = DISQUALIFIED
  ├─ results.is_published = false
  ├─ ActivityLog: exam_terminated_security
  ├─ DB::afterCommit → Email student + teacher + admins (HIGH PRIORITY)
  └─ Response: "Terminated" + redirect
```

### terminated_pending_review Flow (Legacy path)
```
Admin/Teacher Review:
  Approve → status = 'in_progress'
             expires_at extended by locked duration (capped at maxResumeExtensionMinutes)
             approved_by, approved_at, approval_comment set
             Student notified: "Exam Session Approved"

  Reject  → status = 'rejected'
             rejected_by, rejected_at, rejection_comment set
             terminated_at preserved (forensic)
             Student notified: "Exam Session Rejected"
```

### Device Fingerprint (Forensic)
`cheating_logs` table တွင် client-side မှ capture:
- Raw `user_agent` string
- Parsed `browser`, `device`, `os`
- `screen_resolution`, `timezone`
- Server-side `ip_address`


---

## 12. Session Recovery System

### Timing Contract
```
expires_at = MIN(started_at + duration_minutes, schedule.ends_at)
```
This single value is the **Final Expiry Time** — set once at attempt creation, never modified.

### Disconnect → Recovery Flow

```
Student's browser closes / network drops
           │
           ▼
ExamSessionController::disconnect()
           │
           ├─► exam_attempts.disconnected_at = now()
           ├─► exam_attempts.last_question_id = current
           ├─► status STAYS 'in_progress' (NOT changed)
           └─► SessionRecoveryLog created (recovery_status='pending')

           │
           ▼
Student returns to /attempt/{attempt}/take
           │
    ┌──────┴───────┐
    │              │
    ▼              ▼
Within 5 min    After 5 min OR
AND before      expires_at passed
expires_at
    │              │
    ▼              ▼
Path A:         Path B:
Restore         finalizeExpiredSession()
    │              │
    ├─ Clear        ├─ status = 'submitted'
    │  disconnected_at  ├─ GradingService::gradeAttempt()
    ├─ Update log   ├─ exam_session_token cleared
    │  recovery_status='recovered'  └─ Log recovery_status='expired'
    └─ Return frozen_seconds
       (timer from expires_at - disconnected_at)
```

### Invariants (NEVER violated)
- No new ExamAttempt created
- `expires_at` never modified after creation
- `student_answers` never deleted or modified
- `warning_count` never touched by recovery service

---

## 13. Email System

### Email Flow

```
Event occurs (user created, violation, etc.)
     │
     ▼
EmailService::send() / sendWelcomeEmail() / etc.
     │
     ▼
EmailLog created (status='queued')
     │
     ▼
SendEmailJob::dispatch($logId) → queue: 'emails'
     │
     ▼
Worker processes → EmailService::deliver($log)
     │
     ├─ Success → log.status='sent', log.sent_at=now()
     └─ Failure → log.status='failed', log.error=message
                  Retry up to 3× (30s backoff)
```

### Email Types / Events

| Event Key | When Fired |
|-----------|-----------|
| `welcome_account` | New user created |
| `temp_password` | Temporary password issued |
| `password_changed` | Password changed |
| `profile_otp` | OTP for password change |
| `security_warning` | Violation 2 notification |
| `security_incident_high` | Violation 3 termination |
| `exam_timetable` | Timetable batch notification |
| `cheating_detected` | Legacy CheatingDetectionService |
| `bulk` | Admin bulk compose |

### Template Variables
All templates support `{{variable}}` syntax:
`student_name`, `teacher_name`, `name`, `email`, `student_id`, `app_name`, `app_url`, `year`, `year_level`, `academic_year`, `department`, `major`, `semester`, `course_name`

### Inbox (IMAP Sync)
- `InboxSyncService` — IMAP ကို poll လုပ်ပြီး `inbox_emails` table ထဲသိမ်းသည်
- `InboxSyncState` — last sync timestamp tracker
- Scheduler: every minute via `inbox:sync` command
- Thread tracking: `thread_id` / `in_reply_to` / `message_id` columns

---

## 14. Enums & Constants

### RoleSlug (`app/Enums/RoleSlug.php`)
```php
ADMIN   = 'admin'
TEACHER = 'teacher'
STUDENT = 'student'
```

### RecordType (`app/Enums/RecordType.php`)
StudentYearRecord.record_type values

### Result Status Constants (`app/Models/Result.php`)
```php
STATUS_PASSED       = 'PASSED'
STATUS_FAILED       = 'FAILED'
STATUS_ABSENT       = 'ABSENT'
STATUS_DISQUALIFIED = 'DISQUALIFIED'
ATTENDANCE_ATTENDED = 'attended'
ATTENDANCE_ABSENT   = 'absent'
```

### ExamAttempt Status Values
```
in_progress               → Active exam session
submitted                 → Normally completed
terminated                → Security violation 3 (DISQUALIFIED)
suspicious                → Legacy CheatingDetectionService
terminated_pending_review → Locked for human review
rejected                  → Admin reviewed and rejected
```

### Exam Status Values
```
draft              → Teacher working on it
pending_approval   → Submitted to admin
approved           → Admin approved, schedule can be set
published          → Active, students can take
closed             → Window ended
```

### AcademicYear OPTIONS (`app/Support/AcademicYear.php`)
`User::academicYears()` — year integer dropdown options


---

## 15. Key Business Rules

### Login Security
1. Max 3 failed attempts → locked for 10 minutes
2. `force_password_change = true` → redirect to change form on every request
3. Temporary password expires in 24 hours
4. Resend cooldown: 60 seconds between requests
5. OTP: 6-digit, bcrypt stored, 5-minute validity, max 5 wrong attempts

### Exam Access Control
1. Student can take exam ONLY if:
   - Enrolled in the course
   - Has a StudentYearRecord matching exam's academic_year + course's year_level
   - Attempts used < schedule.attempt_limit
   - Current time is within schedule.starts_at → ends_at
2. Questions decrypted ONLY for: admin, owner teacher, or student within schedule window
3. Correct answers visible ONLY after schedule.ends_at (students only)

### Grading Rules
1. Auto-graded: MCQ, true_false, fill_blank
2. Manual grading needed: essay, file_upload
3. fill_blank: case-sensitive exact match (uppercase "A" ≠ lowercase "a")
4. DISQUALIFIED results are NEVER re-graded (guard in GradingService)
5. Result notification sent AFTER schedule.ends_at (not at grading time)

### Absent Detection
- Student is ABSENT if: enrolled + schedule ended + NO attempt with started_at + NO result
- `isDisplayedAsAbsent()` is display-only — does NOT change DB status
- `results:mark-absent` command creates actual ABSENT Result records

### Course-Teacher Assignment Safety
- Historical courses (with published/approved/closed exams) keep their `teacher_id`
- Only courses with draft/pending_approval exams (or no exams) can be unassigned

### Enrollment Context
- `enrollment.year` (int) = legacy field
- `enrollment.year_level_id` = FK to year_levels (proper relational)
- `enrollment.major_id` = null for Year 1 students

### ExamAttempt Timer
```
expires_at = MIN(
    started_at + duration_minutes,
    schedule.ends_at
)
```
This is set ONCE at attempt creation and NEVER modified.
During session recovery, the timer is "frozen" — disconnect time is not consumed.

### Email Queue
- All emails are queued (never synchronous during exam requests)
- `DB::afterCommit()` — emails fire only AFTER the transaction commits
- SendEmailJob: 3 retries, 30-second backoff
- Idempotent: already-sent logs are skipped by the job

### Single Session Enforcement
- Student ကို device တစ်ခုတည်းမှာသာ exam ဆောင်ခွင့်ပြုသည်
- `exam_session_token` (users table) = server-side truth
- Session value ≠ DB value → logout ချက်ချင်း

---

## 16. Complete Data Flow Diagram

```
ADMIN                    TEACHER                  STUDENT
  │                        │                        │
  ▼                        ▼                        │
Create AcademicYear    Create Exam (draft)           │
Create YearLevel       Add Questions (encrypted)     │
Create Major           Add Answers (encrypted)       │
  │                    Submit for Approval           │
  ▼                        │                        │
Create Users           ◄───┘                        │
Assign to AcademicYear                              │
  │                                                 │
  ▼                                                 │
Approve Exam ──────────────────────────────────────►│
Set Schedule                                         │
Publish                                              │
  │                                                 │
  │                                        ─────────┘
  │                                       │
  │                                       ▼
  │                              Student views exam list
  │                              [ExamAccessService::studentCanTakeExam()]
  │                                       │
  │                              POST /student/exams/{exam}/start
  │                                       │
  │                              ExamAttempt created
  │                              question_order saved (shuffle if enabled)
  │                              expires_at = MIN(started + duration, ends_at)
  │                                       │
  │                              ┌────────▼────────┐
  │                              │   LIVE EXAM     │
  │                              │ (EnsureExamActive│
  │                              │  middleware)    │
  │                              └────────┬────────┘
  │                                       │
  │                    ┌──────────────────┼──────────────────┐
  │                    │                  │                  │
  │                    ▼                  ▼                  ▼
  │              Auto-save           Violation          Submit
  │              (saveAnswer)        (violation)        (submit)
  │                    │                  │                  │
  │                    │            ExamSecurity             │
  │                    │            Service                  │
  │                    │                  │                  │
  │                    │         Warning 1/2: continue       │
  │                    │         Warning 3: TERMINATE        │
  │                    │                  │                  │
  │                    │                  ▼                  ▼
  │                    │           GradingService      GradingService
  │                    │           grade + DISQUALIFY  grade normally
  │                    │                  │                  │
  │                    │                  ▼                  ▼
  │                    │              Result            Result
  │                    │              (DISQUALIFIED)    (PASSED/FAILED)
  │                    │                                    │
  │                    └────────────────────────────────────┘
  │                                       │
  │                              [After schedule.ends_at]
  │                                       │
  │                              results:notify-students
  │                              → UserNotification to students
  │                                       │
  │◄──────────────────────────────────────┘
  │
  ▼
Admin views Results
Admin views CheatingLogs
Admin manages Email Inbox
```

---

*Manual generated from full source code scan — BLC_Complete_Final*
*Laravel 10 · PHP 8.1+ · MySQL 8.0+*
