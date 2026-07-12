SECURITY RESPONSE POLICY

The system must follow this exact violation policy.

First Violation

Actions:

Record the violation in the database.
Display Warning 1/2 to the student.
No email notification.
No dashboard notification.
Student continues the exam.
Second Violation

Actions:

Record the violation.
Display Warning 2/2.
Send a standard email notification to:
System Administrator(s)
The Teacher responsible for the current subject/course.
Create a normal dashboard notification for Admin and the responsible Teacher.
Student is allowed to continue the exam.
Third Violation

Actions:

Record the violation.
Immediately terminate the exam session.
Stop the exam timer.
Disable all answer submission.
Lock the exam session.
Change exam status to:
terminated_pending_review

The student must not be able to continue until manually approved.

High Priority Notification (Third Violation)

Immediately send a HIGH PRIORITY security alert email.

Recipients:

System Administrator(s)
The Teacher responsible for this subject/course only.

Email Subject:

🚨 HIGH PRIORITY - Student Exam Terminated Due To Security Violations

Email Body must include:

Student Name
Student ID
Exam Name
Subject
Course
Department
Exam Session ID
Total Violations
Violation Timeline
Violation Types
Browser Information
Device Information
Operating System
Public IP Address
Login IP Address
Date & Time
Exam Status (Terminated)
Action Required

The email should clearly state that the exam has been automatically terminated and requires manual review.

Dashboard Notification

Immediately create a HIGH PRIORITY dashboard notification.

Admin Dashboard:

Red notification badge.
New security incident card.
Student highlighted as "Terminated".
Require review.

Teacher Dashboard:

Show only incidents related to the teacher's own subject/course.
Display:
Student
Subject
Exam
Time
Violation Count
Status

Provide two actions:

Approve Student
Reject Student
Audit Trail

Every security event must be logged.

Log:

Warning 1 issued
Warning 2 issued
Email sent
Dashboard notification created
Exam terminated
Teacher approved
Teacher rejected
Admin approved
Admin rejected

Include:

Timestamp
User
Exam Session
IP Address
Browser
Device
Action
Status

Audit logs must never be editable.