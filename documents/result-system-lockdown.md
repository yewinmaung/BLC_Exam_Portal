# RESULT SYSTEM LOCKDOWN TASK

## CONTEXT
This project already has a partially completed Exam Result System.

The system currently supports:
- Admin viewing all student results
- Teacher viewing all student results
- Student viewing only their own results
- Yearly / Semester / Academic Year structure
- Result storage using YearlyExamResult + Result models

⚠️ The system is 80–90% complete but slightly fragile due to previous AI-driven modifications.

---

## OBJECTIVE
DO NOT add new features.

DO NOT refactor architecture.

DO NOT modify services or database schema.

ONLY stabilize and verify correctness of existing Result System.

---

## STRICT RULES (NON-NEGOTIABLE)

❌ Do NOT:
- Modify database structure
- Rewrite controllers or services
- Change ReAttempt system
- Touch AcademicService / TranscriptService / CertificateService
- Introduce new logic or redesign queries
- Add new features

✔️ Allowed:
- Fix ONLY broken or unsafe result access control
- Fix ONLY incorrect or unsafe queries
- Fix ONLY UI-level issues (routes, links, buttons)
- Improve data safety (no cross-user leakage)

---

## TASKS

### 1. ACCESS CONTROL VERIFICATION

Ensure:

- Admin can see ALL student results
- Teacher can see ALL student results
- Student can ONLY see OWN results

Check:
- Controllers
- Policies (if any)
- Query filters
- Relationships (Result, ExamAttempt, YearlyExamResult)

⚠️ NO data leakage allowed.

---

### 2. RESULT FILTERING VALIDATION

Verify filtering works correctly for:

- academic_year_id
- semester
- year_level

Ensure queries are consistent across:
- Admin
- Teacher
- Student views

---

### 3. UI VALIDATION (SAFE FIX ONLY)

Fix ONLY if broken:
- Missing "View Result" buttons
- Missing "Transcript" links
- Wrong or broken routes
- UI displaying incorrect data

DO NOT change backend logic.

---

### 4. DATA INTEGRITY CHECK

Ensure:
- ExamAttempt relationships are not breaking result queries
- YearlyExamResult is correctly used for yearly reports
- No duplicate or inconsistent result sources

---

## OUTPUT REQUIRED

Provide:

1. List of access control risks (if any)
2. List of possible data leakage points
3. List of broken UI routes or links
4. Minimal safe fixes applied (if any)
5. Confirmation: "Result system is production-safe"

---

## FINAL GOAL

System must be:
- Stable
- Secure
- No cross-user data leak
- No architecture changes