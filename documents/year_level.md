# STUDENT YEAR LEVEL & ENROLLMENT FILTER UPGRADE

==================================================
🔥 PROJECT MODE
===============

THIS IS AN EXISTING UNIVERSITY ERP PROJECT.

Already exists:

* Student Management
* Enrollment Module
* Course Management
* Academic Years
* Semesters

DO NOT rebuild existing modules.

DO NOT create duplicate functionality.

Update existing implementation only.

==================================================
🎯 FEATURE 1: STUDENT YEAR LEVEL ASSIGNMENT
===========================================

Page:

/admin/students/create

Admin must be able to select:

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

Store selected Year Level in student record.

Example:

Student:
John Doe

Academic Year:
2026-2027

Year Level:
First Year

Semester:
Semester 1

==================================================
🎯 FEATURE 2: ENROLLMENT STUDENT FILTER
=======================================

Page:

/admin/enrollments/create

When Admin selects:

1. Academic Year
2. Year Level

Student dropdown must show ONLY students belonging to:

* selected academic year
* selected year level

Example:

Selected:

Academic Year:
2026-2027

Year Level:
First Year

Show only:

* First Year students

Do NOT show:

* Second Year students
* Third Year students
* Final Year students

==================================================
🎯 FEATURE 3: SEMESTER COURSE FILTER
====================================

After selecting:

* Academic Year
* Year Level
* Semester

System must load only courses matching:

# course.academic_year_id

selected academic year

AND

# course.year_level_id

selected year level

AND

# course.semester_id

selected semester

==================================================
📚 EXAMPLE
==========

Selected:

Academic Year:
2026-2027

Year Level:
First Year

Semester:
Semester 1

Show Courses:

* CS101 Programming Fundamentals
* MATH101 Mathematics
* ENG101 English

---

Selected:

Academic Year:
2026-2027

Year Level:
Second Year

Semester:
Semester 2

Show Courses:

* CS201 Data Structures
* DB201 Database Systems
* NET201 Networking

==================================================
🎯 COURSE SELECTION MODES
=========================

Support BOTH:

1. Single Select

Example:

Student enrolls in one course only

---

2. Multiple Select

Example:

Student enrolls in:

☑ CS101
☑ MATH101
☑ ENG101

Store all selected courses correctly.

==================================================
⚙️ DYNAMIC UI REQUIREMENTS
==========================

Use:

* AJAX
  OR
* Alpine.js

No page refresh.

Workflow:

Academic Year
↓
Year Level
↓
Load Students

Semester
↓
Load Courses

==================================================
🔐 VALIDATION RULES
===================

Prevent:

* Enrollment into other year levels
* Enrollment into other semesters
* Enrollment into other academic years

Validate on:

* Frontend
* Backend

==================================================
📂 DATABASE
===========

Reuse existing tables.

Use existing:

* students
* enrollments
* courses
* academic_years
* semesters

Add fields only if missing:

students:

* academic_year_id
* year_level_id

courses:

* academic_year_id
* year_level_id
* semester_id

==================================================
🏗 ARCHITECTURE
===============

Reuse existing:

* Controllers
* Services
* Repositories
* Models
* Blade Views

Do NOT rebuild modules.

Generate upgrade code only.

==================================================
🚀 IMPLEMENTATION RULE
======================

1. Analyze existing Student module.
2. Analyze existing Enrollment module.
3. Analyze existing Course relationships.
4. Update Student Create page.
5. Update Enrollment Create page.
6. Add dynamic filtering.
7. Preserve existing functionality.
8. Maintain backward compatibility.

IMPORTANT:

Do NOT ask for approval after analysis.

Continue implementation automatically.

Generate production-ready Laravel code only.
