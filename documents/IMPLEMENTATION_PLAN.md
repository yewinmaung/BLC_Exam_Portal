# IMPLEMENTATION PLAN
## Believe Exam — Online Examination Management System

> **Document Type:** Official Execution Plan
> **Status:** Approved for Implementation
> **Date:** 2026-07-01
> **Version:** 1.0

---

## Table of Contents

1. [Document Information](#1-document-information)
2. [Implementation Objectives](#2-implementation-objectives)
3. [Change Strategy](#3-change-strategy)
4. [Implementation Phases](#4-implementation-phases)
5. [File Impact Matrix](#5-file-impact-matrix)
6. [Dependency Execution Order](#6-dependency-execution-order)
7. [Database Plan](#7-database-plan)
8. [API Compatibility Plan](#8-api-compatibility-plan)
9. [UI Compatibility Plan](#9-ui-compatibility-plan)
10. [Security Preservation Plan](#10-security-preservation-plan)
11. [Regression Prevention Plan](#11-regression-prevention-plan)
12. [Verification Checklist](#12-verification-checklist)
13. [Rollback Plan](#13-rollback-plan)
14. [Testing Plan](#14-testing-plan)
15. [Deployment Readiness Checklist](#15-deployment-readiness-checklist)
16. [Risks](#16-risks)
17. [Completion Criteria](#17-completion-criteria)

---

## 1. Document Information

### 1.1 Purpose
This document is the official execution plan governing all implementation work on the Believe Exam system. It defines the sequence, scope, safety boundaries, verification requirements, and rollback strategy for every planned modification.

This document does not contain code. It does not contain SQL. It instructs implementers on **what to do, in what order, what to check, and how to recover** if something goes wrong.

### 1.2 Scope
This plan covers exactly the four modifications defined in `REQUIREMENTS.md §8 Feature Modification Requirements`:

| ID | Modification | Complexity |
|---|---|---|
| FM-01 | Security Settings Admin UI toggle controls | Low |
| FM-02 | Result status display badges across all result views | Low |
| FM-03 | Teacher re-attempt UI: cancel and send-to-admin actions | Low |
| FM-04 | Register `results:mark-absent` in the scheduler | Minimal |

All other system behaviour is pre-existing and must not be modified.

### 1.3 References

| Document | Role in This Plan |
|---|---|
| `documents/ANALYSIS.md` | Architecture baseline — defines what currently exists |
| `documents/REQUIREMENTS.md` | Requirements baseline — defines what must be true after implementation |
| `routes/web.php` | Route reference — no changes expected |
| `app/Console/Kernel.php` | Scheduler registration target for FM-04 |
| `app/Services/ExamSecurityService.php` | Must not be touched |
| `public/js/exam-anticheat.js` | Must not be touched |

### 1.4 Version and Status

| Field | Value |
|---|---|
| Version | 1.0 |
| Status | Approved for Implementation |
| Approver | Architecture Review |
| Baseline Documents | ANALYSIS.md v1.0, REQUIREMENTS.md v1.0 |

---

## 2. Implementation Objectives

### 2.1 What Will Be Implemented

The four modifications below represent the complete scope of this implementation. Each is self-contained and limited to specific files identified in REQUIREMENTS.md.

**FM-01 — Security Settings UI Toggles**
The Admin Security Settings page must present all 10 configurable security policy settings using clear toggle controls. Currently the UI may be incomplete or unclear in how settings are presented. After implementation, every flag must be visible as a labelled on/off toggle, and `max_warnings` as a numeric field. No backend change is needed — `SecuritySetting::set()` and `SecuritySetting::policy()` are fully functional.

**FM-02 — Result Status Display Badges**
Six result views across three roles currently display result data without consistently surfacing the `exam_result_status` field. After implementation, every result row in every result view must show a colour-coded badge using `Result::statusLabel()` and `Result::statusBadgeClass()`. The grading logic, data model, and all backend services remain unchanged.

**FM-03 — Teacher Re-Attempt UI Actions**
The teacher re-attempt index view must expose two actions that already have working server-side routes and controller methods: (1) Cancel a pending request and (2) Forward a student request to admin. Currently the UI may not surface these buttons. After implementation, both actions must be available on the teacher re-attempt list for requests in the appropriate state.

**FM-04 — Absent Marking Scheduler Registration**
The `results:mark-absent` artisan command is fully implemented and idempotent but is not registered in the scheduler. After implementation, it will run automatically on a regular schedule so ABSENT result records are created without manual operator intervention.

---

### 2.2 What Will NOT Be Implemented

The following are explicitly out of scope for this plan. Nothing in this list may be touched:

- Any change to `ExamSecurityService` or the 3-tier violation logic
- Any change to `GradingService`
- Any change to `exam-anticheat.js` or `question-builder.js`
- Any change to `ExamSessionController`
- Any change to middleware (`EnsureExamActive`, `EnsureSingleExamSession`, `RoleMiddleware`)
- Any database migration or schema change
- Any route addition or removal
- Any change to the `SecuritySetting` model or its `policy()` method
- Any change to the `Result` model constants
- Any change to the `ReAttemptService`
- Any change to the `EmailService` or `SendEmailJob`
- Any change to the `MarkAbsentResults` command logic
- Any API endpoint modification
- Real-time broadcasting, mobile support, essay grading, role additions

---

### 2.3 Expected Outcome

After successful completion of all four phases:

1. Admins can configure all 10 security policy settings through clearly labelled UI controls.
2. Every result view shows a colour-coded status badge (Passed/Failed/Absent/Disqualified).
3. Teachers can cancel their pending re-attempt requests and forward student requests to admin directly from the teacher UI.
4. ABSENT result records are created automatically by the scheduler without operator intervention.
5. No existing functionality has been broken. All regression protection requirements from REQUIREMENTS.md §9 are satisfied.

---

## 3. Change Strategy

### 3.1 Why Phased Implementation Is Required

Although the four modifications are small, the system they touch is deeply interconnected. The exam security system, grading chain, and session middleware form a zero-tolerance integrity boundary. A careless change to even a Blade view that references a wrong variable name can cause a 500 error on the live exam take page, which would interrupt active student sessions.

Phasing each modification independently ensures:
- Each change is verified in isolation before proceeding to the next
- A failure in one phase does not contaminate the others
- Rollback is scoped to one file or one logical change, not the entire deployment

The four modifications are also naturally ordered: FM-04 has the smallest possible blast radius (one line in one file), FM-01 and FM-02 are view-only, and FM-03 is a view with existing server-side support. Implementing in this order means the most dangerous change is always the one with the most pre-existing verification.

---

### 3.2 Risk Reduction Strategy

**Principle 1 — Smallest possible change surface**
Each modification touches the minimum number of files necessary. No refactoring of working code is permitted. No "while we're in here" changes.

**Principle 2 — Server-side code is already correct**
All four modifications are primarily UI-side or configuration-side. The server-side logic (`SecuritySetting::set()`, `Result::statusLabel()`, `ReAttemptService`, `MarkAbsentResults`) is fully implemented and tested by the existing architecture. The risk is therefore limited to view correctness and Blade syntax, not business logic.

**Principle 3 — No changes to zero-tolerance components**
The following files must not be opened, let alone edited, during this implementation:
- `app/Services/ExamSecurityService.php`
- `app/Services/GradingService.php`
- `app/Http/Controllers/Student/ExamSessionController.php`
- `app/Http/Middleware/EnsureExamActive.php`
- `app/Http/Middleware/EnsureSingleExamSession.php`
- `public/js/exam-anticheat.js`
- Any migration file

**Principle 4 — Independent verification per phase**
Each phase has a defined verification method and exit criteria. Implementation may not proceed to the next phase until the current phase's exit criteria are fully satisfied.

---

### 3.3 Regression Prevention Strategy

- Before any modification: manually confirm the current state of each affected view by loading the corresponding page and verifying it renders without errors.
- After each modification: run the full regression checklist for that phase before moving on.
- The overall regression suite (REQUIREMENTS.md §15.5) must pass after all phases are complete.
- No modification may alter any logic that affects: exam session creation, violation recording, grading, result writing, or email dispatch.

---

### 3.4 Backward Compatibility Strategy

All four modifications are additive or corrective to existing views or configuration. They do not change:
- Any database schema
- Any HTTP API response shape
- Any JavaScript behaviour
- Any session or token mechanism
- Any service method signatures

Backward compatibility is therefore automatically preserved at the data layer, API layer, and JavaScript layer. The only compatibility risk is Blade rendering — which is verified immediately after each file change.

---

## 4. Implementation Phases

---

### Phase 1 — Scheduler Registration (FM-04)

**Phase Number:** 1
**Phase Name:** Absent Marking Scheduler Registration
**Requirement:** FM-04

#### Objective
Register the fully-implemented `results:mark-absent` command in the Laravel scheduler so ABSENT result records are created automatically after exam schedules end.

#### Components Involved
- `app/Console/Kernel.php` — the only file that changes

#### Files Expected to Change
| File | Nature of Change |
|---|---|
| `app/Console/Kernel.php` | Add one scheduler entry for `results:mark-absent` |

#### Files That MUST NOT Change
- `app/Console/Commands/MarkAbsentResults.php` — command logic is correct, must not be touched
- Any model, migration, service, controller, view, or JavaScript file
- `app/Console/Kernel.php` existing `email:process-scheduled` entry — must remain unchanged

#### Dependencies
- None. This phase has no dependencies on any other phase.
- Pre-condition: the `results` table and `exam_schedules` table must exist (they do — confirmed in ANALYSIS.md).

#### Estimated Risk
**Minimal.** A scheduler registration is a one-line addition to the schedule method. It cannot affect any running web request. If the command produces an error during execution, it fails silently (scheduler exceptions do not crash the web application).

#### Verification Method
1. After adding the scheduler entry, run `php artisan schedule:list` and confirm `results:mark-absent` appears in the output.
2. Run `php artisan results:mark-absent --dry-run` and confirm it executes without errors.
3. Confirm the existing `email:process-scheduled` entry still appears in `php artisan schedule:list`.
4. Load the admin dashboard in a browser — no errors should appear.

#### Exit Criteria
- [ ] `php artisan schedule:list` shows `results:mark-absent` registered
- [ ] `php artisan results:mark-absent --dry-run` completes with exit code 0
- [ ] Existing scheduler entry `email:process-scheduled` is still present
- [ ] No PHP syntax errors in `Kernel.php` (`php artisan` commands respond normally)
- [ ] Web application loads without errors

---

### Phase 2 — Security Settings UI (FM-01)

**Phase Number:** 2
**Phase Name:** Security Settings Admin UI Toggle Controls
**Requirement:** FM-01

#### Objective
Ensure all 10 security policy settings are clearly presented in the Admin Security Settings UI with labelled toggle controls and a numeric input for `max_warnings`. The backend (`SecuritySetting::set()`, `SecuritySetting::policy()`, `SecuritySettingsController`) is already fully functional.

#### Components Involved
- `resources/views/admin/security-settings/index.blade.php` — primary UI view
- `resources/views/admin/security-settings/_toggle.blade.php` — existing toggle partial

#### Files Expected to Change
| File | Nature of Change |
|---|---|
| `resources/views/admin/security-settings/index.blade.php` | Ensure all 10 policy settings are rendered using toggle controls; add `max_warnings` numeric input if missing |
| `resources/views/admin/security-settings/_toggle.blade.php` | Adjust partial if toggle label or structure needs correction |

#### Files That MUST NOT Change
- `app/Http/Controllers/Admin/SecuritySettingsController.php`
- `app/Models/SecuritySetting.php`
- `app/Services/ExamSecurityService.php`
- `public/js/exam-anticheat.js`
- `resources/views/student/exam/take.blade.php`
- Any migration file

#### Dependencies
- Phase 1 must be complete (exit criteria satisfied).
- Pre-condition: admin user must exist and be accessible for manual verification.
- Pre-condition: `security_settings` table must have all 10 keys seeded (confirmed by migration `2026_06_29_000001_seed_security_policy_settings.php`).

#### Estimated Risk
**Low.** The view change does not affect any server-side logic. The `SecuritySettingsController::update()` already validates all 10 keys. The only risk is a Blade syntax error causing a 500 on the security settings page, which is isolated from exam sessions.

#### Verification Method
1. Log in as admin and navigate to `/admin/security-settings`.
2. Confirm the page renders without errors.
3. Confirm all 10 policy flag toggles are visible and labelled.
4. Confirm `max_warnings` is visible as a numeric input.
5. Toggle each flag, change `max_warnings`, and submit the form.
6. Confirm the page reloads with a success flash message.
7. Refresh the page and confirm the saved values are reflected in the form.
8. Navigate to any other admin page and confirm no errors.
9. Confirm `admin/cheating-logs`, `admin/exams`, and `admin/dashboard` all still load correctly.

#### Exit Criteria
- [ ] Security settings page renders without errors for admin user
- [ ] All 10 policy toggles are visible with correct labels
- [ ] `max_warnings` field is visible as a numeric input
- [ ] Form submission saves values and shows success message
- [ ] Refreshing after save shows the correct persisted values
- [ ] No change to `SecuritySetting::policy()` output structure (verify via `php artisan tinker`: `App\Models\SecuritySetting::policy()`)
- [ ] The exam take page (`/student/attempt/{id}/take`) is not affected — loads normally for a student with an active attempt

---

### Phase 3 — Result Status Display (FM-02)

**Phase Number:** 3
**Phase Name:** Result Status Badge Display
**Requirement:** FM-02

#### Objective
Surface the `exam_result_status` field with a colour-coded status badge in all six result views across Admin, Teacher, and Student roles. The `Result` model already provides `statusLabel()` and `statusBadgeClass()` helper methods. This phase is purely additive to existing Blade views.

#### Components Involved
Six Blade view files only. No PHP class changes.

#### Files Expected to Change
| File | Nature of Change |
|---|---|
| `resources/views/admin/results/index.blade.php` | Add status badge column to the results table |
| `resources/views/admin/results/student.blade.php` | Add status badge to per-student result rows |
| `resources/views/teacher/exams/results.blade.php` | Add status badge column |
| `resources/views/teacher/results/index.blade.php` | Add status badge column |
| `resources/views/student/results/index.blade.php` | Add status badge to student's own results |
| `resources/views/student/exams/show.blade.php` | Show status badge in the result section after schedule ends |

#### Files That MUST NOT Change
- `app/Models/Result.php` — constants and helpers already exist
- `app/Services/GradingService.php`
- `app/Services/ExamSecurityService.php`
- `app/Http/Controllers/Admin/ResultController.php`
- `app/Http/Controllers/Teacher/ResultController.php`
- `app/Http/Controllers/Student/ResultController.php`
- `app/Http/Controllers/Student/ExamController.php`
- Any migration file
- Any JavaScript file

#### Dependencies
- Phase 2 must be complete (exit criteria satisfied).
- Pre-condition: at least one result record must exist in the database with a non-null `exam_result_status` for verification to be meaningful. If no results exist, create a test exam attempt and submit it.

#### Estimated Risk
**Low.** These are read-only display changes. The result data is already in the database and the helper methods are already on the `Result` model. The only risk is a Blade syntax error or a missing null-check if `exam_result_status` is null on an older result row.

**Important null-safety note:** Some historical `Result` rows may have `exam_result_status = null` (pre-migration rows). The view must handle this gracefully — `$result->statusLabel()` returns `'—'` and `statusBadgeClass()` returns `'bg-light text-dark'` for null status, so the existing model helpers already handle this. The view must call these methods rather than directly accessing the column.

#### Verification Method
1. Log in as admin and navigate to `/admin/results`.
2. Confirm the status badge column renders for each result row.
3. Navigate to `/admin/results/student/{student_id}` for a student with results.
4. Confirm badges display correctly.
5. Log in as teacher and navigate to `/teacher/results`.
6. Open a specific exam's results: `/teacher/exams/{exam}/results`.
7. Confirm badges display in both views.
8. Log in as student and navigate to `/student/results`.
9. Navigate to `/student/exams/{exam}` for a completed exam.
10. Confirm result badge displays after schedule has ended.
11. Confirm that a result with null `exam_result_status` displays `—` rather than crashing.

#### Exit Criteria
- [ ] All 6 result views render without errors
- [ ] Status badge visible in all admin result views
- [ ] Status badge visible in all teacher result views
- [ ] Status badge visible in all student result views
- [ ] Null `exam_result_status` renders gracefully (no 500 error)
- [ ] PASSED shows green badge, FAILED shows red, ABSENT shows grey, DISQUALIFIED shows yellow
- [ ] No change to how results are loaded or queried (data unchanged)
- [ ] Exam take page still loads normally (unrelated to this phase, spot-check only)

---

### Phase 4 — Teacher Re-Attempt UI Actions (FM-03)

**Phase Number:** 4
**Phase Name:** Teacher Re-Attempt Cancel and Forward Actions
**Requirement:** FM-03

#### Objective
Add Cancel and Send-to-Admin buttons to the teacher re-attempt index view. Both server-side routes and controller methods (`reattemptCancel`, `reattemptSendToAdmin`) are already implemented and working. This phase wires them to the UI.

#### Components Involved
- `resources/views/teacher/reattempts/index.blade.php` — primary file to update
- `app/Http/Controllers/Teacher/ExamController.php` — reference only, must not change

#### Files Expected to Change
| File | Nature of Change |
|---|---|
| `resources/views/teacher/reattempts/index.blade.php` | Add Cancel button (DELETE form) and Send-to-Admin button (POST form) with appropriate state conditions |

#### Files That MUST NOT Change
- `app/Http/Controllers/Teacher/ExamController.php` — `reattemptCancel()` and `reattemptSendToAdmin()` are already correct
- `app/Services/ReAttemptService.php`
- `app/Http/Controllers/Admin/ReAttemptController.php`
- `resources/views/admin/reattempts/index.blade.php`
- `resources/views/admin/reattempts/show.blade.php`
- `resources/views/student/reattempts/index.blade.php`
- Any route in `routes/web.php`
- Any migration file

#### Dependencies
- Phase 3 must be complete (exit criteria satisfied).
- Pre-condition: at least one `ReAttemptRequest` record must exist in the database for manual verification. If none exist, create a test request via the student interface.

#### Estimated Risk
**Low.** The routes are registered and the controller methods are correct. The risk is limited to: incorrect form method (must use `DELETE` for cancel and `POST` for send-to-admin), missing CSRF tokens, incorrect route name references, or accidentally showing action buttons for requests in the wrong state.

**State-condition rules (from REQUIREMENTS.md and ANALYSIS.md):**
- Cancel button: visible only when `$reattempt->isPending()` — i.e., `status = pending`
- Send-to-Admin button: visible only when `$reattempt->isPending()` AND `$reattempt->sent_to_admin_at === null`
- Neither button should appear for approved or rejected requests

**Route names to reference:**
- Cancel: `teacher.reattempts.cancel` (DELETE method) — confirmed in `routes/web.php`
- Send-to-Admin: `teacher.reattempts.send_to_admin` (POST method) — confirmed in `routes/web.php`

#### Verification Method
1. Log in as admin, create a test student, teacher, exam, and re-attempt request.
2. Log in as teacher and navigate to `/teacher/reattempts`.
3. Confirm the Cancel button appears for a pending request.
4. Confirm the Send-to-Admin button appears for a pending request where `sent_to_admin_at` is null.
5. Click Send-to-Admin. Confirm:
   - Page reloads with success message
   - The request now has `sent_to_admin_at` set
   - The Send-to-Admin button no longer appears (already forwarded)
   - Log in as admin and confirm the request appears in `/admin/reattempts`
6. Log back in as teacher. Click Cancel on a different pending request. Confirm:
   - Page reloads with success message
   - The cancelled request no longer appears in the list
7. Confirm that approved or rejected requests do not show Cancel or Send-to-Admin buttons.
8. Confirm the admin re-attempt list at `/admin/reattempts` is unaffected for non-forwarded requests.

#### Exit Criteria
- [ ] Teacher re-attempt index renders without errors
- [ ] Cancel button visible for pending requests; not visible for approved/rejected
- [ ] Send-to-Admin button visible for pending requests with `sent_to_admin_at = null`
- [ ] Cancel action deletes the request and shows success message
- [ ] Send-to-Admin action sets `sent_to_admin_at` and shows success message
- [ ] Forwarded request appears in admin re-attempt list (`/admin/reattempts`)
- [ ] Admin and student re-attempt views are unaffected
- [ ] No JavaScript changes were made
- [ ] CSRF token present in both form submissions

---

## 5. File Impact Matrix

The table below covers every file identified as affected by the four modifications. Files marked as "No Change" are referenced for verification purposes only.

### 5.1 Files That Will Change

| File | Purpose | Planned Modification | Risk Level | Depends On | Verification Required |
|---|---|---|---|---|---|
| `app/Console/Kernel.php` | Laravel scheduler registration | Add `results:mark-absent` to `schedule()` method | Minimal | None | `php artisan schedule:list` confirms command registered |
| `resources/views/admin/security-settings/index.blade.php` | Admin security policy configuration UI | Ensure all 10 toggle controls and `max_warnings` field are present and correctly wired to the form | Low | Phase 1 complete | Manual form submission; values persist after save |
| `resources/views/admin/security-settings/_toggle.blade.php` | Reusable toggle partial | Adjust label/structure if needed to support all policy flags | Low | `index.blade.php` change | Rendered correctly for each policy key |
| `resources/views/admin/results/index.blade.php` | Admin all-results list | Add `exam_result_status` badge column via `statusLabel()` and `statusBadgeClass()` | Low | Phase 2 complete | Page renders; badges show correct colours |
| `resources/views/admin/results/student.blade.php` | Admin per-student results | Add status badge to each result row | Low | Phase 2 complete | Page renders; null status handled gracefully |
| `resources/views/teacher/exams/results.blade.php` | Teacher per-exam results | Add status badge column | Low | Phase 2 complete | Page renders; all statuses display |
| `resources/views/teacher/results/index.blade.php` | Teacher all-results list | Add status badge column | Low | Phase 2 complete | Page renders without errors |
| `resources/views/student/results/index.blade.php` | Student own results | Add status badge per result row | Low | Phase 2 complete | Student sees badge; null handled |
| `resources/views/student/exams/show.blade.php` | Student exam detail with result | Add status badge in result section | Low | Phase 2 complete | Badge shows after schedule ends; hidden before |
| `resources/views/teacher/reattempts/index.blade.php` | Teacher re-attempt management | Add Cancel (DELETE form) and Send-to-Admin (POST form) buttons with state conditions | Low | Phase 3 complete | Both actions execute; state conditions correct |

### 5.2 Files That Must Not Change

| File | Purpose | Reason Must Not Change |
|---|---|---|
| `app/Services/ExamSecurityService.php` | 3-tier violation enforcement | Zero-tolerance integrity boundary |
| `app/Services/GradingService.php` | Exam grading and DISQUALIFIED guard | Result integrity — must not be weakened |
| `app/Http/Controllers/Student/ExamSessionController.php` | Live exam session management | Active exam sessions depend on this |
| `app/Http/Middleware/EnsureExamActive.php` | Attempt state gate on session routes | Removing breaks security boundary |
| `app/Http/Middleware/EnsureSingleExamSession.php` | Single-session token enforcement | Concurrent session protection |
| `app/Http/Middleware/RoleMiddleware.php` | Role-based access control | All role gates depend on this |
| `public/js/exam-anticheat.js` | Browser-side security enforcement | JSON response contract must not break |
| `public/js/question-builder.js` | Question form dynamic UI | No modification required |
| `app/Models/SecuritySetting.php` | Security policy storage and cache | `policy()` key names must not change |
| `app/Models/Result.php` | Result model with status constants | DISQUALIFIED guard and constants must not change |
| `app/Models/ExamAttempt.php` | Attempt status helpers | Status enum values must not change |
| `app/Services/ReAttemptService.php` | Re-attempt business logic | Already correct; FM-03 is view-only |
| `app/Services/EmailService.php` | Email dispatch and queuing | Send signature must not change |
| `app/Console/Commands/MarkAbsentResults.php` | Absent result creation logic | FM-04 only adds scheduler registration |
| `routes/web.php` | All route definitions | No new routes required |
| All migration files | Database schema | No schema changes required |
| `app/Http/Controllers/Admin/SecuritySettingsController.php` | Saves security settings | Already validates all 10 keys |
| `app/Http/Controllers/Admin/ResultController.php` | Admin result queries | Data layer is correct |
| `app/Http/Controllers/Teacher/ResultController.php` | Teacher result queries | Data layer is correct |
| `app/Http/Controllers/Student/ResultController.php` | Student result queries | Data layer is correct |
| `app/Http/Controllers/Teacher/ExamController.php` | Re-attempt cancel and forward methods | Already correct; FM-03 is view-only |
| `app/Http/Controllers/Admin/ReAttemptController.php` | Admin re-attempt approval | Must not be modified |
| `resources/views/student/exam/take.blade.php` | Live exam interface | Any error here breaks active exams |
| `app/Providers/AppServiceProvider.php` | Default admin auto-creation on boot | Must not be modified |

---

## 6. Dependency Execution Order

### 6.1 Phase Dependency Graph

```
Phase 1 (FM-04: Scheduler)
  │
  └── Phase 2 (FM-01: Security Settings UI)
        │
        └── Phase 3 (FM-02: Result Status Badges)
              │
              └── Phase 4 (FM-03: Teacher Re-Attempt UI)
```

Each phase must reach its exit criteria before the next begins. The ordering is based on blast-radius principle: smallest first.

---

### 6.2 Rationale for Execution Order

**Phase 1 first** (Scheduler Registration):
FM-04 touches exactly one line in one file (`Kernel.php`) and has zero web-request impact. It is the safest possible change and can be done and verified independently without any UI interaction.

**Phase 2 second** (Security Settings UI):
FM-01 requires the `security_settings` table to already be seeded (which it is). It is isolated to the admin area and has no impact on exam sessions. Completing it early ensures the security policy management is fully operational before any exam-related view work begins.

**Phase 3 third** (Result Status Badges):
FM-02 touches 6 view files but makes no logic changes. It depends on the `Result` model helpers which are already stable. It must come before FM-03 because FM-03 also involves Blade view work — verifying FM-02 first confirms the implementer's Blade workflow is correct before tackling FM-03.

**Phase 4 last** (Teacher Re-Attempt UI):
FM-03 is the only modification that involves HTML form submissions against server-side routes. It has the most verification steps. Doing it last ensures all simpler changes are stable and the implementer is familiar with the codebase before handling form action logic.

---

### 6.3 Within-Phase Component Order

**Phase 2 (FM-01) internal order:**
1. Inspect `_toggle.blade.php` to understand current partial structure
2. Inspect `index.blade.php` to understand current form layout
3. Identify which policy keys are missing or poorly labelled
4. Update `_toggle.blade.php` if needed
5. Update `index.blade.php` to include all 10 keys
6. Verify

**Phase 3 (FM-02) internal order:**
1. Confirm `Result::statusLabel()` and `Result::statusBadgeClass()` return correct values (read-only check — do not edit)
2. Update admin views first (least user-facing risk)
3. Update teacher views second
4. Update student views last (most user-facing, most verification required)
5. Verify all 6 views

**Phase 4 (FM-03) internal order:**
1. Confirm route names `teacher.reattempts.cancel` and `teacher.reattempts.send_to_admin` exist in `routes/web.php` (read-only check)
2. Confirm `reattemptCancel()` and `reattemptSendToAdmin()` methods exist in `Teacher\ExamController` (read-only check)
3. Update `teacher/reattempts/index.blade.php`
4. Verify with live data

---

## 7. Database Plan

### 7.1 Database Changes Required

**No database migrations are required for any of the four modifications.**

This is confirmed by REQUIREMENTS.md §8:
- FM-01 explicitly states: "No migration — no schema change needed"
- FM-02 explicitly states: "Database schema — no changes required"
- FM-03 explicitly states: "Database schema — no changes required"
- FM-04 involves only scheduler registration — no schema involved

All required tables and columns already exist:
- `security_settings` table with `key`, `value`, `label`, `description` columns — confirmed by migration `2026_06_28_000001_create_security_settings_table.php`
- All 10 security policy keys seeded by migration `2026_06_29_000001_seed_security_policy_settings.php`
- `results.exam_result_status` ENUM column — confirmed by migration `2026_06_30_000001_add_result_status_to_results.php`
- `re_attempt_requests.sent_to_admin_at` column — confirmed by migration `2026_06_02_150000_add_sent_to_admin_to_re_attempt_requests.php`

### 7.2 Migration Required

| Phase | Migration Required | Reason |
|---|---|---|
| Phase 1 (FM-04) | No | Scheduler config only |
| Phase 2 (FM-01) | No | `security_settings` table already exists and seeded |
| Phase 3 (FM-02) | No | `exam_result_status` column already exists |
| Phase 4 (FM-03) | No | `sent_to_admin_at` column already exists |

### 7.3 Rollback Strategy
Since no migrations are involved, database rollback is not applicable. If a view causes a 500 error, the fix is reverting the view file — no database state changes.

### 7.4 Data Compatibility
- Historical `Result` rows with `exam_result_status = null` must be handled by views using `$result->statusLabel()` which returns `'—'` for null. The view must call the model helper, not access the column directly.
- Historical `ReAttemptRequest` rows without `sent_to_admin_at` correctly have `sent_to_admin_at = null`. The FM-03 view condition `$reattempt->sent_to_admin_at === null` handles this correctly.

---

## 8. API Compatibility Plan

### 8.1 Existing APIs That Must Remain Compatible

The following JSON endpoints are used by the frontend and must not be altered in any way:

| Endpoint | Consumer | Compatibility Requirement |
|---|---|---|
| `POST /student/attempt/{id}/violation` | `exam-anticheat.js` | Response shape `{warning_count, terminated, locked, message, redirect}` must not change |
| `POST /student/attempt/{id}/save` | `exam-anticheat.js` | Response `{"success": true}` must not change |
| `GET /notifications/unread-count` | All role navigation bars | Returns integer count JSON; must not change |
| `GET /admin/courses-by-year-level` | Enrollment form JS | Returns course list JSON; must not change |
| `GET /admin/enrollments/students-by-year-level` | Enrollment form JS | Returns student list JSON; must not change |
| `GET /chat/{user}/poll` | Chat view JS | Returns unread messages JSON; must not change |

### 8.2 APIs Affected by This Implementation

**None.** All four modifications are view-only or scheduler-only. No endpoint response shape, route, or controller method is modified.

### 8.3 Validation Strategy

After each phase, spot-check the following:
1. `GET /notifications/unread-count` — open browser DevTools → Network tab → confirm JSON response intact
2. `GET /admin/courses-by-year-level?year_level=1` — confirm JSON array returned
3. If a student has an active exam session, confirm `POST /student/attempt/{id}/violation` still responds with the required JSON shape

The violation endpoint can be confirmed without a live exam by reading the `ExamSecurityService` code (which is not being modified) and confirming its return values match the required shape.

---

## 9. UI Compatibility Plan

### 9.1 Student UI

| Screen | Status | Change |
|---|---|---|
| Login / Register | Unchanged | No modification |
| Student Dashboard | Unchanged | No modification |
| Course List (`/student/courses`) | Unchanged | No modification |
| Exam List (`/student/exams`) | Unchanged | No modification |
| Exam Detail (`/student/exams/{exam}`) | **Modified (Phase 3)** | Status badge added to result section |
| Live Exam Take (`/student/attempt/{id}/take`) | **MUST NOT CHANGE** | Critical exam integrity boundary |
| Student Results (`/student/results`) | **Modified (Phase 3)** | Status badge column added |
| Re-Attempt Index (`/student/reattempts`) | Unchanged | No modification |
| Re-Attempt Create (`/student/reattempts/create/{exam}`) | Unchanged | No modification |
| Notifications (`/notifications`) | Unchanged | No modification |
| Chat | Unchanged | No modification |

**Compatibility Requirements — Student UI:**
- The live exam take page (`student/exam/take.blade.php`) must not be touched during any phase.
- All `data-*` attributes on `#examBody` must remain exactly as currently written.
- The `#warningBox`, `#fsOverlay`, `#submitBtn`, `#examForm`, `#timer`, `#timerText`, `.q-nav-btn`, `.question-block` elements must remain present.
- Student can only see result badge after schedule ends and `is_published = true` — this logic is in the controller and must not be changed.

---

### 9.2 Teacher UI

| Screen | Status | Change |
|---|---|---|
| Teacher Dashboard | Unchanged | No modification |
| Exam List (`/teacher/exams`) | Unchanged | No modification |
| Exam Create | Unchanged | No modification |
| Exam Detail with Questions | Unchanged | No modification |
| Edit Question | Unchanged | No modification |
| Exam Results (`/teacher/exams/{exam}/results`) | **Modified (Phase 3)** | Status badge column added |
| Teacher Results Index (`/teacher/results`) | **Modified (Phase 3)** | Status badge column added |
| Re-Attempt Index (`/teacher/reattempts`) | **Modified (Phase 4)** | Cancel and Send-to-Admin buttons added |
| Re-Attempt Create | Unchanged | No modification |
| Profile Show / Edit | Unchanged | No modification |

**Compatibility Requirements — Teacher UI:**
- Re-attempt action buttons must appear only for requests the teacher owns.
- Cancel button must only be active for `status = pending` requests.
- Send-to-Admin button must only be active for `status = pending` AND `sent_to_admin_at = null`.
- Neither button must appear for approved or rejected requests.
- The teacher results views must not change how results are loaded — only how they are displayed.

---

### 9.3 Administrator UI

| Screen | Status | Change |
|---|---|---|
| Admin Dashboard | Unchanged | No modification |
| User Management | Unchanged | No modification |
| Course Management | Unchanged | No modification |
| Enrollment Management | Unchanged | No modification |
| Major Management | Unchanged | No modification |
| Exam Management (index/show/approve/schedule/publish) | Unchanged | No modification |
| Cheating Logs | Unchanged | No modification |
| **Security Settings** | **Modified (Phase 2)** | All 10 toggle controls and `max_warnings` field ensured present |
| Re-Attempt Management (index/show/approve/reject) | Unchanged | No modification |
| **Results (index/student)** | **Modified (Phase 3)** | Status badge columns added |
| Transcripts / Certificates | Unchanged | No modification |
| Academic Years | Unchanged | No modification |
| Email Management | Unchanged | No modification |
| Teachers / Students CRUD | Unchanged | No modification |
| Notifications | Unchanged | No modification |

**Compatibility Requirements — Admin UI:**
- Security settings form must POST to the same route and use the same field names as currently expected by `SecuritySettingsController::update()`.
- Field names: `max_warnings`, `auto_terminate_enabled`, and each of the 8 flag keys — names must not change.
- The toggle partial `_toggle.blade.php` must continue to produce form inputs with the correct `name` attribute.

---

## 10. Security Preservation Plan

All four modifications are UI-level or configuration-level. None of them touch any security mechanism. Nevertheless, the following preservation checklist must be explicitly confirmed after each phase.

### 10.1 Authentication Preservation

**What must remain unchanged:**
- `AuthController` login/logout flow
- Session initialisation and `exam_session_token` handling
- `is_active` check on login

**Verification:** Log in and out as all three roles after each phase. Confirm no session errors.

---

### 10.2 Authorization Preservation

**What must remain unchanged:**
- `RoleMiddleware` enforcement on admin, teacher, and student route groups
- The `role:admin` guard on all admin routes
- The `role:teacher,admin` guard on teacher routes
- The `role:student` guard on student routes

**Verification:** After Phase 4, attempt to access an admin route while logged in as a student — confirm HTTP 403. Attempt to access a student exam route while logged in as teacher — confirm 403.

---

### 10.3 Browser Security Preservation

**What must remain unchanged:**
- `exam-anticheat.js` must not be modified
- All `data-policy-*` attributes on `#examBody` in `take.blade.php` must remain
- The violation POST endpoint must return the same JSON shape
- `SecuritySetting::policy()` must return the same 8 keys + `max_warnings`

**Critical FM-01 requirement:** When saving security settings via the updated UI (FM-01), the saved values must be correctly read back by `SecuritySetting::policy()` on the next exam take page load. This must be verified with:
1. Change `fullscreen_detection_enabled` to false in admin UI
2. Load the exam take page for a new attempt
3. Confirm `data-policy-fullscreen="0"` is present on `#examBody`
4. Confirm the fullscreen listener is NOT registered in the JS (no `fullscreenchange` listener)

---

### 10.4 Warning System Preservation

**What must remain unchanged:**
- `ExamSecurityService::recordViolation()` and all tier logic
- `CheatingLog` creation on every violation
- `warning_count` increment behaviour
- Tier thresholds (Tier 1, Tier 2, Tier 3)

**Verification:** The warning system is unchanged by all four modifications. Confirm by reading (not editing) `ExamSecurityService.php` after all phases — no line should have changed.

---

### 10.5 Auto Termination Preservation

**What must remain unchanged:**
- `ExamSecurityService::recordTierThree()` with `lockForUpdate()`
- The `DB::afterCommit()` email/notification dispatch
- The `terminated_pending_review` status and `DISQUALIFIED` result override
- The `exam.active` middleware blocking post-termination requests

**Verification:** Same as above — `ExamSecurityService.php` must be byte-identical before and after all phases.

---

### 10.6 Session Validation Preservation

**What must remain unchanged:**
- `EnsureSingleExamSession` middleware on all authenticated routes
- The three-way token sync: `users.exam_session_token`, session, and `exam_attempts.session_token`
- `exam.active` middleware on all four exam session routes

**Verification:** After all phases, confirm the middleware stack has not changed by reading `Kernel.php`. The only addition to `Kernel.php` is one scheduler line — the `routeMiddleware` and `middlewareGroups` arrays must be byte-identical.

---

## 11. Regression Prevention Plan

### 11.1 Features That Must Never Break

The following features carry zero tolerance for regression. They must be spot-checked after every phase.

| Feature | Why It Must Not Break | Spot-Check Method |
|---|---|---|
| Student exam start | Creates the session token; losing this blocks all exam access | Start an exam as a student; confirm attempt created |
| Answer auto-save | MCQ click → fetch → `student_answers` updated | Click an MCQ option; inspect Network tab for 200 response |
| Timer countdown | 1-second interval in `exam-anticheat.js` | Load exam take page; confirm timer counts down |
| Form auto-submit on expiry | `form.submit()` called when timer reaches zero | Inspect JS — no modification made |
| Violation recording | POST to violation endpoint returns correct JSON | Inspect `ExamSecurityService` — no modification made |
| Tier 3 termination | `lockForUpdate()` transaction integrity | Inspect `ExamSecurityService` — no modification made |
| DISQUALIFIED guard in grading | `GradingService` returns early on DISQUALIFIED | Inspect `GradingService` — no modification made |
| `exam.active` middleware | Rejects all session routes when attempt not `in_progress` | Inspect `Kernel.php` — routeMiddleware unchanged |
| Email queue | `SendEmailJob` dispatched on violation/publish | Inspect `EmailService` — no modification made |
| Certificate verification | Public route with no auth | Navigate to `/certificates/verify/any-token` — confirm renders |

---

### 11.2 Critical Workflows

The following end-to-end workflows must remain intact after all phases complete:

**Workflow 1 — Student takes exam with violation:**
Login as student → Start exam → Trigger violation → Receive warning → Trigger Tier 3 → See lock overlay → Redirect to exam list → Admin reviews and approves/rejects

**Workflow 2 — Exam lifecycle:**
Teacher creates exam → Adds questions → Submits for approval → Admin approves → Admin schedules → Admin publishes → Student takes → Student submits → Result visible after schedule ends

**Workflow 3 — Re-attempt:**
Student submits request → Teacher sees notification → Teacher forwards to admin → Admin approves with window → Student takes within window → Result recorded

**Workflow 4 — Absent marking:**
Exam schedule ends → Scheduler runs `results:mark-absent` → ABSENT records created for non-attending students → Admin/teacher sees ABSENT status badge in results

---

### 11.3 Existing Integrations That Must Not Break

| Integration | Files Involved | Risk From This Plan |
|---|---|---|
| `exam-anticheat.js` ↔ violation endpoint | `ExamSessionController`, `ExamSecurityService` | None — neither file is modified |
| `exam-anticheat.js` ↔ `data-policy-*` attributes | `take.blade.php`, `SecuritySetting::policy()` | FM-01 must not change `policy()` or `take.blade.php` |
| `SecuritySetting::set()` ↔ `SecuritySettingsController` | Both unchanged | None — controller not modified |
| `Result::statusLabel()` ↔ result views | Model unchanged; views consume it | FM-02 must call the method, not access the column directly |
| `ReAttemptService` ↔ `Teacher\ExamController` | Service unchanged; controller not modified | None — FM-03 is view-only |
| `MarkAbsentResults` ↔ scheduler | Command unchanged; only `Kernel.php` entry added | None — command logic untouched |
| `SendEmailJob` ↔ `EmailService` | Neither modified | None |

---

### 11.4 High-Risk Components — Monitoring During Implementation

These components must be confirmed unchanged (byte-identical or logically equivalent) after all phases:

1. `app/Services/ExamSecurityService.php`
2. `app/Services/GradingService.php`
3. `public/js/exam-anticheat.js`
4. `resources/views/student/exam/take.blade.php`
5. `app/Http/Kernel.php` — `routeMiddleware` and `middlewareGroups` sections only (the `schedule()` method addition in `Console/Kernel.php` is expected)
6. `app/Models/SecuritySetting.php`
7. `app/Models/Result.php`

---

## 12. Verification Checklist

Each phase must satisfy its checklist completely before the next phase begins. No item may be skipped. An unchecked item is a blocked phase.

---

### Phase 1 Verification Checklist (FM-04 — Scheduler)

**Functional Verification:**
- [ ] `php artisan schedule:list` includes `results:mark-absent`
- [ ] Scheduled frequency is appropriate (daily or as configured)
- [ ] `php artisan results:mark-absent --dry-run` exits with code 0
- [ ] `php artisan results:mark-absent --dry-run` produces output (no silent failure)
- [ ] `php artisan email:process-scheduled` still appears in `schedule:list` (existing entry preserved)

**Database Verification:**
- [ ] No migrations were run
- [ ] `results` table schema is unchanged
- [ ] `exam_schedules` table schema is unchanged

**UI Verification:**
- [ ] Admin dashboard loads without errors
- [ ] Teacher dashboard loads without errors
- [ ] Student dashboard loads without errors

**Security Verification:**
- [ ] `app/Http/Kernel.php` `routeMiddleware` array is unchanged
- [ ] `app/Http/Kernel.php` `middlewareGroups` array is unchanged
- [ ] `exam-anticheat.js` is byte-identical to pre-implementation state

**Integration Verification:**
- [ ] `php artisan` responds normally (no class autoload errors from `Kernel.php` change)
- [ ] `php artisan tinker` exits cleanly

---

### Phase 2 Verification Checklist (FM-01 — Security Settings UI)

**Functional Verification:**
- [ ] `/admin/security-settings` page renders without HTTP 500
- [ ] All 10 policy flag toggles visible: `fullscreen_detection_enabled`, `blur_detection_enabled`, `tab_switch_detection_enabled`, `right_click_blocking_enabled`, `copy_detection_enabled`, `paste_detection_enabled`, `devtools_detection_enabled`, `keyboard_shortcut_detection_enabled`, `auto_terminate_enabled`
- [ ] `max_warnings` numeric input visible
- [ ] Toggling all flags and submitting shows success flash message
- [ ] After page refresh, saved toggle states are correctly shown
- [ ] After changing `max_warnings` to 5 and saving, refresh confirms value is 5
- [ ] `php artisan tinker` → `App\Models\SecuritySetting::policy()` → confirms array with 10 keys

**Database Verification:**
- [ ] No migrations were run
- [ ] `security_settings` table has the same rows before and after save (values updated, no rows added or deleted unexpectedly)

**UI Verification:**
- [ ] `/admin/security-settings` renders correctly on multiple browsers
- [ ] Form field `name` attributes match the keys expected by `SecuritySettingsController::update()`: `max_warnings`, `auto_terminate_enabled`, and all 8 flag keys
- [ ] No JavaScript errors in browser console on this page

**Security Verification:**
- [ ] Change one policy flag via UI → load a new exam take page → confirm correct `data-policy-*` attribute value on `#examBody`
- [ ] `SecuritySetting::policy()` return keys are identical to pre-implementation (no rename)
- [ ] `exam-anticheat.js` is byte-identical to pre-implementation state
- [ ] `ExamSecurityService.php` is byte-identical to pre-implementation state
- [ ] `resources/views/student/exam/take.blade.php` is byte-identical to pre-implementation state

**Integration Verification:**
- [ ] `/admin/cheating-logs` loads correctly
- [ ] `/admin/exams` loads correctly
- [ ] `/student/exams` loads correctly for a student user

---

### Phase 3 Verification Checklist (FM-02 — Result Status Badges)

**Functional Verification:**
- [ ] `/admin/results` renders without HTTP 500
- [ ] `/admin/results/student/{id}` renders without HTTP 500 for a student with results
- [ ] `/teacher/exams/{exam}/results` renders without HTTP 500
- [ ] `/teacher/results` renders without HTTP 500
- [ ] `/student/results` renders without HTTP 500
- [ ] `/student/exams/{exam}` renders without HTTP 500 for a completed exam
- [ ] PASSED result shows green badge with text "Passed"
- [ ] FAILED result shows red badge with text "Failed"
- [ ] ABSENT result shows grey badge with text "Absent" (if ABSENT records exist)
- [ ] DISQUALIFIED result shows yellow badge with text "Disqualified" (if DISQUALIFIED records exist)
- [ ] Result with null `exam_result_status` shows "—" without error

**Database Verification:**
- [ ] No migrations were run
- [ ] No result records were modified during implementation (views are read-only)

**UI Verification:**
- [ ] Badge colours are visually distinguishable
- [ ] Status column header is present and labelled in all table views
- [ ] Student result page only shows result if `is_published = true` and schedule ended (existing logic preserved)
- [ ] No layout breakage from added badge column in any table

**Security Verification:**
- [ ] `GradingService.php` is byte-identical to pre-implementation state
- [ ] `ExamSecurityService.php` is byte-identical to pre-implementation state
- [ ] `Result.php` model is byte-identical to pre-implementation state
- [ ] No result record `exam_result_status` was changed during implementation

**Integration Verification:**
- [ ] `/admin/exams` still loads correctly
- [ ] `/student/exams` still loads correctly
- [ ] Live exam take page (`/student/attempt/{id}/take`) loads correctly for a student with an active attempt

---

### Phase 4 Verification Checklist (FM-03 — Teacher Re-Attempt UI)

**Functional Verification:**
- [ ] `/teacher/reattempts` renders without HTTP 500
- [ ] Cancel button visible for a pending request where teacher is owner
- [ ] Send-to-Admin button visible for a pending request where `sent_to_admin_at` is null
- [ ] Neither button visible for approved requests
- [ ] Neither button visible for rejected requests
- [ ] Cancel action: request is deleted; success message shown; list refreshes without the deleted item
- [ ] Send-to-Admin action: `sent_to_admin_at` is set; success message shown; Send-to-Admin button disappears for that request
- [ ] After Send-to-Admin: request appears in `/admin/reattempts`
- [ ] After Send-to-Admin: admin receives in-app notification (verify `user_notifications` table or notifications page)

**Database Verification:**
- [ ] No migrations were run
- [ ] Cancelled request is deleted from `re_attempt_requests`
- [ ] `re_attempt_logs` has a `cancelled` entry for the cancelled request
- [ ] Forwarded request has `sent_to_admin_at` populated

**UI Verification:**
- [ ] CSRF token is present in both the Cancel form (`DELETE`) and Send-to-Admin form (`POST`)
- [ ] Cancel button uses HTTP method spoofing `@method('DELETE')` inside a form
- [ ] Send-to-Admin button uses `POST` method
- [ ] Form actions use named routes (`route('teacher.reattempts.cancel', $reattempt)` and `route('teacher.reattempts.send_to_admin', $reattempt)`)
- [ ] No JavaScript errors in browser console

**Security Verification:**
- [ ] A teacher cannot cancel another teacher's request (controller enforces `teacher_id` check)
- [ ] Attempting to cancel a non-pending request returns an error (controller enforces status check)
- [ ] `ReAttemptService.php` is byte-identical to pre-implementation state
- [ ] `Admin\ReAttemptController.php` is byte-identical to pre-implementation state
- [ ] `Teacher\ExamController.php` is byte-identical to pre-implementation state

**Integration Verification:**
- [ ] `/admin/reattempts` correctly shows forwarded requests
- [ ] `/student/reattempts` is unaffected
- [ ] `/admin/dashboard` loads correctly
- [ ] Re-attempt approval flow (admin approves → student notified) is unaffected

---

## 13. Rollback Plan

### 13.1 General Rollback Principle

All four modifications are confined to Blade view files and a single PHP configuration file. There are no database migrations. Rollback for any phase is simply reverting the affected file(s) to their pre-implementation state.

Since these are version-controlled files (`.git` is present in the project root), the rollback method is:
1. Identify the file(s) changed in the failing phase
2. Revert only those files using `git checkout -- <file>`
3. Confirm the application is back to the pre-modification state

---

### Phase 1 Rollback — Scheduler Registration (FM-04)

**Rollback Trigger:**
- `php artisan` commands fail after modification of `Kernel.php`
- The scheduler kills the running `email:process-scheduled` entry
- Any PHP parse error in `Kernel.php`

**Rollback Method:**
- Revert `app/Console/Kernel.php` to its pre-modification state
- Command: `git checkout -- app/Console/Kernel.php`

**Recovery Validation:**
- `php artisan schedule:list` shows only `email:process-scheduled` (no `results:mark-absent`)
- `php artisan` commands respond normally
- The web application loads without errors

**Notes:**
- Reverting Phase 1 does not affect the web application at all — only the scheduler daemon is affected.
- If the scheduler daemon is running, it will pick up the reverted `Kernel.php` on the next invocation cycle.

---

### Phase 2 Rollback — Security Settings UI (FM-01)

**Rollback Trigger:**
- HTTP 500 on `/admin/security-settings`
- Form submission causes a 500 or validation error not expected
- `SecuritySetting::policy()` returns a different shape than expected
- Any Blade compile error in the view files

**Rollback Method:**
- Revert `resources/views/admin/security-settings/index.blade.php`
- Revert `resources/views/admin/security-settings/_toggle.blade.php` if it was changed
- Commands:
  ```
  git checkout -- resources/views/admin/security-settings/index.blade.php
  git checkout -- resources/views/admin/security-settings/_toggle.blade.php
  ```

**Recovery Validation:**
- `/admin/security-settings` loads without errors (may be in previous incomplete UI state)
- `SecuritySetting::policy()` still returns the correct 10-key array
- Exam take page is unaffected (was never touched)
- No data loss — `security_settings` table retains whatever values were last saved

**Notes:**
- If a bad form submission saved incorrect values to `security_settings`, use the previously-working UI or `php artisan tinker` to call `SecuritySetting::set('key', value)` for any corrupted key.

---

### Phase 3 Rollback — Result Status Badges (FM-02)

**Rollback Trigger:**
- HTTP 500 on any of the 6 result views
- Blade exception due to missing null check on `exam_result_status`
- Unexpected layout breakage breaking a student or teacher workflow

**Rollback Method:**
- Revert the affected view file(s):
  ```
  git checkout -- resources/views/admin/results/index.blade.php
  git checkout -- resources/views/admin/results/student.blade.php
  git checkout -- resources/views/teacher/exams/results.blade.php
  git checkout -- resources/views/teacher/results/index.blade.php
  git checkout -- resources/views/student/results/index.blade.php
  git checkout -- resources/views/student/exams/show.blade.php
  ```
- Only revert the specific file that caused the issue if others are working correctly

**Recovery Validation:**
- All 6 result views load without errors
- No status badges displayed (reverted to pre-modification state)
- No data was changed — result records are read-only from views

---

### Phase 4 Rollback — Teacher Re-Attempt UI (FM-03)

**Rollback Trigger:**
- HTTP 500 on `/teacher/reattempts`
- Form submission (cancel or send-to-admin) returns HTTP 405 or 404 (wrong method or route)
- A teacher can cancel another teacher's request (authorization bypass — revert immediately)
- Any unintended deletion of re-attempt records

**Rollback Method:**
- Revert `resources/views/teacher/reattempts/index.blade.php`:
  ```
  git checkout -- resources/views/teacher/reattempts/index.blade.php
  ```

**Recovery Validation:**
- `/teacher/reattempts` loads without Cancel or Send-to-Admin buttons
- Any accidentally cancelled requests must be investigated in the database; if deleted unintentionally, they must be restored from backup
- Admin re-attempt list is unaffected

**Important:** If a re-attempt request was accidentally deleted during testing (e.g., Cancel was incorrectly triggered for a non-pending request), the data loss must be assessed. Since `re_attempt_requests` has no soft delete, a deleted record must be restored from a database backup if it was a production record.

---

## 14. Testing Plan

### 14.1 Unit Testing

Unit tests verify individual methods in isolation. For this implementation, the backend methods used by the four modifications are already implemented and stable. Unit tests must confirm they have not been changed.

| Method | Test Focus | Expected Result |
|---|---|---|
| `SecuritySetting::policy()` | Returns array with exactly 10 keys | Keys unchanged; types boolean/integer correct |
| `SecuritySetting::get('max_warnings', 3)` | Returns integer from DB; falls back to default | Returns DB value or 3 if not set |
| `SecuritySetting::set('max_warnings', 5)` | Writes value; clears cache for that key | DB row updated; subsequent `get()` returns 5 |
| `Result::statusLabel()` | Returns string for each status value | PASSED→"Passed", FAILED→"Failed", ABSENT→"Absent", DISQUALIFIED→"Disqualified", null→"—" |
| `Result::statusBadgeClass()` | Returns Bootstrap class for each status | PASSED→"bg-success", FAILED→"bg-danger", ABSENT→"bg-secondary", DISQUALIFIED→"bg-warning text-dark", null→"bg-light text-dark" |
| `MarkAbsentResults::handle()` with `--dry-run` | No records written; exits code 0 | Output shows would-create count; zero DB writes |
| `ReAttemptRequest::isPending()` | Returns true only when status=pending | true for pending; false for approved/rejected |

---

### 14.2 Integration Testing

Integration tests verify that multiple components work correctly together.

| Scenario | Components | Expected Outcome |
|---|---|---|
| Save security setting via form → read back via policy() | `SecuritySettingsController`, `SecuritySetting`, `security_settings` table | Saved value equals read-back value |
| Save security setting → load exam take page | `SecuritySetting::policy()`, `take.blade.php`, `data-policy-*` | Correct attribute value on `#examBody` |
| Admin submits security settings form with all 10 fields | `SecuritySettingsController::update()`, validation | All 10 keys saved; 200 redirect with success message |
| Result view renders with PASSED/FAILED/ABSENT/DISQUALIFIED records | Result views, `Result::statusLabel()`, `Result::statusBadgeClass()` | Correct badge per row |
| Result view renders with null `exam_result_status` | Result views, `Result::statusLabel()` | Renders "—" without exception |
| Teacher cancels own pending request | `Teacher\ExamController::reattemptCancel()`, `re_attempt_requests`, `re_attempt_logs` | Request deleted; log entry created |
| Teacher tries to cancel another teacher's request | `Teacher\ExamController::reattemptCancel()` | HTTP 403 |
| Teacher forwards student request to admin | `Teacher\ExamController::reattemptSendToAdmin()`, `ReAttemptService::sendToAdmin()` | `sent_to_admin_at` set; admin notified |
| Scheduler runs `results:mark-absent` on ended exam | `MarkAbsentResults`, `results` table | ABSENT records created; no duplicates on re-run |

---

### 14.3 Manual Testing

Manual testing covers the UI interactions that cannot be automated without a full browser test suite.

| Test | Steps | Pass Condition |
|---|---|---|
| Security settings all toggles | Log in as admin → `/admin/security-settings` → toggle all flags → save → refresh | All values persist; no errors |
| Security setting affects exam | Disable one flag → start exam → trigger that violation → confirm no violation counted | Violation silently ignored |
| Result badge visual check | View results page with all 4 status types present | Correct badge colour and label per row |
| Result badge null check | View results page with a row where `exam_result_status` is null | Displays "—" badge, no crash |
| Teacher cancel button state | Teacher views re-attempt with pending request → Cancel button visible | Button present for pending; absent for others |
| Teacher cancel action | Click Cancel on a pending request → confirm | Request gone from list; success flash |
| Teacher send-to-admin | Click Send-to-Admin on unforwarded pending request → log in as admin → check reattempts | Request visible in admin list |
| Teacher re-attempt idempotency | Click Send-to-Admin on already-forwarded request | No-op; page reloads without error |
| Absent marking dry run | Run `php artisan results:mark-absent --dry-run` | Outputs would-create count; zero DB writes |

---

### 14.4 Browser Testing

| Scenario | Browsers | Expected Outcome |
|---|---|---|
| Security settings page renders | Chrome, Firefox, Edge | Page loads; all toggles visible |
| Result badge colours | Chrome, Firefox, Edge | Bootstrap badge colours display correctly |
| Teacher re-attempt form actions | Chrome, Firefox, Edge | Cancel (DELETE) and Send-to-Admin (POST) submit correctly with CSRF |
| Exam take page unaffected | Chrome, Firefox, Edge | Timer runs; violations report; no regression |

---

### 14.5 Security Testing

| Test | Expected Outcome |
|---|---|
| Student accesses `/admin/security-settings` | HTTP 403 |
| Teacher accesses `/admin/security-settings` | HTTP 403 |
| Student accesses `/admin/results` | HTTP 403 |
| Student cancels teacher's re-attempt | HTTP 403 or redirect to login |
| Teacher cancels another teacher's re-attempt | HTTP 403 (controller checks `teacher_id`) |
| CSRF missing on Cancel form | HTTP 419 |
| CSRF missing on Send-to-Admin form | HTTP 419 |
| `SecuritySetting::policy()` after FM-01 | Returns identical keys to pre-modification (confirm no key renamed) |

---

### 14.6 Regression Testing

The regression test suite covers the entire system, not just the modified areas. This suite must pass after Phase 4 is complete and before deployment.

| # | Test | Pass Condition |
|---|---|---|
| RT-01 | Login all 3 roles | Each role redirects to correct dashboard |
| RT-02 | Student starts exam | Attempt created; session token set; take page loads |
| RT-03 | Student saves MCQ answer | `student_answers` row created; 200 JSON response |
| RT-04 | Student saves fill-blank answer | `student_answers` row created after 800ms debounce |
| RT-05 | Student submits exam | Attempt status=submitted; result created; redirect to exam show |
| RT-06 | Timer visible and counting down | Timer element visible; decrements each second |
| RT-07 | Violation POST returns correct JSON | `{warning_count, terminated, locked, message}` all present |
| RT-08 | Tier 1 violation — no email | Warning box shown; no email queued; `warning_count=1` |
| RT-09 | Tier 2 violation — email queued | Email log record created with status=queued |
| RT-10 | Tier 3 violation — attempt locked | `status=terminated_pending_review`; result=DISQUALIFIED; lock overlay shown |
| RT-11 | Admin approves terminated attempt | `status=in_progress`; `expires_at` extended; student notified |
| RT-12 | Admin rejects terminated attempt | `status=rejected`; student notified |
| RT-13 | Exam publish notifies students | Email log entries created for all enrolled students |
| RT-14 | Re-attempt approval grants access | Student can start exam in approved window |
| RT-15 | Certificate verification — valid token | Certificate details displayed without login |
| RT-16 | `results:mark-absent` creates absent records | ABSENT result created for non-attending student |
| RT-17 | `results:mark-absent` idempotent | Second run creates no duplicates |
| RT-18 | Security settings change persists | Saved value equals read-back value |
| RT-19 | Result badges display correctly | All 4 status types show correct badge |
| RT-20 | Teacher re-attempt cancel | Pending request deleted; log entry created |

---

### 14.7 User Acceptance Testing

UAT confirms the modified features meet the expectations of each role. These tests are performed by the product owner or designated stakeholders.

| Scenario | Role | Pass Condition |
|---|---|---|
| Admin configures all security policy flags from UI | Admin | All 10 flags saved and reflected on next exam load |
| Admin sets `max_warnings` to 2 | Admin | Exam terminates after 2 violations (not 3) |
| Admin views results with all 4 statuses | Admin | PASSED/FAILED/ABSENT/DISQUALIFIED badges clearly visible |
| Teacher cancels a pending re-attempt request | Teacher | Request removed; no error |
| Teacher forwards a student request to admin | Teacher | Request appears in admin re-attempt list |
| Scheduler marks absent students automatically | Admin | After exam ends, ABSENT records appear without manual command |

---

## 15. Deployment Readiness Checklist

This checklist must be completed in full before the implementation is considered ready for production deployment. Every item must be confirmed by the implementer and reviewed by a second party.

### 15.1 Database Readiness
- [ ] No new migrations were written or run during implementation
- [ ] `security_settings` table contains all 10 required keys (query: `SELECT key FROM security_settings ORDER BY key`)
- [ ] `results.exam_result_status` column exists (query: `DESCRIBE results`)
- [ ] `re_attempt_requests.sent_to_admin_at` column exists (query: `DESCRIBE re_attempt_requests`)
- [ ] No existing data was modified or deleted during implementation

### 15.2 Source Code Review
- [ ] `app/Console/Kernel.php` — only the `schedule()` method was changed; `routeMiddleware` and `middlewareGroups` are unchanged
- [ ] `resources/views/admin/security-settings/index.blade.php` — only form toggle additions; no PHP logic added
- [ ] `resources/views/admin/security-settings/_toggle.blade.php` — structure only, no server-side logic change
- [ ] Six result view files — only badge column additions using `$result->statusLabel()` and `$result->statusBadgeClass()`; no data loading changes
- [ ] `resources/views/teacher/reattempts/index.blade.php` — only form button additions; correct route names used; CSRF tokens present
- [ ] **Zero changes to any service, controller, middleware, model, route, or JavaScript file**
- [ ] `git diff --name-only` shows exactly the expected files (no accidental changes)

### 15.3 Tests Passed
- [ ] All 20 regression tests (RT-01 through RT-20) pass
- [ ] All phase verification checklists (Phases 1–4) are complete
- [ ] All unit tests for `SecuritySetting::policy()`, `Result::statusLabel()`, `Result::statusBadgeClass()` pass
- [ ] All integration tests pass
- [ ] All security tests pass — no role bypass, no CSRF bypass
- [ ] UAT sign-off received from product owner

### 15.4 No Regression Confirmed
- [ ] The live exam take page loads and functions correctly (timer, answers, violations)
- [ ] A complete exam session can be started, answered, and submitted without error
- [ ] Violation recording still returns the correct JSON shape: `{warning_count, terminated, locked, message}`
- [ ] Tier 3 termination still locks the exam and sets DISQUALIFIED result
- [ ] Email queue worker processes emails without errors
- [ ] `php artisan schedule:list` shows both `email:process-scheduled` and `results:mark-absent`

### 15.5 Documentation Updated
- [ ] This `IMPLEMENTATION_PLAN.md` reflects the actual implementation (no spec drift)
- [ ] `ANALYSIS.md` updated if any architectural fact changed (it should not have)
- [ ] `REQUIREMENTS.md` is unchanged (no new requirements discovered)
- [ ] If any deviation from the plan was made, it is documented in a deviation log

### 15.6 Rollback Confirmed
- [ ] Rollback procedure tested in staging environment (git revert verified)
- [ ] All changed files are committed to version control before deployment
- [ ] A database backup was taken before deployment (even though no DB changes were made — for safety)
- [ ] The rollback commands are documented and accessible to the operations team

---

## 16. Risks

### Phase 1 Risks (FM-04 — Scheduler)

| Risk | Type | Probability | Impact | Mitigation |
|---|---|---|---|---|
| PHP parse error in `Kernel.php` | Technical | Low | Medium | Verify with `php artisan` before and after |
| Scheduler entry conflicts with `email:process-scheduled` | Functional | Very Low | Low | Both entries are independent commands |
| `results:mark-absent` runs and creates wrong ABSENT records | Functional | Very Low | High | Command is idempotent; run `--dry-run` first to confirm output; the command's eligibility logic is already verified |
| Scheduler daemon not restarted after deploy | Operational | Medium | Low | Document restart step in deployment instructions |

---

### Phase 2 Risks (FM-01 — Security Settings UI)

| Risk | Type | Probability | Impact | Mitigation |
|---|---|---|---|---|
| Blade syntax error causes 500 on security settings page | Technical | Low | Low | Isolated to admin settings page; no student impact |
| Form field `name` attribute does not match controller expectation | Functional | Low | Medium | Verify field names match `SecuritySettingsController::POLICY_RULES` before submitting |
| Saved value not reflected in `data-policy-*` on exam page | Security | Very Low | High | Explicit verification step: change a setting → load exam take page → inspect `#examBody` attributes |
| `SecuritySetting::policy()` key names accidentally changed | Security | Very Low | Critical | Phase 2 exit criteria explicitly checks `policy()` output shape |
| Toggle UI produces `is_1` instead of `1` as POST value | Functional | Low | Medium | Verify POST payload in DevTools Network tab during form submission |
| Cache not cleared after save causes stale policy on exam page | Security | Low | Medium | `SecuritySetting::set()` already calls `Cache::forget()` — verify by checking model code (not modifying it) |

---

### Phase 3 Risks (FM-02 — Result Status Badges)

| Risk | Type | Probability | Impact | Mitigation |
|---|---|---|---|---|
| Null `exam_result_status` causes PHP error | Technical | Medium | Medium | Must use `$result->statusLabel()` (handles null) not `$result->exam_result_status` directly |
| Badge column breaks table layout on narrow screens | UI | Low | Low | Responsive CSS from Bootstrap handles this; spot-check |
| Student result page shows result before schedule ends | Security | Very Low | High | This logic is in `Student\ExamController::show()` which is NOT modified; verify controller is unchanged |
| DISQUALIFIED badge shown as published to student without admin review | Security | Very Low | High | Result visibility controlled by `is_published = false` for DISQUALIFIED — view logic must not override this |
| Badge references `statusBadgeClass()` which returns null on a future unknown status | Technical | Very Low | Low | `statusBadgeClass()` has a default case returning `'bg-light text-dark'` |

---

### Phase 4 Risks (FM-03 — Teacher Re-Attempt UI)

| Risk | Type | Probability | Impact | Mitigation |
|---|---|---|---|---|
| Cancel button uses wrong HTTP method (GET instead of DELETE) | Technical | Medium | Medium | Blade form must use `@method('DELETE')`; verify route definition requires DELETE |
| Send-to-Admin form missing CSRF token | Security | Low | Medium | Laravel `@csrf` directive must be in the form |
| Cancel button visible for approved/rejected requests | Functional | Low | Low | Blade condition must check `$reattempt->isPending()` |
| Teacher can see other teachers' requests (data leak) | Security | Very Low | High | Confirm controller scope: `where('teacher_id', auth()->id())` — already implemented |
| Cancelled request was already forwarded to admin | Functional | Low | Medium | Controller blocks cancellation if not pending — this is already enforced |
| Accidental deletion of a non-pending request | Regression | Very Low | High | Controller enforces `isPending()` check; rollback restores view only — deleted DB record cannot be restored from view rollback alone |

---

### Cross-Phase Risks

| Risk | Type | Probability | Impact | Mitigation |
|---|---|---|---|---|
| A view file accidentally imports/includes a modified file during edit | Technical | Low | Medium | Do not use `@include` to pull in files outside the four modification targets |
| IDE auto-formatting changes unrelated lines in modified files | Technical | Medium | Low | Use targeted edits only; review `git diff` before committing |
| Wrong branch deployed to production | Operational | Medium | High | Verify branch name before deployment; use PR review |
| Exam sessions active during deployment | Operational | Medium | Medium | Deploy during off-peak hours; active sessions are view-independent except for `take.blade.php` which is NOT being changed |

---

## 17. Completion Criteria

Implementation is considered **complete** when every condition below is measurably satisfied. These criteria are derived directly from REQUIREMENTS.md acceptance criteria and the verification checklists in this document.

---

### CC-01 — Scheduler Registration (FM-04)

**Condition:** `php artisan schedule:list` output includes `results:mark-absent` with a scheduled frequency.

**Measurement:** Run the command; inspect output. Pass if the command is listed. Fail if absent.

**Secondary condition:** `email:process-scheduled` is still listed and unchanged.

---

### CC-02 — Security Settings UI (FM-01)

**Condition:** A logged-in admin can navigate to `/admin/security-settings`, see all 10 policy flag toggles and the `max_warnings` numeric input, change values, save the form, and have those values persist and be reflected on the next page load.

**Measurement:** Manual verification. Load page → verify 10 toggles present → change `max_warnings` to 5 → save → refresh → confirm `max_warnings` shows 5.

**Secondary condition:** `SecuritySetting::policy()` returns an array with exactly these 10 keys, unchanged in name: `max_warnings`, `auto_terminate_enabled`, `fullscreen_detection_enabled`, `blur_detection_enabled`, `tab_switch_detection_enabled`, `right_click_blocking_enabled`, `copy_detection_enabled`, `paste_detection_enabled`, `devtools_detection_enabled`, `keyboard_shortcut_detection_enabled`.

---

### CC-03 — Result Status Badges (FM-02)

**Condition:** All 6 result views render without errors and display a colour-coded status badge for each result row using the values PASSED (green), FAILED (red), ABSENT (grey), or DISQUALIFIED (yellow). A null status renders "—" without error.

**Measurement:** Manual inspection of each of the 6 views with test data covering all 4 status values.

**Views:** `/admin/results`, `/admin/results/student/{id}`, `/teacher/results`, `/teacher/exams/{id}/results`, `/student/results`, `/student/exams/{id}` (after schedule ends).

---

### CC-04 — Teacher Re-Attempt UI (FM-03)

**Condition 1:** The Cancel button appears on the teacher re-attempt index for pending requests and is absent for approved/rejected requests. Clicking Cancel deletes the request and shows a success message.

**Condition 2:** The Send-to-Admin button appears for pending requests where `sent_to_admin_at` is null. Clicking it sets `sent_to_admin_at` and makes the request visible in the admin re-attempt list.

**Measurement:** Manual verification with test data. Create a pending request → verify buttons → execute both actions → verify outcomes.

---

### CC-05 — Zero Regression

**Condition:** All 20 regression tests (RT-01 through RT-20) pass without modification to any service, controller, middleware, model, route file, or JavaScript file outside the planned scope.

**Measurement:** Execute regression test suite. All 20 items must have a documented pass result.

**Critical sub-condition:** `app/Services/ExamSecurityService.php`, `app/Services/GradingService.php`, `public/js/exam-anticheat.js`, and `resources/views/student/exam/take.blade.php` are byte-identical to their pre-implementation state.

---

### CC-06 — Deployment Readiness

**Condition:** All 6 sections of the Deployment Readiness Checklist (§15) are fully checked.

**Measurement:** Signed checklist with implementer name and review date.

---

### CC-07 — UAT Sign-Off

**Condition:** All 6 UAT scenarios in §14.7 have been executed by the product owner or designated stakeholder and passed.

**Measurement:** Written or documented sign-off on each scenario.

---

### Summary Table

| Criteria | Measurement Method | Pass / Fail |
|---|---|---|
| CC-01: Scheduler registered | `php artisan schedule:list` output | — |
| CC-02: Security settings UI complete | Manual form test; `policy()` key inspection | — |
| CC-03: Result badges in all 6 views | Manual view inspection with all 4 statuses | — |
| CC-04: Teacher re-attempt actions | Manual action test with test data | — |
| CC-05: Zero regression (20 tests) | Regression test execution | — |
| CC-06: Deployment checklist signed | Document sign-off | — |
| CC-07: UAT sign-off | Stakeholder sign-off | — |

**Implementation is complete only when all 7 criteria show PASS.**

---

*End of IMPLEMENTATION_PLAN.md*

---

> **Document Summary**
> This plan covers 4 modifications across 10 files (1 PHP config, 2 admin view files, 6 result view files, 1 teacher view file). No migrations, no service changes, no JavaScript changes, no API changes. Total blast radius is minimal. All four phases are independently verifiable and independently rollback-able. The zero-tolerance security boundary (ExamSecurityService, GradingService, exam-anticheat.js, take.blade.php, all middleware) must not be touched during any phase of this implementation.
