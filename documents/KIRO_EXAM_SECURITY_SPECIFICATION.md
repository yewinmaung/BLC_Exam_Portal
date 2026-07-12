# KIRO_EXAM_SECURITY_SPECIFICATION
## Goal

Enhance the existing Exam Management System without breaking existing
functionality.

## Non-Negotiable Rules

-   Do NOT rewrite existing modules.
-   Do NOT modify exam timer, grading, question flow, or submission
    workflow.
-   Extend only.
-   Follow existing architecture and coding style.
-   Use SOLID principles.
-   Backward compatible.

## Security Detection

-   Browser tab switch (visibilitychange)
-   Window blur
-   Fullscreen exit (optional)
-   Copy/Paste (optional)
-   Right click (optional)
-   DevTools detection (best effort)

## Violation Policy

### First Violation

-   Record violation.
-   Show Warning 1/2.
-   No email.
-   No dashboard notification.
-   Student continues.

### Second Violation

-   Record violation.
-   Show Warning 2/2.
-   Send standard email to:
    -   Administrator(s)
    -   Responsible subject teacher only.
-   Create normal dashboard notification.
-   Student continues.

### Third Violation

-   Record violation.
-   Stop timer.
-   Disable answering.
-   Lock exam session.
-   Set status = terminated_pending_review.
-   Student cannot continue until approved.

## High Priority Notification

On the third violation: - Send HIGH PRIORITY email to: -
Administrator(s) - Responsible subject teacher only. - Create HIGH
PRIORITY dashboard notification.

Email includes: - Student Name - Student ID - Course - Subject -
Department - Exam Name - Exam Session ID - Violation Count - Violation
Timeline - Violation Types - Browser - Device - Operating System -
Public IP - Login IP - Date & Time - Exam Status - Action Required

## Dashboard

### Admin

-   View all incidents
-   Red badge
-   Security incident cards
-   Approve
-   Reject

### Teacher

-   Only own subjects
-   Approve
-   Reject
-   View history

## Audit Trail

Log: - Warning 1 - Warning 2 - Email sent - Dashboard notification -
Exam terminated - Teacher approval/rejection - Admin approval/rejection

Include: - Timestamp - User - Exam Session - Browser - Device - IP -
Action - Status

Logs must be immutable.

## Email & Queue

-   Queue all emails.
-   Retry failed jobs.
-   Never delay the student's exam.

## Notifications

-   Use Laravel Database Notifications.
-   If broadcasting exists, also send real-time notifications.

## Database

Extend exam_sessions: - violation_count - status - terminated_at -
approved_by - approved_at

Create: - exam_violations - exam_security_logs

## Backend

Create: - POST /exam/violation

Protect exam endpoints: - Reject requests if status != active.

## Final Verification

-   Existing features unchanged.
-   Existing timer unchanged.
-   Existing grading unchanged.
-   Existing submission unchanged.
-   Anti-cheat works independently.
-   Emails queued.
-   Notifications working.
-   Audit logs complete.
-   Production ready.
