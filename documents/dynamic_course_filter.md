# STUDENT CREATE PAGE - DYNAMIC COURSE FILTER UPDATE

==================================================
🔥 PROJECT MODE
===============

THIS IS AN EXISTING UNIVERSITY ERP PROJECT.

The Student Create page already exists:

/admin/students/create

Do NOT rebuild the Student Module.

Do NOT create a new Student Create page.

Update the existing form only.

==================================================
🎯 OBJECTIVE
============

When Admin creates a student:

1. Select Academic Year
2. Select Year Level
3. Select Semester

The Course dropdown must automatically display only matching courses.

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

Show only:

* CS101 Programming Fundamentals
* MATH101 Engineering Mathematics
* ENG101 English

---

Selected:

Academic Year:
2026-2027

Year Level:
Second Year

Semester:
Semester 2

Show only:

* CS201 Data Structures
* DB201 Database Systems
* NET201 Computer Networks

==================================================
⚙️ FILTER LOGIC
===============

Load courses where:

# course.academic_year_id

selected academic_year_id

AND

# course.year_level_id

selected year_level_id

AND

# course.semester_id

selected semester_id

==================================================
🖥 UI REQUIREMENTS
==================

Use existing form.

When:

* Academic Year changes
* Year Level changes
* Semester changes

Automatically reload course list.

No page refresh.

Use:

* AJAX
  OR
* Alpine.js

==================================================
📂 DATABASE REQUIREMENTS
========================

Reuse existing tables.

Do NOT create duplicate tables.

Use existing:

* courses
* students
* enrollments

Only add fields if missing:

* academic_year_id
* year_level_id
* semester_id

==================================================
🔐 VALIDATION
=============

Backend validation required.

Student cannot be assigned to:

* courses from another year level
* courses from another semester
* courses from another academic year

==================================================
🚀 IMPLEMENTATION RULE
======================

1. Analyze existing Student Create page.
2. Analyze existing Course relationships.
3. Update existing form.
4. Create API endpoint if required.
5. Implement dynamic course filtering.
6. Preserve existing functionality.
7. Generate upgrade code only.

IMPORTANT:

Do not rebuild Student Management.

Do not ask for approval after analysis.

Continue implementation automatically.
