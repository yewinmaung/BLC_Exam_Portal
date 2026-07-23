# Database Cleanup Analysis Report

**Date**: July 8, 2026  
**Purpose**: Identify unused, duplicate, or obsolete tables that can be safely removed

---

## Executive Summary

**Total Tables Analyzed**: 35  
**Tables in Active Use**: 28  
**Tables Potentially Unused**: 7  
**Tables Already Removed**: 2 (via 2026_06_16_000001_drop_legacy_tables.php)

---

## Complete Table Inventory

### ✅ Core System Tables (Active - Do NOT Remove)

#### 1. users
- **Purpose**: User accounts (students, teachers, admins)
- **Model**: `User.php`
- **Used in**: All controllers, authentication, relationships
- **Status**: ✅ **KEEP - Critical**

#### 2. roles
- **Purpose**: Role-based access control
- **Model**: `Role.php`
- **Used in**: Authentication, middleware, user management
- **Status**: ✅ **KEEP - Critical**

#### 3. password_resets
- **Purpose**: Laravel password reset tokens
- **Model**: None (framework table)
- **Used in**: Laravel auth system
- **Status**: ✅ **KEEP - Critical**

#### 4. failed_jobs
- **Purpose**: Laravel queue failed job tracking
- **Model**: None (framework table)
- **Used in**: Laravel queue system
- **Status**: ✅ **KEEP - Critical**

#### 5. jobs
- **Purpose**: Laravel queue jobs
- **Model**: None (framework table)
- **Used in**: Email queue, background jobs
- **Status**: ✅ **KEEP - Critical**

#### 6. personal_access_tokens
- **Purpose**: API token authentication (Laravel Sanctum)
- **Model**: None (framework table)
- **Used in**: API authentication
- **Status**: ✅ **KEEP - Critical**

---

### ✅ Exam Core Tables (Active - Do NOT Remove)

#### 7. exams
- **Purpose**: Exam definitions
- **Model**: `Exam.php`
- **Used in**: All exam controllers, schedules, attempts
- **Status**: ✅ **KEEP - Critical**

#### 8. exam_schedules
- **Purpose**: Exam scheduling and availability windows
- **Model**: `ExamSchedule.php`
- **Used in**: Admin scheduling, student exam list
- **Status**: ✅ **KEEP - Critical**

#### 9. exam_attempts
- **Purpose**: Student exam session tracking
- **Model**: `ExamAttempt.php`
- **Used in**: Exam sessions, recovery, cheating detection, results
- **Columns**: 
  - Core: id, exam_id, student_id, attempt_number, status
  - Timing: started_at, submitted_at, expires_at
  - Security: warning_count, session_token, terminated_at
  - Approval: approved_by, approved_at, approval_comment
  - Rejection: rejected_by, rejected_at, rejection_comment
  - Recovery: disconnected_at, last_question_id (NEW)
  - Attendance: absent_marked_at (NEW)
- **Status**: ✅ **KEEP - Critical**

#### 10. questions
- **Purpose**: Exam questions
- **Model**: `Question.php`
- **Used in**: Exam builder, student exam view, grading
- **Status**: ✅ **KEEP - Critical**

#### 11. answers
- **Purpose**: Question answer options (for MCQ/True-False)
- **Model**: `Answer.php`
- **Used in**: Exam builder, student exam view, grading
- **Status**: ✅ **KEEP - Critical**

#### 12. student_answers
- **Purpose**: Student's submitted answers
- **Model**: `StudentAnswer.php`
- **Used in**: Exam sessions, grading, results
- **Status**: ✅ **KEEP - Critical**

#### 13. results
- **Purpose**: Graded exam results
- **Model**: `Result.php`
- **Used in**: Result viewing, GPA calculation, reports
- **Status**: ✅ **KEEP - Critical**

---

### ✅ Academic Structure Tables (Active - Do NOT Remove)

#### 14. courses
- **Purpose**: Course definitions
- **Model**: `Course.php`
- **Used in**: Enrollment, exam creation, navigation
- **Status**: ✅ **KEEP - Critical**

#### 15. majors
- **Purpose**: Student major/program (CT, CS, CST)
- **Model**: `Major.php`
- **Used in**: Course filtering, student records
- **Status**: ✅ **KEEP - Critical**

#### 16. enrollments
- **Purpose**: Student course enrollments
- **Model**: `Enrollment.php`
- **Used in**: Course access control, student course list
- **Status**: ✅ **KEEP - Critical**

#### 17. academic_years
- **Purpose**: Academic year definitions (2025-2026, etc.)
- **Model**: `AcademicYear.php`
- **Used in**: Student records, course scheduling
- **Status**: ✅ **KEEP - Critical**

