# Course Module Redesign

## Migration Plan

### New migration: `2026_06_21_000001_create_majors_and_add_major_to_courses_enrollments`

| Step | DDL |
|---|---|
| Create `majors` | id, name, code (unique), description, is_active, timestamps |
| Add `courses.major_id` | nullable FK → majors (null = Year 1 / all majors) |
| Add `enrollments.year_level_id` | nullable FK → year_levels (proper relational FK alongside legacy `year` integer) |
| Add `enrollments.major_id` | nullable FK → majors (null for Year 1 students) |

Run: `php artisan migrate`

---

## Relationship Diagram

```
academic_years ─┐
                ├─ courses ─── exams ─── exam_attempts
year_levels ────┤    │
                │    └── enrollments ──── students (users)
majors ─────────┘              │
                               └── year_level_id → year_levels
                               └── major_id      → majors

users (teacher) ──── courses.teacher_id

student_year_records ──── student_id → users
                     ──── academic_year_id → academic_years
                     ──── year_level_id    → year_levels
                     (major stored as string text in `major` column)
```

---

## Rules Implemented

| Rule | Enforcement |
|---|---|
| Every course must have a Teacher | `teacher_id` required in store/update validation |
| Every course must have an Academic Year | `academic_year_id` required |
| Every course must have a Semester (1 or 2) | `semester` required, values 1–2 only |
| Year 1 courses — major NOT required | `major_id` forced null when `year_level < 2` |
| Year 2+ courses — major REQUIRED | Closure validator + JS enforcement in blade |
| Student sees only their year + major courses | `StudentCourseController::index()` filters by `year_level_id` |
| Admin student edit — course scope | `StudentController::getAllowedCourses()` includes `major_id` scope |
| Teacher sees only assigned courses | `Teacher/ExamController::create()` — `Course::where('teacher_id', auth()->id())` (unchanged) |
| Admin sees everything with filters | `Admin/CourseController::index()` — year_level, semester, major, academic_year, teacher, search filters |
| Enrollment backend validation | `EnrollmentController::store()` validates course ↔ year/major/semester match |
| Injection prevention | `StudentController::update()` validates `course_ids.*` against `getAllowedCourses()` allow-list |

---

## Controller Changes

### `Admin/CourseController`
- `index()` — added query filters: year_level, academic_year_id, semester, major_id, teacher_id, search
- `create()` — passes `$majors` collection to view
- `store()` — `teacher_id` now required; `academic_year_id` now required; `semester` now 1–2 only; major validation closure
- `edit()` — passes `$majors` to view
- `update()` — same required fields as store; forces `major_id = null` for Year 1
- `byYearLevel()` AJAX — added `major_id` filter parameter

### `Admin/EnrollmentController`
- `index()` — replaced `year` integer filter with `year_level_id` + `major_id` filters; loads `yearLevel`, `major` relations
- `store()` — now validates `year_level_id` (required FK) instead of loose `year`; validates major match for Year 2+; writes `year_level_id` + `major_id` to enrollment row
- Removed legacy `year` label array (uses `YearLevel::name` from DB now)

### `Admin/StudentController`
- `getAllowedCourses()` — extended with `major_id` scoping: Year 1 shows only `major_id IS NULL` courses; Year 2+ shows `major_id IS NULL OR major_id = student_major`

### `Student/CourseController`
- `index()` — now scopes by `year_level_id` from active `StudentYearRecord`; eager-loads `course.major`, `course.academicYear`; passes `$currentRecord` to view for the context banner

### `Services/CourseAssignmentService`
- All three sync methods now write `year_level_id` and `major_id` to enrollment rows
- New private `resolveStudentAcademicContext()` — reads `StudentYearRecord` for proper FK values; falls back to legacy `academic_year` integer if no record exists

### New: `Admin/MajorController`
- Full CRUD (index, create, store, edit, update, destroy)
- Destroy blocked if courses are assigned to the major

---

## New Files

| File | Purpose |
|---|---|
| `database/migrations/2026_06_21_000001_...php` | Creates majors table + adds FKs to courses + enrollments |
| `app/Models/Major.php` | Eloquent model with `courses()` and `enrollments()` HasMany |
| `app/Http/Controllers/Admin/MajorController.php` | CRUD for majors |
| `resources/views/admin/majors/index.blade.php` | Majors list with course count |
| `resources/views/admin/majors/create.blade.php` | Create major form |
| `resources/views/admin/majors/edit.blade.php` | Edit major form |

---

## Updated Files

| File | Change Summary |
|---|---|
| `app/Models/Course.php` | Added `major_id` fillable/cast, `major()` BelongsTo, `yearLevel()` convenience relation, `requiresMajor()` helper, label accessors |
| `app/Models/Enrollment.php` | Added `year_level_id`, `major_id` fillable/casts and BelongsTo relations |
| `app/Http/Controllers/Admin/CourseController.php` | Full rewrite — filters, required teacher, required academic year, major validation |
| `app/Http/Controllers/Admin/EnrollmentController.php` | year_level_id + major_id throughout |
| `app/Http/Controllers/Admin/StudentController.php` | `getAllowedCourses()` extended for major scoping |
| `app/Http/Controllers/Student/CourseController.php` | Scoped by year_level_id, passes currentRecord |
| `app/Services/CourseAssignmentService.php` | Writes year_level_id + major_id on all enrollment syncs |
| `resources/views/admin/courses/create.blade.php` | Added academic year (required), major (conditional), teacher (required), JS toggle |
| `resources/views/admin/courses/edit.blade.php` | Same as create + is_active checkbox |
| `resources/views/admin/courses/index.blade.php` | Full filter bar, major/semester/teacher columns |
| `resources/views/student/courses/index.blade.php` | Academic context banner, major badge, semester badge |
| `routes/web.php` | Added `Route::resource('majors', MajorController::class)` |

---

## Cleanup Report

### Confirmed unused logic removed
| Item | Reason |
|---|---|
| `Course::$semesterLabels[0]` ("Both Semesters") | No longer offered on create/edit forms (courses must be sem 1 or 2) — kept in model for display purposes on old data |
| `Course::$yearLevelLabels[0]` ("All Year Levels") | Kept for display of legacy data; new courses can still be set to 0 via `year_level` field but it's not recommended |
| Loose `year` integer in `EnrollmentController` | Replaced by `year_level_id`; `year` column still written for backward compatibility |
| `$yearLabels` static array in old `EnrollmentController` | Removed; now uses `YearLevel::name` from DB |

### NOT touched (as required)
- ReAttempt system — zero changes
- Authentication — zero changes
- Chat module — zero changes
- Email module — zero changes
- Result system — zero changes
- Exam flow — zero changes
- Transcript / Certificate — zero changes
