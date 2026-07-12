# STUDENT SUBJECT SCOPE RULE (ADMIN UPDATE RESTRICTION)

## 🎯 Goal
When Admin updates a Student, the system MUST show ONLY the subjects that the student is allowed to learn.

---

## 👥 ROLE CONTEXT

### Admin
- Can update any student
- BUT subject assignment must be restricted based on:
  - Year Level
  - Academic Year
  - Course Enrollment
  - Teacher assignment rules

---

## 📚 SUBJECT VISIBILITY RULE

When Admin opens "Edit Student":

### Allowed Subjects
- Subjects linked to student's:
  - YearLevel
  - AcademicYear
  - Enrolled Courses
  - Assigned Teacher (if applicable)

### NOT ALLOWED
- Subjects from other year levels
- Subjects not in student's academic plan
- Subjects from unrelated courses

---

## 🔒 ENFORCEMENT RULE (CRITICAL)

Restriction MUST be applied in:

1. Controller query level (MANDATORY)
   - NOT only Blade filtering

2. Model scope OR service layer (preferred)
   - e.g. StudentService or AcademicService

3. View layer (secondary safety only)

---

## ⚠️ SECURITY REQUIREMENT

- User must NOT be able to bypass restriction via:
  - URL manipulation
  - Direct subject ID injection
  - API request tampering

---

## 📌 IMPLEMENTATION EXPECTATION

When loading edit student page:

- Fetch ONLY allowed subjects using relationship filtering
- Example logic idea:
  - student->yearLevel->subjects
  - or enrollment-based subject mapping

---

## 🚫 DO NOT

- Do NOT show all subjects in system
- Do NOT use unfiltered Subject::all()
- Do NOT modify unrelated systems
- Do NOT touch ReAttempt system
- Do NOT touch Result system

---

## ✅ SUCCESS CRITERIA

- Admin sees ONLY valid subjects for that student
- No unrelated subjects appear in dropdown/select
- Backend enforces restriction
- Data integrity preserved
🧠 KIRO PROMPT
Focus ONLY on fixing SUBJECT VISIBILITY inside ADMIN → STUDENT UPDATE page.

When Admin edits a student, the subject list MUST be restricted.

RULES:
1. Student must see only subjects assigned to:
   - their YearLevel
   - their AcademicYear
   - their enrolled Course

2. Admin must NOT see all subjects in system for that student

3. Restriction MUST be enforced at backend query level (NOT UI only)

4. Prevent subject injection via request tampering

5. Use proper relationships (YearLevel / Course / Enrollment / AcademicYear)

DO NOT TOUCH:
- Result system
- ReAttempt system
- AttemptReset system
- Auth system
- Chat system
- Email system

DO NOT BREAK EXISTING LOGIC.

Output:
- Fixed controller logic for student edit/update
- Correct subject filtering query
- Any needed relationship usage fix