#### 18. year_levels
- **Purpose**: Year level definitions (Year 1-5)
- **Model**: `YearLevel.php`
- **Used in**: Student classification, course filtering
- **Status**: ✅ **KEEP - Critical**

#### 19. student_year_records
- **Purpose**: Permanent per-student per-year academic record
- **Model**: `StudentYearRecord.php`
- **Used in**: Academic history, promotion tracking
- **Status**: ✅ **KEEP - Critical**

---

### ✅ Security & Recovery Tables (Active - Do NOT Remove)

#### 20. cheating_logs
- **Purpose**: Security violation audit trail
- **Model**: `CheatingLog.php`
- **Used in**: ExamSecurityService, admin cheating log view
- **Columns**: attempt_id, student_id, violation_type, details, warning_number, user_agent, browser, device, os, screen_resolution, timezone, ip_address
- **Status**: ✅ **KEEP - Critical**

#### 21. session_recovery_logs (NEW)
- **Purpose**: Temporary exam disconnect/recovery audit trail
- **Model**: `SessionRecoveryLog.php`
- **Used in**: SessionRecoveryService, admin evidence
- **Columns**: attempt_id, student_id, exam_id, disconnected_at, reconnected_at, disconnected_duration_seconds, disconnect_reason, last_question_id, browser_info, user_agent, ip_address, recovery_status
- **Status**: ✅ **KEEP - Critical**

---

### ✅ Re-Attempt System Tables (Active - Do NOT Remove)

#### 22. re_attempt_requests
- **Purpose**: Student requests for exam re-attempts
- **Model**: `ReAttemptRequest.php`
- **Used in**: Student re-attempt flow, teacher/admin approval
- **Status**: ✅ **KEEP - Critical**

#### 23. re_attempt_logs
- **Purpose**: Re-attempt decision history
- **Model**: `ReAttemptLog.php`
- **Used in**: Re-attempt audit trail
- **Status**: ✅ **KEEP - Critical**

---

### ✅ Email System Tables (Active - Do NOT Remove)

#### 24. email_templates
- **Purpose**: Email template definitions
- **Model**: `EmailTemplate.php`
- **Used in**: Email system, template management
- **Status**: ✅ **KEEP - Critical**

#### 25. email_logs
- **Purpose**: Sent email tracking and delivery status
- **Model**: `EmailLog.php`
- **Used in**: Email system, admin email logs
- **Status**: ✅ **KEEP - Critical**

#### 26. scheduled_emails
- **Purpose**: Scheduled email queue (send later)
- **Model**: `ScheduledEmail.php`
- **Used in**: Email system, scheduled sending
- **Status**: ✅ **KEEP - Critical**

---

### ✅ Communication Tables (Active - Do NOT Remove)

#### 27. user_notifications
- **Purpose**: In-app notification system
- **Model**: `UserNotification.php`
- **Used in**: Notification system, dashboard
- **Status**: ✅ **KEEP - Critical**

#### 28. chat_messages
- **Purpose**: Internal messaging between users
- **Model**: `ChatMessage.php`
- **Used in**: Chat system
- **Status**: ✅ **KEEP - Critical**

#### 29. activity_logs
- **Purpose**: System activity audit trail
- **Model**: `ActivityLog.php`
- **Used in**: Activity tracking, admin audit logs
- **Status**: ✅ **KEEP - Critical**

---

### ⚠️ Questionable Tables (Needs Investigation)

#### 30. question_categories
- **Purpose**: Question categorization/tagging
- **Model**: `QuestionCategory.php`
- **Created in**: 2024_05_25_000001_create_examination_system_tables.php
- **Current Usage**: 
  - ❓ No references in controllers
  - ❓ No references in services
  - ❓ Questions table has `category_id` foreign key
- **Investigation Needed**:
  ```bash
  grep -r "QuestionCategory" app/
  grep -r "question_categories" app/
  grep -r "category_id" app/Http/Controllers/
  ```
- **Recommendation**: 🔍 **INVESTIGATE FIRST**
  - Check if `questions.category_id` is ever set
  - Check if admin UI uses categories
  - If unused, consider removal

---

#### 31. yearly_exam_results
- **Purpose**: Aggregated yearly exam results archive
- **Model**: None found
- **Created in**: 2026_06_01_000001_create_academic_system_tables.php
- **Current Usage**:
  - ❓ No model file exists
  - ❓ No references in controllers
  - ❓ Purpose: "Aggregated yearly exam results (permanent archive)"
- **Investigation Needed**:
  ```bash
  grep -r "yearly_exam_results" app/
  grep -r "YearlyExamResult" app/
  ```
