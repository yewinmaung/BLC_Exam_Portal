# ENROLLMENT SUBJECT RESTRICTION RULE

## Goal

Students must only be enrolled into subjects that belong to their
academic year and major.

The system must automatically filter available courses/subjects.

---

## Academic Structure

### First Year

- No major specialization
- All students share the same curriculum
- Only First Year subjects are available

Example:

First Year:
- Myanmar
- English
- Mathematics
- Physics
- Principle of IT

---

### Second Year and Above

Students belong to a major.

Example majors:

- CS (Computer Science)
- CT (Computer Technology)

Only subjects belonging to the student's major should be available.

Example:

CS Student:
- Data Structure
- OOP
- Database
- Operating System

CT Student:
- Electronics
- Digital Logic
- Embedded System

A CS student must never see CT subjects.

A CT student must never see CS subjects.

---

## Enrollment Rules

When Admin opens Enrollment:

### First Year Student

Show:
- Year 1 subjects only

Hide:
- Year 2+
- CS-only subjects
- CT-only subjects

---

### Second Year+ Student

Show:
- Subjects matching student's Year Level
- Subjects matching student's Major

Hide:
- Other majors
- Other year levels

---

## Required Filtering

Filter using:

- year_level_id
- major_id (or course_track)
- academic_year_id

Never use:

Subject::all()

Course::all()

without filtering.

---

## Security

Restrictions must be enforced:

1. Controller query level
2. Validation level
3. Database relation level if possible

Not only in Blade/UI.

Prevent:

- URL manipulation
- Manual request tampering
- Wrong subject assignment

---

## Expected Behavior

Student:
Year = 2
Major = CT

Enrollment Page:

VISIBLE:
✔ Electronics
✔ Embedded Systems
✔ Digital Logic

HIDDEN:
✘ Data Structures
✘ Database Systems
✘ Operating Systems

---

## Do Not Touch

- Result System
- ReAttempt System
- Authentication
- Chat
- Email

Only modify enrollment-related logic.