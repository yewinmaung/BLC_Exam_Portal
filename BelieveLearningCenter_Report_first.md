# BELIEVE LEARNING CENTER — SYSTEM IMPLEMENTATION REPORT

**Project:** Believe Learning Center Online Examination System  
**Report File:** BelieveLearningCenter_Report_first.md  
**Framework:** Laravel 10 (PHP), Blade, Vanilla JavaScript  
**Analysis Basis:** Full codebase inspection — controllers, services, models, middleware, jobs, migrations, and frontend JavaScript  

---

## TABLE OF CONTENTS

- [CHAPTER 1 — SYSTEM OVERVIEW](#chapter-1--system-overview)
- [CHAPTER 2 — AUTHENTICATION AND USER MANAGEMENT](#chapter-2--authentication-and-user-management)
- [CHAPTER 3 — ACADEMIC MANAGEMENT](#chapter-3--academic-management)
- [CHAPTER 4 — COURSE AND ENROLLMENT MANAGEMENT](#chapter-4--course-and-enrollment-management)
- [CHAPTER 5 — EXAMINATION MANAGEMENT](#chapter-5--examination-management)
- [CHAPTER 6 — ONLINE EXAMINATION PROCESS](#chapter-6--online-examination-process)
- [CHAPTER 7 — EXAM TIMER](#chapter-7--exam-timer)
- [CHAPTER 8 — ANTI-CHEATING MECHANISMS](#chapter-8--anti-cheating-mechanisms)
- [CHAPTER 9 — SESSION RECOVERY](#chapter-9--session-recovery)
- [CHAPTER 10 — RESULT AND GRADING](#chapter-10--result-and-grading)
- [CHAPTER 11 — NOTIFICATION AND EMAIL SYSTEM](#chapter-11--notification-and-email-system)
- [CHAPTER 12 — SECURITY IMPLEMENTATION](#chapter-12--security-implementation)
- [CHAPTER 13 — DASHBOARD AND REPORTING](#chapter-13--dashboard-and-reporting)
- [CHAPTER 14 — LOGGING AND AUDIT](#chapter-14--logging-and-audit)
- [CHAPTER 15 — SYSTEM INTEGRATION AND SCHEDULED TASKS](#chapter-15--system-integration-and-scheduled-tasks)
- [CHAPTER 16 — DATABASE RELATIONSHIPS](#chapter-16--database-relationships)
- [CHAPTER 17 — LEGACY AND DUPLICATE IMPLEMENTATIONS](#chapter-17--legacy-and-duplicate-implementations)
- [CHAPTER 18 — COMPLETE EXAM FLOWCHART](#chapter-18--complete-exam-flowchart)
- [CHAPTER 19 — FEATURE STATUS SUMMARY](#chapter-19--feature-status-summary)
- [CHAPTER 20 — REFERENCE TABLES](#chapter-20--reference-tables)
- [CHAPTER 21 — PROJECT IMPLEMENTATION SUMMARY](#chapter-21--project-implementation-summary)

---

## CHAPTER 1 — SYSTEM OVERVIEW

### 1.1 Purpose

The Believe Learning Center Online Examination System is a web-based platform built with Laravel 9 that manages the complete lifecycle of academic examinations. The system handles user account management, academic year and enrollment administration, examination creation and approval, live online examination delivery with security monitoring, automatic grading, result management, and email communication.

### 1.2 User Roles

Three roles are defined in the `roles` table and controlled by the `RoleMiddleware`:

| Role | Slug | Access Area |
|------|------|-------------|
| Administrator | `admin` | `/admin/*` — full system management |
| Teacher | `teacher` | `/teacher/*` — exam creation, results |
| Student | `student` | `/student/*` — exams, results, courses |

Evidence: `app/Enums/RoleSlug.php`, `app/Http/Middleware/RoleMiddleware.php`, `routes/web.php`

### 1.3 Technology Stack

- **Backend:** PHP / Laravel 9, Eloquent ORM, Laravel Queue (`emails` queue)
- **Database:** MySQL (Eloquent migrations)
- **Frontend:** Blade templates, vanilla JavaScript (no Vue/React)
- **Email:** SMTP via Laravel Mail, Webklex IMAP (inbox sync)
- **Encryption:** Laravel `Crypt::encryptString()` for question/answer content
- **Scheduling:** Laravel Console Kernel — three commands run every minute

### 1.4 Project Directory Structure

```
app/
  Console/Commands/        4 Artisan commands
  Http/Controllers/        Admin (11), Teacher (3), Student (4), Auth (2), Shared (3)
  Http/Middleware/         8 middleware classes
  Models/                  25 Eloquent models
  Services/                16 service classes
  Jobs/                    7 queued job classes
  Mail/                    5 Mailable classes
  Enums/                   2 enum classes
public/js/                 3 JavaScript files
resources/views/           Blade views (admin, teacher, student, auth, email, profile)
database/migrations/       44 migration files
routes/web.php             All HTTP routes (no API routes used by the application)
```

---

## CHAPTER 2 — AUTHENTICATION AND USER MANAGEMENT

### 2.1 Login

**Route:** `POST /login` (guest middleware)  
**Controller:** `app/Http/Controllers/Auth/AuthController.php`  
**Method:** `login(Request $request)`

**Process:**

```
User submits email + password
        ↓
Validate: email required|email, password required
        ↓
Find user by email (User::where('email', ...)->first())
        ↓
Check isLocked() — locked_until is in the future → return error with locked_until timestamp
        ↓
Auth::attempt($credentials, $remember)
        ↓
On failure:
  → incrementFailedLogins() on User model
  → If failed_login_attempts >= 3 → set locked_until = now()->addMinutes(10)
  → Return remaining attempts message
        ↓
On success:
  → Check is_active — inactive → logout + error
  → Check isTemporaryPasswordExpired() — expired → logout + temp_expired_email in session
  → resetFailedLogins() — clears failed_login_attempts and locked_until
  → Update last_login_at = now()
  → ActivityLogService::log('login', ...)
  → session()->regenerate()
  → redirect to dashboardRoute($user) based on role
```

**Account lock constants (User model):**
- `MAX_FAILED_ATTEMPTS = 3`
- `LOCK_DURATION_MINUTES = 10`
- `TEMP_PASSWORD_EXPIRY_HOURS = 24`

Evidence: `app/Http/Controllers/Auth/AuthController.php::login()`, `app/Models/User.php`

### 2.2 Logout

**Route:** `POST /logout` (auth middleware)  
**Method:** `AuthController::logout()`  
**Process:** Logs `'logout'` activity → `Auth::logout()` → `session()->invalidate()` → `session()->regenerateToken()` → redirect to login.

### 2.3 Registration (Self-Registration)

**Route:** `GET/POST /register` (guest middleware)  
**Method:** `AuthController::showRegister()` / `AuthController::register()`  
**Validation:** name, email unique, password confirmed + `Password::defaults()`, academic_year integer 1–5  
**Process:** Creates `User` with `student` role → `Auth::login($user)` → redirect to `student.dashboard`

Note: Self-registration creates a student account without academic year records or enrollments. Admin-created students (via `AdminStudentController::store()`) receive a temporary password via email and are pre-assigned to academic year records and courses.

### 2.4 Admin-Created Student Accounts

**Route:** `POST /admin/students`  
**Controller:** `app/Http/Controllers/Admin/StudentController.php::store()`

**Process:**
1. Validate name, email, phone, academic_year_id, year_level_id, major_id, semester, department, record_type, remark, course_ids
2. `YearLevelProgressionValidator::validate()` — checks year-level progression rules
3. `StudentMajorLockService::validateMajor()` — enforces major lock rules
4. `User::create()` with `force_password_change = true`, `temporary_password_expires_at = now()->addHours(24)`
5. Generate 12-char temporary password (3 uppercase + 3 lowercase + 3 digits + 3 symbols, Fisher-Yates shuffled, `random_int()`)
6. Create `StudentYearRecord`
7. Create `Enrollment` records for selected courses
8. `ActivityLogService::log('student_created', ...)`
9. Dispatch `SendWelcomeAccountJob` to `emails` queue with temporary password

Evidence: `app/Http/Controllers/Admin/StudentController.php::store()`

### 2.5 Force Password Change (First Login)

**Route:** `GET/POST /password/change` (auth middleware)  
**Middleware:** `ForcePasswordChange` — intercepts every request (except `/password/change` and `/logout`) when `force_password_change = true`  
**Method:** `AuthController::showForcePasswordChange()` / `AuthController::updateForcePasswordChange()`

**Validation:** password required, confirmed, min:8, different from current_password  
**On success:** Sets `force_password_change = false`, clears `temporary_password_expires_at`, clears `failed_login_attempts` and `locked_until`, logs `'forced_password_changed'`.

Evidence: `app/Http/Middleware/ForcePasswordChange.php`, `app/Http/Controllers/Auth/AuthController.php::updateForcePasswordChange()`

### 2.6 Temporary Password Re-Request

**Route:** `POST /login/request-new-password` (guest middleware)  
**Method:** `AuthController::requestNewTemporaryPassword()`

**Guards:**
1. User must exist and have `force_password_change = true`
2. Account must NOT be locked
3. 60-second cooldown: `canRequestNewTempPassword()` checks `temp_password_last_requested_at`

**On success:** Generates new 12-char temporary password → hashes and saves → resets `failed_login_attempts` and `locked_until` → sets new `temporary_password_expires_at` (+24h) → sets `temp_password_last_requested_at = now()` → dispatches `SendNewTemporaryPasswordJob`

Evidence: `app/Http/Controllers/Auth/AuthController.php::requestNewTemporaryPassword()`

### 2.7 Forgot Password (OTP-Based Reset)

**Routes:** `GET/POST /forgot-password`, `GET/POST /forgot-password/verify`, `POST /forgot-password/check-otp`, `POST /forgot-password/resend`  
**Controller:** `app/Http/Controllers/Auth/ForgotPasswordController.php`

**Two-step process:**

Step 1 — `sendOtp()`:
- Finds user by email (enumerate-safe: always redirects to verify)
- `ProfileOtp::generate($user, '')` — deletes all previous OTPs, creates new 6-digit code (bcrypt hashed), expires in 5 minutes
- `dispatch_sync(new SendProfileOtpJob($user->id, $plainCode))` — sends immediately (no queue)
- Stores `fp_user_id` in session

Step 2 — `checkOtp()`:
- Validates: OTP exists, not used (`used_at` is null), not expired (`expires_at` not past), attempts < 3, code matches `Hash::check()`
- On wrong code: `otp->increment('attempts')`; if >= 3 attempts → blocked
- On correct code: `otp->update(['used_at' => now()])` → sets `fp_otp_verified = true` in session

Step 3 — `resetPassword()`:
- Guards: `fp_otp_verified` must be true in session
- Validates: password min:8, mixedCase, numbers, confirmed
- `user->update(['password' => Hash::make(...)])`
- Dispatches `SendPasswordChangedJob`
- Clears `fp_user_id` and `fp_otp_verified` from session

Resend OTP — `resendOtp()`:
- Allows immediate resend if OTP is expired, used, or max attempts reached
- Enforces 60-second cooldown for valid OTPs

Evidence: `app/Http/Controllers/Auth/ForgotPasswordController.php`, `app/Models/ProfileOtp.php`

### 2.8 User Management (Admin)

**Routes:** `resource /admin/users` (index, create, store, edit, update — no show)  
**Controller:** `app/Http/Controllers/Admin/UserController.php`  
**Additional routes:** `POST /admin/users/{user}/terminate`, `POST /admin/users/{user}/restore`

- **index():** Filtered by search, role_id, status (active/inactive). Paginated 10.
- **store():** Validates name, email unique, password min:8, role_id, academic_year (required for students). Creates User → logs → sends welcome email via `EmailService::sendWelcomeEmail()`.
- **terminate():** Sets `is_active = false`, sends `AccountTerminatedMail`, logs.
- **restore():** Sets `is_active = true`, logs.

Evidence: `app/Http/Controllers/Admin/UserController.php`

### 2.9 Profile Management

**Routes:** `GET /profile`, `POST /profile/photo`, `DELETE /profile/photo`, `POST /profile/password`  
**Controller:** `app/Http/Controllers/ProfileController.php`

- **show():** For students, builds grouped enrollment history by academic year → year level → semester.
- **updatePhoto():** Accepts Base64-encoded WebP data URI. Validates format. Decodes, enforces 2 MB limit, stores as `avatars/{userId}.webp` on public disk, updates `profile_photo` column.
- **deletePhoto():** Deletes file from storage, sets `profile_photo = null`.
- **changePassword():** Validates password min:8, mixedCase, numbers, confirmed. Updates password hash. Dispatches `SendPasswordChangedJob`. Logs.

Evidence: `app/Http/Controllers/ProfileController.php`

---

## CHAPTER 3 — ACADEMIC MANAGEMENT

### 3.1 Academic Year Management

**Routes:** `resource /admin/academic/years` (index, create, store, show, edit, update, destroy)  
**Controller:** `app/Http/Controllers/Admin/AcademicYearController.php`  
**Model:** `app/Models/AcademicYear.php` → table `academic_years`

**Fields:** `name`, `start_year`, `end_year`, `is_current`

- **store():** Validates name unique, start_year 4 digits, end_year >= start_year. If `is_current = true`, clears all other `is_current` flags first. `AcademicYear::create()`.
- **destroy():** Blocked if `studentYearRecords()->count() > 0`.
- `AcademicYear::current()` — static method returns the record with `is_current = true`.

Evidence: `app/Http/Controllers/Admin/AcademicYearController.php`, `app/Models/AcademicYear.php`

### 3.2 Student Academic Year Record Assignment

**Routes:** `GET/POST /admin/academic/years/{year}/students`, `DELETE /admin/academic/years/{year}/students/{student}`  
**Methods:** `assignStudents()`, `removeStudent()`  
**Model:** `app/Models/StudentYearRecord.php` → table `student_year_records`

**Fields:** `student_id`, `academic_year_id`, `year_level_id`, `semester`, `department`, `major` (text name), `status`, `record_type`, `remark`

**assignStudents() process:**
1. Validates student_ids[], year_level_id, semester, department, major, major_id, record_type, remark
2. `record_type` ∈ `RecordType::ALL` = `['NORMAL', 'TRANSFER', 'READMISSION']`
3. Remark required when record_type is TRANSFER or READMISSION
4. For each student: `YearLevelProgressionValidator::validate()` — checks progression rules
5. `StudentMajorLockService::validateMajor()` — enforces specialization lock
6. `StudentMajorLockService::resolveMajorIdForSave()` — resolves correct major_id
7. Checks for duplicate (same student + academic_year + year_level + semester) → skip
8. `StudentYearRecord::create()`

Evidence: `app/Http/Controllers/Admin/AcademicYearController.php::assignStudents()`

### 3.3 Year Level Progression Validator

**Service:** `app/Services/YearLevelProgressionValidator.php`

**Rules enforced:**
- `NORMAL`: First record must be Year 1; year levels must advance +1; academic years must be consecutive
- `TRANSFER`: Only allowed as the student's first record; may start at any year level; subsequent years must be consecutive
- `READMISSION`: Not allowed as first record; allows academic year gap before the re-admission record; year level still must advance +1
- Duplicate academic year → rejected
- Duplicate year level → rejected
- Full timeline sorted by `start_year` and validated pair-by-pair

**Methods:** `validate()` (CREATE path), `validateEdit()` (UPDATE path — excludes the record being edited)

Evidence: `app/Services/YearLevelProgressionValidator.php`

### 3.4 Student Major Lock Service

**Service:** `app/Services/StudentMajorLockService.php`

**Rules:**
- Year 1: no major lock (all Year 1 students are CST)
- Year 2 entry: student chooses major (CS or CT); this becomes the canonical major
- Year 3+: `isMajorLocked()` returns true — major cannot be changed
- `getCanonicalMajorId()` — returns major_id from the earliest Year 2+ record
- `validateMajor()` — returns error string if submitted major differs from canonical
- `resolveMajorIdForSave()` — always returns canonical major_id when locked

Evidence: `app/Services/StudentMajorLockService.php`

### 3.5 Year Level Model

**Model:** `app/Models/YearLevel.php` → table `year_levels`  
**Levels:** 1 = First Year, 2 = Second Year, 3 = Third Year, 4 = Fourth Year, 5 = Final Year  
`ensureDefaults()` — seeds default year levels when table is empty

### 3.6 Major Management

**Routes:** `resource /admin/majors` (index, store, update, destroy), `GET /admin/majors/{major}/courses`  
**Controller:** `app/Http/Controllers/Admin/MajorController.php`  
**Model:** `app/Models/Major.php` → table `majors`  
**Fields:** `name`, `code`, `is_active`  
Default seeded majors: CS (Computer Science), CT (Computer Technology). CST is a combined designation used for Year 1 courses.

`Major::resolveIdFromLabel()` — resolves major ID from name or code string (used by enrollment and student assignment)

Evidence: `app/Http/Controllers/Admin/MajorController.php`, `app/Models/Major.php`

### 3.7 Teacher Management

**Routes:** `GET/POST /admin/teachers`, `GET /admin/teachers/{teacher}`, `GET/PUT /admin/teachers/{teacher}/edit`  
**Controller:** `app/Http/Controllers/Admin/TeacherController.php`

### 3.8 Academic History (AcademicService)

**Service:** `app/Services/AcademicService.php`

- `enrollStudent()` — `StudentYearRecord::firstOrCreate()` for a student + academic year + year level
- `getStudentHistory()` — returns array of `['record' => StudentYearRecord, 'results' => Collection]` for each of a student's year records. Results are matched by course year_level and semester, not by academic_year_id (to handle course reuse).

Evidence: `app/Services/AcademicService.php`

---

## CHAPTER 4 — COURSE AND ENROLLMENT MANAGEMENT

### 4.1 Course Management

**Routes:** `resource /admin/courses` (except show), `GET /admin/courses-by-year-level`  
**Controller:** `app/Http/Controllers/Admin/CourseController.php`  
**Model:** `app/Models/Course.php` → table `courses`

**Course fields:** `title`, `code` (unique), `description`, `teacher_id`, `created_by`, `is_active`, `year_level` (integer 0–5, 0 = all), `academic_year_id`, `semester` (0 = both, 1, 2), `major_id`

**Relationships:**
- `belongsTo(AcademicYear::class)` via `academic_year_id`
- `belongsTo(YearLevel::class)` via `year_level` (matched to `year_levels.level`)
- `belongsTo(Major::class)` via `major_id`
- `belongsTo(User::class, 'teacher_id')` — assigned teacher
- `hasMany(Enrollment::class)`
- `hasMany(Exam::class)`
- `belongsToMany(User::class, 'enrollments', ...)` — enrolled students

`byYearLevel()` — AJAX endpoint returning courses filtered by year_level integer.

Evidence: `app/Models/Course.php`, `app/Http/Controllers/Admin/CourseController.php`

### 4.2 Course Assignment to Teacher (Teacher Profile)

**Routes:** `GET /teacher/profile`, `GET/PUT /teacher/profile/edit`, `GET /teacher/profile/courses/{course}`  
**Controller:** `app/Http/Controllers/Teacher/ProfileController.php`  
Teacher sees their assigned courses via `taughtCourses()` relationship on User.

### 4.3 Enrollment Management

**Routes:** `GET /admin/enrollments`, `POST /admin/enrollments`, `DELETE /admin/enrollments/{enrollment}`, `GET /admin/enrollments/students-by-year-level`  
**Controller:** `app/Http/Controllers/Admin/EnrollmentController.php`  
**Model:** `app/Models/Enrollment.php` → table `enrollments`

**Enrollment fields:** `course_id`, `student_id`, `enrolled_at`, `year` (legacy integer), `year_level_id` (FK), `major_id`

**store() — Enrollment creation process:**

```
Admin selects: course_ids[], student_ids[], year_level_id, major_id, academic_year_id, semester
        ↓
Backend: Validate each student has a StudentYearRecord for the selected year_level + academic_year + major
        ↓
For each course_id:
  1. Year level mismatch check (course.year_level vs selected year level)
  2. Semester mismatch check
  3. Major validation:
     - Year 1: course must be a CST course
     - Year 2+: submitted major must match course.major_id
       Exception: CS or CT student may enroll in CST courses
  4. For each student_id:
     - Verify valid StudentYearRecord exists
     - Duplicate check: same course_id + student_id + year_level_id → skip
     - Enrollment::create()
     - NotificationService::notify() → 'enrolled' notification to student
```

**studentsByYearLevel()** — AJAX endpoint: returns students filtered by academic_year_id + year_level_id + major_id + semester, querying StudentYearRecord.

**destroy()** — `Enrollment::delete()` (hard delete).

Evidence: `app/Http/Controllers/Admin/EnrollmentController.php`

### 4.4 Student Course View

**Route:** `GET /student/courses`  
**Controller:** `app/Http/Controllers/Student/CourseController.php`  
Students view their enrolled courses via `enrollments()->with('course')`.

---

## CHAPTER 5 — EXAMINATION MANAGEMENT

### 5.1 Exam Creation (Teacher)

**Route:** `GET /teacher/exams/create`, `POST /teacher/exams`  
**Controller:** `app/Http/Controllers/Teacher/ExamController.php::create()` / `store()`  
**Model:** `app/Models/Exam.php` → table `exams`

**Validation:**
- `course_id`: must exist in courses, teacher must own it
- `academic_year_id`: must exist in academic_years
- `title`: required, max 255
- `description`: nullable
- `passing_marks`: required, integer, min 0
- `total_marks`: required, integer, min 1
- `shuffle_questions`: nullable boolean

**Process:** `Exam::create()` with `teacher_id = auth()->id()` and `status = 'draft'`.

**Exam status lifecycle:** `draft` → `pending_approval` → `approved` → `published` → `closed`

Evidence: `app/Http/Controllers/Teacher/ExamController.php::store()`

### 5.2 Question Creation

**Route:** `POST /teacher/exams/{exam}/questions`  
**Method:** `TeacherExamController::addQuestion()`

**Question types:** `mcq`, `true_false`, `essay`, `fill_blank`

**Validation:**
- `type`: required, in: `mcq, true_false, essay, fill_blank`
- `content`: required string
- `marks`: required, integer, min 1
- `category_id`: nullable, exists question_categories
- `answers[]`: array with `content` and `is_correct` sub-fields
- `blank_answers[]`: array for fill_blank type

**Answer validation:**
- `fill_blank`: at least one non-empty blank_answers entry required
- `mcq`/`true_false`: at least one answer with `is_correct = true` and non-empty content required

**Process:**
1. `ensureEditable()` — aborts 403 if exam is not `draft` or `pending_approval`
2. `authorizeTeacher()` — aborts 403 if not the exam's teacher (admin bypasses)
3. `Question::create()` with `content_encrypted = EncryptionService::encrypt()`
4. For `fill_blank`: `saveBlankAnswers()` — creates Answer records with `is_blank_answer = true`
5. For `mcq`/`true_false`: `saveAnswers()` — creates Answer records
6. If exam is `pending_approval`: notifies all admins via `NotificationService`

**Content encryption:** All question content and answer content is stored encrypted using `Crypt::encryptString()` (Laravel's built-in AES-256-CBC encryption).

Evidence: `app/Http/Controllers/Teacher/ExamController.php::addQuestion()`

### 5.3 Question Update and Delete

**Update route:** `GET /teacher/exams/{exam}/questions/{question}/edit`, `PUT /teacher/exams/{exam}/questions/{question}`  
**Delete route:** `DELETE /teacher/exams/{exam}/questions/{question}`

- `updateQuestion()`: Rebuilds all answers (deletes existing, recreates)
- `deleteQuestion()`: Deletes attachment from storage, deletes answers, soft-deletes question

### 5.4 Question Import

**Route:** `POST /teacher/exams/{exam}/import`  
**Method:** `TeacherExamController::importQuestions()`  
**Service:** `app/Services/QuestionImportService.php`

**Supported formats:** `.txt`, `.pdf`, `.doc`, `.docx` (max 5 MB)

**Moodle-style text format:**
```
[MCQ] Question text? (2 marks)
A. Option 1
B. Option 2 *
C. Option 3

[TRUE_FALSE] Statement? (1 mark)
True *
False
```

`parseText()` — parses blocks by type prefix → extracts content, marks, answers  
`extractTextFromFile()` — extracts text from docx (ZipArchive), pdf (regex), doc (string extract)  
`importParsed()` — creates Question + Answer records with encrypted content

Evidence: `app/Services/QuestionImportService.php`

### 5.5 Submit for Approval

**Route:** `POST /teacher/exams/{exam}/submit`  
**Method:** `TeacherExamController::submitForApproval()`

**Conditions checked (backend — never trusts browser):**
1. Exam status must be `draft` or `pending_approval`
2. At least one question required
3. `sum('marks')` of all questions must exactly equal `exam->total_marks`
   - If less: shows remaining marks needed
   - If more: shows excess marks

**On success (DB::transaction):**
1. Updates `status = 'pending_approval'`, `submitted_at = now()`
2. Fetches all active admin users
3. `NotificationService::notify()` each admin with type `'exam_submitted'`

Evidence: `app/Http/Controllers/Teacher/ExamController.php::submitForApproval()`

### 5.6 Admin Exam Approval

**Route:** `POST /admin/exams/{exam}/approve`  
**Method:** `AdminExamController::approve()`

**Guard:** exam.status must be `pending_approval`  
**Process:** `exam->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => auth()->id()])`  
**Notification:** `NotificationService::notify()` the teacher with type `'exam_approved'`

Evidence: `app/Http/Controllers/Admin/ExamController.php::approve()`

### 5.7 Exam Scheduling

**Route:** `POST /admin/exams/{exam}/schedule`  
**Method:** `AdminExamController::schedule()`

**Guard:** A schedule can only be set once — `exam->schedules()->exists()` → reject if already exists.

**Validation:**
- `starts_at`: required, date, after_or_equal: now
- `ends_at`: required, date, after: starts_at
- `duration_minutes`: required, integer, min 1
- `attempt_limit`: required, integer, min 1
- `target_year`: nullable, integer, min 1, max 5

**Process:** `ExamSchedule::create()` with the validated data and `exam_id`.

**Design note (from code comments):**  
`duration_minutes` controls each student's personal countdown, independent of `ends_at`. The server caps each student's `expires_at` as `MIN(start + duration, ends_at)`. A duration larger than the open window is allowed.

**Schedule update/delete:** Both `updateSchedule()` and `deleteSchedule()` return an error: schedule cannot be changed or deleted once set.

Evidence: `app/Http/Controllers/Admin/ExamController.php::schedule()`

### 5.8 Exam Publishing

**Route:** `POST /admin/exams/{exam}/publish`  
**Method:** `AdminExamController::publish()`

**Guard:** Schedule must exist  
**Process:**
1. `exam->update(['status' => 'published'])`
2. `schedule->update(['is_published' => true, 'published_at' => now(), 'published_by' => auth()->id()])`
3. `NotificationService::notify()` each enrolled student with type `'exam_published'`
4. `NotificationService::notify()` the teacher with type `'exam_published'`
5. `ActivityLogService::log('exam_published', ...)`

Evidence: `app/Http/Controllers/Admin/ExamController.php::publish()`

### 5.9 Exam Close and Reopen

**Close route:** `POST /admin/exams/{exam}/close`  
- Guard: status must be `published`
- `exam->update(['status' => 'closed'])`
- `activeSchedule->update(['is_published' => false])`

**Open route:** `POST /admin/exams/{exam}/open`  
- Guard: status must be `closed`; schedule must exist
- `exam->update(['status' => 'published'])`
- `schedule->update(['is_published' => true, ...])`

### 5.10 Exam Delete (Teacher, Draft Only)

**Route:** `DELETE /teacher/exams/{exam}`  
**Method:** `TeacherExamController::destroy()`

- Guard: status must be `draft`
- Loads all questions including soft-deleted (`withTrashed()`)
- For each question: deletes attachment file from storage → hard-deletes answers → `forceDelete()` on question
- `exam->forceDelete()` — permanently removes exam row

Evidence: `app/Http/Controllers/Teacher/ExamController.php::destroy()`

---

## CHAPTER 6 — ONLINE EXAMINATION PROCESS

### 6.1 Student Exam Eligibility Check

**Method:** `ExamAccessService::studentCanTakeExam(User $user, Exam $exam)`

**Conditions (all must pass):**
1. User is a student
2. Exam status is `approved` or `published`
3. A schedule exists (`exam->student_schedule` attribute)
4. Student is enrolled in the exam's course
5. Student has a `StudentYearRecord` matching `exam->academic_year_id` AND `course->year_level`
6. Used attempt count < `schedule->attempt_limit` (finalized statuses: submitted, terminated, suspicious, rejected)
7. `isScheduleActive()` — `now()` is between `starts_at` and `ends_at`

Evidence: `app/Services/ExamAccessService.php::studentCanTakeExam()`

### 6.2 Starting an Exam

**Route:** `POST /student/exams/{exam}/start`  
**Controller:** `app/Http/Controllers/Student/ExamController.php::start()`

**Process:**
```
studentCanTakeExam() check → abort if not eligible
        ↓
Check for existing in_progress attempt → redirect to take if found (resume)
        ↓
Generate session token: Str::random(60)
        ↓
User::update(['exam_session_token' => $token])
        ↓
session(['exam_session_token' => $token])
        ↓
Build question order array:
  $questionIds = exam->questions()->orderBy('order')->pluck('id')
  if shuffle_questions: shuffle($questionIds)  [server-side only]
        ↓
Calculate expires_at:
  $durationExpiry = now()->addMinutes($schedule->duration_minutes)
  $finalExpiry = $durationExpiry < $schedule->ends_at ? $durationExpiry : $schedule->ends_at->copy()
  [Formula: MIN(started_at + duration_minutes, schedule.ends_at)]
        ↓
ExamAttempt::create([
  exam_id, schedule_id, student_id,
  attempt_number = used_count + 1,
  status = 'in_progress',
  started_at = now(),
  expires_at = $finalExpiry,
  session_token = $token,
  question_order = $questionIds
])
        ↓
Redirect to student.exam.take
```

Evidence: `app/Http/Controllers/Student/ExamController.php::start()`

### 6.3 Exam Page Rendering

**Route:** `GET /student/attempt/{attempt}/take` (middleware: `auth`, `exam.session`, `force.password.change`, `exam.active`)  
**Controller:** `app/Http/Controllers/Student/ExamSessionController.php::take()`

**Flow on take():**

```
1. authorizeAttempt() — attempt.student_id must equal auth()->id()
        ↓
2. Disconnect recovery guard:
   if status = in_progress AND disconnected_at != null:
     → handleReconnect() [see Chapter 9]
        ↓
3. Timer-expiry guard:
   if now() >= attempt.expires_at:
     → submitAttempt() → redirect with "Time expired"
        ↓
4. Schedule-end guard (belt-and-braces for legacy attempts):
   if schedule and now() >= schedule.ends_at:
     → submitAttempt() → redirect
        ↓
5. Normal path:
   computeNormalSeconds(attempt, schedule)
   effectiveEndsAt = now()->addSeconds(normalSeconds)
   renderExamView(attempt, schedule, effectiveEndsAt)
```

**renderExamView() — builds the view data:**
- `ExamAccessService::canDecryptQuestions()` — abort 403 if not authorized
- Applies `question_order` JSON array (saved at attempt creation) to sort questions
- Maps each question: decrypts content and all answers using `EncryptionService::decrypt()`
- Loads `savedAnswers` = `attempt->studentAnswers()->pluck('answer_id', 'question_id')`
- Passes `endsAt` as Unix timestamp (for the JS timer)
- Passes `securityPolicy` array — all detection flags set to `true`
- Passes `isSessionRecovery`, `isReturning`, `resumeQuestionId`

Evidence: `app/Http/Controllers/Student/ExamSessionController.php::take()`, `::renderExamView()`

### 6.4 Answer Auto-Save

**Route:** `POST /student/attempt/{attempt}/save` (middleware: `exam.active`)  
**Method:** `ExamSessionController::saveAnswer()`

**Validation:**
- `question_id`: required, exists:questions,id
- `answer_id`: nullable, exists:answers,id
- `answer_text`: nullable string
- `answer_file`: nullable, file, mimes: pdf,doc,docx, max 10 MB

**Process:**
```
If file present: store to exams/{exam_id}/attempts/{attempt_id}/ on public disk
        ↓
StudentAnswer::updateOrCreate(
  ['attempt_id' => ..., 'question_id' => ...],
  ['answer_id' => ..., 'answer_text' => ..., 'file_path' => ...]
)
        ↓
return response()->json(['success' => true])
```

**Frontend triggers:**
- MCQ/True-False: click on `.mcq-option` label → `saveAnswer(qid, radio.value, null)`
- Fill in the blank: `input` event with 800ms debounce → `saveAnswer(qid, null, value)`
- Essay: `input` event with 1500ms debounce → `saveAnswer(qid, null, value)`
- Periodic MCQ backup: `setInterval` every 10 seconds scans all checked radios

Evidence: `app/Http/Controllers/Student/ExamSessionController.php::saveAnswer()`, `public/js/exam-anticheat.js`

### 6.5 Exam Submission

**Route:** `POST /student/attempt/{attempt}/submit` (middleware: `exam.active`)  
**Method:** `ExamSessionController::submit()`

**Process:**
```
authorizeAttempt()
        ↓
submitAttempt(attempt):
  attempt->update(['status' => 'submitted', 'submitted_at' => now()])
  User::update(['exam_session_token' => null])
  session()->forget('exam_session_token')
  GradingService::gradeAttempt(attempt->fresh([...]))
        ↓
Redirect to student.exams.show with 'success'
```

**Frontend submission:** Submit button click → confirm dialog → `examForm.action = submitUrl` → `form.submit()`. When timer expires: `document.getElementById('examForm')?.submit()` directly.

Evidence: `app/Http/Controllers/Student/ExamSessionController.php::submit()`, `::submitAttempt()`, `public/js/exam-anticheat.js`

### 6.6 EnsureExamActive Middleware

**File:** `app/Http/Middleware/EnsureExamActive.php`

Rejects requests to save, violation, submit routes when `attempt->isActive()` returns false. Returns JSON with `terminated: true` for AJAX requests, or redirects for normal requests.

Status-specific messages: `terminated_pending_review`, `rejected`, `submitted`, `terminated` each return a distinct message string.

### 6.7 EnsureSingleExamSession Middleware

**File:** `app/Http/Middleware/EnsureSingleExamSession.php`

Applied to all authenticated routes as the `exam.session` middleware. Detects when a second browser session logs in while a first session has an active exam token. If `session_token != user->exam_session_token` → logout + "Another active exam session was detected."

---

## CHAPTER 7 — EXAM TIMER

### 7.1 expires_at Calculation

The `expires_at` timestamp is calculated exactly once at attempt creation in `StudentExamController::start()`:

```php
$durationExpiry = now()->addMinutes($schedule->duration_minutes);
$finalExpiry    = $durationExpiry->lessThan($schedule->ends_at)
                  ? $durationExpiry
                  : $schedule->ends_at->copy();
```

This implements: **`expires_at = MIN(started_at + duration_minutes, schedule.ends_at)`**

`expires_at` is never modified after attempt creation (confirmed: SessionRecoveryService contract states "expires_at is never touched").

Evidence: `app/Http/Controllers/Student/ExamController.php::start()`

### 7.2 Concrete Examples

**Example A — Student starts on time:**
- `schedule.starts_at` = 14:00, `schedule.ends_at` = 15:00, `duration_minutes` = 60
- Student starts at 14:00
- `durationExpiry` = 14:00 + 60 min = 15:00
- `MIN(15:00, 15:00)` = **`expires_at = 15:00`**

**Example B — Student starts late:**
- `schedule.ends_at` = 15:00, `duration_minutes` = 60
- Student starts at 14:30
- `durationExpiry` = 14:30 + 60 min = 15:30
- `MIN(15:30, 15:00)` = **`expires_at = 15:00`** (student gets only 30 effective minutes)

**Example C — Short duration, early start:**
- `schedule.ends_at` = 16:00, `duration_minutes` = 30
- Student starts at 14:00
- `durationExpiry` = 14:00 + 30 min = 14:30
- `MIN(14:30, 16:00)` = **`expires_at = 14:30`** (student gets full 30 minutes)

### 7.3 Server-Side Timer Enforcement

The server checks `expires_at` on every page load in `ExamSessionController::take()`:

```php
if (now()->gte($attempt->expires_at)) {
    $this->submitAttempt($attempt);
    return redirect()->route('student.exams.show', $attempt->exam_id)
        ->with('success', 'Time expired. Exam auto-submitted.');
}
```

A secondary schedule-end guard also exists for legacy attempts:
```php
if ($schedule && now()->gte($schedule->ends_at)) {
    $this->submitAttempt($attempt);
}
```

The `ExamAttempt::canAutoRecover()` method also checks `now()->gte($this->expires_at)` to block recovery of expired sessions.

### 7.4 Client-Side Timer

**File:** `public/js/exam-anticheat.js`

The client timer reads `endsAt` from `body.dataset.endsAt` (passed from `renderExamView()` as `$effectiveEndsAt->timestamp`):

```javascript
const endsAt = parseInt(body.dataset.endsAt, 10) * 1000;

setInterval(() => {
    if (examLocked) return;
    const left = endsAt - Date.now();
    if (left <= 0) {
        examStarted  = false;
        isSubmitting = true;
        intervals.forEach(clearInterval);
        document.getElementById('examForm')?.submit();
        return;
    }
    const m = Math.floor(left / 60000);
    const s = Math.floor((left % 60000) / 1000);
    timerText.textContent = `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
    if (timerEl) timerEl.classList.toggle('warning', left < 300000);
}, 1000);
```

The timer turns to warning (red class) when under 5 minutes remain.

### 7.5 Timer Source for Normal vs Recovery Path

For a **normal page load**, `effectiveEndsAt` is computed by `SessionRecoveryService::computeNormalSeconds()`:
```php
$examRemaining  = max(0, (int) $now->diffInSeconds($attempt->expires_at, false));
$schedRemaining = max(0, (int) $now->diffInSeconds($schedule->ends_at, false));
return min($examRemaining, $schedRemaining);
// Then: effectiveEndsAt = now()->addSeconds(normalSeconds)
```

For a **session recovery load**, `effectiveEndsAt` uses `computeFrozenSeconds()`:
```php
$frozen = max(0, (int) $attempt->disconnected_at->diffInSeconds($attempt->expires_at, false));
if ($schedule) {
    $schedRemaining = max(0, (int) now()->diffInSeconds($schedule->ends_at, false));
    return min($frozen, $schedRemaining);
}
```

This means the disconnected time is **not consumed** — the timer resumes at the number of seconds remaining at the moment of disconnect.

### 7.6 Browser Timer Manipulation Protection

The client timer is derived from `endsAt` (a Unix timestamp), not from a countdown. The server independently validates `expires_at` on every request to `/take`, `/save`, `/violation`, and `/submit`. Manipulating `Date.now()` in the browser cannot extend the server-side deadline. When the student submits or the timer fires, the server re-validates `expires_at` before processing.

### 7.7 What Happens in Each Scenario

| Scenario | Client | Server |
|----------|--------|--------|
| Student starts early (before `starts_at`) | `studentCanTakeExam()` blocks start — schedule not active | N/A |
| Student starts late | `expires_at` is MIN-capped to `ends_at` | Less time shown in timer |
| Student reaches `expires_at` | JS submits form | `take()` calls `submitAttempt()` |
| Student reaches `schedule.ends_at` | JS already submitted (same value for new attempts) | Belt-and-braces guard in `take()` |
| Browser timer manipulated | JS deadline fixed to server timestamp | Server checks `expires_at` on every request |
| Page refreshed | `take()` re-computes remaining seconds | `computeNormalSeconds()` returns current remaining |
| Student disconnects | `beforeunload` fires `sendBeacon` to disconnect endpoint | `recordDisconnect()` saves `disconnected_at` |

---

## CHAPTER 8 — ANTI-CHEATING MECHANISMS

### 8.1 Overview

Anti-cheating is implemented in two layers:
1. **Client-side detection** (`public/js/exam-anticheat.js`) — event listeners detect prohibited activities and POST to the server
2. **Server-side enforcement** (`ExamSecurityService`) — records violations and applies the 3-violation termination policy

The `CheatingDetectionService` is retained in the codebase but is marked `@deprecated` and is no longer injected into `ExamSessionController`. All active violation handling uses `ExamSecurityService`.

### 8.2 Security Policy Flags

All policy flags are passed from `renderExamView()` in the `securityPolicy` array. All are set to `true`:

```php
'fullscreen_detection_enabled'        => true,
'blur_detection_enabled'              => true,
'tab_switch_detection_enabled'        => true,
'right_click_blocking_enabled'        => true,
'copy_detection_enabled'              => true,
'paste_detection_enabled'             => true,
'devtools_detection_enabled'          => true,
'keyboard_shortcut_detection_enabled' => true,
```

The JavaScript reads these from `body.dataset.policy*` attributes and registers listeners accordingly.

### 8.3 Detection Mechanisms

#### 8.3.1 Fullscreen Exit Detection

**Trigger:** `document.addEventListener('fullscreenchange', onFullscreenChange)`  
**Logic:**
- Student exits fullscreen → `openFsRecovery()` → starts 10-second `fsRecoveryTimer`
- Timer shows `fsRecoveryOverlay` with countdown bar
- If student returns to fullscreen within 10 seconds → `cancelFsRecovery(false)` — no violation
- If timer expires → `reportViolation('fullscreen_exit', ...)` + `autoRestoreFullscreen()`
- The exam requires the student to enter fullscreen initially via a gate overlay (`fsOverlay` → `enterFullscreen` button → `document.documentElement.requestFullscreen()`)

**Constant:** `FS_RECOVERY_SECONDS = 10`

#### 8.3.2 Tab Switching (visibilitychange)

**Trigger:** `document.addEventListener('visibilitychange', onVisibilityChange)`  
**Logic:**
- If `document.hidden` becomes true and not in grace window → `reportViolation('tab_switch', 'Tab switched')`
- During FS recovery window: fires compound violation (see 8.3.5)
- Grace period: `violationGraceUntil = Date.now() + 2500` ms set at session activation

#### 8.3.3 Window Blur (Focus Loss)

**Trigger:** `window.addEventListener('blur', onWindowBlur)`  
**Logic:**
- If exam started and not in grace window → `reportViolation('window_blur', 'Window lost focus')`
- During FS recovery window: fires compound violation (see 8.3.5)

#### 8.3.4 DevTools Keyboard Shortcuts

**Trigger:** `document.addEventListener('keydown', onKeydown)`  
**Blocked keys:**
- `F12`
- `Ctrl+Shift+I`, `Ctrl+Shift+J`, `Ctrl+Shift+C`
- `Ctrl+U`

**Action:** `e.preventDefault()` → `reportViolation('devtools_shortcut', 'DevTools shortcut blocked')`

#### 8.3.5 Compound Violation (Fullscreen Exit + Focus Loss)

When a focus-loss event (`visibilitychange` or `blur`) fires while the FS recovery countdown is active:
1. `cancelFsRecovery(false)` — stops the 10-second timer silently
2. `sendCompoundViolation(focusLostType, details)` — sends two sequential POST requests:
   - First: `type = 'fullscreen_exit'`
   - Second: `type = 'tab_switch'` or `'window_blur'`
3. After both responses arrive, if exam still active → `showFinalWarningModal()` (15-second decision)

Guard flag `focusLostDuringRecovery` prevents the same compound event cluster from sending more than one pair of violations.

**Final Warning Modal (15 seconds):**
- Outcome A: Student clicks "Return to Fullscreen" → `autoRestoreFullscreen()` — no further violation
- Outcome B: Student clicks "Exit & Consume Warning" → `reportViolation('fullscreen_exit', ...)` — counts as 3rd violation → termination
- Outcome C: 15 seconds expire → `autoRestoreFullscreen()` — no violation

#### 8.3.6 Right-Click Blocking

**Trigger:** `document.addEventListener('contextmenu', onContextMenu)`  
**Action:** `e.preventDefault()` — no violation is sent; just blocks the context menu

#### 8.3.7 Copy / Cut / Paste Blocking

**Trigger:** `document.addEventListener('copy', onCopyCutPaste)`, `cut`, `paste`  
**Action:** `e.preventDefault()` — no violation is sent; just blocks the clipboard operations

#### 8.3.8 Browser Close / Page Unload

**Trigger:** `window.addEventListener('beforeunload', ...)`  
**Action:** Sends `navigator.sendBeacon(disconnectUrl, formData)` with `question_id` and `reason = 'browser_close'`  
**Note:** This fires the disconnect endpoint (NOT the violation endpoint). A browser close is treated as a session disconnect, not a cheating violation.

### 8.4 Violation Reporting to Server

**JavaScript function:** `reportViolation(type, details)`  
**Endpoint:** `POST /student/attempt/{attempt}/violation` (middleware: `exam.active`)  
**Controller method:** `ExamSessionController::violation()`  
**Service:** `ExamSecurityService::recordViolation()`

```javascript
fetch(violationUrl, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
    body: JSON.stringify({ type, details }),
})
.then(r => r.json())
.then(handleViolationResponse)
```

### 8.5 Three-Violation Warning Sequence (Server)

**Service:** `app/Services/ExamSecurityService.php`  
**Constant:** `MAX_VIOLATIONS = 3` (fixed — not configurable)

| Violation Count | Action |
|----------------|--------|
| Warning 1 (count → 1) | Record `CheatingLog`, increment `warning_count`, return warning message |
| Warning 2 (count → 2) | Record `CheatingLog`, increment `warning_count`, email teacher + admins, notify teacher + admins |
| Warning 3 (count → 3) | `DB::transaction` with `lockForUpdate()` — terminates exam unconditionally |

**Warning 3 process (recordViolationThree):**
1. `ExamAttempt::lockForUpdate()->find()` — row-level lock prevents concurrent terminations
2. Check `warning_count >= MAX_VIOLATIONS` or `status != in_progress` → return `lockedResponse()` if already done
3. `CheatingLog::create()` with violation details
4. `attempt->update(['warning_count' => 3, 'status' => 'terminated', 'terminated_at' => now()])`
5. `User::update(['exam_session_token' => null])` — invalidates session
6. `GradingService::gradeAttempt()` — grades existing saved answers
7. `Result::update(['is_passed' => false, 'exam_result_status' => 'DISQUALIFIED', 'is_published' => false, 'violation_reason' => ..., 'disqualified_at' => now()])`
8. `ActivityLogService::log('exam_terminated_security', ...)`
9. `DB::afterCommit()` — sends email and notification to student + teacher + admins

**Server response to client:**
```json
{
  "warning_count": 3,
  "terminated": true,
  "locked": true,
  "message": "Your examination has been terminated due to 3 security violations...",
  "redirect": "/student/exams"
}
```

### 8.6 Client Response to Termination

`handleViolationResponse(data)`:
- Updates `warningCount` local variable
- Shows `warningBox` with message for 8 seconds
- If `data.terminated` → `lockExamInterface(data.message)` → redirect after 3 seconds

`lockExamInterface()`:
- Sets `examLocked = true`, `examStarted = false`
- Clears all intervals and timeouts
- Removes all event listeners
- Disables all form inputs
- Exits fullscreen
- Creates lock overlay with termination message

### 8.7 CheatingLog Model

**Model:** `app/Models/CheatingLog.php` → table `cheating_logs`  
**Fields:** `attempt_id`, `student_id`, `violation_type`, `details`, `warning_number`, `user_agent`, `browser`, `device`, `os`, `screen_resolution`, `timezone`, `ip_address`

The `warning_number` field stores the per-violation-type count (how many times that specific type was logged for this attempt).

### 8.8 Admin Cheating Logs View

**Route:** `GET /admin/cheating-logs`  
**Controller:** `app/Http/Controllers/Admin/CheatingLogController.php`  
Displays all `CheatingLog` records with student info, exam info, violation type, and IP address.

---
