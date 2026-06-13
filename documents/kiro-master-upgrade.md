# UNIVERSITY ERP MASTER UPGRADE SPECIFICATION

==================================================
🔥 PROJECT MODE
===============

THIS IS AN EXISTING PROJECT.

DO NOT CREATE A NEW PROJECT.

An existing Laravel University ERP System already exists.

Already implemented:

* Authentication
* RBAC
* Admin Panel
* Teacher Panel
* Student Panel
* Course Management
* Teacher Course Assignment
* Student Management
* Enrollment System
* Exam System
* Dashboard UI
* Notifications
* Existing Database Tables

==================================================
🔥 CRITICAL SAFETY RULES
========================

1. Do NOT rebuild existing modules.
2. Do NOT delete existing code.
3. Do NOT remove existing routes.
4. Do NOT rename existing tables.
5. Do NOT rename existing columns.
6. Do NOT drop tables.
7. Do NOT overwrite working modules.
8. Preserve all existing data.
9. Maintain backward compatibility.
10. Extend existing functionality only.
11. Reuse existing architecture.
12. Generate upgrade migrations only when required.

==================================================
🔥 ANALYSIS REQUIREMENT
=======================

Before coding:

Analyze:

* Existing Models
* Existing Controllers
* Existing Services
* Existing Repositories
* Existing Tables
* Existing Relationships
* Existing RBAC Structure
* Existing Exam System
* Existing Student System

Reuse all existing implementations whenever possible.

==================================================
🔥 NEW FEATURES TO INTEGRATE
============================

1. Yearly Academic Record System
2. Student Promotion System
3. Historical Result Archive
4. Transcript Export System
5. Certificate Generation System
6. Re-Attempt Request System
7. Moodle-Style Exam Import System
8. Year-Level Course Assignment
9. Year-Level Exam Assignment
10. Academic Record Request System

==================================================
🎓 YEARLY ACADEMIC RECORD SYSTEM
================================

Students belong to:

* Academic Year
* Year Level
* Semester
* Department
* Major

Year Levels:

* First Year
* Second Year
* Third Year
* Final Year

Admin assigns:

* Courses by Year Level
* Exams by Year Level

Students can access only:

* Assigned Courses
* Assigned Exams
* Assigned Materials

==================================================
🔄 STUDENT PROMOTION SYSTEM
===========================

At end of academic year:

Admin can:

* Review results
* Promote students
* Archive records

Examples:

First Year → Second Year
Second Year → Third Year
Third Year → Final Year

Old records must NEVER be deleted.

==================================================
📚 HISTORICAL ACADEMIC RECORDS
==============================

Store permanently:

* GPA
* Passed Subjects
* Failed Subjects
* Attendance
* Exam Results
* Transcript History

Student academic history must remain searchable forever.

==================================================
📜 TRANSCRIPT SYSTEM
====================

Generate:

* PDF Transcript
* Excel Transcript
* Academic Reports

Export by:

* Academic Year
* Semester
* Year Level

==================================================
🏆 CERTIFICATE SYSTEM
=====================

Generate:

* Transcript Certificate
* Promotion Certificate
* Completion Certificate
* Achievement Certificate

Certificate must include:

* University Logo
* Student Name
* Department
* Major
* Academic Year
* GPA
* Serial Number
* Current Date
* Current Time
* Issued By
* Signature Area
* QR Verification Code

==================================================
📌 CERTIFICATE SERIAL NUMBER
============================

Format:

CERT-2026-0001
CERT-2026-0002

Store in certificate_logs.

==================================================
🔄 RE-ATTEMPT REQUEST SYSTEM
============================

Teacher can:

* Select Student
* Select Exam
* Submit Re-Attempt Request
* Add Reason

Admin can:

* Approve
* Reject
* Add Remarks
* Set Re-Attempt Schedule

Rules:

Pending:

* No Exam Access

Rejected:

* No Exam Access

Approved:

* Create New Exam Session
* Increase Allowed Attempts
* Enable Exam Access

Prevent duplicate pending requests.

Integrate with:

* exams
* exam_sessions
* exam_results
* exam_violations

==================================================
📂 MOODLE-STYLE EXAM IMPORT SYSTEM
==================================

Teacher uploads:

* PDF
* DOC
* DOCX

Files contain:

* Questions
* Answers
* Marks

System must:

* Read file
* Extract text
* Parse questions
* Detect question type
* Detect answers
* Generate question bank
* Create answerable online exam

Supported:

* MCQ
* True/False
* Fill in the Blank

Admin:

* Review imported exam
* Attach schedule
* Enable/Disable exam

questions and Correct answers must be encrypted.

==================================================
📊 REQUIRED TABLES
==================

Only create if not already existing:

* academic_years
* year_levels
* student_year_records
* yearly_exam_results
* yearly_transcripts
* promotion_histories
* certificate_logs
* re_attempt_requests
* re_attempt_logs
* exam_import_logs

Reuse existing exam tables whenever possible.

==================================================
🏗 ARCHITECTURE
===============

Controller
→ Service
→ Repository
→ Model

Use:

* Form Requests
* Policies
* Middleware
* Events
* Listeners
* Notifications
* Queues
* Activity Logs

==================================================
🚀 IMPLEMENTATION RULE
======================

After analysis:

DO NOT STOP.

DO NOT ASK FOR APPROVAL.

Automatically continue implementation.

Generate production-ready Laravel code only.

Continue until all features are integrated into the existing project.
