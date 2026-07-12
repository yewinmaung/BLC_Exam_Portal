# REQUIREMENTS SPECIFICATION
## Believe Exam — Online Examination Management System

> **Document Type:** Official Functional Requirements Specification
> **Status:** Approved for Implementation
> **Date:** 2026-07-01
> **Version:** 1.0

---

## Table of Contents

1. [Document Information](#1-document-information)
2. [Project Objectives](#2-project-objectives)
3. [Functional Requirements](#3-functional-requirements)
   - 3.1 Authentication
   - 3.2 Exam Management
   - 3.3 Question Management
   - 3.4 Exam Session
   - 3.5 Browser Security
   - 3.6 Warning System
   - 3.7 Auto Termination
   - 3.8 Result Processing
   - 3.9 Student Dashboard
   - 3.10 Teacher Dashboard
   - 3.11 Administrator Dashboard
   - 3.12 Notifications
   - 3.13 Logs and Audit Trail
   - 3.14 Reports
   - 3.15 Course and Enrollment Management
   - 3.16 Re-Attempt System
   - 3.17 Academic Records, Transcripts, and Certificates
   - 3.18 Email Management
   - 3.19 Chat
4. [Non-Functional Requirements](#4-non-functional-requirements)
5. [Business Rules](#5-business-rules)
6. [Validation Rules](#6-validation-rules)
7. [User Roles and Permissions](#7-user-roles-and-permissions)
8. [Feature Modification Requirements](#8-feature-modification-requirements)
9. [Regression Protection Requirements](#9-regression-protection-requirements)
10. [Acceptance Criteria](#10-acceptance-criteria)
11. [Out of Scope](#11-out-of-scope)
12. [Assumptions](#12-assumptions)
13. [Constraints](#13-constraints)
14. [Risks](#14-risks)
15. [Testing Requirements](#15-testing-requirements)

---

## 1. Document Information

### 1.1 Purpose
This document defines the complete functional and non-functional requirements for the Believe Exam Online Examination Management System. It serves as the official specification that governs all future development, modification, testing, and acceptance work on the system.

This is a **requirements** document. It describes **what** the system must do. It does not describe how to implement any feature.

### 1.2 Scope
The scope covers the entire Believe Exam web application, including:
- All three user roles: Administrator, Teacher, Student
- The complete examination lifecycle from exam creation to result publication
- The exam security and anti-cheat enforcement system
- The re-attempt request workflow
- The academic records, transcript, and certificate modules
- The email communication and notification systems
- The course and enrollment management modules
- The real-time exam session management including timer, answer saving, and violations
- The reporting and audit trail features

This document does not cover server infrastructure, deployment pipelines, or database administration procedures.

### 1.3 References

| Reference | Description |
|---|---|
| `documents/ANALYSIS.md` | Comprehensive Architecture Analysis — the primary technical reference for all requirements in this document |
| `documents/KIRO_EXAM_SECURITY_SPECIFICATION.md` | Exam security specification used during security feature development |
| `documents/INSTALLATION.md` | Installation and environment setup guide |
| `routes/web.php` | Authoritative route definitions |
| `app/Services/ExamSecurityService.php` | Security tier implementation reference |
| `public/js/exam-anticheat.js` | Client-side security enforcement reference |

---

## 2. Project Objectives

### 2.1 Overall Goals
The Believe Exam system must provide a complete, secure, and manageable online examination platform for BLC (Believe Learning Centre). The system must support the full academic workflow from user enrolment through to certificate issuance, with particular emphasis on examination integrity.

### 2.2 Problems to Solve
1. **Examination integrity**: Prevent students from cheating during online examinations through configurable browser-level security monitoring.
2. **Fair review process**: Provide administrators and teachers with a structured review workflow for security violations before penalising students.
3. **Attempt control**: Prevent students from taking exams more times than their allowed limit, while supporting controlled re-attempts when justified.
4. **Transparent results**: Ensure every student receives a result record (PASSED, FAILED, ABSENT, or DISQUALIFIED) for every exam they were scheduled for.
5. **Academic continuity**: Maintain permanent academic records including yearly transcripts and certificates across academic years.
6. **Communication**: Automate email and in-app notifications for all examination events.
7. **Configurable policy**: Allow administrators to adjust security policies without code deployments.

### 2.3 Expected Improvements
- All configurable security settings must be changeable from the Admin UI without touching `.env` or restarting the server.
- Security violation responses must follow a consistent 3-tier escalation model.
- All examination results must be permanently categorised with a single, authoritative status field.
- Re-attempt workflows must be tracked end-to-end with a full audit log.
- Email delivery must be reliable, queued, and observable through the admin email log.

---

## 3. Functional Requirements

---

### 3.1 Authentication

#### 3.1.1 Login
**Current Behaviour:** A login form accepts email and password. On success, the session is initialised, `last_login_at` is updated, and the user is redirected to their role-specific dashboard. On failure, an error message is shown.

**Required Behaviour:**
- The system must reject login attempts for users where `is_active = false` with a clear error message.
- The system must redirect authenticated users who access `/login` to their role-appropriate dashboard.
- On successful login, `users.last_login_at` must be updated to the current timestamp.
- On successful login, the `exam_session_token` for students must be stored in the server-side session.

**Inputs:** Email (string), Password (string)

**Outputs:** Session initialised, redirect to dashboard, or error message

**Validation Rules:**
- Email: required, valid email format, must exist in `users` table
- Password: required, minimum 8 characters
- User must have `is_active = true`

**User Roles:** All (Admin, Teacher, Student)

**Error Handling:**
- Invalid credentials: display generic "These credentials do not match our records" message
- Inactive account: display "Your account has been deactivated" message
- Already authenticated: redirect to role dashboard without re-login

---

#### 3.1.2 Registration
**Current Behaviour:** A public registration form creates a new user with the Student role.

**Required Behaviour:**
- Self-registration must only create accounts with the Student role.
- The system must not allow self-registration for Admin or Teacher roles.
- On successful registration the user must be logged in and redirected to the student dashboard.

**Inputs:** Name (string), Email (string), Password (string), Password Confirmation (string)

**Outputs:** New user record with student role, session started, redirect to student dashboard

**Validation Rules:**
- Name: required, max 255 characters
- Email: required, valid format, unique in `users` table
- Password: required, min 8 characters, confirmed
- Role: fixed as student — not user-selectable

**User Roles:** Unauthenticated (public)

---

#### 3.1.3 Logout
**Required Behaviour:**
- Logout must invalidate the current session and redirect to the login page.
- `exam_session_token` must be cleared from the session on logout.
- Logout requires a POST request (CSRF protected).

**User Roles:** All authenticated users

---

#### 3.1.4 Single Exam Session Enforcement
**Current Behaviour:** `EnsureSingleExamSession` middleware runs on all authenticated routes. It maintains a token in `users.exam_session_token` and the session. If a mismatch is detected, the current session is invalidated.

**Required Behaviour:**
- A student must only be able to have one active session at any time.
- If a student logs in from a second device while a session is active, the second login must be permitted but the first session must detect the mismatch on the next request and force logout with the message "Another active exam session was detected."
- Non-student users must pass through this middleware without any token checks.

**User Roles:** Students (enforced); Admin and Teacher (pass-through)

---

### 3.2 Exam Management

#### 3.2.1 Exam Lifecycle
**Current Behaviour:** Exams progress through five states: `draft` → `pending_approval` → `approved` → `published` → `closed`. Each state transition is triggered by a specific actor action.

**Required Behaviour:**

| Transition | Actor | Trigger |
|---|---|---|
| draft → pending_approval | Teacher | Teacher submits exam for approval |
| pending_approval → approved | Admin | Admin approves exam |
| approved → published | Admin | Admin publishes exam (requires existing schedule) |
| published → closed | Admin | Admin closes exam |
| closed → published | Admin | Admin reopens exam (requires existing schedule) |

- An exam must have at least one question before it can be submitted for approval.
- An exam must have at least one schedule before it can be published.
- Only an exam in `pending_approval` status can be approved.
- Only an exam in `approved` or `published` status is visible to enrolled students.
- An exam can be closed only when it is in `published` status.
- A closed exam can be reopened only when it has an existing schedule.

**User Roles:**
- Teacher: create, add questions, edit questions (draft/pending_approval only), submit for approval
- Admin: view all, approve, schedule, publish, close, reopen

---

#### 3.2.2 Exam Scheduling
**Current Behaviour:** Admin creates `ExamSchedule` records with start/end datetime, duration, and attempt limit. An exam can have multiple schedules; the latest is considered current.

**Required Behaviour:**
- Admins must be able to create, edit, and delete schedules for any exam.
- A published schedule must not be deletable. The exam must be closed first.
- A schedule must have `ends_at` strictly after `starts_at`.
- `duration_minutes` must be a positive integer.
- `attempt_limit` must be at least 1.
- When an exam is published, the latest schedule's `is_published` must be set to `true` and `published_at` must be recorded.
- When an exam is closed, the active schedule's `is_published` must be set to `false`.

**Inputs:** `starts_at` (datetime), `ends_at` (datetime), `duration_minutes` (int), `attempt_limit` (int), `target_year` (int, optional)

**Validation Rules:**
- `ends_at` must be after `starts_at`
- `duration_minutes`: required, integer ≥ 1
- `attempt_limit`: required, integer ≥ 1
- `target_year`: optional, integer 1–5

---

#### 3.2.3 Exam Publication and Notification
**Current Behaviour:** When an exam is published, enrolled students and the exam's teacher receive in-app notifications and queued emails.

**Required Behaviour:**
- On publish, every student enrolled in the exam's course must receive:
  - An in-app notification of type `exam_published`
  - A queued email using the `exam_published` template
- The teacher of the exam must receive an in-app notification confirming publication.
- Email sending must be queued, never blocking the HTTP response.

---

#### 3.2.4 Exam Visibility to Students
**Required Behaviour:**
- Students must only see exams where:
  - The exam status is `approved` or `published`
  - The student is enrolled in the exam's course
- Students must not see draft or pending_approval exams.

---

### 3.3 Question Management

#### 3.3.1 Question Types
**Current Behaviour:** Four question types are supported: MCQ, True/False, Essay, and Fill-in-the-Blank.

**Required Behaviour:**
- `mcq`: Multiple choice with exactly one correct answer. Minimum 2 answer options required.
- `true_false`: Exactly two answer options: True and False. One must be marked correct.
- `essay`: Free-text answer. No auto-grading. No answer options required.
- `fill_blank`: One or more accepted text answers. Graded by case-insensitive exact match.
- All question content and answer content must be stored encrypted.
- Questions must be ordered within an exam by the `order` field.

---

#### 3.3.2 Question Creation and Editing
**Current Behaviour:** Teachers add and edit questions via a form backed by `question-builder.js`. Questions can only be added or modified when the exam is in `draft` or `pending_approval` status.

**Required Behaviour:**
- Teachers must not be able to add or modify questions on exams in `approved`, `published`, or `closed` status.
- If an exam is in `pending_approval`, admins must be notified when a question is added.
- Each question must have: type, content (required), marks (min 1), difficulty (easy/medium/hard).
- Category (`category_id`) is optional.
- Questions may have an optional file attachment (image or document).

**Inputs (question):** type (enum), content (string), marks (int ≥ 1), difficulty (enum), category_id (nullable int)
**Inputs (MCQ/TF answers):** array of content strings, one marked as correct
**Inputs (fill_blank):** array of accepted answer strings (at least one)

**Validation Rules:**
- Type: required, one of: mcq, true_false, essay, fill_blank
- Content: required, non-empty string
- Marks: required, integer ≥ 1
- Difficulty: required, one of: easy, medium, hard
- MCQ must have at least 2 answer options with exactly one marked correct
- True/False must have exactly 2 options
- Fill_blank must have at least 1 accepted answer string

**Error Handling:**
- Attempt to add/edit question on approved exam: HTTP 403
- Empty question content: validation error returned to form

---

#### 3.3.3 Question Import
**Current Behaviour:** Teachers can import questions from `.txt`, `.docx`, `.pdf`, or `.doc` files using a Moodle-style plain-text format.

**Required Behaviour:**
- The system must parse the import file and create `Question` and `Answer` records.
- Format: `[MCQ]`, `[TRUE_FALSE]`, `[ESSAY]`, `[FILL_BLANK]` block headers.
- Marks can be specified in parentheses: `(2 marks)`.
- Correct answers are denoted with `*`.
- Imported question content and answer content must be stored encrypted.
- The system must report how many questions were successfully imported.
- If no parseable questions are found in a PDF/DOC, a single document-type question is created referencing the file.

**Inputs:** File (`.txt`, `.docx`, `.pdf`, `.doc`), optional `category_id`
**Outputs:** Count of questions imported, questions visible in exam

**Validation Rules:**
- File type: must be txt, docx, pdf, or doc
- File size: max 5 MB

---

#### 3.3.4 Question Deletion
**Required Behaviour:**
- Only the teacher who owns the exam (or an admin) may delete questions.
- Questions may only be deleted when the exam is in `draft` or `pending_approval` status.
- On deletion, any attached file must be removed from storage.
- Answers associated with the deleted question must be deleted.

---

### 3.4 Exam Session

#### 3.4.1 Starting an Exam
**Current Behaviour:** `Student\ExamController::start()` validates eligibility, creates an `ExamAttempt`, sets the session token, and redirects to the take page.

**Required Behaviour:**
- The system must verify the student is enrolled in the exam's course before allowing start.
- The system must verify the exam is in `approved` or `published` status.
- The system must verify the schedule window is currently active, or an approved re-attempt window is active.
- The system must verify the student has not exceeded the effective attempt limit.
- If the student has an existing `in_progress` attempt, the system must resume it instead of creating a new one.
- On a successful new start:
  - A new `ExamAttempt` record must be created with `status = in_progress`
  - `started_at` must be set to the current timestamp
  - `expires_at` must be set to `now() + duration_minutes`
  - A session token must be generated, stored in `users.exam_session_token` and `session`
- The student must be immediately redirected to the exam take page.

**Inputs:** Exam ID (route parameter)
**Outputs:** `ExamAttempt` created, redirect to take page

**Error Handling:**
- Not enrolled: redirect to exam list with error
- Attempt limit reached: show error, offer re-attempt request link if eligible
- Outside schedule window: redirect with "Exam is not available" error
- Active re-attempt window present but outside it: show precise window dates in error

---

#### 3.4.2 Taking an Exam
**Current Behaviour:** `ExamSessionController::take()` decrypts questions, builds the question list, and renders the exam view with the security policy baked into `data-*` attributes.

**Required Behaviour:**
- The exam interface must present questions one at a time using a question navigator.
- Questions must be decryptable only during active schedule or re-attempt windows.
- If `expires_at` has already passed when the take page loads, the attempt must be auto-submitted and the student redirected.
- The view must receive the `securityPolicy` array from `SecuritySetting::policy()`.
- All 8 security policy flags must be written to `data-policy-*` attributes on the `#examBody` element.
- A visible countdown timer must be displayed, counting down to `expires_at`.
- Previously saved answers must be pre-loaded from `student_answers`.

**Inputs:** Attempt ID (route parameter)
**Outputs:** Rendered exam page with questions, timer, and security policy active

**Validation:**
- Attempt must belong to the authenticated student (403 if not)
- Attempt must be `in_progress` (enforced by `exam.active` middleware)
- Questions must be accessible per `ExamAccessService::canDecryptQuestions()`

---

#### 3.4.3 Saving Answers
**Current Behaviour:** `saveAnswer()` uses `updateOrCreate` on `StudentAnswer`. Called via fetch from `exam-anticheat.js` on MCQ click, fill-blank input (800ms debounce), essay input (1500ms debounce), and periodic MCQ auto-save every 10 seconds.

**Required Behaviour:**
- Each save request must create or update exactly one `StudentAnswer` row.
- The system must accept `answer_id` for MCQ/TF, `answer_text` for fill-blank and essay.
- If the attempt is no longer `in_progress`, the server must reject save requests with HTTP 403.
- File answers may be submitted for essay questions (PDF, DOC, DOCX, max 10 MB).

**Inputs:** `question_id` (required), `answer_id` (nullable), `answer_text` (nullable), `answer_file` (nullable file)
**Outputs:** `{"success": true}` JSON response

**Validation Rules:**
- `question_id`: required, must exist in `questions` table
- `answer_id`: nullable, must exist in `answers` table if provided
- `answer_text`: nullable string
- `answer_file`: nullable, mimes: pdf, doc, docx, max 10240 KB

---

#### 3.4.4 Exam Submission
**Current Behaviour:** `submit()` updates the attempt to `submitted`, clears the session token, and calls `GradingService::gradeAttempt()`.

**Required Behaviour:**
- Submission must set `attempt.status = submitted` and `attempt.submitted_at = now()`.
- Submission must clear `users.exam_session_token` and remove `exam_session_token` from the session.
- `GradingService::gradeAttempt()` must be called immediately after submission.
- The student must be redirected to the exam show page with a success message.
- Timer expiry must auto-submit using the same logic as manual submission.

**User Roles:** Student only

---

#### 3.4.5 Exam Session Middleware Guard
**Required Behaviour:**
- All exam session routes (`take`, `save`, `violation`, `submit`) must be protected by `exam.active` middleware.
- If the attempt status is not `in_progress`, the middleware must:
  - Return HTTP 403 JSON for fetch/XHR requests with fields: `terminated`, `locked`, `message`, `redirect`
  - Return a redirect response for browser navigation
- Status-specific messages:
  - `terminated_pending_review`: "Your exam session is locked pending review. You will be notified once a decision is made."
  - `rejected`: "Your exam session has been rejected following a security review."
  - `submitted`: "This exam has already been submitted."
  - Other: "This exam session is no longer active."

---

### 3.5 Browser Security

#### 3.5.1 Security Policy Configuration
**Current Behaviour:** All security policy flags are stored in `security_settings` table as JSON-encoded values. They are loaded by `SecuritySetting::policy()` and passed to the exam view, which writes them as `data-policy-*` attributes.

**Required Behaviour:**
- The following 8 security policy flags must be configurable by admins from the Security Settings UI:

| Setting Key | Default | Description |
|---|---|---|
| `fullscreen_detection_enabled` | true | Detect when student exits fullscreen |
| `blur_detection_enabled` | true | Detect when exam window loses focus |
| `tab_switch_detection_enabled` | true | Detect document hidden / tab switch |
| `right_click_blocking_enabled` | true | Block right-click context menu |
| `copy_detection_enabled` | true | Detect copy and cut events |
| `paste_detection_enabled` | true | Detect paste events |
| `devtools_detection_enabled` | true | Detect DevTools via element size changes |
| `keyboard_shortcut_detection_enabled` | true | Block F12, Ctrl+Shift+I/J/C, Ctrl+U |

- In addition, `max_warnings` (integer, min 1) and `auto_terminate_enabled` (boolean) must be configurable.
- All settings must be persisted in the `security_settings` table.
- Settings must be cached for 60 minutes per key and invalidated immediately when saved.
- Policy flags must be read from the DB (not hardcoded) when the exam take page loads.
- The exam JavaScript must register event listeners only for flags that are enabled.

---

#### 3.5.2 Fullscreen Enforcement
**Required Behaviour:**
- When `fullscreen_detection_enabled` is true, the exam interface must request fullscreen on entry.
- A fullscreen gate overlay must block the exam until the student clicks "Enter Fullscreen".
- If the student exits fullscreen during the exam, a violation of type `fullscreen_exit` must be reported.
- `examStarted` flag must be set to `true` only after the fullscreen overlay is dismissed.

---

#### 3.5.3 Tab Switch and Window Blur Detection
**Required Behaviour:**
- When `tab_switch_detection_enabled` is true, a `visibilitychange` listener must report `tab_switch` violations when `document.hidden` becomes true.
- When `blur_detection_enabled` is true, a `window.blur` listener must report `window_blur` violations, but only after `examStarted = true`.

---

#### 3.5.4 Input Blocking
**Required Behaviour:**
- When `right_click_blocking_enabled` is true, `contextmenu` must be prevented on the exam page.
- When `copy_detection_enabled` is true, `copy` and `cut` events must be prevented and reported.
- When `paste_detection_enabled` is true, `paste` events must be prevented and reported.
- When `keyboard_shortcut_detection_enabled` is true, F12, Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+Shift+C, and Ctrl+U must be blocked and reported as `devtools_shortcut`.

---

#### 3.5.5 Interface Lock on Termination
**Required Behaviour:**
- When the server responds with `terminated: true`, the exam JavaScript must immediately call `lockExamInterface()`.
- `lockExamInterface()` must:
  1. Stop all running intervals and timeouts
  2. Remove all detection event listeners
  3. Disable all answer inputs, navigation buttons, and the submit button
  4. Exit fullscreen if active
  5. Display a full-viewport `#examLockedOverlay` with the termination message
  6. Redirect to the student exam list after 3 seconds
- Once called, `lockExamInterface()` must be idempotent (calling it again must have no effect).

---

### 3.6 Warning System

> **Change Request Applied:** Maximum Warning Behaviour — approved 2026-07-01.
> The violation counter is now fixed at 3. Violation 1 and 2 warn and continue. Violation 3 terminates unconditionally. The `auto_terminate_enabled` flag no longer gates termination on violation 3. The `max_warnings` configurable threshold no longer governs the 1-2-3 sequence; the sequence is fixed by this business rule.

#### 3.6.1 Violation Recording
**Current Behaviour:** `ExamSessionController::violation()` delegates to `ExamSecurityService::recordViolation()`. The service checks if the violation type is enabled, increments `warning_count`, persists a `CheatingLog` row, and returns a structured JSON response.

**Required Behaviour:**
- Violation requests must only be accepted for attempts with `status = in_progress`.
- If the violation type is disabled by the current security policy, the violation must be silently recorded as a no-op (no warning count increment, no log).
- If `warning_count` is already at or above 3, the system must return the locked response immediately without further processing.
- Every violation that is counted must create a `CheatingLog` record.
- The `CheatingLog` record must store: `attempt_id`, `student_id`, `violation_type`, `details`, `warning_number`, and client metadata (`user_agent`, `browser`, `device`, `os`, `screen_resolution`, `timezone`, `ip_address`).
- Duplicate violations of the same type are counted independently — each POST increments the counter by exactly 1.

**Inputs:** `type` (string, max 80), `details` (nullable string, max 500)

**Required JSON Response Shape (must not change):**
```json
{
  "warning_count": <integer>,
  "terminated": <boolean>,
  "locked": <boolean>,
  "message": <string>,
  "redirect": <string|null>
}
```

---

#### 3.6.2 Warning 1 — First Violation
**Required Behaviour:**
- When `warning_count` reaches 1 after incrementing:
  - A `CheatingLog` record must be created.
  - Log an `ActivityLog` entry with action `security_warning_1`.
  - Return `terminated: false`, `locked: false`.
  - Return message: "⚠️ Warning 1 of 3: Prohibited activity detected. A second violation will notify your instructor."
  - The student must be allowed to continue the examination.
- No email or notification must be sent at Warning 1.

---

#### 3.6.3 Warning 2 — Second Violation
**Required Behaviour:**
- When `warning_count` reaches 2 after incrementing:
  - A `CheatingLog` record must be created.
  - Log an `ActivityLog` entry with action `security_warning_2`.
  - Queue an email to the responsible teacher and all active admins using the `security-warning` email template.
  - Send an in-app notification to the teacher and all active admins.
  - Return `terminated: false`, `locked: false`.
  - Return message: "🚨 Warning 2 of 3: Your instructor has been notified. Any further violation will immediately terminate your examination."
  - The student must be allowed to continue the examination.

---

#### 3.6.4 Warning Display (Browser)
**Required Behaviour:**
- On Warning 1 and Warning 2 responses, the `#warningBox` element must become visible showing the message text.
- The warning box must automatically hide after 5 seconds.
- If the exam is locked (`examLocked = true`), the warning box must not be shown for any subsequent responses.

---

### 3.7 Auto Termination

> **Change Request Applied:** Maximum Warning Behaviour — approved 2026-07-01.
> The third violation must unconditionally terminate the examination. The `auto_terminate_enabled` flag no longer gates this behaviour. The attempt is marked terminated (invalid). The student must not be permitted to resume.

#### 3.7.1 Termination on Third Violation
**Current Behaviour:** When `warning_count` reaches `max_warnings` and `auto_terminate_enabled` is true, `recordTierThree()` is executed inside a database transaction with `lockForUpdate()`.

**Required Behaviour:**
- The third violation (the one that pushes `warning_count` to 3) must unconditionally terminate the examination.
- Termination is not conditional on `auto_terminate_enabled`. The setting no longer gates this path.
- Termination must execute inside a single database transaction.
- A `lockForUpdate()` row lock must be acquired on the `ExamAttempt` row to prevent concurrent double-termination.
- If the attempt is already at `warning_count >= 3` or no longer `in_progress` when the lock is acquired, the system must return the locked response immediately without processing the violation again.
- On the third violation the following must occur atomically:
  1. A `CheatingLog` record must be created for the third violation.
  2. `warning_count` incremented to 3.
  3. `attempt.status` set to `terminated` (the attempt is invalid and final; no pending review).
  4. `attempt.terminated_at` set to the current timestamp.
  5. `users.exam_session_token` set to null — the student cannot resume from any tab.
  6. `GradingService::gradeAttempt()` called to preserve the marks answered up to that point.
  7. `result.exam_result_status` overridden to `DISQUALIFIED`.
  8. `result.violation_reason` set to the violation detail string.
  9. `result.disqualified_at` set to the current timestamp.
  10. `result.is_published` set to false.
  11. `result.attendance_status` set to `attended`.
  12. `result.exam_finished_at` set to the current timestamp.
  13. `ActivityLog` entry written with action `exam_terminated_security`.
- After the transaction commits, via `DB::afterCommit()`:
  - Emails must be queued to: the student, the responsible teacher, and all active admins.
  - In-app notifications must be sent to the same recipients.
  - Recipients must be deduplicated by user ID.
- The response to the student's browser must include `terminated: true`, `locked: true`, and a `redirect` URL.

---

#### 3.7.2 Termination Response to Browser
**Required Behaviour:**
- The browser must receive `terminated: true` and `locked: true` in the JSON response.
- The message must explain the reason: "Your examination has been terminated due to 3 security violations. Your result has been invalidated."
- The JavaScript must call `lockExamInterface()` on receipt.
- After 3 seconds the browser must redirect to the student exam list.

---

#### 3.7.3 Terminated Attempt Is Final — No Resume
**Required Behaviour:**
- An attempt with `status = terminated` is permanently closed. The student must not be permitted to resume it under any circumstances.
- The `exam.active` middleware must block all session routes (`take`, `save`, `violation`, `submit`) for an attempt in `terminated` status.
- The attempt is invalid. No post-termination approval or rejection workflow applies to a `terminated` attempt.

> **Note on existing `terminated_pending_review` status:** The `terminated_pending_review` status and the approval/rejection workflow implemented in `ExamSecurityService` predate this change request. That workflow remains intact for any attempts currently in `terminated_pending_review` state. New terminations from violation 3 set status to `terminated`, not `terminated_pending_review`.

---

#### 3.7.4 Attempt Status Terminal States
**Required Behaviour:** The following statuses represent finished attempts. Students must not be able to save answers or report violations on attempts in these states:
- `submitted`
- `terminated`
- `suspicious`
- `terminated_pending_review`
- `rejected`

The `exam.active` middleware is the enforcement mechanism. It must remain on all exam session routes.

---

### 3.8 Result Processing

#### 3.8.1 Automatic Grading
**Current Behaviour:** `GradingService::gradeAttempt()` is called after submission and after Tier 3 termination. It scores MCQ, True/False, and Fill-blank questions and writes a `Result` record.

**Required Behaviour:**
- Grading must run automatically on: normal submission, timer expiry auto-submit, and Tier 3 termination.
- The grading service must not overwrite a result that already has `exam_result_status = DISQUALIFIED`.
- For MCQ and True/False: a student answer is correct only if its linked `Answer` has `is_correct = true`.
- For Fill-blank: the student's `answer_text` (trimmed, lowercased) must match at least one accepted answer for the question.
- Essay questions must not be auto-graded (no marks awarded automatically).
- After grading, a `Result` record must be created or updated with:
  - `total_marks`, `obtained_marks`, `percentage`
  - `grade` (A/B/C/D/F per scale)
  - `is_passed` (obtained_marks ≥ exam.passing_marks)
  - `exam_result_status` set to PASSED or FAILED
  - `attendance_status = attended`
  - `exam_finished_at` = submission or termination timestamp

**Grade Scale (current):**
| Percentage | Grade |
|---|---|
| ≥ 80% | A |
| ≥ 70% | B |
| ≥ 60% | C |
| ≥ 50% | D |
| < 50% | F |

---

#### 3.8.2 Result Status Values
**Required Behaviour:** Every `Result` record must have exactly one of the following `exam_result_status` values:

| Status | Meaning |
|---|---|
| `PASSED` | Student submitted, obtained ≥ passing marks |
| `FAILED` | Student submitted, obtained < passing marks |
| `ABSENT` | Student never started the exam; schedule has ended |
| `DISQUALIFIED` | Attempt terminated by security system |

- `DISQUALIFIED` results must have `is_published = false` until manually reviewed.
- `ABSENT` results are created by the `results:mark-absent` artisan command, not by the grading service.
- Once a result is `DISQUALIFIED`, no subsequent grading call may change that status.

---

#### 3.8.3 Result Visibility
**Required Behaviour:**
- Students must only see their result after both conditions are true:
  1. The exam schedule has ended (`ends_at < now()`)
  2. The result record has `is_published = true`
- Teachers must see all results for their exams regardless of publication status.
- Admins must see all results across all exams.

---

#### 3.8.4 Absent Result Marking
**Current Behaviour:** The `results:mark-absent` artisan command creates ABSENT result records for enrolled students who never started a closed exam.

**Required Behaviour:**
- The command must only create ABSENT records for students who meet all of the following:
  1. The exam has a published schedule that has ended
  2. The student is enrolled in the exam's course
  3. The student has no `ExamAttempt` row where `started_at IS NOT NULL`
  4. The student does not already have a `Result` record for this exam
- The command must be idempotent (safe to run multiple times).
- A `--dry-run` flag must preview what would be created without writing.
- An `--exam=ID` flag must limit the command to one specific exam.

---

### 3.9 Student Dashboard

**Current Behaviour:** Shows count of enrolled courses and completed exam attempts.

**Required Behaviour:**
- The student dashboard must display:
  - Count of courses the student is enrolled in
  - Count of submitted exam attempts (status = `submitted`)
- The dashboard must be accessible only to users with the `student` role.

---

### 3.10 Teacher Dashboard

**Current Behaviour:** Shows count of teacher's courses, total exams, and exams pending approval.

**Required Behaviour:**
- The teacher dashboard must display:
  - Count of courses assigned to the teacher
  - Count of exams created by the teacher
  - Count of exams awaiting admin approval (status = `pending_approval`)
- The dashboard must be accessible to users with the `teacher` or `admin` role.

---

### 3.11 Administrator Dashboard

**Current Behaviour:** Shows system-wide counts of users, courses, exams, and cheating logs.

**Required Behaviour:**
- The admin dashboard must display:
  - Total user count (all roles)
  - Total course count
  - Total exam count
  - Total cheating log count
- The dashboard must be accessible only to users with the `admin` role.

---

### 3.12 Notifications

#### 3.12.1 In-App Notification Delivery
**Current Behaviour:** `NotificationService::notify()` creates a `UserNotification` row. The notification list is loaded on the notifications page. Unread count is available via a JSON endpoint for badge polling.

**Required Behaviour:**
- Every in-app notification must include: `type`, `title`, `message`, and optional `link`.
- Notifications must be persisted per user in `user_notifications`.
- Unread notifications must display a badge count in the navigation.
- The unread count endpoint (`GET notifications/unread-count`) must return a JSON response.
- Users must be able to mark individual notifications as read.
- Users must be able to mark all notifications as read in one action.
- No real-time push is currently implemented — polling is the only mechanism.

#### 3.12.2 Notification Events
The following events must trigger notifications:

| Event | Recipients | Type |
|---|---|---|
| Exam submitted for approval | All active admins | `exam_submitted` |
| Exam approved | Exam's teacher | `exam_approved` |
| Exam published | All enrolled students + teacher | `exam_published` |
| Security Tier 2 violation | Teacher + all active admins | `security_warning` |
| Security Tier 3 termination | Student + teacher + all active admins | `security_incident_high` |
| Security approved | Student | `security_approved` |
| Security rejected | Student | `security_rejected` |
| Re-attempt submitted (teacher→admin) | All active admins | `re_attempt_submitted` |
| Re-attempt submitted (student→teacher) | Teacher | `re_attempt_submitted` |
| Re-attempt approved | Student + teacher | `re_attempt_approved` |
| Re-attempt rejected | Student + teacher | `re_attempt_rejected` |

---

### 3.13 Logs and Audit Trail

#### 3.13.1 Activity Log
**Required Behaviour:**
- Every significant system action must be written to `activity_logs`.
- Each entry must include: `user_id`, `action` (machine-readable key), optional `model_type` and `model_id`, `description` (plain string or JSON array), `ip_address`.
- Security-related events must store structured JSON in the `description` field so individual keys are queryable.
- Activity logs must never be modified or deleted through the application interface — they are append-only.

**Logged Events (minimum required):**
- `exam_approved`, `exam_published`, `exam_closed`, `exam_opened`
- `exam_terminated_security`, `security_warning_1`, `security_warning_2`
- `security_approved`, `security_rejected`
- `security_settings_updated`, `security_email_sent`

---

#### 3.13.2 Cheating Log
**Current Behaviour:** `CheatingLogController::index()` renders a read-only table of all `CheatingLog` records.

**Required Behaviour:**
- The admin must be able to view all cheating log entries.
- Each entry must display: student name, exam title, violation type, warning number, details, timestamp, and client fingerprint data (browser, device, OS, resolution, timezone, IP).
- The cheating log view must be read-only — no delete or edit actions.
- The log must be accessible only to admins.

---

#### 3.13.3 Email Log
**Required Behaviour:**
- Every email send attempt must create an `EmailLog` record with status `queued`.
- On successful delivery, status must change to `sent` and `sent_at` must be recorded.
- On failure, status must change to `failed` and the error message must be stored.
- Admins must be able to view the email log list filtered by status.
- Admins must be able to view the full HTML of any individual email log.
- Admins must be able to retry a failed email, which re-queues the `SendEmailJob`.
- `SendEmailJob` must attempt delivery up to 3 times with 30-second backoff before marking as failed.

---

#### 3.13.4 Re-Attempt Audit Log
**Required Behaviour:**
- Every lifecycle event of a `ReAttemptRequest` must be recorded in `re_attempt_logs`.
- Required events: `create`, `submit_to_admin`, `approved`, `rejected`, `schedule_change`, `cancelled`.
- Each entry must record: `request_id`, `action`, `actor_id`, `actor_role`, `remarks`.
- The re-attempt log must be displayed on the admin re-attempt detail view.

---

### 3.14 Reports

#### 3.14.1 Results Report — Admin
**Required Behaviour:**
- Admins must be able to view all results across the system.
- Admins must be able to view all results for a specific student.
- Results must display: student name, exam title, course, obtained marks, total marks, percentage, grade, result status (PASSED/FAILED/ABSENT/DISQUALIFIED), attendance status.

#### 3.14.2 Results Report — Teacher
**Required Behaviour:**
- Teachers must be able to view all results for exams they own.
- The report must display the same fields as the admin view, scoped to the teacher's exams.

#### 3.14.3 Results Report — Student
**Required Behaviour:**
- Students must be able to view only their own results.
- Results must be visible only after the schedule ends and `is_published = true`.

---

### 3.15 Course and Enrollment Management

#### 3.15.1 Course Management
**Required Behaviour:**
- Only admins may create, edit, or delete courses.
- Each course must have: title (required), code (required, unique), description (optional), teacher assignment (optional), year level (0–5; 0 = all years), academic year (optional FK), semester (0 = both, 1, or 2), major (optional FK).
- Soft deletion must be used — deleted courses must not appear in lists but must not cascade-delete exams.
- Admins must be able to filter courses by year level via the `courses-by-year-level` JSON endpoint.

---

#### 3.15.2 Enrollment Management
**Required Behaviour:**
- Only admins may enrol students in courses or remove enrolments.
- Enrolment is course-specific: a student may be enrolled in multiple courses.
- The unique constraint (`course_id`, `student_id`) must be enforced — a student cannot be enrolled in the same course twice.
- The enrolment form must dynamically filter students by year level when a year level is selected.
- Removing an enrolment does not delete the student's exam attempts or results for that course.
- The `CourseAssignmentService` must be used for all enrolment sync operations.

---

#### 3.15.3 Major Management
**Required Behaviour:**
- Only admins may create, edit, or delete majors.
- Each major must have: name (required), code (required, unique), description (optional), `is_active` flag.
- Default majors (CS and CT) must be seeded automatically if the table is empty.
- Majors are associated with courses (year 2+) and enrolments.

---

### 3.16 Re-Attempt System

#### 3.16.1 Student-Initiated Request
**Current Behaviour:** Students can submit a re-attempt request to their teacher via `Student\ReAttemptController`.

**Required Behaviour:**
- A student may only submit a re-attempt request for an exam they have already attempted.
- The request must include a reason.
- The request is initially addressed to the teacher only (`sent_to_admin_at = null`).
- The teacher must receive an in-app notification.
- The student must receive a confirmation notification that the request was submitted.
- A student must not be able to submit a duplicate pending request for the same exam.

---

#### 3.16.2 Teacher-Initiated Request
**Required Behaviour:**
- A teacher may create a re-attempt request directly on behalf of a student for any exam they own.
- Teacher-initiated requests are immediately forwarded to admin (`sent_to_admin_at = now()`).
- Admins must receive in-app notifications.
- The student must receive a notification that a request has been submitted on their behalf.

---

#### 3.16.3 Teacher Forwarding Student Request to Admin
**Required Behaviour:**
- A teacher must be able to forward a pending student-initiated request to admin.
- Forwarding sets `sent_to_admin_at = now()`.
- This action must be idempotent — forwarding an already-forwarded request must be a no-op.
- Admins must receive in-app notifications when forwarded.

---

#### 3.16.4 Admin Approval
**Required Behaviour:**
- Admins must see only requests where `sent_to_admin_at IS NOT NULL`.
- On approval, the admin must provide:
  - Optional remark
  - Mandatory re-attempt start datetime
  - Mandatory re-attempt end datetime (must be after start)
- The approval must not delete any existing attempts.
- Approval grants exactly one additional attempt within the specified window.
- The maximum total allowed attempts for any student on any exam is 3 (base limit + approved re-attempts, capped at 3).
- Students and the teacher must be notified of approval.

---

#### 3.16.5 Admin Rejection
**Required Behaviour:**
- A rejection remark is required.
- Students and the teacher must be notified of rejection with the remark.

---

#### 3.16.6 Re-Attempt Window Enforcement
**Required Behaviour:**
- `ExamAccessService::studentCanTakeExam()` must check whether:
  - The main schedule window is active, OR
  - An approved re-attempt window is currently active (within `re_attempt_start_at` and `re_attempt_end_at`)
- If neither window is active, the student must not be permitted to start the exam.
- `ExamAccessService::canDecryptQuestions()` must apply the same dual-window logic.

---

### 3.17 Academic Records, Transcripts, and Certificates

#### 3.17.1 Academic Year Management
**Required Behaviour:**
- Admins must be able to create, edit, and delete academic years.
- Each academic year must have: name, start year, end year, `is_current` flag.
- Admins must be able to assign students to academic years (creates `StudentYearRecord`).
- Admins must be able to remove students from an academic year assignment.

---

#### 3.17.2 Transcript Generation
**Required Behaviour:**
- Admins must be able to generate a `YearlyTranscript` for a student for a specific academic year, year level, and semester.
- Generation aggregates `YearlyExamResult` data: total marks, obtained marks, percentage, GPA (percentage ÷ 25 on a 4.0 scale), grade, pass/fail.
- Generated transcripts must be downloadable as PDF via DomPDF.
- The PDF must include student name, academic year, year level, all exam results, and overall GPA.

---

#### 3.17.3 Certificate Issuance
**Current Behaviour:** `CertificateService::issue()` validates eligibility based on year level passage in `yearly_transcripts`.

**Required Behaviour:**
- Certificate types: `transcript`, `completion`, `promotion`, `achievement`.
- For `completion` type: the student must have a passed `YearlyTranscript` for Year Level 4 or 5.
- For all other types: the student must have a passed `YearlyTranscript` for Year Level 5 (Final Year).
- If eligibility is not met, the system must return a validation error — no certificate must be created.
- Each certificate must have a unique serial number (`CERT-YYYY-NNNN`) and a unique UUID QR token.
- Certificates must be downloadable as PDF including an embedded QR code (SVG format).
- The QR code must link to the public certificate verification page.

---

#### 3.17.4 Certificate Verification (Public)
**Required Behaviour:**
- The route `GET /certificates/verify/{token}` must be publicly accessible (no login required).
- On a valid token, it must display the certificate details: student name, type, serial number, academic year, year level, issued by, issued at.
- On an invalid token, it must display an appropriate error message.

---

### 3.18 Email Management

#### 3.18.1 Email Template Management
**Required Behaviour:**
- Admins must be able to create, edit, delete, and preview email templates.
- Each template must have: name, slug (unique), subject, HTML body, optional plain-text body, event trigger key, `is_active` flag.
- Templates support `{{variable}}` substitution. The following variables must be supported: `student_name`, `teacher_name`, `name`, `email`, `exam_name`, `course_name`, `year_level`, `academic_year`, `department`, `major`, `semester`, `app_name`, `app_url`.
- Inactive templates must not be used for sending.

---

#### 3.18.2 Bulk Email
**Required Behaviour:**
- Admins must be able to send bulk emails to predefined recipient groups.
- Groups: `all_students`, `all_teachers`, `all_users`, `first_year`, `second_year`, `third_year`, `fourth_year`, `final_year`.
- Year-based groups must be resolved from active `StudentYearRecord` data.
- Each recipient must receive a personalised email with their own variable substitution.
- All bulk sends must be queued.

---

#### 3.18.3 Scheduled Emails
**Required Behaviour:**
- Admins must be able to schedule a bulk email for a future datetime.
- The scheduler must process due scheduled emails every minute (`email:process-scheduled`).
- After sending, the `ScheduledEmail` record must be marked `is_sent = true` with `sent_at` recorded.
- Admins must be able to delete a scheduled email before it is sent.

---

#### 3.18.4 SMTP Configuration
**Required Behaviour:**
- Admins must be able to update SMTP settings (host, port, username, password, encryption, from address, from name) via the admin UI.
- Changes must apply immediately for the current process.
- Changes do not persist across server restarts (this is the current intended behaviour).

---

#### 3.18.5 Test Email
**Required Behaviour:**
- Admins must be able to send a test email to a specified address to verify SMTP configuration.

---

### 3.19 Chat

#### 3.19.1 Direct Messaging
**Current Behaviour:** `ChatController` provides a simple 1-to-1 messaging system. Messages are encrypted at rest using Laravel's `Crypt::encryptString`.

**Required Behaviour:**
- Any authenticated user must be able to send direct messages to any other authenticated user.
- Messages must be stored encrypted in the database.
- The chat index must list all users the current user has exchanged messages with.
- The conversation view must display all messages between the two users, decrypted for display.
- Unread messages must be retrievable via the poll endpoint for lightweight polling.
- No real-time WebSocket push is currently implemented.

---

## 4. Non-Functional Requirements

### 4.1 Security

- All question and answer content must be stored encrypted using Laravel's symmetric encryption (AES-256-CBC via `Crypt`).
- Chat messages must be stored encrypted using the same mechanism.
- All POST, PUT, and DELETE routes must require a valid CSRF token.
- All authenticated routes must require an active session.
- Role access must be enforced by middleware, not checked only in controllers.
- Question content must only be decrypted inside `ExamAccessService::canDecryptQuestions()` — never by direct model access outside this gate.
- The exam session token mechanism must prevent concurrent session usage by students.
- The `APP_KEY` must not be rotated without re-encrypting all `content_encrypted` columns.
- Security policy settings must be read from the database, not hardcoded.
- `DB::afterCommit()` must be used for email and notification side effects after security transactions to ensure the database is the source of truth.
- Violation processing at Tier 3 must use `lockForUpdate()` to prevent race conditions.

---

### 4.2 Performance

- `SecuritySetting::get()` calls must be cached for 60 minutes per key to avoid per-request DB queries during live exams.
- All email sending must be asynchronous (queued via `SendEmailJob`). No email dispatch may block an HTTP response.
- The violation endpoint must respond within normal HTTP latency; the DB transaction including `lockForUpdate()` is acceptable.
- Exam question lists must be eager-loaded with answers to avoid N+1 queries.

---

### 4.3 Reliability

- `SendEmailJob` must retry up to 3 times with 30-second backoff before marking a log as failed.
- The Tier 3 termination transaction must be atomic — partial termination (status updated without result update) must not occur.
- `results:mark-absent` must be fully idempotent.
- `EnsureDefaultAdminService` must check schema availability before running to survive cold-start `php artisan migrate`.
- All DB mutations with security implications must use transactions.

---

### 4.4 Availability

- The queue worker (`php artisan queue:work --queue=emails`) must be running at all times for email delivery to function.
- The scheduler (`email:process-scheduled` every minute) must be running for scheduled emails to be processed.
- If the queue worker is down, exams and violations continue functioning — only email notifications are delayed.

---

### 4.5 Scalability

- The current database queue (`QUEUE_CONNECTION=database`) is sufficient for the current scale. No horizontal scaling requirement is imposed by this document.
- The email log table grows with every send; periodic archival may be needed operationally but is outside the scope of this document.

---

### 4.6 Maintainability

- Security policy settings must be configurable through the admin UI without code changes.
- The grade scale, maximum attempts (3), and maximum resume extension (120 minutes) are currently hardcoded or in config files. Any change to these must be reflected in both the config and all dependent logic.
- The `CheatingDetectionService` is deprecated and must not be called from any new code. It must be removed when the legacy `/admin/cheating-logs` view is confirmed to work without it.
- All email sending must go through `EmailService`. No controller may call `Mail::send()` directly.
- All notification sending must go through `NotificationService`. No controller may create `UserNotification` records directly.

---

### 4.7 Usability

- Error messages shown to students during exam violations must be clear, non-technical, and actionable.
- The exam interface must display a visible countdown timer at all times.
- The exam interface must display progress (answered / total questions).
- The lock overlay displayed on Tier 3 termination must be full-viewport and must prevent any interaction.
- All administrative confirmation dialogs must clearly state what will happen before a destructive action is executed.

---

### 4.8 Browser Compatibility

- The exam interface (`exam-anticheat.js`) uses `fetch()`, `document.requestFullscreen()`, `document.visibilitychange`, and `document.fullscreenchange`. These require modern browsers.
- Minimum target: Chrome 90+, Firefox 88+, Edge 90+, Safari 14+.
- Internet Explorer is not supported.
- Mobile browsers may not support `requestFullscreen()` — the fullscreen gate must fail open (proceed without fullscreen) if the API is unavailable.

---

## 5. Business Rules

The following business rules are derived directly from the existing implementation. No rule has been invented.

### BR-01 — Exam Status Progression
An exam may only move forward through its lifecycle states in the defined order. No reverse transitions are permitted except: `closed → published` (admin reopen).

### BR-02 — Question Lock on Approval
Questions may only be added, edited, or deleted when an exam is in `draft` or `pending_approval` status. Once an exam is `approved`, its questions are locked.

### BR-03 — Minimum Question Requirement
An exam must have at least one question before a teacher can submit it for approval.

### BR-04 — Schedule Required Before Publish
An exam must have at least one schedule before it can be published.

### BR-05 — Published Schedule Lock
A schedule that is currently published (`is_published = true`) may not be deleted. The exam must be closed first.

### BR-06 — Student Enrollment Gate
A student must be enrolled in the exam's course before they are permitted to start that exam.

### BR-07 — Attempt Limit
The maximum number of attempts a student may make on any single exam is 3. This cap applies regardless of the schedule's `attempt_limit` value or the number of approved re-attempts. Formula: `effective_limit = min(3, schedule.attempt_limit + approved_reattempts_count)`.

### BR-08 — Attempt Count for Limit
Only attempts with status `submitted`, `terminated`, or `suspicious` count toward the attempt limit. Attempts with status `in_progress`, `terminated_pending_review`, or `rejected` do not consume an attempt slot.

### BR-09 — Resume Active Attempt
If a student has an `in_progress` attempt for an exam, starting the exam again must resume the existing attempt — not create a new one.

### BR-10 — Exam Window Requirement
A student may only start or take an exam during the active schedule window OR during an active approved re-attempt window. Outside of both windows, access is denied.

### BR-11 — Timer is Absolute
The exam timer is set at `started_at + duration_minutes`. The server enforces this via `expires_at`. When `expires_at` is reached, the attempt must be auto-submitted regardless of the student's actions.

### BR-12 — Violation Type Gating
A violation event must only be recorded and counted if the corresponding security policy flag is enabled in `security_settings`. Disabled violation types are silently discarded.

### BR-13 — Fixed 3-Violation Warning Sequence
> **Updated by Change Request: Maximum Warning Behaviour — 2026-07-01.**

The maximum violation count is fixed at 3. The sequence is:
- Violation 1 (`warning_count = 1`): warn student only; examination continues.
- Violation 2 (`warning_count = 2`): warn student; notify teacher and admins; examination continues.
- Violation 3 (`warning_count = 3`): terminate the examination unconditionally; attempt is invalid.

Every detected violation increments the counter by 1. Duplicate violations of the same type each count as a separate violation.

### BR-14 — Unconditional Termination on Third Violation
> **Updated by Change Request: Maximum Warning Behaviour — 2026-07-01.**

The third violation terminates the examination unconditionally. The `auto_terminate_enabled` setting does not gate this behaviour. Termination on violation 3 is not configurable and cannot be disabled. The attempt is set to `terminated` status and the student must not be permitted to resume.

### BR-15 — DISQUALIFIED Result Is Final
Once a result record has `exam_result_status = DISQUALIFIED`, no grading or other process may change it. It can only be changed by direct administrative action (not currently exposed in the UI).

### BR-16 — DISQUALIFIED Result Is Unpublished
Results from termination on violation 3 must have `is_published = false` until an administrator explicitly reviews and, if appropriate, publishes the result.

### BR-17 — Re-Attempt Does Not Delete History
Approving a re-attempt request must never delete previous attempt records. Previous attempts remain in the database.

### BR-18 — Re-Attempt Window is Mandatory
Admin approval of a re-attempt request requires both a start datetime and an end datetime. These define the exclusive window during which the student may access the exam.

### BR-19 — Re-Attempt Request Admin Visibility
Only re-attempt requests that have been forwarded to admin (`sent_to_admin_at IS NOT NULL`) are visible to admins.

### BR-20 — Certificate Eligibility
- `completion` certificate: student must have a passed `YearlyTranscript` for Year Level 4 or Year Level 5.
- All other certificate types: student must have a passed `YearlyTranscript` for Year Level 5.

### BR-21 — ABSENT Status Assignment
ABSENT results are created only by the `results:mark-absent` command — never by live user action or the grading service.

### BR-22 — Session Token Three-Way Sync
The student's `exam_session_token` exists in three places simultaneously: `users.exam_session_token` (DB), `session['exam_session_token']` (PHP session), and `exam_attempts.session_token` (attempt record). All three must be consistent at all times.

### BR-23 — Default Admin Auto-Creation
If no user exists with the configured admin email on application boot, the default admin user and all three roles must be created automatically.

### BR-24 — Question Content Encryption
All question and answer text content must be stored encrypted using `EncryptionService::encrypt()`. Content must never be stored in plaintext.

### BR-25 — Email Always Queued
All email sending through `EmailService::send()` with `$queue = true` (the default) must be dispatched to the `emails` queue. No transactional email may block an HTTP response.

### BR-26 — Recipient Deduplication
When sending notifications or emails to multiple recipients (e.g., teacher + admins), recipients must be deduplicated by user ID. A teacher who is also an admin receives exactly one message.

### BR-27 — GPA Scale
GPA is calculated as `percentage ÷ 25`, rounded to 2 decimal places, on a 4.0 scale. 100% = 4.0 GPA.

---

## 6. Validation Rules

### 6.1 Authentication
| Field | Rule |
|---|---|
| email (login) | required, valid email, exists in users |
| password (login) | required |
| name (register) | required, max:255 |
| email (register) | required, valid email, unique in users |
| password (register) | required, min:8, confirmed |

### 6.2 Exam Creation and Scheduling
| Field | Rule |
|---|---|
| course_id | required, exists in courses |
| title | required, max:255 |
| passing_marks | required, integer, min:0 |
| total_marks | required, integer, min:1 |
| starts_at | required, valid datetime |
| ends_at | required, valid datetime, after:starts_at |
| duration_minutes | required, integer, min:1 |
| attempt_limit | required, integer, min:1 |
| target_year | optional, integer, min:1, max:5 |

### 6.3 Question Management
| Field | Rule |
|---|---|
| type | required, in: mcq, true_false, essay, fill_blank |
| content | required, non-empty string |
| marks | required, integer, min:1 |
| difficulty | required, in: easy, medium, hard |
| category_id | optional, exists in question_categories |
| answers (MCQ/TF) | array, min 2 items, exactly one marked correct |
| blank_answers (fill_blank) | array, min 1 non-empty string |

### 6.4 Answer Saving (Exam Session)
| Field | Rule |
|---|---|
| question_id | required, exists in questions |
| answer_id | nullable, exists in answers |
| answer_text | nullable string |
| answer_file | nullable, mimes: pdf, doc, docx, max:10240 KB |

### 6.5 Violation Reporting
| Field | Rule |
|---|---|
| type | required, string, max:80 |
| details | nullable, string, max:500 |

### 6.6 Security Settings
| Field | Rule |
|---|---|
| max_warnings | required, integer, min:1, max:20 |
| auto_terminate_enabled | required, in:0,1 |
| All flag settings | required, in:0,1 |

### 6.7 Re-Attempt Request
| Field | Rule |
|---|---|
| student_id | required, exists in users |
| exam_id | required, exists in exams |
| reason | required, string, max:1000 |

### 6.8 Re-Attempt Approval (Admin)
| Field | Rule |
|---|---|
| admin_remark | optional, string, max:500 |
| re_attempt_start_at | required, valid datetime |
| re_attempt_end_at | required, valid datetime, after:re_attempt_start_at |

### 6.9 Re-Attempt Rejection (Admin)
| Field | Rule |
|---|---|
| admin_remark | required, string, max:500 |

### 6.10 Course Management
| Field | Rule |
|---|---|
| title | required, string |
| code | required, string, unique in courses |
| year_level | optional, integer, 0–5 |
| semester | optional, integer, 0–2 |
| academic_year_id | optional, exists in academic_years |
| major_id | optional, exists in majors |

### 6.11 Email Template
| Field | Rule |
|---|---|
| name | required, string |
| slug | required, string, unique in email_templates |
| subject | required, string |
| body_html | required, string |
| event | optional, string |
| is_active | required, boolean |

### 6.12 Question Import
| Field | Rule |
|---|---|
| import_file | required, file, mimes: txt, pdf, doc, docx, max:5120 KB |
| category_id | optional, exists in question_categories |

### 6.13 Duplicate Prevention
- A student cannot be enrolled in the same course twice (unique constraint: `course_id`, `student_id`)
- An email template slug must be unique
- A `StudentYearRecord` must be unique per (`student_id`, `academic_year_id`, `year_level_id`, `semester`)
- A `StudentAnswer` must be unique per (`attempt_id`, `question_id`)
- A certificate serial number must be unique
- A certificate QR token must be unique (UUID)
- A `ReAttemptRequest` may not be duplicated for the same student+exam while one is pending

---

## 7. User Roles and Permissions

### 7.1 Role Definitions
| Role Slug | Description |
|---|---|
| `admin` | System administrator. Full access to all features. |
| `teacher` | Exam author and course instructor. Manages their own exams and re-attempt requests. |
| `student` | Examination candidate. Takes exams and views their own results. |

---

### 7.2 Administrator Permissions

| Feature | Permission |
|---|---|
| User management | Create, edit, soft-delete, and terminate all users |
| Course management | Full CRUD |
| Major management | Full CRUD |
| Enrollment management | Enrol and remove students from courses |
| Exam management | View all exams; approve; create and manage schedules; publish; close; reopen |
| Question management | View questions on any exam (read-only in admin view) |
| Security settings | View and update all security policy settings |
| Re-attempt requests | View all (forwarded to admin), approve, reject, update window |
| Cheating logs | View all (read-only) |
| Results | View all results; view per-student results |
| Transcripts | Generate, view, export PDF |
| Certificates | Issue, view, export PDF |
| Academic years | Full CRUD; assign and remove students |
| Email templates | Full CRUD |
| Email logs | View, retry failed emails |
| Bulk email | Send to recipient groups |
| Scheduled emails | Create, delete |
| SMTP settings | Update at runtime |
| Test email | Send |
| Notifications | View and manage own notifications |
| Chat | Send and receive messages with any user |
| Dashboard | Admin dashboard |
| Teacher area | Admin role also satisfies `role:teacher,admin` middleware — can access teacher routes |

---

### 7.3 Teacher Permissions

| Feature | Permission |
|---|---|
| Exams | Create, view own exams; add/edit/delete questions (draft/pending_approval only); submit for approval; import questions |
| Exam results | View results for own exams |
| Re-attempt requests | Create requests (teacher-initiated); view own requests; cancel pending requests; forward student requests to admin |
| Profile | View and edit own profile |
| Notifications | View and manage own notifications |
| Chat | Send and receive messages with any user |
| Dashboard | Teacher dashboard |
| Student exams | No access to student exam session or results for other teachers' exams |

---

### 7.4 Student Permissions

| Feature | Permission |
|---|---|
| Courses | View own enrolled courses only |
| Exams | View exams where enrolled in course AND status is approved/published |
| Exam session | Start, take, save answers, report violations, submit (own attempts only) |
| Results | View own results (after schedule ends and result is published) |
| Re-attempt requests | Create requests for own exams; view own requests |
| Notifications | View and manage own notifications |
| Chat | Send and receive messages with any user |
| Dashboard | Student dashboard |
| Other students' data | No access |
| Admin/Teacher areas | No access — 403 if attempted |

---

### 7.5 Public (Unauthenticated) Permissions

| Feature | Permission |
|---|---|
| Login page | View and submit |
| Register page | View and submit |
| Certificate verification | `GET /certificates/verify/{token}` — read-only |
| All other routes | Redirect to login |

---

## 8. Feature Modification Requirements

This section documents requirements for features that are planned for modification or extension. Each entry specifies what must change, what must not change, and what backward compatibility must be preserved.

---

### FM-01 — Security Settings: Admin UI Toggle Controls

**Objective:** All 10 security policy settings must be editable by admins via the Security Settings UI with clear toggle controls.

**Reason:** Admin-configurable security without code changes is a core requirement.

**Expected Behaviour:**
- Each policy flag must be presented as a toggle (on/off) in the admin security settings form.
- `max_warnings` must be presented as a numeric input (integer, 1–20).
- On save, all 10 values must be persisted via `SecuritySetting::set()`.
- Changes must take effect on the next page load; no server restart required.
- An `ActivityLog` entry must be written on every save.

**Components Affected:**
- `admin/security-settings/index.blade.php`
- `admin/security-settings/_toggle.blade.php` (existing partial)
- `Admin\SecuritySettingsController`
- `SecuritySetting` model

**Components NOT Affected:**
- `exam-anticheat.js` — must not be modified
- `SecuritySetting::policy()` return shape — must not change
- `ExamSecurityService` — must not be modified
- Any migration — no schema change needed

**Backward Compatibility:** All existing settings keys must remain unchanged. No setting key may be renamed or removed.

---

### FM-02 — Result Status Display

**Objective:** All result views must display the `exam_result_status` field with a human-readable label and a colour-coded badge.

**Reason:** The `exam_result_status` column and its constants already exist. Views must surface this data consistently.

**Expected Behaviour:**
- PASSED → green badge labelled "Passed"
- FAILED → red badge labelled "Failed"
- ABSENT → grey badge labelled "Absent"
- DISQUALIFIED → yellow/warning badge labelled "Disqualified"
- Labels and badge colours are defined by `Result::statusLabel()` and `Result::statusBadgeClass()`.

**Components Affected:**
- `admin/results/index.blade.php`
- `admin/results/student.blade.php`
- `teacher/exams/results.blade.php`
- `teacher/results/index.blade.php`
- `student/results/index.blade.php`
- `student/exams/show.blade.php`

**Components NOT Affected:**
- `GradingService` — must not be modified
- `Result` model constants — must not be changed
- `ExamSecurityService` — must not be modified
- Database schema — no changes required

**Backward Compatibility:** The result display must not alter how results are calculated or stored.

---

### FM-03 — Re-Attempt Request: Teacher Cancel and Send to Admin

**Objective:** The teacher re-attempt management UI must support cancelling pending requests and forwarding student requests to admin.

**Reason:** These routes and service methods exist but UI completeness must be verified.

**Expected Behaviour:**
- Teacher can cancel any pending request they own (via delete route).
- Teacher can forward a student-initiated request to admin (sets `sent_to_admin_at`).
- Forwarded requests appear in the admin re-attempt list.
- Cancelled requests are permanently deleted (soft delete not implemented on this model).

**Components Affected:**
- `teacher/reattempts/index.blade.php`
- `Teacher\ExamController` (reattemptCancel, reattemptSendToAdmin)

**Components NOT Affected:**
- `ReAttemptService` — must not be modified
- `Admin\ReAttemptController` — must not be modified
- Database schema — no changes required

---

### FM-04 — Absent Marking: Scheduler Registration

**Objective:** The `results:mark-absent` command should be added to the scheduler to run automatically after exam schedules end.

**Reason:** Currently the command is not scheduled, which means ABSENT records are never created unless run manually.

**Expected Behaviour:**
- The command must run on a schedule that processes ended exams promptly.
- The command is idempotent — running it multiple times must not create duplicate records.
- A `--dry-run` flag must remain available for manual verification.

**Components Affected:**
- `app/Console/Kernel.php` — schedule registration

**Components NOT Affected:**
- `MarkAbsentResults` command logic — must not be modified
- Any model or migration

**Backward Compatibility:** Existing exam results must not be affected by scheduler registration.

---

### FM-05 — Maximum Warning Behaviour

> **Change Request approved: 2026-07-01.**

**Objective:** Implement a fixed 3-violation maximum warning sequence where violation 1 warns, violation 2 warns and notifies, and violation 3 unconditionally terminates the examination. The terminated attempt must be marked invalid and the student must not be allowed to resume.

**Reason:** The approved business rules require a fixed, unconditional termination on the third violation regardless of admin configuration.

**Expected Behaviour:**
- Violation 1: increment `warning_count` to 1, create `CheatingLog`, log `ActivityLog`, return warning message, allow continuation.
- Violation 2: increment `warning_count` to 2, create `CheatingLog`, log `ActivityLog`, queue email to teacher and admins, send in-app notification, return warning message, allow continuation.
- Violation 3: increment `warning_count` to 3, create `CheatingLog`, inside a `lockForUpdate()` transaction set `attempt.status = terminated`, set `terminated_at`, clear `exam_session_token`, grade attempt, set result to `DISQUALIFIED`, log `ActivityLog`. After commit, queue high-priority emails and send in-app notifications to student + teacher + admins. Return `terminated: true` to browser.
- Duplicate violations of the same type each count as independent violations.
- `auto_terminate_enabled` no longer gates the third-violation termination path.
- The `max_warnings` configurable value no longer controls the 1–2–3 sequence.

**Components Affected:**
- `app/Services/ExamSecurityService.php` — `recordViolation()`, `recordTierThree()`, `handleTierOne()`, `handleTierTwo()`, `lockedResponse()`, `maxWarnings()` usage
- `app/Http/Controllers/Student/ExamSessionController.php` — violation endpoint (only if controller-level guard uses `max_warnings`; currently passes through to service)
- `public/js/exam-anticheat.js` — `handleViolationResponse()` message text; currently reads `data.terminated` which remains unchanged
- `resources/views/student/exam/take.blade.php` — security policy data attributes passed from controller (only if `max_warnings` attribute is surfaced to JS)

**Components NOT Affected:**
- `app/Models/ExamAttempt.php` — `terminated` status already exists in the ENUM; no schema change needed
- `app/Models/CheatingLog.php` — no change
- `app/Models/Result.php` — constants and DISQUALIFIED status unchanged
- `app/Models/SecuritySetting.php` — `policy()`, `set()`, `get()` unchanged; `auto_terminate_enabled` key remains in DB for existing UI but no longer gates termination
- `app/Http/Middleware/EnsureExamActive.php` — `terminated` already in the terminal state list
- `app/Services/GradingService.php` — no change
- `app/Services/EmailService.php` — no change
- `app/Services/NotificationService.php` — no change
- `app/Services/ActivityLogService.php` — no change
- Database schema — no migration required; `terminated` ENUM value already exists

**Backward Compatibility:**
- The JSON response shape from `recordViolation()` must not change: `{warning_count, terminated, locked, message, redirect}`.
- The `exam.active` middleware blocking logic must not change.
- Any attempt currently in `terminated_pending_review` state is unaffected — the existing approval/rejection workflow remains for those records.
- The `auto_terminate_enabled` setting key remains in `security_settings` (for the existing admin UI) but no longer controls violation-3 behaviour.
- The `max_warnings` setting key remains in `security_settings` (for the existing admin UI) but no longer controls the 1–2–3 sequence; it may still be used to drive the "N of M" display string in warning messages.

---

## 9. Regression Protection Requirements

### 9.1 Existing Features That Must Continue Working

The following features must not be broken by any future modification:

| Feature | Critical Requirement |
|---|---|
| Login / Logout | All three roles must log in and out correctly |
| Student exam start | Session token must be set; no duplicate attempts created |
| Answer auto-save | MCQ click, fill-blank debounce, essay debounce, periodic MCQ — all must continue |
| Timer auto-submit | When `expires_at` is reached, the form must submit automatically |
| Violation recording | All violation types must record `CheatingLog`; warning count must increment |
| Tier 3 termination | Must be atomic; must not double-terminate; result must be DISQUALIFIED |
| Security approval / rejection | Must restore or permanently reject attempt; notification to student required |
| Exam grading | MCQ, true_false, fill_blank must be scored correctly; DISQUALIFIED guard must not be removed |
| Result visibility | Students must not see results before schedule ends and `is_published = true` |
| Re-attempt window | `ExamAccessService` must check both schedule and re-attempt windows |
| Email queue | All `EmailService::send()` calls with `$queue=true` must dispatch `SendEmailJob` |
| Certificate verification | Public route must work without authentication |
| Cheating log view | Admin read-only view must continue to display all entries |
| `SecuritySetting::policy()` keys | The 8 flag keys + `max_warnings` must never change names |
| `recordViolation()` JSON shape | `warning_count`, `terminated`, `locked`, `message`, `redirect` — never rename |
| `lockExamInterface()` | Must remain irreversible; must disable all inputs |
| Notification badge | `unread-count` endpoint must return correct JSON count |

---

### 9.2 Features That Must Never Be Broken

The following are zero-tolerance protection items. Breaking any of these would compromise exam integrity or data permanence:

1. **Tier 3 `lockForUpdate()` transaction** — concurrent termination prevention must remain.
2. **`DB::afterCommit()` for security emails** — emails and notifications must not fire inside the transaction.
3. **`GradingService` DISQUALIFIED guard** — must never be removed or weakened.
4. **`exam.active` middleware on all session routes** — must never be removed from `take`, `save`, `violation`, `submit`.
5. **`exam.session` middleware on all authenticated routes** — must never be removed from the group.
6. **`APP_KEY` encryption** — rotating the key without re-encrypting data will corrupt all question content.
7. **`attempt.status` ENUM values** — existing values must never be renamed or removed; adding new values requires a raw MySQL ALTER.
8. **`result.exam_result_status` constants** — `PASSED`, `FAILED`, `ABSENT`, `DISQUALIFIED` string values must never change.

---

### 9.3 Database Compatibility Requirements

- No existing column may be removed or renamed without a corresponding data migration.
- No existing ENUM value may be removed without verifying zero rows use that value.
- The unique constraints on `enrollments(course_id, student_id)` and `student_answers(attempt_id, question_id)` must not be dropped.
- The `certificate_logs.serial_number` and `certificate_logs.qr_token` unique constraints must not be dropped.
- Foreign keys with `cascadeOnDelete` on `enrollments.course_id` and `enrollments.student_id` must not be changed to `restrictOnDelete` without reviewing impact on exam access.

---

### 9.4 API Compatibility Requirements

- The violation endpoint response shape must not change.
- The notification unread count endpoint must continue to return `{"count": <integer>}` JSON.
- The chat poll endpoint response must remain backward compatible.
- The `courses-by-year-level` JSON endpoint must remain available and return the same structure.
- The `enrollments/students-by-year-level` JSON endpoint must remain available.

---

### 9.5 UI Compatibility Requirements

- The `#examBody` data attributes (`data-save-url`, `data-violation-url`, `data-submit-url`, `data-ends-at`, all `data-policy-*`) must remain on the `#examBody` element in `student/exam/take.blade.php`.
- The `#fsOverlay` and `#enterFullscreen` elements must remain in the take view for the fullscreen gate to function.
- The `#warningBox` and `#warningText` elements must remain for Tier 1/2 warning display.
- The `#submitBtn` and `#examForm` elements must remain for submit and timer auto-submit.
- The `.question-block`, `.q-nav-btn`, `#progressFill`, `#progressText`, `#timer`, `#timerText` elements must remain for navigation and timer UI.
- The `#qType` select element and the `#questionForm` form must remain in the question builder view.

---

## 10. Acceptance Criteria

### AC-01 — Authentication

**Given** a user with valid credentials and `is_active = true`
**When** they submit the login form
**Then** they are redirected to their role-specific dashboard and `last_login_at` is updated.

**Given** a user with `is_active = false`
**When** they submit the login form
**Then** they receive an error message and are not logged in.

**Given** an already-authenticated student on the login page
**When** they navigate to `/login`
**Then** they are redirected to the student dashboard without re-authentication.

---

### AC-02 — Single Exam Session Enforcement

**Given** a student is logged in on Device A with an active exam session
**When** the same student logs in on Device B
**Then** Device A's next request detects the token mismatch, logs the user out, and displays "Another active exam session was detected."

---

### AC-03 — Exam Lifecycle

**Given** an exam in `draft` status with 0 questions
**When** the teacher attempts to submit for approval
**Then** the system returns a validation error: "Add at least one question before submitting."

**Given** an exam in `approved` status with no schedule
**When** an admin attempts to publish it
**Then** the system returns an error: "Create a schedule before publishing."

**Given** a published exam
**When** an admin closes it
**Then** the exam status becomes `closed` and the schedule `is_published` becomes false.

---

### AC-04 — Question Lock

**Given** an exam in `approved` status
**When** the teacher attempts to add a question
**Then** the system returns HTTP 403 "Cannot modify questions after approval."

---

### AC-05 — Student Exam Access

**Given** a student enrolled in a course with a published exam in an active schedule window
**When** the student navigates to the exam list
**Then** the exam is visible.

**Given** the same student outside the schedule window and no active re-attempt window
**When** they attempt to start the exam
**Then** the system redirects with "Exam is not available."

---

### AC-06 — Answer Saving

**Given** a student on the exam take page
**When** they click an MCQ option
**Then** a `fetch()` POST to the save endpoint is made within the same user interaction, and `student_answers` is updated.

**Given** a student on the exam take page with `examLocked = true`
**When** they attempt to click any answer
**Then** the click handler exits without making any network request.

---

### AC-07 — Violation Recording — Warning 1

**Given** a student with `warning_count = 0` taking an exam
**When** a tab switch is detected
**Then** `warning_count` becomes 1, a `CheatingLog` row is created, no email is sent, and the response contains `warning_count: 1, terminated: false`.

---

### AC-08 — Violation Recording — Warning 2

**Given** a student with `warning_count = 1`
**When** a second violation is detected
**Then** `warning_count` becomes 2, an email is queued to teacher and all admins, an in-app notification is sent, and the response contains `terminated: false`.

---

### AC-09 — Termination on Third Violation

**Given** a student with `warning_count = 2`
**When** a third violation is detected
**Then**:
- `attempt.status` becomes `terminated`
- `result.exam_result_status` becomes `DISQUALIFIED`
- `result.is_published` is false
- The student's `exam_session_token` is cleared
- The browser receives `terminated: true` and `locked: true`
- `lockExamInterface()` is called
- The student is redirected to the exam list after 3 seconds

**Given** the same terminated attempt
**When** the student attempts to navigate back to the take page
**Then** the `exam.active` middleware blocks access with the terminal state message.

**Given** two simultaneous third-violation requests for the same attempt
**When** both are processed concurrently
**Then** only one termination occurs; the second request receives the locked response without double-termination.

---

### AC-09a — Duplicate Violations Counted Independently

**Given** a student who has triggered `tab_switch` twice consecutively
**When** both violation POSTs are received by the server
**Then** `warning_count` is incremented for each, totalling the correct cumulative count.

---

### AC-10 — Security Approval

**Given** an attempt with `status = terminated_pending_review` and `terminated_at` set 30 minutes ago
**When** an admin approves it with a comment
**Then**:
- `attempt.status` becomes `in_progress`
- `attempt.expires_at` is extended by 30 minutes (or the cap, whichever is less)
- `attempt.terminated_at` is null
- `attempt.approved_by` and `approved_at` are set
- The student receives an in-app notification

---

### AC-11 — Grading

**Given** a submitted attempt with 5 MCQ questions (2 marks each) and 3 correct
**When** grading runs
**Then** `obtained_marks = 6`, `total_marks = 10`, `percentage = 60.00`, `grade = C`, `is_passed` depends on `passing_marks`.

**Given** a result with `exam_result_status = DISQUALIFIED`
**When** `gradeAttempt()` is called
**Then** the result is returned unchanged.

---

### AC-12 — Result Visibility

**Given** a student with a submitted attempt and published result
**When** the schedule has not yet ended
**Then** the student's result view shows no result.

**Given** the same student after the schedule ends
**When** they view the exam detail
**Then** the result is displayed with grade, percentage, and status badge.

---

### AC-13 — Absent Results

**Given** a published exam schedule that has ended and a student enrolled but never started
**When** `results:mark-absent` is run
**Then** a `Result` row is created with `exam_result_status = ABSENT`, `attendance_status = absent`, `obtained_marks = 0`.

**Given** the same student already has a result
**When** `results:mark-absent` is run again
**Then** no duplicate result is created.

---

### AC-14 — Re-Attempt Window

**Given** a student with an approved re-attempt window currently active
**When** they attempt to start the exam
**Then** the start succeeds even if the main schedule window has ended.

**Given** the same student outside the re-attempt window
**When** they attempt to start the exam
**Then** the system shows the window dates in the error message and denies access.

---

### AC-15 — Certificate Verification

**Given** a valid certificate QR token
**When** a public user navigates to `/certificates/verify/{token}` without logging in
**Then** the certificate details are displayed.

**Given** an invalid or non-existent token
**When** the same route is accessed
**Then** an appropriate error message is displayed.

---

### AC-16 — Security Settings Persistence

**Given** an admin changes `max_warnings` to 5 in the Security Settings UI
**When** a student subsequently violates the exam 5 times
**Then** the exam is terminated on the 5th violation (not the 3rd).

---

### AC-17 — Email Queue

**Given** the queue worker is running
**When** an exam is published
**Then** all enrolled students receive an email within the queue processing time (no direct HTTP delay).

**Given** an email delivery failure
**When** the job retries 3 times and all fail
**Then** the `EmailLog` record has `status = failed` with the error message.

---

## 11. Out of Scope

The following items are explicitly outside the scope of this requirements specification and must not be implemented as part of any work governed by this document:

1. **Real-time WebSocket broadcasting** — Laravel Echo and Pusher are commented out. No real-time push notifications are required. Polling is the current and accepted mechanism.
2. **Email verification enforcement** — The `MustVerifyEmail` interface is implemented on `User` but the `verified` middleware is not applied to any route. Email verification is not currently enforced and must not be added.
3. **Mobile application** — The system is a web application only. No mobile app API extensions are in scope.
4. **Essay auto-grading** — Essay questions are not auto-graded. Manual grading of essays is not currently implemented and is not in scope.
5. **File upload answer grading** — `file_upload` type questions can store a file path but scoring logic does not exist. This remains out of scope.
6. **Production SMTP persistence** — The `applySmtpConfig()` method applies settings in-memory only. Persisting SMTP settings to `.env` is out of scope.
7. **Role creation or modification** — Only three roles exist (`admin`, `teacher`, `student`). Adding new roles or modifying role slugs is out of scope.
8. **Dark mode or UI theming** — No UI theme customisation is in scope.
9. **Student promotion workflow** — `AcademicService::promoteStudent()` exists but no admin UI route is wired to it. Promotion workflows through the UI are out of scope.
10. **Multi-language support** — `lang/en.json` exists but no language switching is implemented. Internationalisation is out of scope.
11. **API expansion** — The three Sanctum API routes are minimal and not used by the web frontend. No new API endpoints are in scope.
12. **GDPR or data export** — No personal data export or deletion request workflows are in scope.
13. **Legacy `CheatingDetectionService` removal** — Removing the deprecated service is a maintenance task, not a requirements-driven feature, and is out of scope for this specification.

---

## 12. Assumptions

**A-01** — The application runs on a server with PHP 8.0+, MySQL 5.7+, and the `zip` extension available (required for DOCX import).

**A-02** — The queue worker (`php artisan queue:work --queue=emails`) is running in production for email delivery to function. This is an operational assumption, not a code assumption.

**A-03** — The application scheduler (`php artisan schedule:run`) runs every minute via cron.

**A-04** — `APP_KEY` is set and never rotated without a coordinated data re-encryption of all `content_encrypted` columns.

**A-05** — `security_settings` table contains all required policy keys (seeded by the `2026_06_29_000001_seed_security_policy_settings.php` migration). If a key is missing, `SecuritySetting::get()` returns the hardcoded default.

**A-06** — The `Imagick` PHP extension is not available. QR codes in certificates use the SVG format, not PNG/JPEG.

**A-07** — Only the three default roles (admin, teacher, student) exist in the `roles` table. No custom roles are present.

**A-08** — The `year_levels` table contains exactly five records (levels 1–5) created by `YearLevel::ensureDefaults()`.

**A-09** — SMTP credentials are correctly configured in `.env` (`MAIL_*` variables) for email delivery to work.

**A-10** — Students enrolled in a course share the same academic context (year level, major) as defined in their active `StudentYearRecord`. The legacy `users.academic_year` column is only used as a fallback when no `StudentYearRecord` exists.

**A-11** — The exam take page is loaded over HTTPS in production. `document.requestFullscreen()` requires a secure context.

**A-12** — All browsers used by students support `fetch()`, `document.visibilitychange`, and `document.fullscreenchange`.

---

## 13. Constraints

**C-01 — No Real-Time Broadcasting**
The system does not use WebSockets or server-sent events. All live updates (notification badge, chat unread count) rely on client polling.

**C-02 — Database Queue Only**
The queue driver is `database`. Redis queues are not configured. All job processing requires the MySQL queue table.

**C-03 — No In-App SMTP Persistence**
Admin SMTP changes are runtime-only. A server or worker restart reverts to `.env` values.

**C-04 — Exam Security Is Client-Enforced First**
The browser-side `exam-anticheat.js` is the primary detection mechanism. The server side validates and records violations but does not independently detect them. Client-side JavaScript can be bypassed by a sufficiently technical user — the server-side warning count and Tier 3 lock provide the deterrent.

**C-05 — Maximum 3 Attempts Per Student Per Exam**
This limit is hardcoded in `ExamAccessService`. Changing it requires a code change, not a configuration change.

**C-06 — GPA Scale Is Fixed**
The GPA scale (100% = 4.0, calculated as percentage ÷ 25) is hardcoded in `AcademicService` and `TranscriptService`.

**C-07 — Grade Scale Is Fixed**
The A/B/C/D/F grade thresholds are hardcoded in `GradingService::calculateGrade()`.

**C-08 — Laravel 9 Framework**
All features must remain compatible with Laravel 9. No framework upgrade is in scope.

**C-09 — Bootstrap 5 UI Framework**
All views use Bootstrap 5 and Bootstrap Icons. No other CSS framework is in scope.

**C-10 — Encryption Key Dependency**
All question and chat message content is encrypted with `APP_KEY`. The system has no mechanism to re-encrypt data if the key changes.

---

## 14. Risks

**RISK-01 (High) — Queue Worker Not Running**
If `php artisan queue:work` is not running, all email notifications are silently queued but never delivered. Students do not receive exam notifications; teachers and admins do not receive security alerts.
*Mitigation:* Monitor the jobs table for growing queue depth. Use a process manager (Supervisor) in production.

**RISK-02 (High) — Concurrent Termination Race**
Without `lockForUpdate()` in `recordTierThree()`, two simultaneous violation requests could both terminate the same attempt, creating two DISQUALIFIED results.
*Mitigation:* The `lockForUpdate()` guard must not be removed. Any refactor of `ExamSecurityService` must preserve the transaction boundary.

**RISK-03 (High) — `APP_KEY` Rotation Without Re-encryption**
If the application key is rotated (e.g., during a migration to a new server), all `content_encrypted` columns become unreadable. Questions will decrypt to `null`, rendering all exams blank.
*Mitigation:* Never rotate `APP_KEY` without first decrypting and re-encrypting all affected columns.

**RISK-04 (High) — Legacy Service Accidental Use**
`CheatingDetectionService` is deprecated but still present. If it is accidentally injected in new code, it uses hard-coded 3-warning logic, bypasses the configurable policy, and sets status to `suspicious` instead of `terminated_pending_review` — bypassing the review workflow.
*Mitigation:* Do not inject or call `CheatingDetectionService` in any new code. Add an automated check.

**RISK-05 (Medium) — ABSENT Records Never Created**
The `results:mark-absent` command is not scheduled. If not run after each exam closes, the results page shows incomplete data (no ABSENT entries for students who never attended).
*Mitigation:* Register the command in the scheduler (see FM-04).

**RISK-06 (Medium) — SecuritySetting Cache Lag**
Security policy changes via the Admin UI are cached for 60 minutes. A student starting an exam shortly after a policy change will see the old policy baked into the page's `data-*` attributes for the duration of their session.
*Mitigation:* Acceptable operational limitation. Documented behaviour. For immediate effect, the exam must be reloaded.

**RISK-07 (Medium) — DISQUALIFIED Result Not Published**
Tier 3 sets `is_published = false`. If no admin reviews and publishes the result, it never appears in the student's results page. The student may not know their result is DISQUALIFIED.
*Mitigation:* Admins must be aware of the review obligation. The high-priority email and notification sent on Tier 3 serves as the alert.

**RISK-08 (Low) — Enrollment Cascade Delete**
Deleting a course cascades to delete all its enrollments. Students enrolled in that course lose access to all associated published exams silently (no error shown to students).
*Mitigation:* The admin course delete UI should warn that enrollments will be removed.

**RISK-09 (Low) — Re-Attempt Max 3 Cap Bypassed in DB**
The max-3-attempt cap is enforced in `ExamAccessService`. If `ReAttemptRequest` rows are created directly in the DB (bypassing the application), the cap may be exceeded.
*Mitigation:* Application-level enforcement only. No DB-level constraint enforces the cap.

**RISK-10 (Low) — Chat Encryption Key Rotation**
All chat messages are encrypted with `APP_KEY`. Key rotation will render all historical messages unreadable with the same consequences as question content rotation.
*Mitigation:* Same mitigation as RISK-03.

---

## 15. Testing Requirements

### 15.1 Unit Testing

The following units must be covered by isolated unit tests:

| Unit | Test Cases |
|---|---|
| `GradingService::gradeAttempt()` | MCQ correct/incorrect, fill_blank case-insensitive match, DISQUALIFIED guard |
| `GradingService::calculateGrade()` | All 5 grade thresholds including boundaries |
| `ExamAccessService::studentCanTakeExam()` | Enrolled, not enrolled, attempt limit, schedule active, re-attempt window |
| `ExamAccessService::canDecryptQuestions()` | Admin, teacher-owner, student in window, student outside window |
| `SecuritySetting::get()` | Missing key returns default, cached value returned |
| `SecuritySetting::isViolationTypeEnabled()` | Each violation type maps to correct setting key |
| `EmailTemplate::render()` | Variable substitution, unrecognised variables remain as-is |
| `EncryptionService::decrypt()` | Valid ciphertext, null input, corrupted ciphertext returns null |
| `ReAttemptService::hasActiveApprovedWindow()` | Inside window, outside window, no window set |

---

### 15.2 Integration Testing

The following integration scenarios must be tested:

| Scenario | Expected Outcome |
|---|---|
| Student starts exam → saves answer → submits | Attempt created, `StudentAnswer` saved, result created with PASSED or FAILED |
| Student starts exam → timer expires | Auto-submit triggers via form submit; result created |
| Violation reported → Tier 1 | `CheatingLog` created, no email dispatched |
| Violation reported → Tier 2 | Email queued for teacher and admins |
| Violation reported → Tier 3 | Attempt `terminated_pending_review`, result `DISQUALIFIED`, email queued, notification created |
| Tier 3 → Admin approves | Attempt `in_progress`, `expires_at` extended, student notified |
| Tier 3 → Admin rejects | Attempt `rejected`, student notified |
| Admin publishes exam | Students and teacher notified (email queued + in-app) |
| Re-attempt approved → student starts during window | Attempt created successfully |
| `results:mark-absent` runs on ended exam | ABSENT results created only for students who never started |
| Certificate issued for ineligible student | ValidationException thrown, no certificate created |

---

### 15.3 Browser Testing

The following browser scenarios must be tested manually or via automated browser testing:

| Scenario | Expected Outcome |
|---|---|
| Click MCQ option | `saveAnswer()` fetch fires; `.selected` class applied; nav button turns answered |
| Fill-blank input (typing) | After 800ms debounce, `saveAnswer()` fires |
| Switch tab during exam (policy enabled) | `window_blur` or `tab_switch` violation reported; warning box shown |
| Exit fullscreen during exam (policy enabled) | `fullscreen_exit` violation reported; warning box shown |
| Press F12 during exam (policy enabled) | Key blocked; `devtools_shortcut` violation reported |
| Right-click during exam (policy enabled) | Context menu blocked |
| Timer reaches zero | Form auto-submits via `form.submit()` |
| Click Submit with unanswered questions | Confirmation dialog shows unanswered count |
| Server responds with `terminated: true` | `lockExamInterface()` fires; overlay shown; redirect after 3s |
| Fullscreen gate | Overlay shown until "Enter Fullscreen" clicked |
| Violation policy flag disabled | Corresponding event listener not registered; no violation reported |

---

### 15.4 Security Testing

| Test | Requirement |
|---|---|
| Student accesses another student's attempt URL | HTTP 403 |
| Student accesses admin routes | HTTP 403 |
| Student saves answer after submission | HTTP 403 (exam.active middleware) |
| CSRF token missing on POST | HTTP 419 |
| Duplicate Tier 3 POST race condition | Only one termination occurs |
| Direct question access outside exam window | 403 or null content |
| Student enrols in a course they are not assigned to | Not possible via UI (no self-enrolment) |

---

### 15.5 Regression Testing

Before any deployment, the following regression suite must pass:

1. Login and logout for all three roles
2. Student can see enrolled exams; cannot see unenrolled exams
3. Student can start, answer, and submit an exam
4. Security violation increments `warning_count` correctly
5. Tier 3 sets attempt to `terminated_pending_review` and result to `DISQUALIFIED`
6. Admin can approve and reject a terminated attempt
7. `GradingService` does not overwrite DISQUALIFIED result
8. Teacher can create exam, add questions, and submit for approval
9. Admin can approve, schedule, and publish exam
10. Re-attempt request flows from student → teacher → admin → approval
11. Certificate verification page works without login
12. Email queue processes successfully and logs are updated
13. Security settings changes persist and affect new exam sessions
14. `results:mark-absent` creates ABSENT records without duplicates
15. Chat messages are encrypted in the database and decrypted in the view

---

### 15.6 User Acceptance Testing

The following UAT scenarios must be validated by the product owner or designated stakeholder:

| Scenario | Acceptor |
|---|---|
| Admin configures security policy, teacher creates exam, student takes exam with violations | Admin + Teacher + Student |
| Full re-attempt workflow: student requests → teacher forwards → admin approves → student retakes | Admin + Teacher + Student |
| Admin generates transcript and certificate, verifies via QR code | Admin |
| Admin sends bulk email to all students; students receive it | Admin + Student |
| Admin closes exam; all students see their results | Admin + Student |
| Teacher views exam results with all status labels (PASSED, FAILED, ABSENT, DISQUALIFIED) | Teacher |

---

*End of REQUIREMENTS.md*