- **Recommendation**: 🔍 **INVESTIGATE FIRST**
  - Was this intended for future use?
  - Is there a seeder or admin feature planned?
  - If truly unused and no data, consider removal

---

#### 32. promotion_histories
- **Purpose**: Student promotion/graduation tracking
- **Model**: None found
- **Created in**: 2026_06_01_000001_create_academic_system_tables.php
- **Current Usage**:
  - ❓ No model file exists
  - ❓ No references in controllers
  - ❓ Purpose: "Promotion history (never deleted)"
- **Investigation Needed**:
  ```bash
  grep -r "promotion_histories" app/
  grep -r "PromotionHistory" app/
  ```
- **Recommendation**: 🔍 **INVESTIGATE FIRST**
  - Is this for future academic year transition?
  - Check if student year-end promotion is implemented
  - If not implemented, consider removal or keep for future

---

#### 33. certificate_logs
- **Purpose**: Certificate serial number tracking
- **Model**: None found
- **Created in**: 2026_06_01_000001_create_academic_system_tables.php
- **Current Usage**:
  - ❓ No model file exists
  - ❓ No references in controllers
  - ❓ Purpose: "Certificate log (serial numbers, permanent)"
- **Investigation Needed**:
  ```bash
  grep -r "certificate_logs" app/
  grep -r "CertificateLog" app/
  ```
- **Recommendation**: 🔍 **INVESTIGATE FIRST**
  - Is certificate generation implemented?
  - Check for PDF generation features
  - If not implemented, keep for future or remove if not planned

---

#### 34. yearly_transcripts
- **Purpose**: Yearly student transcripts
- **Model**: None found
- **Created in**: 2026_06_03_132542_create_missing_upgrade_tables.php
- **Current Usage**:
  - ❓ No model file exists
  - ❓ No references in controllers
  - ❓ Purpose: "Yearly transcript records"
- **Investigation Needed**:
  ```bash
  grep -r "yearly_transcripts" app/
  grep -r "YearlyTranscript" app/
  ```
- **Recommendation**: 🔍 **INVESTIGATE FIRST**
  - Potential duplicate of `student_year_records`?
  - Check if this is redundant
  - Consider consolidation or removal

---

### ✅ Already Removed (Previous Cleanup)

#### ~~35. attempt_reset_requests~~ (REMOVED)
- **Status**: ❌ Deleted by 2026_06_16_000001_drop_legacy_tables.php
- **Reason**: Superseded by `re_attempt_requests`
- **Confirmed**: DB empty, zero callers

#### ~~36. exam_import_logs~~ (REMOVED)
- **Status**: ❌ Deleted by 2026_06_16_000001_drop_legacy_tables.php
- **Reason**: Model has zero callers
- **Confirmed**: DB empty, no references

---

## Investigation Commands

Run these commands to investigate questionable tables:

```bash
# Question Categories
php artisan tinker
>>> \App\Models\Question::whereNotNull('category_id')->count()
>>> \App\Models\QuestionCategory::count()

grep -r "QuestionCategory" app/Http/Controllers/
grep -r "question_categories" resources/views/
grep -r "category_id" app/Http/Controllers/Teacher/ExamController.php

# Yearly Exam Results
grep -r "yearly_exam_results" app/
grep -r "YearlyExamResult" app/

# Promotion Histories  
grep -r "promotion_histories" app/
grep -r "PromotionHistory" app/

# Certificate Logs
grep -r "certificate_logs" app/
grep -r "CertificateLog" app/

# Yearly Transcripts
grep -r "yearly_transcripts" app/
grep -r "YearlyTranscript" app/
```

---

## Cleanup Recommendations

### Phase 1: Investigation (Required Before Removal)

For each questionable table:

1. **Check database contents**:
   ```sql
   SELECT COUNT(*) FROM question_categories;
   SELECT COUNT(*) FROM yearly_exam_results;
   SELECT COUNT(*) FROM promotion_histories;
   SELECT COUNT(*) FROM certificate_logs;
   SELECT COUNT(*) FROM yearly_transcripts;
   ```

2. **Check code references**:
   - Search for model references
   - Search for table name in migrations
   - Search for table name in seeders
   - Search for table name in controllers/services

3. **Check admin UI**:
   - Look for any admin pages using these tables
   - Check navigation menus
   - Check routes

### Phase 2: Safe Removal (Only if confirmed unused)

If a table is confirmed unused:

1. **Verify zero data**:
   ```sql
   SELECT COUNT(*) FROM table_name;
   -- Must be 0 before removal
   ```

2. **Verify zero code references**:
   ```bash
   grep -r "TableName" app/ --exclude-dir=vendor
   grep -r "table_name" app/ --exclude-dir=vendor
   # Must return no critical matches
   ```

3. **Create removal migration**:
   ```php
   php artisan make:migration drop_unused_table_name
   ```

4. **Include rollback**:
   ```php
   public function down() {
       // Recreate table structure for safety
       Schema::create('table_name', function (Blueprint $table) {
           // Original structure
       });
   }
   ```

---

## Detailed Analysis Results

### QuestionCategory Investigation

**Files to check**:
- [ ] `app/Models/QuestionCategory.php` - Does model exist?
- [ ] `app/Http/Controllers/Teacher/ExamController.php` - Question creation
- [ ] `resources/views/teacher/exams/questions/*.blade.php` - Question forms
- [ ] Database: Count records in `question_categories`
- [ ] Database: Count questions with `category_id IS NOT NULL`

**Decision Criteria**:
- If no records AND no UI references AND no controller usage → **Safe to remove**
- If records exist OR UI references exist → **KEEP**

---

### yearly_exam_results Investigation

**Purpose Check**:
- Intended for year-end academic reports?
- Duplicate of `results` table with aggregation?
- Future feature not yet implemented?

**Files to check**:
- [ ] Search entire codebase for `yearly_exam_results`
- [ ] Check if `student_year_records` serves same purpose
- [ ] Database: Count records

**Decision Criteria**:
- If no model, no references, no data, no clear future use → **Safe to remove**
- If future feature planned → **KEEP but add TODO comments**

---

### promotion_histories Investigation

**Purpose Check**:
- Student year-end promotion tracking
- Graduation ceremony data
- Academic progression logging

**Files to check**:
- [ ] Search for promotion/graduation features
- [ ] Check admin year-end workflows
- [ ] Database: Count records

**Decision Criteria**:
- If no implementation and not planned → **Safe to remove**
- If year-end promotion exists → **KEEP**

---

### certificate_logs Investigation

**Purpose Check**:
- Certificate PDF generation
- Serial number tracking for official documents
- Graduation certificates

**Files to check**:
- [ ] Search for PDF generation code
- [ ] Search for certificate download features
- [ ] Database: Count records

**Decision Criteria**:
- If no certificate generation → **Safe to remove**
- If certificates implemented → **KEEP**

---

### yearly_transcripts Investigation

**Purpose Check**:
- Official transcript generation
- Potential duplicate of `student_year_records`

**Files to check**:
- [ ] Compare with `student_year_records` table structure
- [ ] Check if these serve same purpose
- [ ] Database: Count records in both tables

**Decision Criteria**:
- If duplicate of `student_year_records` → **Safe to remove**
- If serves different purpose → **KEEP**

---

## Summary Table

| Table | Status | Action Required | Priority |
|-------|--------|-----------------|----------|
| question_categories | 🔍 Investigate | Check usage in exam builder | Medium |
| yearly_exam_results | 🔍 Investigate | Check if duplicate/unused | Low |
| promotion_histories | 🔍 Investigate | Check if feature exists | Low |
| certificate_logs | 🔍 Investigate | Check if certificates exist | Low |
| yearly_transcripts | 🔍 Investigate | Check if duplicate | Medium |

---

## Risk Assessment

### High Risk (Do NOT Remove Without Thorough Testing)
- Any table with Model file
- Any table with foreign key references
- Any table with data
- Any table referenced in active code

### Medium Risk (Investigate Carefully)
- Tables without Model but with foreign keys
- Tables mentioned in migrations but no active code
- Tables that might be future features

### Low Risk (Safe to Remove if Confirmed Unused)
- No Model file
- No code references
- No database records
- No foreign key references
- Clear indication of being superseded

---

## Next Steps

1. ✅ **Run investigation commands** for each questionable table
2. ✅ **Check database record counts**
3. ✅ **Search codebase for references**
4. ❌ **Do NOT delete anything yet**
5. ✅ **Document findings**
6. ✅ **Get confirmation** before proceeding with removals
7. ✅ **Create migration** only after approval
8. ✅ **Test thoroughly** after removal

---

## Conclusion

**Safe to Remove Immediately**: 0 tables  
**Requires Investigation**: 5 tables  
**Definitely Keep**: 29 tables  
**Already Removed**: 2 tables

**Recommendation**: 
Complete investigation phase before removing any tables. All questionable tables require:
1. Database record count check
2. Code reference search
3. Feature implementation verification
4. Stakeholder confirmation

---

**Date**: July 8, 2026  
**Analyst**: Database Cleanup Review  
**Status**: Investigation Phase - No Deletions Yet
