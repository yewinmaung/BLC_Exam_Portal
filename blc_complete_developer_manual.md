# BLC Complete Developer Knowledge Manual
## Believe Learning Center — Exam Portal
### Full Technical Documentation for Developers

---

> **Purpose:** This manual explains the entire Believe Exam Portal codebase so thoroughly that a new Laravel developer can understand every feature, every flow, and every design decision without reading source code first.
>
> **Generated from:** Full codebase analysis — Controllers, Models, Migrations, Services, Jobs, Middleware, Routes, Views, and JavaScript files.
>
> **Accuracy policy:** Where something cannot be confirmed from source, the notation `[Needs verification]` is used.

---

## TABLE OF CONTENTS

1. [Complete System Understanding](#1-complete-system-understanding)
2. [Technology Stack & Technical Implementation](#2-technology-stack--technical-implementation)
3. [Complete Database Analysis](#3-complete-database-analysis)
4. [Complete Function Analysis](#4-complete-function-analysis)
5. [Laravel Architecture Explanation](#5-laravel-architecture-explanation)
6. [Complete Feature Flow Documentation](#6-complete-feature-flow-documentation)
7. [Complete Email System Documentation](#7-complete-email-system-documentation)
8. [Queue & Background Processing System](#8-queue--background-processing-system)
9. [Route Documentation](#9-route-documentation)
10. [Developer Change Guide](#10-developer-change-guide)
11. [Debugging Manual](#11-debugging-manual)
12. [Visual Diagrams](#12-visual-diagrams)

---

---

## 1. COMPLETE SYSTEM UNDERSTANDING

### 1.1 What This Project Does

**Believe Exam Portal** is a full-stack web application built for **Believe Learning Center (BLC)**, a multi-year academic institution. It is a complete digital examination management system that replaces paper-based exams.

**The system handles:**
- Creating and managing academic courses by year level, semester, and major
- Teacher-created exams with encrypted questions and answers
- Admin approval and scheduling of exams
- Students taking online exams in a controlled, secure browser environment
- Real-time anti-cheat monitoring with violation tracking and exam termination
- Automatic grading immediately after submission
- Result management with PASSED / FAILED / ABSENT / DISQUALIFIED statuses
- A complete email system (outgoing SMTP + incoming IMAP inbox)
- In-app notification system with live polling
- Academic year tracking with student year records

### 1.2 Who Uses the System

There are exactly **3 user roles**, defined in the `roles` table and referenced via `RoleSlug` constants:

| Role | Slug | Access Area | Key Capabilities |
|------|------|-------------|-----------------|
| Administrator | `admin` | `/admin/*` | Full control — users, courses, exams, email, results |
| Teacher | `teacher` | `/teacher/*` | Create exams, manage questions, view results |
| Student | `student` | `/student/*` | View courses, take exams, view results |

### 1.3 How Users Interact With the System

```
PUBLIC LANDING PAGE (/)
        |
        v
    LOGIN PAGE (/login)
        |
        +-- Student --> /student/dashboard
        |
        +-- Teacher --> /teacher/dashboard
        |
        +-- Admin   --> /admin/dashboard
```

**Student Journey:**
1. Log in → forced password change if `force_password_change = true`
2. View enrolled courses → `/student/courses`
3. View available exams → `/student/exams`
4. Start exam (creates ExamAttempt) → `/student/exams/{exam}/start`
5. Take exam in fullscreen → `/student/attempt/{attempt}/take`
6. Answers auto-saved via AJAX every selection
7. Submit → graded immediately → redirected to exam show page
8. View result after exam window closes

**Teacher Journey:**
1. Log in → `/teacher/dashboard`
2. Create exam for assigned course → `/teacher/exams/create`
3. Add questions (MCQ, True/False, Fill Blank) → encrypted and stored
4. Submit exam for admin approval
5. View results after admin publishes exam

**Admin Journey:**
1. Log in → `/admin/dashboard`
2. Manage users (students, teachers) → create with temporary passwords
3. Manage courses, majors, enrollments, academic years
4. Review and approve teacher-submitted exams
5. Set exam schedule (open window + duration + attempt limit)
6. Publish exam → students notified
7. Monitor cheating logs and security incidents
8. Manage email inbox (IMAP sync) and send emails


### 1.4 Master Data Flow Diagram

```
USER ACTION
    |
    v
BROWSER (Blade View / AJAX fetch)
    |
    v
HTTP REQUEST (GET / POST / PUT / DELETE)
    |
    v
routes/web.php  ──  Middleware Stack
    |                   |
    |                   +-- auth (session check)
    |                   +-- role:admin|teacher|student
    |                   +-- exam.session (token uniqueness)
    |                   +-- force.password.change
    |                   +-- exam.active (for live exam routes)
    v
CONTROLLER (app/Http/Controllers/)
    |
    +-- Request Validation (validate())
    |
    +-- Service Layer (app/Services/)
    |       |
    |       +-- Business Logic
    |       +-- Eloquent Model calls
    |       +-- Job dispatch (queue)
    |       +-- Notification dispatch
    v
MODEL (app/Models/) via Eloquent ORM
    |
    v
MySQL Database (b_exam)
    |
    v
RESPONSE
    +-- Blade View rendered (HTML)
    +-- JSON response (AJAX)
    +-- Redirect with flash message
```

### 1.5 How All Layers Communicate

| Layer | Communicates With | Method |
|-------|-------------------|--------|
| Browser | Controller | HTTP Request (form POST, AJAX fetch) |
| Controller | Service | Direct PHP method call (dependency injection) |
| Service | Model | Eloquent ORM methods (create, update, find) |
| Model | Database | SQL via PDO (Eloquent translates to SQL) |
| Controller | Queue | `Job::dispatch()` |
| Queue Worker | Service | PHP method call inside `handle()` |
| Service | Email | `EmailService::send()` → `SendEmailJob::dispatch()` |
| Email Job | SMTP | Laravel `Mail::send()` → Brevo SMTP |
| Scheduler | IMAP | `InboxSyncService::sync()` via Webklex IMAP client |
| Service | Notifications | `NotificationService::notify()` → `user_notifications` table |
| Browser | Notifications | JavaScript polling every 30 seconds via Fetch API |
| Anti-cheat JS | Controller | AJAX POST to `/student/attempt/{id}/violation` |


---

## 2. TECHNOLOGY STACK & TECHNICAL IMPLEMENTATION

### 2.1 Backend Technologies

#### A. Laravel Framework

| Detail | Value |
|--------|-------|
| **Version** | Laravel 9.x (`"laravel/framework": "^9.0"`) |
| **PHP Requirement** | PHP ^8.0 |
| **Architecture** | MVC + Service Layer |

**What Laravel is:** Laravel is a PHP web framework that provides routing, ORM, authentication, queues, mail, and CLI tooling out of the box.

**Why used here:** It provides the complete infrastructure needed — session auth, CSRF protection, Eloquent ORM for all database operations, queued email jobs, and Artisan scheduled commands for background tasks.

**Where it is used:** Everywhere. All PHP files in `app/` follow Laravel conventions.

**Request Lifecycle in this project:**
```
1. Browser sends HTTP request to public/index.php
2. Laravel bootstraps: loads .env, registers service providers
3. bootstrap/app.php creates the Application instance
4. HTTP Kernel (app/Http/Kernel.php) runs global middleware
5. Router matches the URL to a route in routes/web.php
6. Route-specific middleware runs (auth, role, exam.session, etc.)
7. Controller method is called
8. Controller calls Services → Models → Database
9. Response returned (View or JSON)
```

**MVC Implementation:**
- **Models** → `app/Models/` — data + relationships
- **Views** → `resources/views/` — Blade templates
- **Controllers** → `app/Http/Controllers/` — request/response coordination

**Routing System:**
- All web routes defined in `routes/web.php`
- API routes (Sanctum-protected) in `routes/api.php`
- Named routes used throughout (`route('admin.exams.index')`)
- Route groups used for role-based prefix organization:
  - `prefix('admin')->middleware('role:admin')` 
  - `prefix('teacher')->middleware('role:teacher,admin')`
  - `prefix('student')->middleware('role:student')`

**Controller Structure:**
- Organized by role: `Admin/`, `Teacher/`, `Student/`, `Auth/`
- Each controller uses constructor Dependency Injection for services
- Example: `ExamController` injects `ActivityLogService` and `NotificationService`

**Middleware System (registered in `app/Http/Kernel.php`):**

| Middleware Class | Alias | Purpose |
|-----------------|-------|---------|
| `Authenticate` | `auth` | Verify user is logged in |
| `RoleMiddleware` | `role` | Check user role slug matches allowed roles |
| `EnsureSingleExamSession` | `exam.session` | Prevent concurrent student sessions |
| `EnsureExamActive` | `exam.active` | Block requests to finished exam attempts |
| `ForcePasswordChange` | `force.password.change` | Redirect to password change page |
| `RedirectIfAuthenticated` | `guest` | Block logged-in users from login page |
| `VerifyCsrfToken` | *(global)* | CSRF token verification on all POST/PUT/DELETE |


**Authentication System:**
- Uses Laravel's built-in `Auth` facade with session-based authentication
- `AuthController::login()` calls `Auth::attempt($credentials)`
- Session is regenerated on login: `$request->session()->regenerate()`
- Custom login security: account lock after 3 failed attempts for 10 minutes
- Force password change flag (`force_password_change`) for admin-created accounts
- Temporary password expiry (24 hours) for admin-created accounts
- OTP-based forgot password via `ForgotPasswordController`

**Authorization System:**
- Role-based: `RoleMiddleware` checks `user->role->slug` against allowed roles
- Object-level: Controllers use `abort(403)` checks like `$exam->teacher_id !== auth()->id()`
- No Laravel Gates/Policies used — authorization is manual in controllers

**Validation System:**
- All input validated using `$request->validate([...])` in controllers
- Rules: `required`, `exists:table,column`, `integer`, `string|max:255`, `email|unique:users`
- Custom error messages in some controllers

**Eloquent ORM:**
- All database access goes through Eloquent Model classes
- Relationships: `hasMany`, `belongsTo`, `hasOne`, `belongsToMany`
- SoftDeletes used on: `User`, `Exam`, `Question`, `Course`
- Scopes used: `where('is_active', true)`, `whereHas('role', fn...)`
- `updateOrCreate` used in `GradingService` for idempotent result storage

**Migration System:**
- 44 migration files covering the full DB schema evolution
- Naming convention: `YYYY_MM_DD_HHMMSS_description.php`
- Main exam tables created in `2024_05_25_000001_create_examination_system_tables.php`

**Artisan Commands (custom):**
- `EmailStats` — email statistics reporting
- `MarkAbsentResults` — creates ABSENT result records for no-shows
- `NotifyStudentResults` — sends result notifications after exam windows close
- `SyncInbox` — manually triggers IMAP inbox synchronization

#### B. PHP

| Detail | Value |
|--------|-------|
| **Version** | PHP ^8.0 (uses PHP 8 features) |
| **OOP Style** | Full OOP with PSR-4 autoloading |

**PHP 8 Features Used:**
- Named arguments: `$this->send(toEmail: ..., toName: ...)`
- Match expressions: `match($user->role->slug) { 'admin' => ... }`
- Constructor property promotion: `public function __construct(private EmailService $emailService)`
- Nullsafe operator: `$user->role?->slug`
- Union types: `string|array|null $description`
- First-class callable syntax in some closures

**OOP Patterns:**
- **Classes:** Every model, controller, service, job, middleware is a class
- **Interfaces:** `ShouldQueue` interface implemented by all Job classes
- **Traits:** `HasFactory`, `Notifiable`, `SoftDeletes`, `HasApiTokens` used on User model
- **Namespaces:** PSR-4 namespacing — `App\Models`, `App\Services`, `App\Http\Controllers`
- **Dependency Injection:** All services injected via constructor in controllers

**Composer Dependency Management:**
- `composer.json` defines all PHP package dependencies
- Autoloading configured for `App\` → `app/`, `Database\Factories\` → `database/factories/`
- `vendor/autoload.php` loaded by Laravel bootstrap


### 2.2 Frontend Technologies

#### A. Blade Template Engine

**What it is:** Laravel's built-in server-side templating language. Blade files have `.blade.php` extension and compile to plain PHP.

**Why used:** It allows dynamic HTML generation with PHP variables, loops, conditionals, and template inheritance — all server-rendered.

**Layout Structure:**
```
resources/views/layouts/app.blade.php   ← Master layout
    ├── resources/views/partials/admin-sidebar.blade.php
    ├── resources/views/partials/teacher-sidebar.blade.php
    ├── resources/views/partials/student-sidebar.blade.php
    └── @yield('content')  ← Each page fills this
```

**Template Inheritance:**
- Master layout: `resources/views/layouts/app.blade.php`
- Every page extends it: `@extends('layouts.app')`
- Page-specific content fills: `@section('content') ... @endsection`
- Page title: `@section('title', 'Exam List')`
- Additional CSS: `@push('styles') ... @endpush`
- Additional JS: `@push('scripts') ... @endpush`

**Key Blade Directives Used:**
- `@auth / @endauth` — show content only when logged in
- `@foreach / @endforeach` — iterate collections
- `@if / @elseif / @else / @endif` — conditionals
- `@csrf` — CSRF token hidden field
- `@method('PUT')` — method spoofing for HTML forms
- `@yield('section')` — define extensible sections
- `@include('partials.admin-sidebar')` — include sub-views
- `@stack('scripts') / @push('scripts')` — inject scripts

**Passing Data from Controllers to Views:**
```php
// Controller
return view('admin.exams.index', compact('exams', 'courses', 'yearLevels'));

// Blade accesses it as:
{{ $exams->count() }}
@foreach($exams as $exam) ... @endforeach
```

#### B. CSS Technologies

**Bootstrap 5.3.3** — loaded from CDN in `layouts/app.blade.php`
- Used for: grid system, buttons, alerts, tables, modals, forms, badges
- Files: No local Bootstrap — pure CDN import
- Why: Rapid responsive UI without writing custom grid code

**Custom CSS Theme** — `public/css/believe-theme.css`
- The project has a custom BLC theme with CSS variables
- Variables: `--blc-royal` (primary blue `#2d27a0`), `--surface`, `--border-2`, `--text-primary`
- Dark mode support via `data-theme="dark"` on `<html>` element, toggled by localStorage
- Custom sidebar styles, topbar, notification bell, form components
- The theme is the primary styling — Bootstrap is used for layout/components only

**Bootstrap Icons 1.11.3** — loaded from CDN
- Used throughout for icons: `<i class="bi bi-bell"></i>`

#### C. JavaScript Technologies

**Vanilla JavaScript** — used in `layouts/app.blade.php` inline and in separate JS files

**Key JS features used:**
- `fetch()` API for all AJAX calls (no jQuery AJAX)
- `localStorage` for theme persistence
- `navigator.sendBeacon()` for exam disconnect on page unload
- `setInterval` / `clearInterval` for timer countdowns
- `document.fullscreenElement` / `requestFullscreen()` for fullscreen management
- `document.addEventListener()` for anti-cheat event listeners
- `document.querySelector()` / `querySelectorAll()` for DOM manipulation

**jQuery 3.7.1** — loaded from CDN (used only for DataTables initialization)

**DataTables 1.13.8** — loaded from CDN
- Applied to tables with class `.datatable`
- Provides search, pagination, sorting automatically
- Configuration in `layouts/app.blade.php`

**exam-anticheat.js** — `public/js/exam-anticheat.js` (808 lines)
- The most complex JS file in the project
- Loaded only on the exam take page (`student/exam/take.blade.php`)
- Detailed coverage in Section 6.4 (Anti-Cheat System)

**profile.js** — `public/js/profile.js`
- Handles profile photo upload and OTP verification logic

**question-builder.js** — `public/js/question-builder.js`
- Dynamic question/answer form builder for teacher exam creation

**Frontend-Backend Communication:**
- Standard form submissions for CRUD operations
- AJAX `fetch()` for: answer saving, violation reporting, notification polling, inbox polling
- All AJAX requests include `X-CSRF-TOKEN` header from `<meta name="csrf-token">`
- Responses: JSON objects with `success`, `terminated`, `message`, `redirect` keys


### 2.3 Database Technologies

**Database:** MySQL / MariaDB
**Driver:** `DB_CONNECTION=mysql` (configured in `.env`)
**Default DB Name:** `b_exam`
**Default Host:** `127.0.0.1:3306`

**Data Flow:**
```
Laravel Model
    |
    v  (Eloquent ORM translates PHP to SQL)
Eloquent ORM (Illuminate\Database\Eloquent)
    |
    v  (PDO executes the query)
SQL Query (SELECT, INSERT, UPDATE, DELETE)
    |
    v
MySQL/MariaDB Database (b_exam)
    |
    v  (PDO result set returned)
PHP array / Collection
    |
    v
Eloquent Model instance(s) returned to controller
```

**Eloquent features actively used:**
- `Model::create([...])` — insert
- `$model->update([...])` — update
- `Model::find($id)` — fetch by primary key
- `Model::where()->get()` — collection fetch
- `Model::with(['relation'])` — eager loading (prevents N+1)
- `Model::lockForUpdate()->find($id)` — SELECT FOR UPDATE (used in `ExamSecurityService`)
- `DB::transaction(function() {...})` — wraps multiple writes atomically
- `DB::afterCommit(function() {...})` — sends emails/notifications only after DB commit

**Query Builder used for raw-ish queries:**
- `DB::table('inbox_sync_state')->updateOrInsert(...)`

### 2.4 Authentication & Security Technologies

**Session-based Authentication:**
- `SESSION_DRIVER=file` (stored in `storage/framework/sessions/`)
- `SESSION_LIFETIME=120` minutes
- Sessions regenerated on login and invalidated on logout

**Password Hashing:**
- `Hash::make($password)` — bcrypt hashing (Laravel default)
- Comparison: `Auth::attempt($credentials)` handles hash comparison

**CSRF Protection:**
- `VerifyCsrfToken` middleware active globally
- Every form has `@csrf` (renders `<input type="hidden" name="_token">`)
- AJAX calls include `X-CSRF-TOKEN` header from meta tag

**Login Security Features (implemented in `User` model + `AuthController`):**
- Max 3 failed login attempts before 10-minute account lock
- `failed_login_attempts` and `locked_until` columns track this
- `isLocked()`, `incrementFailedLogins()`, `resetFailedLogins()` helper methods
- Temporary password expiry (24 hours) — `temporary_password_expires_at`
- 60-second cooldown between temp password re-requests

**Exam Session Security (`EnsureSingleExamSession` middleware):**
- Each student has an `exam_session_token` in the `users` table
- A matching token is stored in the browser session
- If the tokens differ → concurrent session detected → forced logout

**Content Encryption (`EncryptionService`):**
- All question content and answer content stored encrypted
- Uses Laravel's `Crypt::encryptString()` (AES-256-CBC with app key)
- Decrypted on-demand via `getDecryptedContentAttribute()` accessor
- Students can only decrypt when `ExamAccessService::canDecryptQuestions()` returns true

**Role-Based Access Control:**
- `RoleMiddleware` checks `$user->role->slug` against allowed roles
- Three role slugs: `admin`, `teacher`, `student` (defined in `RoleSlug` enum)
- Teachers can also access admin exam routes: `middleware('role:teacher,admin')`

### 2.5 Email Technologies

**Outgoing Email Provider:** Brevo (Sendinblue) SMTP
```
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
```

**Laravel Mail System:**
- `Mail::send()` used in `EmailService::deliver()` with a raw HTML body
- All emails go through `EmailService` → `SendEmailJob` → `EmailService::deliver()`
- Every email creates an `EmailLog` record (status: queued → sent/failed)

**Incoming Email (IMAP):**
- Package: `webklex/laravel-imap: ^6.2`
- Protocol: IMAP over SSL (port 993)
- Config: `config/imap.php` — account `default`
- `InboxSyncService::sync()` — connects, fetches UIDs above last checkpoint, saves to `inbox_emails`

**Queue-based Email:**
- `QUEUE_CONNECTION=database` — jobs stored in `jobs` table
- Queue name: `emails` — both `SendEmailJob` and `InboxSyncJob` use this queue
- Worker command: `php artisan queue:work --queue=emails`


### 2.6 Package Dependency Analysis

#### composer.json — Production Dependencies

| Package | Version | Purpose | Where Used |
|---------|---------|---------|-----------|
| `laravel/framework` | ^9.0 | Core Laravel framework | Entire application |
| `laravel/sanctum` | ^2.14 | API token authentication | `routes/api.php` — API endpoints |
| `laravel/tinker` | ^2.7 | REPL for local debugging | Development only |
| `barryvdh/laravel-dompdf` | ^3.1 | PDF generation | Certificate/report generation [Needs verification on active use] |
| `fruitcake/laravel-cors` | ^2.0.5 | CORS headers for API | API routes |
| `guzzlehttp/guzzle` | ^7.2 | HTTP client | Laravel internals, mail |
| `maatwebsite/excel` | 3.1.* | Excel import/export | `QuestionImportService` — import questions from files |
| `simplesoftwareio/simple-qrcode` | ^4.2 | QR code generation | Certificate QR tokens [Needs verification on active use] |
| `webklex/laravel-imap` | ^6.2 | IMAP email reading | `InboxSyncService` — reads Gmail/email inbox |

#### composer.json — Development Dependencies

| Package | Purpose |
|---------|---------|
| `fakerphp/faker` | Fake data for database seeders |
| `laravel/sail` | Docker dev environment |
| `mockery/mockery` | Mocking in tests |
| `nunomaduro/collision` | Better CLI error display |
| `phpunit/phpunit` | Unit and feature testing |
| `spatie/laravel-ignition` | Enhanced error pages |

#### package.json — Frontend Dependencies

| Package | Version | Purpose | Where Used |
|---------|---------|---------|-----------|
| `axios` | ^0.25 | HTTP client (JS) | Available but Fetch API used instead in most places |
| `laravel-mix` | ^6.0.6 | Webpack asset compilation | `webpack.mix.js` — compiles CSS/JS |
| `lodash` | ^4.17.19 | Utility functions | Available in JS |
| `postcss` | ^8.1.14 | CSS post-processing | Laravel Mix pipeline |

**CDN Libraries (loaded in `layouts/app.blade.php`):**
- Bootstrap 5.3.3 (CSS + JS)
- Bootstrap Icons 1.11.3
- jQuery 3.7.1
- DataTables 1.13.8 with Bootstrap 5 styling
- Google Fonts (Inter)

### 2.7 Development Environment & Tools

| Tool | Purpose | Usage |
|------|---------|-------|
| **XAMPP** | Local Apache + MySQL + PHP server | Running the application locally |
| **Composer** | PHP dependency manager | `composer install`, `composer update` |
| **NPM** | Node.js package manager | `npm install`, `npm run dev` |
| **Laravel Mix (Webpack)** | JS/CSS asset bundler | `npm run dev` / `npm run production` |
| **Laravel Artisan** | CLI tool | Migrations, queue, cache, commands |
| **Git** | Version control | `.git/` directory exists — project is version-controlled |

**Required PHP Extensions:**
- `pdo_mysql` — database connection
- `mbstring` — string handling
- `openssl` — encryption (`Crypt::encryptString`)
- `imap` — IMAP email reading (Webklex)
- `fileinfo` — file upload detection
- `gd` or `imagick` — image processing [Needs verification]
- `zip` — for Excel import (Maatwebsite)

**Key Artisan Commands:**
```bash
php artisan migrate                    # Run pending migrations
php artisan queue:work --queue=emails  # Start email queue worker
php artisan schedule:run               # Run scheduled tasks (every minute via cron)
php artisan inbox:sync                 # Manually sync IMAP inbox
php artisan results:notify-students    # Send result notifications
php artisan results:mark-absent        # Create absent records
php artisan tinker                     # Interactive REPL
```

### 2.8 Software Architecture Patterns

| Pattern | Where Used | Why |
|---------|-----------|-----|
| **MVC** | Entire app | Standard Laravel — separation of data, logic, presentation |
| **Service Layer** | `app/Services/` (15 services) | Keeps controllers thin; business logic reusable across controllers |
| **Repository Pattern** | NOT used | Direct Eloquent in services is the chosen approach |
| **Event-Driven Pattern** | `NewEmailReceived` event + listeners | Decouples inbox sync from broadcast |
| **Job Queue Pattern** | `SendEmailJob`, `InboxSyncJob` | Async email sending; avoids blocking HTTP responses |
| **Observer Pattern** | NOT explicitly used | Eloquent events not implemented |
| **Dependency Injection** | All controllers, services, commands | Laravel IoC container resolves dependencies automatically |
| **Strategy Pattern** | `EmailService::resolveRecipients()` | Different recipient groups resolved by string key |


---

## 3. COMPLETE DATABASE ANALYSIS

### 3.1 Database Overview

**Database name:** `b_exam`  
**Total tables (from migrations):** 30+ tables  
**ORM:** Eloquent (Laravel)  
**Migration count:** 44 files

### 3.2 Complete Table-by-Table Analysis

---

#### TABLE: `users`

**Purpose:** Stores all system users — admins, teachers, and students.  
**Why it exists:** Single user table for all roles. Role is determined by `role_id` FK.

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Primary key |
| `name` | string | Full name |
| `email` | string unique | Login email |
| `email_verified_at` | timestamp nullable | Email verification timestamp |
| `password` | string | Bcrypt-hashed password |
| `role_id` | FK → roles | Determines admin/teacher/student |
| `is_active` | boolean | Soft-enable/disable without deleting |
| `phone` | string nullable | Contact number |
| `academic_year` | integer | Legacy field — student's year level integer |
| `exam_session_token` | string nullable | Used by `EnsureSingleExamSession` middleware |
| `last_login_at` | datetime nullable | Tracked on every successful login |
| `profile_photo` | string nullable | Path in `storage/app/public/` |
| `failed_login_attempts` | integer | Count of consecutive failed logins |
| `locked_until` | datetime nullable | Account locked until this time |
| `temporary_password_expires_at` | datetime nullable | Expiry for admin-assigned temp passwords |
| `temp_password_last_requested_at` | datetime nullable | Rate-limit temp password requests |
| `force_password_change` | boolean | Forces password change on next login |
| `deleted_at` | timestamp nullable | SoftDeletes — record kept but invisible |
| `remember_token` | string nullable | Laravel "remember me" feature |
| `created_at`, `updated_at` | timestamps | Standard Laravel timestamps |

**Relationships:**
```
users hasMany courses          (as teacher_id)
users hasMany enrollments      (as student_id)
users hasMany examAttempts     (as student_id)
users hasMany examsAsTeacher   (as teacher_id)
users hasMany notifications    (user_notifications)
users hasMany studentYearRecords
users belongsTo roles
```

**Data Lifecycle:**
- Created by: Admin via `UserController::store()` or self-registration via `AuthController::register()`
- Updated by: Admin edits, profile photo upload, password change, login tracking
- Soft-deleted by: Admin `terminate()` action — sets `deleted_at`
- Restored by: Admin `restore()` action — clears `deleted_at`

**Used by:**
- Controllers: All controllers via `auth()->user()`, `UserController`, `StudentController`, `TeacherController`
- Services: `ActivityLogService`, `ExamSecurityService`, `EmailService`, `NotificationService`
- Views: Every authenticated page (sidebar shows user name/role)

---

#### TABLE: `roles`

**Purpose:** Role lookup table for role-based access control.  
**Why it exists:** Normalizes role names; allows clean slug-based checking.

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Primary key |
| `name` | string unique | Display name e.g. "Administrator" |
| `slug` | string unique | Machine key: `admin`, `teacher`, `student` |

**Seeded values:** `admin`, `teacher`, `student`  
**Used by:** `RoleMiddleware`, `User::isAdmin()`, `User::isTeacher()`, `User::isStudent()`

---

#### TABLE: `courses`

**Purpose:** Academic courses that students enroll in and teachers teach.

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Primary key |
| `title` | string | Course name e.g. "Introduction to Programming" |
| `code` | string unique | Short code e.g. "CS101" |
| `description` | text nullable | Course description |
| `teacher_id` | FK → users | Assigned teacher |
| `created_by` | FK → users | Who created this course |
| `is_active` | boolean | Visible to students only when active |
| `year_level` | integer | 0=All, 1=First Year … 5=Fifth Year |
| `academic_year_id` | FK → academic_years nullable | Which AY this course belongs to |
| `semester` | integer | 0=Both, 1=Semester 1, 2=Semester 2 |
| `major_id` | FK → majors nullable | null=all majors (Year 1), set for Year 2+ |
| `deleted_at` | timestamp nullable | SoftDeletes |

**Relationships:**
```
courses belongsTo users (teacher)
courses belongsTo academic_years
courses belongsTo majors
courses hasMany enrollments
courses hasMany exams
courses belongsToMany users (students via enrollments)
```

**Used by:** `CourseController` (Admin), `StudentCourseController`, `TeacherProfileController`


---

#### TABLE: `enrollments`

**Purpose:** Tracks which students are enrolled in which courses.  
**Why it exists:** Many-to-many pivot between users (students) and courses.

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Primary key |
| `course_id` | FK → courses | The course being enrolled into |
| `student_id` | FK → users | The student enrolling |
| `enrolled_at` | datetime | When the enrollment occurred |
| `year` | integer | Legacy year level integer (1–5), kept for backward compat |
| `year_level_id` | FK → year_levels nullable | Proper relational FK replacing integer `year` |
| `major_id` | FK → majors nullable | null for Year 1; set for Year 2+ |

**Unique constraint:** `(course_id, student_id)` — a student can only enroll once per course.

**Relationships:**
```
enrollments belongsTo courses
enrollments belongsTo users (student)
enrollments belongsTo year_levels
enrollments belongsTo majors
```

**Used by:** `EnrollmentController` (Admin), `ExamAccessService::studentCanTakeExam()`, `AcademicService`  
**Views:** `admin/enrollments/index.blade.php`

---

#### TABLE: `exams`

**Purpose:** The central exam entity — links a course, teacher, and set of questions.

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Primary key |
| `course_id` | FK → courses | Which course this exam belongs to |
| `academic_year_id` | FK → academic_years nullable | Locks exam to a specific academic year |
| `teacher_id` | FK → users | Who created the exam |
| `title` | string | Exam name |
| `description` | text nullable | Optional description |
| `status` | enum | `draft` → `pending_approval` → `approved` → `published` → `closed` |
| `total_marks` | integer | Total marks the exam is worth |
| `passing_marks` | integer | Minimum marks required to pass |
| `shuffle_questions` | boolean | Whether questions are randomized per student |
| `submitted_at` | datetime nullable | When teacher submitted for approval |
| `approved_at` | datetime nullable | When admin approved |
| `approved_by` | FK → users nullable | Which admin approved it |
| `deleted_at` | timestamp nullable | SoftDeletes |

**Status Flow:**
```
draft → pending_approval → approved → published → closed
  ^                                        |
  |_______(admin can reopen)_______________|
```

**Relationships:**
```
exams belongsTo courses
exams belongsTo academic_years
exams belongsTo users (teacher)
exams hasMany questions
exams hasMany exam_schedules
exams hasOne activeSchedule (published schedule)
exams hasMany exam_attempts
exams hasMany results
```

**Used by:**  
- Controllers: `Admin\ExamController`, `Teacher\ExamController`, `Student\ExamController`  
- Services: `ExamAccessService`, `GradingService`, `ExamSecurityService`  
- Views: `admin/exams/`, `teacher/exams/`, `student/exams/`

---

#### TABLE: `exam_schedules`

**Purpose:** Defines the open window, duration, and attempt limit for an exam.  
**Why it exists:** Separates scheduling metadata from the exam itself. One exam = one schedule (immutable once set).

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Primary key |
| `exam_id` | FK → exams | The exam this schedule belongs to |
| `starts_at` | datetime | When students can start taking the exam |
| `ends_at` | datetime | When the exam window closes |
| `duration_minutes` | integer | Personal time limit per student |
| `attempt_limit` | integer | How many attempts each student gets (default 1) |
| `target_year` | integer nullable | Restrict to a specific year level |
| `is_published` | boolean | Whether the schedule is active for students |
| `published_at` | datetime nullable | When admin published it |
| `published_by` | FK → users nullable | Which admin published it |

**Key Business Rule:** `expires_at` for an attempt = `MIN(started_at + duration_minutes, ends_at)`.  
This means a student who starts late gets less time — they can never exceed the window end.

**Relationships:**
```
exam_schedules belongsTo exams
exam_schedules hasMany exam_attempts
```

**Used by:**  
- Controllers: `Admin\ExamController::schedule()`, `::publish()`, `::close()`  
- Services: `ExamAccessService::isScheduleActive()`, `SessionRecoveryService`  
- Middleware: `EnsureExamActive`

---

#### TABLE: `questions`

**Purpose:** Stores exam questions with encrypted content.

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Primary key |
| `exam_id` | FK → exams | Which exam this question belongs to |
| `category_id` | FK → question_categories nullable | Optional grouping category |
| `type` | enum | `mcq`, `true_false`, `fill_blank`, `essay` |
| `content_encrypted` | longText | AES-256 encrypted question text |
| `attachment_path` | string nullable | Path to attached image/file |
| `attachment_name` | string nullable | Original filename |
| `attachment_mime` | string nullable | MIME type of attachment |
| `difficulty` | string nullable | `easy`, `medium`, `hard` (optional) |
| `marks` | integer | Marks awarded for correct answer |
| `order` | integer | Display order within the exam |
| `deleted_at` | timestamp nullable | SoftDeletes |

**Encryption:** Content is encrypted with `EncryptionService::encrypt()` on save.  
Accessed via `$question->decrypted_content` accessor (auto-decrypts using app key).

**Relationships:**
```
questions belongsTo exams
questions hasMany answers
```

**Used by:**  
- Controllers: `Teacher\ExamController::addQuestion()`, `::updateQuestion()`  
- Services: `GradingService`, `ExamAccessService`, `EncryptionService`, `QuestionImportService`  
- Views: `teacher/exams/show.blade.php`, `student/exam/take.blade.php`

---

#### TABLE: `answers`

**Purpose:** Stores the answer options for each question — also encrypted.

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Primary key |
| `question_id` | FK → questions | Which question this answer belongs to |
| `content_encrypted` | longText | AES-256 encrypted answer text |
| `is_correct` | boolean | Whether this is the correct answer |
| `is_blank_answer` | boolean | For `fill_blank` type — marks accepted blank answers |
| `order` | integer | Display order |

**Encryption:** Same as questions — decrypted via `$answer->decrypted_content` accessor.

**Relationships:**
```
answers belongsTo questions
```

**Used by:**  
- Services: `GradingService` — checks `answer->is_correct` and `answer->is_blank_answer`  
- Controllers: `Teacher\ExamController::saveAnswers()`, `::saveBlankAnswers()`  
- Views: `student/exam/take.blade.php` — renders answer options

---

#### TABLE: `exam_attempts`

**Purpose:** Records each student's attempt at an exam. The most critical runtime table.

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Primary key |
| `exam_id` | FK → exams | Which exam is being attempted |
| `schedule_id` | FK → exam_schedules | Which schedule window this attempt falls under |
| `student_id` | FK → users | The student taking the exam |
| `attempt_number` | integer | 1, 2, 3… (if multiple attempts allowed) |
| `status` | enum | See status values below |
| `warning_count` | tinyint | Number of anti-cheat violations (0–3) |
| `started_at` | datetime nullable | When the student clicked Start |
| `submitted_at` | datetime nullable | When submitted or auto-submitted |
| `expires_at` | datetime nullable | `MIN(started_at + duration, ends_at)` — hard deadline |
| `session_token` | string nullable | Cleared on submit to prevent re-entry |
| `terminated_at` | datetime nullable | When security service terminated it |
| `approved_by` | FK → users nullable | Admin who restored a `terminated_pending_review` attempt |
| `approved_at` | datetime nullable | When approved |
| `approval_comment` | text nullable | Admin note on approval |
| `rejected_by` | FK → users nullable | Admin who rejected it |
| `rejected_at` | datetime nullable | When rejected |
| `rejection_comment` | text nullable | Admin note on rejection |
| `disconnected_at` | datetime nullable | Set when browser closes; cleared on reconnect |
| `last_question_id` | integer nullable | Last question viewed before disconnect |
| `question_order` | JSON nullable | Shuffled question ID array for this attempt |

**Status values:**
- `in_progress` — student is actively taking the exam
- `submitted` — student finished normally
- `terminated` — terminated by 3rd violation (final, no review)
- `suspicious` — legacy status from old `CheatingDetectionService` (≥3 warnings via old path)
- `terminated_pending_review` — terminated, waiting for admin review [Needs verification if still active path]
- `rejected` — admin reviewed and rejected the attempt

**Relationships:**
```
exam_attempts belongsTo exams
exam_attempts belongsTo exam_schedules
exam_attempts belongsTo users (student)
exam_attempts hasMany student_answers
exam_attempts hasOne results
exam_attempts hasMany cheating_logs
exam_attempts hasMany session_recovery_logs
```

**Used by:**  
- Controllers: `Student\ExamController::start()`, `ExamSessionController` (all methods)  
- Services: `ExamSecurityService`, `SessionRecoveryService`, `GradingService`, `ExamAccessService`  
- Middleware: `EnsureExamActive`, `EnsureSingleExamSession`  
- Views: `student/exam/take.blade.php`, `admin/cheating-logs/`

---

#### TABLE: `student_answers`

**Purpose:** Stores each answer a student selected or typed during an exam attempt.

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Primary key |
| `attempt_id` | FK → exam_attempts | Which attempt this answer belongs to |
| `question_id` | FK → questions | Which question was answered |
| `answer_id` | FK → answers nullable | Selected answer for MCQ/True-False |
| `answer_text` | longText nullable | Typed answer for fill_blank/essay |
| `file_path` | string nullable | Uploaded file path for file_upload type |
| `is_correct` | boolean nullable | Set by `GradingService` after submission |
| `marks_awarded` | integer nullable | Marks given for this answer |

**Unique constraint:** `(attempt_id, question_id)` — one answer per question per attempt.  
Uses `updateOrCreate` in `ExamSessionController::saveAnswer()` — AJAX auto-save.

**Relationships:**
```
student_answers belongsTo exam_attempts
student_answers belongsTo questions
student_answers belongsTo answers
```

**Data Lifecycle:**
- Created/updated: Every time student selects/types an answer via AJAX auto-save
- Updated again: `GradingService` sets `is_correct` and `marks_awarded` after submission
- Never deleted: Permanent record even after exam ends

**Used by:**  
- Controllers: `ExamSessionController::saveAnswer()`  
- Services: `GradingService::gradeAttempt()` — iterates all answers to calculate score  
- Views: `student/exam/take.blade.php` — pre-fills saved answers on page load

---

#### TABLE: `results`

**Purpose:** Stores the final graded result for each exam attempt.

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Primary key |
| `attempt_id` | FK → exam_attempts nullable | The attempt this result comes from (null = ABSENT) |
| `exam_id` | FK → exams | Which exam |
| `student_id` | FK → users | Which student |
| `total_marks` | integer | Total marks possible |
| `obtained_marks` | integer | Marks the student got |
| `percentage` | decimal(5,2) | `(obtained / total) × 100` |
| `grade` | enum nullable | A/B/C/D/F (legacy — grading removed from code) |
| `is_passed` | boolean | `obtained_marks >= passing_marks` |
| `is_published` | boolean | Whether student can see it (always `true` after grading) |
| `exam_result_status` | string | `PASSED`, `FAILED`, `ABSENT`, `DISQUALIFIED` |
| `violation_reason` | text nullable | For DISQUALIFIED — what violation caused it |
| `disqualified_at` | datetime nullable | When DISQUALIFIED status was set |
| `attendance_status` | string | `attended` or `absent` |
| `exam_finished_at` | datetime nullable | When exam ended |

**Status Constants (in `Result` model):**
- `STATUS_PASSED = 'PASSED'`
- `STATUS_FAILED = 'FAILED'`
- `STATUS_ABSENT = 'ABSENT'`
- `STATUS_DISQUALIFIED = 'DISQUALIFIED'`

**Relationships:**
```
results belongsTo exam_attempts
results belongsTo exams
results belongsTo users (student)
```

**Used by:**  
- Services: `GradingService::gradeAttempt()` — creates/updates on submission  
- Commands: `MarkAbsentResults` — creates ABSENT records, `NotifyStudentResults`  
- Controllers: `Admin\ResultController`, `Teacher\ResultController`, `Student\ResultController`  
- Views: `admin/results/`, `teacher/exams/results.blade.php`, `student/results/`

---

#### TABLE: `cheating_logs`

**Purpose:** Records every detected anti-cheat violation during an exam attempt.

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Primary key |
| `attempt_id` | FK → exam_attempts | Which attempt triggered the violation |
| `student_id` | FK → users | Which student |
| `violation_type` | string | e.g. `tab_switch`, `fullscreen_exit`, `devtools_shortcut`, `window_blur` |
| `details` | text nullable | Human-readable detail from JavaScript |
| `warning_number` | tinyint | Per-type count: how many times this type was logged |
| `user_agent` | string nullable | Browser user-agent string |
| `browser` | string nullable | Parsed browser name |
| `device` | string nullable | Parsed device type |
| `os` | string nullable | Parsed OS name |
| `screen_resolution` | string nullable | Student's screen dimensions |
| `timezone` | string nullable | Browser timezone |
| `ip_address` | string nullable | Request IP |

**Used by:**  
- Services: `ExamSecurityService::persistViolationLog()` (primary), `CheatingDetectionService` (legacy)  
- Controllers: `Admin\CheatingLogController::index()`  
- Views: `admin/cheating-logs/index.blade.php`

---

#### TABLE: `activity_logs`

**Purpose:** Immutable audit trail for all significant system events.

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Primary key |
| `user_id` | FK → users nullable | Who performed the action (null = system) |
| `action` | string | Machine-readable key e.g. `login`, `exam_approved`, `exam_terminated_security` |
| `model_type` | string nullable | Eloquent model class e.g. `App\Models\Exam` |
| `model_id` | bigint nullable | ID of the related model |
| `description` | text nullable | Human text OR JSON metadata for security events |
| `ip_address` | string nullable | Request IP |

**Used by:**  
- Service: `ActivityLogService::log()` — called from all controllers and security services  
- Views: Admin activity log page [Needs verification — no dedicated controller found]

---

#### TABLE: `user_notifications`

**Purpose:** In-app notification inbox for all users.

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Primary key |
| `user_id` | FK → users | Recipient |
| `type` | string | Category: `exam_published`, `exam_approved`, `exam_result`, `cheating`, `security_warning`, etc. |
| `title` | string | Short notification title |
| `message` | text | Full notification text |
| `link` | string nullable | URL to navigate when clicked |
| `is_read` | boolean | Read status |

**Used by:**  
- Service: `NotificationService::notify()` — creates notifications  
- Controllers: `NotificationController` — mark read, mark all read, unread count  
- Views: `layouts/app.blade.php` (notification bell), `notifications/index.blade.php`  
- JS: `layouts/app.blade.php` — polls `/notifications/unread-count` every 30 seconds

---

#### TABLE: `academic_years`

**Purpose:** Represents academic years like "2025-2026".

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Primary key |
| `name` | string | Display name: "2025-2026" |
| `start_year` | year | 2025 |
| `end_year` | year | 2026 |
| `is_current` | boolean | Marks the active academic year |

**Used by:** `AcademicYearController`, `AcademicService`, `ExamAccessService`, course/exam filtering  
**Static helper:** `AcademicYear::current()` returns the one with `is_current = true`

---

#### TABLE: `year_levels`

**Purpose:** Year level definitions (First Year through Fifth Year).

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Primary key |
| `level` | tinyint | Integer 1–5 |
| `name` | string | "First Year", "Second Year", etc. |
| `department` | string nullable | Optional department grouping |
| `major` | string nullable | Optional major name |

---

#### TABLE: `student_year_records`

**Purpose:** Permanent per-student record for each academic year + year level combination. Acts as the student's academic history.

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Primary key |
| `student_id` | FK → users | The student |
| `academic_year_id` | FK → academic_years | Which academic year |
| `year_level_id` | FK → year_levels | Which year level |
| `semester` | string | "1" or "2" |
| `department` | string nullable | Department name |
| `major` | string nullable | Major name (text, not FK) |
| `gpa` | decimal nullable | GPA for this period |
| `status` | enum | `active`, `promoted`, `failed`, `withdrawn` |
| `promoted_at` | timestamp nullable | When promoted to next year |

**Unique constraint:** `(student_id, academic_year_id, year_level_id, semester)`

**Used by:**  
- Services: `AcademicService::enrollStudent()`, `::getStudentHistory()`  
- Services: `EmailService::resolveAcademicRecipients()` — filter email recipients  
- Controllers: `Admin\AcademicYearController`  
- Views: `admin/academic/`

---

#### TABLE: `majors`

**Purpose:** Academic major/specialization options for Year 2+ students.

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Primary key |
| `name` | string | "Computer Science", "Civil Engineering", etc. |
| `code` | string unique | "CS", "CE", etc. |
| `description` | text nullable | Optional description |
| `is_active` | boolean | Whether currently offered |

**Used by:** `Admin\MajorController`, `CourseController`, `EnrollmentController`  
**Views:** `admin/majors/`

---

#### TABLE: `email_logs`

**Purpose:** Records every email sent through the system — audit trail + retry support.

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Primary key |
| `to_email` | string | Recipient email |
| `to_name` | string nullable | Recipient name |
| `from_email` | string | Sender email |
| `from_name` | string nullable | Sender name |
| `subject` | string | Email subject line |
| `body_html` | longText nullable | Full HTML email body |
| `template_slug` | string nullable | Which template was used |
| `event` | string nullable | Trigger event e.g. `welcome_account`, `security_warning` |
| `email_type` | string nullable | Type label e.g. `welcome` |
| `status` | enum | `queued`, `sent`, `failed` |
| `provider` | string | `smtp` |
| `error` | text nullable | Error message if failed |
| `message_id` | string nullable | SMTP message-id header |
| `user_id` | FK → users nullable | Related user |
| `queued_at` | timestamp nullable | When queued |
| `sent_at` | timestamp nullable | When successfully sent |

**Used by:** `EmailService::send()`, `SendEmailJob::handle()`, `Admin\EmailController`  
**Views:** `admin/email/logs.blade.php`, `admin/email/sent.blade.php`

---

#### TABLE: `inbox_emails`

**Purpose:** Stores incoming emails received via IMAP sync from the admin inbox.

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Primary key |
| `from_email` | string | Sender's email address |
| `from_name` | string nullable | Sender's display name |
| `sender_type` | string | `student` (only student emails are imported) |
| `user_id` | FK → users nullable | Matched registered student |
| `subject` | string | Email subject (max 255 chars) |
| `body_html` | longText nullable | HTML body |
| `body_text` | longText nullable | Plain text body |
| `message_id` | string nullable | RFC 2822 Message-ID or `uid:default:INBOX:{uid}` |
| `in_reply_to` | string nullable | RFC 2822 In-Reply-To header |
| `references` | text nullable | RFC 2822 References header chain |
| `thread_id` | string nullable | `md5(root_message_id)` — groups conversation threads |
| `parent_id` | FK → inbox_emails nullable | Direct parent message in thread |
| `status` | string | `unread`, `read`, `replied`, `archived` |
| `category` | string nullable | Optional category tag |
| `replied_by` | FK → users nullable | Admin who replied |
| `replied_at` | datetime nullable | When replied |
| `received_at` | datetime | When email was received |

**Used by:** `InboxSyncService` (populates), `Admin\EmailController` (reads/displays)  
**Views:** `admin/email/inbox.blade.php`

---

#### TABLE: `inbox_sync_state`

**Purpose:** Stores the IMAP UID cursor for incremental sync. Prevents duplicate imports.

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Primary key |
| `account` | string | IMAP account name (default: "default") |
| `folder` | string | Folder name (default: "INBOX") |
| `last_uid` | bigint | Last successfully imported IMAP UID |
| `synced_at` | datetime | When last sync ran |

**Used by:** `InboxSyncService::sync()` — reads and updates on every sync

---

#### TABLE: `profile_otps`

**Purpose:** Stores one-time passwords for profile/security verification.

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Primary key |
| `user_id` | FK → users | The user requesting OTP |
| `otp` | string | The OTP code |
| `type` | string | Purpose type: `profile_change`, `forgot_password`, etc. |
| `expires_at` | datetime | When the OTP expires |
| `used_at` | datetime nullable | When it was consumed |

**Used by:** `ForgotPasswordController`, `ProfileController`  
**Job:** `SendProfileOtpJob` — sends OTP via email  
**Views:** `auth/forgot-password-verify.blade.php`, `profile/show.blade.php`

---

#### TABLE: `jobs`

**Purpose:** Laravel queue jobs waiting to be processed by the queue worker.

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Primary key |
| `queue` | string | Queue name (e.g. `emails`) |
| `payload` | longText | Serialized job data |
| `attempts` | tinyint | How many times this job has been tried |
| `reserved_at` | int nullable | Unix timestamp when worker picked it up |
| `available_at` | int | When the job becomes available |
| `created_at` | int | When the job was created |

**Used by:** All Jobs — `SendEmailJob`, `InboxSyncJob`, `SendWelcomeAccountJob`, etc.  
**Worker command:** `php artisan queue:work --queue=emails`

---

#### TABLE: `failed_jobs`

**Purpose:** Stores jobs that failed after all retry attempts.

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Primary key |
| `uuid` | string unique | Unique job identifier |
| `connection` | text | Queue connection name |
| `queue` | text | Queue name |
| `payload` | longText | Serialized job payload |
| `exception` | longText | Exception message and trace |
| `failed_at` | timestamp | When it failed |

**Used by:** Queue system — jobs with `$tries` exhausted land here  
**Artisan:** `php artisan queue:failed` to list, `php artisan queue:retry uuid` to retry

---

#### TABLE: `session_recovery_logs`

**Purpose:** Audit trail for all exam session disconnect/reconnect events.

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Primary key |
| `attempt_id` | FK → exam_attempts | The attempt that disconnected |
| `student_id` | FK → users | Which student |
| `exam_id` | FK → exams | Which exam |
| `disconnect_reason` | string | `browser_close`, `network_error`, etc. |
| `disconnected_at` | datetime | When the disconnect was recorded |
| `last_question_id` | integer nullable | Last question the student viewed |
| `recovery_status` | string | `pending`, `recovered`, `expired` |
| `browser_info` | text nullable | Browser/platform info |
| `user_agent` | string nullable | Full user-agent string |
| `ip_address` | string nullable | Student's IP |
| `disconnected_duration_seconds` | integer nullable | How long they were disconnected |
| `reconnected_at` | datetime nullable | When they came back |

**Used by:** `SessionRecoveryService` — creates on disconnect, updates on reconnect

---

### 3.3 Complete Entity Relationship Map

```
roles ──────────────── users
                          |
          ┌───────────────┼──────────────────┐
          |               |                  |
       courses       exam_attempts      enrollments
          |               |                  |
          |          ┌────┴────┐        year_levels
       exams         |         |
          |     student_   cheating_
    ┌─────┴───┐  answers    logs
    |         |
questions  exam_
    |     schedules
  answers
          |
       results

academic_years ──── student_year_records ──── year_levels

email_logs ──────── user_notifications ──── inbox_emails
                                                |
                                        inbox_sync_state

profile_otps ─── users
jobs / failed_jobs (queue tables — no FK)
activity_logs ─── users (nullable)
session_recovery_logs ─── exam_attempts
majors ─── courses / enrollments
```

---

### 3.4 Real Usage Tracking — Which Layer Uses Which Table

| Table | Controllers | Services | Jobs | Commands | Views |
|-------|-------------|----------|------|----------|-------|
| `users` | All | All | SendEmailJob | MarkAbsent | All |
| `roles` | UserController | — | — | — | admin/users |
| `courses` | CourseController (Admin/Student/Teacher) | CourseAssignmentService | — | — | admin/courses, student/courses |
| `enrollments` | EnrollmentController | AcademicService | — | MarkAbsent | admin/enrollments |
| `exams` | ExamController (Admin/Teacher/Student) | ExamAccessService, GradingService | — | NotifyResults | admin/exams, teacher/exams, student/exams |
| `exam_schedules` | Admin\ExamController | ExamAccessService, SessionRecoveryService | — | MarkAbsent, NotifyResults | admin/exams/show |
| `questions` | Teacher\ExamController | EncryptionService, QuestionImportService | — | — | teacher/exams/show, student/exam/take |
| `answers` | Teacher\ExamController | GradingService, EncryptionService | — | — | student/exam/take |
| `exam_attempts` | ExamSessionController, Student\ExamController | ExamSecurityService, SessionRecoveryService, GradingService | — | MarkAbsent | student/exam/take, admin/cheating-logs |
| `student_answers` | ExamSessionController | GradingService | — | — | student/exam/take |
| `results` | Admin\ResultController, Teacher\ResultController, Student\ResultController | GradingService, AcademicService | — | MarkAbsent, NotifyResults | all results views |
| `cheating_logs` | Admin\CheatingLogController | ExamSecurityService | — | — | admin/cheating-logs |
| `activity_logs` | — | ActivityLogService | InboxSyncJob | — | — |
| `user_notifications` | NotificationController | NotificationService | — | NotifyResults | layouts/app.blade.php, notifications/index |
| `email_logs` | Admin\EmailController | EmailService | SendEmailJob | EmailStats | admin/email/logs |
| `inbox_emails` | Admin\EmailController | InboxSyncService | InboxSyncJob | SyncInbox | admin/email/inbox |
| `inbox_sync_state` | — | InboxSyncService | — | SyncInbox | — |
| `academic_years` | Admin\AcademicYearController | AcademicService | — | — | admin/academic |
| `student_year_records` | Admin\AcademicYearController | AcademicService, EmailService | — | — | admin/academic, admin/results |
| `profile_otps` | ProfileController, ForgotPasswordController | — | SendProfileOtpJob | — | profile/show, auth/forgot-password |
| `jobs` | — | — | ALL Jobs | — | — |
| `majors` | Admin\MajorController | — | — | — | admin/majors |

---

## 4. COMPLETE FUNCTION ANALYSIS

### 4.1 AuthController

---

**`login(Request $request)`**

| Item | Detail |
|------|--------|
| Purpose | Authenticate a user and redirect to their dashboard |
| Route | `POST /login` |
| Input | `email` (required, email), `password` (required) |

Flow:
```
1. Validate email + password fields
2. Find user by email (no auth yet)
3. Check if account is locked (locked_until > now) → return error with countdown
4. Call Auth::attempt($credentials)
5. If fail → increment failed_login_attempts
6.     If now locked → log 'login_locked', return lock error with timer
7.     Else return error with remaining attempts count
8. If success → check is_active (deactivated → logout + error)
9. Check isTemporaryPasswordExpired() → logout + temp_expired_email flash
10. Reset failed_login_attempts, update last_login_at
11. Log 'login' activity
12. Regenerate session
13. Redirect to role-based dashboard
```

DB operations: `User::where('email')`, `User::update()` (failed attempts, last_login_at)  
Output: Redirect to dashboard or back with errors

---

**`requestNewTemporaryPassword(Request $request)`**

| Item | Detail |
|------|--------|
| Purpose | Issue a new temp password when the old one has expired |
| Route | `POST /login/request-new-password` |
| Guards | force_password_change must be true, not locked, 60s cooldown |

Flow:
```
1. Validate email
2. Log attempt (even on failure — for audit)
3. Find user; check force_password_change = true (else: silent success)
4. Check not locked (show lock timer if locked)
5. Check canRequestNewTempPassword() — 60s cooldown
6. Generate 12-char random password (3 upper+lower+digit+symbol, Fisher-Yates shuffle)
7. Hash and save new password + expiry (+24h) + cooldown timestamp
8. Reset failed_login_attempts and locked_until
9. Dispatch SendNewTemporaryPasswordJob
10. Log 'temp_password_reissued'
11. Return success flash (same message on all paths — prevents email enumeration)
```

---

**`updateForcePasswordChange(Request $request)`**

| Item | Detail |
|------|--------|
| Purpose | Handle forced password change for admin-created accounts |
| Route | `POST /password/change` |

Flow:
```
1. Validate new password (min:8, confirmed, different from current)
2. Hash and save new password
3. Set force_password_change = false
4. Clear temporary_password_expires_at, reset login counters
5. Log 'forced_password_changed'
6. Redirect to role dashboard with success message
```

### 4.2 ExamSessionController

---

**`take(ExamAttempt $attempt)`**

| Item | Detail |
|------|--------|
| Purpose | Render the live exam page for a student |
| Route | `GET /student/attempt/{attempt}/take` |
| Middleware | `auth`, `exam.session`, `exam.active` |

Flow:
```
1. authorizeAttempt() — abort(403) if attempt.student_id != auth user
2. Load schedule for this attempt
3. If disconnected_at is set (disconnect recovery path):
   a. Call SessionRecoveryService::handleReconnect()
   b. If recovery expired → auto-submit + grade + redirect with message
   c. If recovery OK → render exam with frozen timer seconds
4. If expires_at has passed → submitAttempt() + redirect
5. If schedule has ended → submitAttempt() + redirect
6. Compute normal remaining seconds via SessionRecoveryService::computeNormalSeconds()
7. Call renderExamView() with attempt, schedule, effective end time
```

**`renderExamView()` (private helper)**
```
1. Load exam with questions and answers (eager loaded)
2. ExamAccessService::canDecryptQuestions() — abort(403) if not allowed
3. Apply question_order (shuffled array stored at attempt creation)
4. Map questions: decrypt content + decrypt answers via ExamAccessService
5. Load saved answers (for pre-filling)
6. Pass securityPolicy flags (all true — hardcoded from controller)
7. Return view 'student.exam.take' with all data
```

---

**`saveAnswer(Request $request, ExamAttempt $attempt)`**

| Item | Detail |
|------|--------|
| Purpose | Auto-save a student's answer selection via AJAX |
| Route | `POST /student/attempt/{attempt}/save` |
| Middleware | `exam.active` (blocks if not in_progress) |

Flow:
```
1. authorizeAttempt()
2. Validate: question_id (exists), answer_id (nullable), answer_text (nullable)
3. If file uploaded → store in public disk under exams/{exam_id}/attempts/{attempt_id}/
4. StudentAnswer::updateOrCreate(['attempt_id', 'question_id'], [...answer data...])
5. Return JSON {success: true}
```

---

**`violation(Request $request, ExamAttempt $attempt)`**

| Item | Detail |
|------|--------|
| Purpose | Record an anti-cheat violation detected by the browser |
| Route | `POST /student/attempt/{attempt}/violation` |
| Middleware | `exam.active` |

Flow:
```
1. authorizeAttempt()
2. Validate: type (string max:80), details (nullable string max:500)
3. Call ExamSecurityService::recordViolation(attempt.fresh(), type, details, [], ip)
4. Return JSON response from service:
   {warning_count, terminated, locked, message, redirect?}
```

---

**`submit(Request $request, ExamAttempt $attempt)`**

| Item | Detail |
|------|--------|
| Purpose | Student manually submits the exam |
| Route | `POST /student/attempt/{attempt}/submit` |

Flow:
```
1. authorizeAttempt()
2. submitAttempt(attempt):
   a. attempt.update(status=submitted, submitted_at=now)
   b. Clear exam_session_token from users table
   c. Clear session exam_session_token
   d. GradingService::gradeAttempt(attempt.fresh with answers)
3. Redirect to exams.show with success message
```

---

**`disconnect(Request $request, ExamAttempt $attempt)`**

| Item | Detail |
|------|--------|
| Purpose | Record a temporary browser-close / network disconnect |
| Route | `POST /student/attempt/{attempt}/disconnect` |
| Note | NO `exam.active` middleware — must be reachable during page unload |

Flow:
```
1. authorizeAttempt()
2. Check attempt.status === 'in_progress' (else return 400)
3. Validate question_id, reason
4. SessionRecoveryService::recordDisconnect(attempt, questionId, reason, browserInfo)
   → Sets disconnected_at = now, last_question_id = questionId
   → Status stays in_progress
   → Creates SessionRecoveryLog record
5. Return JSON {success: true}
```

### 4.3 Admin\ExamController

---

**`approve(Exam $exam)`**

| Item | Detail |
|------|--------|
| Purpose | Admin approves a teacher-submitted exam |
| Route | `POST /admin/exams/{exam}/approve` |

Flow:
```
1. Check exam.status === 'pending_approval' (else return error)
2. exam.update(status=approved, approved_at=now, approved_by=auth.id)
3. NotificationService::notify(exam.teacher, 'exam_approved', title, message, link)
4. ActivityLogService::log('exam_approved', ...)
5. Redirect back with success
```

---

**`schedule(Request $request, Exam $exam)`**

| Item | Detail |
|------|--------|
| Purpose | Admin sets the exam open window and duration |
| Route | `POST /admin/exams/{exam}/schedule` |

Flow:
```
1. Check exam.schedules()->exists() → block if already has schedule (immutable)
2. Validate: starts_at (after_or_equal:now), ends_at (after:starts_at),
             duration_minutes (integer min:1), attempt_limit (integer min:1)
3. ExamSchedule::create({exam_id, starts_at, ends_at, duration_minutes, attempt_limit})
4. Redirect back with success
```

---

**`publish(Exam $exam)`**

| Item | Detail |
|------|--------|
| Purpose | Make the exam visible and accessible to students |
| Route | `POST /admin/exams/{exam}/publish` |

Flow:
```
1. Check schedule exists (else error)
2. exam.update(status=published)
3. schedule.update(is_published=true, published_at=now, published_by=auth.id)
4. For each enrolled student:
   NotificationService::notify(student, 'exam_published', 'New Exam Available 📝', ..., link)
5. NotificationService::notify(exam.teacher, 'exam_published', 'Your Exam is Now Live 🎉', ...)
6. ActivityLogService::log('exam_published', ...)
7. Redirect back with success
```

### 4.4 GradingService

---

**`gradeAttempt(ExamAttempt $attempt) : Result`**

| Item | Detail |
|------|--------|
| Purpose | Calculate and persist the student's exam result |
| Called by | `ExamSessionController::submitAttempt()`, `SessionRecoveryService::finalizeExpiredSession()`, `ExamSecurityService` (violation-3 path) |

Flow:
```
1. Guard: if result exists AND status is DISQUALIFIED → return existing result unchanged
2. Load all exam questions
3. totalMarks = sum of all question marks (regardless of how many answered)
4. obtainedMarks = 0
5. For each student_answer:
   - MCQ/True-False: check answer.is_correct → award question.marks or 0
   - Fill Blank: compare answer_text against accepted blank answers (exact case-sensitive)
   - Update student_answer.is_correct and marks_awarded
6. percentage = (obtainedMarks / totalMarks) × 100
7. isPassed = obtainedMarks >= exam.passing_marks
8. Result::updateOrCreate(['attempt_id'], {
     exam_id, student_id, total_marks, obtained_marks, percentage,
     is_passed, is_published=true,
     exam_result_status: PASSED or FAILED,
     attendance_status: 'attended',
     exam_finished_at: now()
   })
9. Return Result
```

**Important:** Notifications are NOT sent here. `NotifyStudentResults` command handles that after `ends_at` passes.

### 4.5 ExamSecurityService

---

**`recordViolation(attempt, type, details, client, ip) : array`**

Flow:
```
1. If warning_count >= 3 → return lockedResponse() (idempotent guard)
2. If warning_count === 2 (will be 3rd) → recordViolationThree() with DB row lock
3. Else → recordWarning() for violations 1 and 2

recordWarning():
  - Increment warning_count
  - Persist CheatingLog with client metadata
  - Warning 1: log activity, return "Warning 1 of 3" message
  - Warning 2: log activity + send email + notification to teacher+admins

recordViolationThree():
  DB::transaction + lockForUpdate():
  - Re-read attempt with write lock (prevents duplicate terminations)
  - Persist CheatingLog
  - Update attempt: status=terminated, terminated_at=now, warning_count=3
  - Clear user.exam_session_token
  - GradingService::gradeAttempt()
  - Override result: exam_result_status=DISQUALIFIED, is_passed=false
  - ActivityLog entry
  - DB::afterCommit(): send emails + notifications to student+teacher+admins
  - Return {terminated:true, locked:true, redirect: student.exams.index}
```

### 4.6 SessionRecoveryService

---

**`recordDisconnect(attempt, questionId, reason, browserInfo) : SessionRecoveryLog`**
```
1. attempt.update(disconnected_at=now, last_question_id=questionId)
   → status stays 'in_progress' — disconnect is NOT termination
2. SessionRecoveryLog::create({attempt_id, disconnect_reason, disconnected_at, recovery_status='pending'})
3. Return the log record
```

---

**`handleReconnect(ExamAttempt $attempt) : array`**
```
1. attempt.canAutoRecover()?
   → Check: status=in_progress, disconnected_at set,
            elapsed < recovery_time_limit (default 300s),
            now < expires_at, now < schedule.ends_at
2. If cannot recover → finalizeExpiredSession()
   → status=submitted, grade with saved answers, clear token
   → Return {success:false, message:...}
3. Compute frozen remaining seconds:
   frozen = expires_at - disconnected_at (capped by schedule.ends_at)
4. Clear disconnected_at on attempt
5. Update SessionRecoveryLog: recovery_status=recovered, reconnected_at=now
6. Return {success:true, frozen_seconds:N, message:'Welcome back...'}
```

### 4.7 InboxSyncService

---

**`sync() : array`**
```
1. Acquire Cache lock 'imap_inbox_sync' (150s TTL) → if locked: return 'Sync already running'
2. Read last_uid from inbox_sync_state
3. Connect to IMAP via Webklex Client::account('default')
4. Read uidnext from IMAP SELECT response
5. If nextUid (last_uid+1) >= uidnext → no new messages, release lock, return
6. UID SEARCH 'UID {nextUid}:*' directly via protocol (avoids Gmail quoting bug)
7. Sort UIDs ascending
8. For each UID:
   a. processMessage(message, uid):
      - Primary dedup: check 'uid:default:INBOX:{uid}' in inbox_emails.message_id
      - Secondary dedup: check RFC Message-ID header
      - Extract from_email; if empty → filtered
      - Student-only filter: sender must be registered student
      - Extract subject, date, body (HTML+text), threading headers
      - resolveThread(): determine thread_id (md5) and parent_id
      - InboxEmail::create({...})
      - Broadcast NewEmailReceived event
      - Return 'imported'
   b. On imported/skipped: advance InboxSyncState::saveLastUid(uid)
   c. On error: stop loop (retry next run from last good UID)
9. Release lock
10. Return {imported, skipped, errors, message}
```

### 4.8 EmailService

---

**`send(toEmail, toName, subject, bodyHtml, event, templateSlug, userId, queue) : EmailLog`**
```
1. EmailLog::create({to_email, subject, body_html, status='queued', ...})
2. If queue=true → SendEmailJob::dispatch(log.id)
3. If queue=false → deliver(log) immediately
4. Return EmailLog
```

**`deliver(EmailLog $log) : void`**
```
1. Mail::send([], [], function(Message $msg) {
     $msg->to(...)->from(...)->subject(...)->html(log.body_html)
   })
2. On success: log.markSent() → sets status='sent', sent_at=now
3. On exception: log.markFailed(error) → sets status='failed', error=message
```

**`sendWelcomeEmail(User $user, string $temporaryPassword) : void`**
```
1. Render view 'emails.welcome-account' with user data + temp password
2. Call send() with event='welcome_account', queue=true
```

**`sendBulk(recipientGroup, subject, bodyHtml) : int`**
```
1. resolveRecipients(group) → collection of users
2. For each user with email:
   - Build per-recipient variable map (name, email, year_level, courses, etc.)
   - Replace {{variable}} placeholders in subject + body
   - Call send()
3. Return count of sent emails
```

---

## 5. LARAVEL ARCHITECTURE EXPLANATION

### 5.1 Controllers — Responsibilities and Structure

Controllers live in `app/Http/Controllers/` organized by role:

```
Controllers/
├── Auth/
│   ├── AuthController.php          Login, logout, register, force password change
│   └── ForgotPasswordController.php  OTP-based password reset
├── Admin/
│   ├── ExamController.php          Approve, schedule, publish, close exams
│   ├── UserController.php          CRUD users (admin/teacher/student)
│   ├── CourseController.php        CRUD courses
│   ├── StudentController.php       Student management
│   ├── TeacherController.php       Teacher management
│   ├── EnrollmentController.php    Enroll students in courses
│   ├── MajorController.php         CRUD majors
│   ├── AcademicYearController.php  Manage academic years + student assignments
│   ├── ResultController.php        View all results
│   ├── CheatingLogController.php   View cheating logs
│   └── EmailController.php         Full email management (inbox, compose, logs, timetable)
├── Teacher/
│   ├── ExamController.php          Create exams, add questions, submit, view results
│   ├── ProfileController.php       Teacher profile
│   └── ResultController.php        View results for teacher's exams
├── Student/
│   ├── CourseController.php        View enrolled courses
│   ├── ExamController.php          View + start exams
│   ├── ExamSessionController.php   Live exam session (take, save, violate, submit, disconnect)
│   └── ResultController.php        View own results
├── DashboardController.php         Role-based dashboard data
├── NotificationController.php      Notification CRUD + unread counts
└── ProfileController.php           Shared profile (photo, password)
```

**Controller responsibility rules in this project:**
- Controllers only: validate input, call services, return responses
- No business logic in controllers — all in Services
- Authorization done inline with `abort(403)` checks
- All DB calls go through service layer or Eloquent models directly

### 5.2 Models — Relationships, Accessors, and Business Rules

**Key Model features used:**

| Feature | Example | Where |
|---------|---------|-------|
| Relationships | `hasMany`, `belongsTo`, `hasOne` | All models |
| SoftDeletes | `use SoftDeletes;` | User, Exam, Question, Course |
| Accessors | `getDecryptedContentAttribute()` | Question, Answer |
| Casts | `'is_active' => 'boolean'`, `'question_order' => 'array'` | User, ExamAttempt |
| Constants | `STATUS_PASSED`, `MAX_FAILED_ATTEMPTS` | Result, User |
| Helper methods | `isAdmin()`, `isLocked()`, `canAutoRecover()` | User, ExamAttempt |
| Static methods | `AcademicYear::current()` | AcademicYear |

**Business logic in models (intentional design choices):**
- `User::incrementFailedLogins()` — tracks and locks accounts
- `User::canRequestNewTempPassword()` — enforces cooldown logic
- `ExamAttempt::canAutoRecover()` — all recovery eligibility logic in one place
- `ExamAttempt::isDisplayedAsAbsent()` — display-only helper, doesn't change DB
- `Result::statusBadgeClass()` — returns Bootstrap CSS class for UI badge

### 5.3 Services — Why the Service Layer Exists

The service layer (`app/Services/`) exists to:
1. Keep controllers thin and testable
2. Allow the same business logic to be called from multiple places (controllers, commands, jobs)
3. Encapsulate complex multi-step operations as named units

| Service | Responsibility |
|---------|---------------|
| `ActivityLogService` | Write audit log entries |
| `AcademicService` | Enroll students, build academic history |
| `CheatingDetectionService` | LEGACY — read-only, kept for admin cheating-logs view |
| `CourseAssignmentService` | Assign courses to teachers/students |
| `EmailService` | Send emails, bulk email, template variable substitution |
| `EncryptionService` | Wrap Laravel Crypt for question/answer content |
| `EnsureDefaultAdminService` | Create default admin on startup if missing |
| `ExamAccessService` | Control who can decrypt questions, who can take exams |
| `ExamSecurityService` | Violation recording, 3-strike termination, approve/reject flow |
| `GradingService` | Calculate and persist exam results |
| `InboxSyncService` | Full IMAP sync — connect, fetch, dedup, store, broadcast |
| `NotificationService` | Create `user_notifications` records |
| `QuestionImportService` | Parse uploaded files to create questions |
| `SessionRecoveryService` | Disconnect/reconnect logic, timer management |
| `YearLevelProgressionValidator` | Validate year level advancement rules |

### 5.4 Middleware — Full Stack Applied to Routes

**Global middleware** (runs on every request — defined in `Kernel.php`):
- `EncryptCookies` — encrypts cookie values
- `AddQueuedCookiesToResponse` — adds queued cookies
- `StartSession` — starts PHP session
- `ShareErrorsFromSession` — makes `$errors` available in views
- `VerifyCsrfToken` — validates CSRF token on non-GET requests
- `SubstituteBindings` — resolves route model bindings

**Route middleware aliases (applied per route/group):**

| Alias | Class | What it Does |
|-------|-------|-------------|
| `auth` | `Authenticate` | Redirects to login if not authenticated |
| `guest` | `RedirectIfAuthenticated` | Redirects to dashboard if already logged in |
| `role:admin` | `RoleMiddleware` | Aborts 403 if user role slug ≠ allowed roles |
| `exam.session` | `EnsureSingleExamSession` | Checks `exam_session_token` matches session |
| `exam.active` | `EnsureExamActive` | Blocks save/violation/submit if attempt not in_progress |
| `force.password.change` | `ForcePasswordChange` | Redirects to `/password/change` if flag set |

**Middleware stack for authenticated pages:**
```
auth → exam.session → force.password.change → [role:X] → Controller
```

**Middleware stack for live exam routes:**
```
auth → exam.session → force.password.change → role:student → exam.active → Controller
```

### 5.5 Jobs — Background Processing

All jobs implement `ShouldQueue` and are stored in the `jobs` database table.

| Job | Queue | Tries | Backoff | Purpose |
|-----|-------|-------|---------|---------|
| `SendEmailJob` | `emails` | 3 | 30s | Deliver one `EmailLog` via SMTP |
| `InboxSyncJob` | `emails` | 2 | 60s | Run InboxSyncService::sync() asynchronously |
| `SendWelcomeAccountJob` | `emails` | 3 | 30s | Send welcome email to new user |
| `SendExamTimetableNotificationJob` | `emails` | 3 | 30s | Send timetable notification to students |
| `SendNewTemporaryPasswordJob` | `emails` | 3 | 30s | Send new temp password email |
| `SendPasswordChangedJob` | `emails` | 3 | 30s | Notify user their password was changed |
| `SendProfileOtpJob` | `emails` | 3 | 30s | Send OTP for profile verification |

**All jobs use the `emails` queue** — meaning one worker handles all email + sync tasks.

### 5.6 Events and Listeners

| Event | Trigger | Effect |
|-------|---------|--------|
| `NewEmailReceived` | `InboxSyncService::processMessage()` after saving `InboxEmail` | Broadcast to admin (real-time notification of new inbox email) |

The event system is minimal — only one event is actively used.

### 5.7 Artisan Scheduled Commands

Registered in `app/Console/Kernel.php`, run via: `php artisan schedule:run` (every minute by cron).

| Command | Schedule | Purpose |
|---------|----------|---------|
| `inbox:sync` | Every minute | Sync IMAP inbox → `inbox_emails` |
| `results:notify-students` | Every minute | Send result notifications after exam windows close |

Both commands use `withoutOverlapping(5)` — prevent stacked runs if previous run takes too long.

**For production, add to server crontab:**
```
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

---

## 6. COMPLETE FEATURE FLOW DOCUMENTATION

### 6.1 Authentication Flow

```
STUDENT/TEACHER/ADMIN
        |
        v
GET /login → auth.login view
        |
        v (user fills form)
POST /login
        |
        v
AuthController::login()
        |
        +-- User::where('email') → find user record
        |
        +-- isLocked()? → 403 with countdown timer displayed
        |
        +-- Auth::attempt({email, password})
        |       |
        |       +-- FAIL → incrementFailedLogins()
        |       |           → if now locked: log + return lock message
        |       |           → else: return "N attempts remaining"
        |       |
        |       +-- PASS → check is_active (false = deactivated error)
        |                  → isTemporaryPasswordExpired()? logout + error
        |                  → resetFailedLogins()
        |                  → update last_login_at
        |                  → log 'login'
        |                  → session()->regenerate()
        |
        v
REDIRECT to role-based dashboard
        |
        +-- admin   → /admin/dashboard
        +-- teacher → /teacher/dashboard
        +-- student → /student/dashboard
```

**Force Password Change path:**
```
After login, if force_password_change = true:
    ForcePasswordChange middleware intercepts EVERY request
        → Redirect to /password/change
        → User must change password before accessing anything
        → On success: flag cleared, redirect to dashboard
```

**Forgot Password (OTP) Flow:**
```
GET /forgot-password → enter email
POST /forgot-password/send → ForgotPasswordController::sendOtp()
    → Generate OTP, save to profile_otps, dispatch SendProfileOtpJob
GET /forgot-password/verify → enter OTP
POST /forgot-password/check-otp → validate OTP
POST /forgot-password/verify → reset password
```

### 6.2 Student Management Flow

**Admin creates a student:**
```
GET /admin/users/create → UserController::create()
        |
        v
POST /admin/users → UserController::store()
        |
        v
1. Validate name, email, role, password
2. Hash password with Hash::make()
3. User::create({...force_password_change: true, temporary_password_expires_at: +24h})
4. EmailService::sendWelcomeEmail(user, plainTextPassword)
   → Renders emails/welcome-account.blade.php
   → Dispatches SendWelcomeAccountJob → SendEmailJob → SMTP
5. ActivityLog 'user_created'
6. Redirect back with success
```

**Admin enrolls student in course:**
```
POST /admin/enrollments
    → EnrollmentController::store()
    → Validate course_id, student_id, year_level_id, major_id
    → Enrollment::create({...}) with unique constraint check
    → ActivityLog
    → Redirect back
```

**Student views their courses:**
```
GET /student/courses
    → StudentCourseController::index()
    → Load auth()->user()->enrollments()->with('course')
    → Return view with enrolled courses list
```

### 6.3 Complete Exam System Flow

```
PHASE 1: TEACHER CREATES EXAM
─────────────────────────────
Teacher → POST /teacher/exams
    → Teacher\ExamController::store()
    → Exam::create({status: 'draft', teacher_id, course_id, academic_year_id, ...})

Teacher → POST /teacher/exams/{exam}/questions
    → Teacher\ExamController::addQuestion()
    → Validate question type, content, marks, answers
    → Validate at least one correct answer
    → Question::create({content_encrypted: EncryptionService::encrypt(content), ...})
    → For MCQ/True-False: Answer::create() for each option
    → For Fill Blank: Answer::create({is_blank_answer: true}) for each accepted answer

PHASE 2: TEACHER SUBMITS FOR APPROVAL
──────────────────────────────────────
Teacher → POST /teacher/exams/{exam}/submit
    → Teacher\ExamController::submitForApproval()
    → Validate question count > 0
    → Validate sum(question.marks) === exam.total_marks (exact match required)
    → DB::transaction:
        exam.update(status='pending_approval', submitted_at=now)
        For each admin: NotificationService::notify('exam_submitted', ...)

PHASE 3: ADMIN APPROVES + SCHEDULES
────────────────────────────────────
Admin → POST /admin/exams/{exam}/approve
    → exam.update(status='approved')
    → Notify teacher

Admin → POST /admin/exams/{exam}/schedule
    → ExamSchedule::create({starts_at, ends_at, duration_minutes, attempt_limit})
    → Schedule is IMMUTABLE — cannot be changed or deleted after creation

Admin → POST /admin/exams/{exam}/publish
    → exam.update(status='published')
    → schedule.update(is_published=true)
    → For each enrolled student: Notify 'exam_published'
    → Notify teacher 'exam_published'

PHASE 4: STUDENT STARTS EXAM
──────────────────────────────
Student → POST /student/exams/{exam}/start
    → Student\ExamController::start()
    → ExamAccessService::studentCanTakeExam():
        - Check exam status is 'approved' or 'published'
        - Check schedule exists and is active (now between starts_at and ends_at)
        - Check student enrolled in course
        - Check StudentYearRecord exists for this academic year + year level
        - Check attempt_limit not exceeded
    → Determine question order:
        - If shuffle_questions: shuffle question IDs
        - Else: natural order
    → ExamAttempt::create({
          exam_id, schedule_id, student_id,
          status: 'in_progress',
          started_at: now(),
          expires_at: MIN(now() + duration_minutes, schedule.ends_at),
          question_order: [shuffled array or null],
          session_token: Str::random(60)
      })
    → user.update(exam_session_token: attempt.session_token)
    → session()->put('exam_session_token', token)
    → Redirect to /student/attempt/{attempt}/take

PHASE 5: STUDENT TAKES EXAM (LIVE SESSION)
───────────────────────────────────────────
GET /student/attempt/{attempt}/take
    → ExamSessionController::take()
    → Decrypt all questions + answers for this student
    → Apply question_order for display
    → Pass endsAt timestamp to view
    → Render student/exam/take.blade.php
    → exam-anticheat.js initializes:
        - Fullscreen enforcement
        - Anti-cheat event listeners
        - Countdown timer
        - Auto-save on answer selection

While exam is active:
    Student clicks answer:
        → AJAX POST /student/attempt/{attempt}/save
        → StudentAnswer::updateOrCreate()
    
    Student exits fullscreen:
        → JS detects fullscreenchange event
        → 10-second recovery modal shown
        → If not back in 10s: reportViolation('fullscreen_exit')
    
    Student switches tab:
        → JS detects visibilitychange
        → reportViolation('tab_switch')
    
    Any violation POST /student/attempt/{attempt}/violation:
        → ExamSecurityService::recordViolation()
        → Warning 1: warn only
        → Warning 2: warn + notify teacher/admins
        → Warning 3: terminate + disqualify + notify all

PHASE 6: EXAM SUBMISSION
─────────────────────────
Student clicks Submit:
    → POST /student/attempt/{attempt}/submit
    → attempt.update(status='submitted', submitted_at=now)
    → Clear exam_session_token
    → GradingService::gradeAttempt()
        → Calculate marks for all answers
        → Result::updateOrCreate({is_passed, percentage, exam_result_status})
    → Redirect to exams.show with success

Auto-submit triggers:
    → Timer expires (checked in ExamSessionController::take())
    → Schedule ends (same check)
    → Recovery window expires (SessionRecoveryService::finalizeExpiredSession())

PHASE 7: RESULTS PUBLISHED
────────────────────────────
After schedule.ends_at passes:
    → results:notify-students command runs every minute
    → Finds results where attempt.status='submitted' and not DISQUALIFIED/ABSENT
    → For each: NotificationService::notify(student, 'exam_result', ...)
    → Dedup check: only sends once per student per exam

Student views result:
    → GET /student/results
    → Student\ResultController::index()
    → Shows PASSED/FAILED/DISQUALIFIED/ABSENT with marks and percentage
```

### 6.4 Anti-Cheat System — Complete Flow

**Overview:**  
The anti-cheat system has two layers: a JavaScript detector (`exam-anticheat.js`) and a PHP enforcer (`ExamSecurityService`). The JS detects events and reports them; the PHP decides consequences.

#### Layer 1: JavaScript Detection (`public/js/exam-anticheat.js`)

**Initialization:**
```javascript
1. Reads policy flags from data-policy-* attributes on #examBody element
2. Reads endsAt timestamp for countdown timer
3. Reads warningCount from server (for page refresh continuity)
4. Sets up all event listeners based on policy flags
```

**Events Monitored:**

| Event | Listener | Violation Type Reported |
|-------|----------|------------------------|
| Fullscreen exit | `fullscreenchange` | `fullscreen_exit` |
| Tab switch | `visibilitychange` | `tab_switch` |
| Window blur | `blur` on window | `window_blur` |
| Right-click | `contextmenu` | (blocked, not reported) |
| Copy/Cut/Paste | `copy`, `cut`, `paste` | (blocked, not reported) |
| DevTools shortcut | `keydown` (F12, Ctrl+Shift+I/J/C, Ctrl+U) | `devtools_shortcut` |
| Browser close | `beforeunload` | Sends `navigator.sendBeacon` to `/disconnect` |

**Fullscreen Recovery Modal (10-second grace window):**
```
Student exits fullscreen
    → showFsRecoveryModal() — 10-second countdown displayed
    → If student returns to fullscreen within 10s → cancelFsRecovery() → NO violation
    → If 10s expires → reportViolation('fullscreen_exit') → server records Warning +1
    → autoRestoreFullscreen() attempts programmatic re-entry

If tab switch occurs DURING the 10-second window (compound violation):
    → cancelFsRecovery() (stop the timer)
    → sendCompoundViolation(): sends TWO sequential POSTs
        1. POST violation: fullscreen_exit
        2. POST violation: tab_switch / window_blur
    → After both responses: showFinalWarningModal() (if not terminated)
```

**Final Warning Modal (15-second — shown after warning_count reaches 2):**
```
Three outcomes:
    A) Student clicks "Return Fullscreen" → autoRestoreFullscreen() → NO violation
    B) Student clicks "Exit & Consume Warning" → reportViolation → termination
    C) 15 seconds expire → autoRestoreFullscreen() → NO violation
```

**Violation Reporting:**
```javascript
reportViolation(type, details):
    → fetch POST to violationUrl with {type, details}
    → handleViolationResponse(data):
        → Update local warningCount from server response
        → Show warning box message for 8 seconds
        → If data.terminated: lockExamInterface(message) → redirect in 3s
```

**Interface Lock (Tier 3 Termination):**
```javascript
lockExamInterface(message):
    → Set examLocked = true
    → Stop all timers and intervals
    → Remove all event listeners
    → Disable all answer inputs and buttons
    → Exit fullscreen
    → Overlay a dark lock screen with termination message
    → setTimeout 3000ms → redirect to student exam list
```

#### Layer 2: PHP Enforcement (`ExamSecurityService`)

```
POST /student/attempt/{attempt}/violation arrives
    → ExamSessionController::violation()
    → ExamSecurityService::recordViolation()
    
    WARNING 1 (warning_count → 1):
        → CheatingLog::create() with client metadata
        → attempt.increment('warning_count')
        → ActivityLog: 'security_warning_1'
        → Return: {warning_count:1, terminated:false, message:"Warning 1 of 3..."}
    
    WARNING 2 (warning_count → 2):
        → CheatingLog::create()
        → attempt.increment('warning_count')
        → Send email to teacher + admins (queued)
        → Send notifications to teacher + admins
        → ActivityLog: 'security_warning_2'
        → Return: {warning_count:2, terminated:false, message:"Warning 2 of 3..."}
    
    WARNING 3 (warning_count → 3):
        DB::transaction + lockForUpdate():
        → CheatingLog::create()
        → attempt.update(status='terminated', terminated_at=now, warning_count=3)
        → Clear user.exam_session_token
        → GradingService::gradeAttempt()
        → Result override: exam_result_status='DISQUALIFIED', is_passed=false
        → ActivityLog: 'exam_terminated_security'
        → DB::afterCommit():
            → Send email to student + teacher + admins (high priority)
            → Send notifications to all
        → Return: {warning_count:3, terminated:true, locked:true, redirect:...}
```

### 6.5 Result System Flow

**Result Calculation (immediate at submission):**
```
GradingService::gradeAttempt(attempt):

    Step 1: Load all exam questions (even if unanswered)
    Step 2: totalMarks = SUM(question.marks) for ALL questions

    Step 3: For each student_answer:
        MCQ / True-False:
            → correct = (student_answer.answer.is_correct === true)
            → marks_awarded = correct ? question.marks : 0
        Fill Blank:
            → studentText = trim(answer_text)
            → acceptedAnswers = question.answers where is_blank_answer=true
            → correct = acceptedAnswers contains studentText (exact, case-sensitive)
            → marks_awarded = correct ? question.marks : 0
        Unanswered questions → no student_answer row → contribute 0 marks automatically

    Step 4: percentage = (obtainedMarks / totalMarks) × 100
    Step 5: isPassed = (obtainedMarks >= exam.passing_marks)
    Step 6: Result::updateOrCreate(
                {attempt_id},
                {exam_result_status: PASSED/FAILED, is_passed, percentage, ...}
            )
```

**Result Notification (delayed — after exam window closes):**
```
results:notify-students (runs every minute):
    → Find all ExamSchedules where ends_at < now()
    → For each schedule → exam → results:
        - Filter out DISQUALIFIED, ABSENT
        - Filter: attempt.status === 'submitted'
        - Dedup: check user_notifications for (user_id, type='exam_result', link=exam URL)
        - If not yet notified:
            NotificationService::notify(student, 'exam_result', 'Result Available', ..., link)
```

**Absent Marking (separate command):**
```
results:mark-absent:
    → Find ended exam schedules (ends_at < now, is_published=true)
    → For each schedule → exam:
        enrolledStudentIds = enrollments for course
        alreadyHaveResult = results where exam_id matches
        startedStudentIds = exam_attempts where started_at IS NOT NULL
        absentStudentIds = enrolled - already_have_result - started
        For each absent student:
            Result::create({
                attempt_id: null,  ← No attempt was made
                exam_result_status: 'ABSENT',
                obtained_marks: 0,
                is_passed: false
            })
```

**Result Display Logic:**
```
Result statuses and their meaning:
    PASSED       → obtained_marks >= passing_marks
    FAILED       → obtained_marks < passing_marks (exam attempted normally)
    DISQUALIFIED → 3 anti-cheat violations (set by ExamSecurityService)
    ABSENT       → student never started the exam (set by MarkAbsentResults command)

Result::statusBadgeClass() returns Bootstrap class for colored badge:
    PASSED       → 'bg-success' (green)
    FAILED       → 'bg-danger'  (red)
    ABSENT       → 'bg-secondary' (grey)
    DISQUALIFIED → 'bg-warning text-dark' (yellow)
```

### 6.6 Session Recovery Flow

```
NORMAL EXAM SESSION:
    Student takes exam → expires_at = MIN(started_at + duration_minutes, ends_at)

DISCONNECT SCENARIO:
    Browser closes / network drops
        → beforeunload event fires
        → navigator.sendBeacon() → POST /student/attempt/{id}/disconnect
        → ExamSessionController::disconnect()
        → SessionRecoveryService::recordDisconnect():
            attempt.disconnected_at = now()      ← flag set
            attempt.last_question_id = current   ← resume point
            attempt.status STAYS in_progress     ← NOT terminated

STUDENT RETURNS (within 5 minutes AND before expires_at):
    GET /student/attempt/{id}/take
        → ExamSessionController::take()
        → Detects disconnected_at is set
        → SessionRecoveryService::handleReconnect():
            canAutoRecover()? → elapsed < 300s AND now < expires_at
            YES:
                frozen_seconds = expires_at - disconnected_at
                attempt.disconnected_at = null (cleared)
                SessionRecoveryLog.recovery_status = 'recovered'
                Return {success:true, frozen_seconds:N}
            → Render exam with frozen timer
            → Flash message: "Welcome back! Your session has been restored."

RECOVERY WINDOW EXPIRED (> 5 min OR expires_at passed):
    → SessionRecoveryService::finalizeExpiredSession():
        attempt.status = 'submitted'
        GradingService::gradeAttempt() with saved answers
        Unanswered questions → 0 marks
        Return {success:false, message:...}
    → Redirect to exam show page with info message
```

---

## 7. COMPLETE EMAIL SYSTEM DOCUMENTATION

### 7.1 Outgoing Email Architecture

```
APPLICATION CODE
    |
    v
EmailService::send()
    → EmailLog::create({status: 'queued'})
    → SendEmailJob::dispatch(log.id)
    |
    v (queue worker picks up job)
jobs TABLE (database queue)
    |
    v
SendEmailJob::handle(EmailService $emailService)
    → EmailLog::find(logId)
    → emailService::deliver(log)
        → Mail::send([], [], fn(Message $msg) {
              $msg->to(...)->from(...)->subject(...)->html(log.body_html)
          })
        → On success: log.markSent() → status='sent', sent_at=now()
        → On failure: log.markFailed(error) → status='failed'
    |
    v
SMTP (Brevo smtp-relay.brevo.com:587 TLS)
    |
    v
RECIPIENT'S INBOX
```

**Retry behavior:**  
`SendEmailJob` has `$tries = 3` and `$backoff = 30` seconds.  
After 3 failures → job moves to `failed_jobs` table.  
Admin can retry from `Admin\EmailController::retryLog()`.

### 7.2 Email Types in the System

| Email Type | Trigger | Template View | Event Key |
|-----------|---------|---------------|-----------|
| Welcome Account | Admin creates user | `emails/welcome-account.blade.php` | `welcome_account` |
| New Temporary Password | User requests new temp password | `emails/new-temporary-password.blade.php` | — |
| Password Changed | User changes password | `emails/password-changed.blade.php` | — |
| Profile OTP | Profile security verification | `emails/profile-otp.blade.php` | — |
| Exam Timetable Notification | Admin sends timetable | `emails/exam-timetable.blade.php` | — |
| Cheating Detected (legacy) | Old CheatingDetectionService | `emails/cheating-detected.blade.php` | `cheating_detected` |
| Security Warning | ExamSecurityService Warning 2 | `emails/security-warning.blade.php` | `security_warning` |
| Security Terminated | ExamSecurityService Warning 3 | `emails/security-terminated.blade.php` | `security_incident_high` |
| Account Terminated | Admin terminates user | `emails/account-terminated.blade.php` | — |
| Manual Message | Admin compose/custom send | `emails/manual-message.blade.php` | `bulk` |

### 7.3 Outgoing Email Flow Detail

```
Event/Trigger
    |
    +── Admin creates user
    |       → EmailService::sendWelcomeEmail(user, password)
    |       → Renders emails/welcome-account.blade.php
    |       → EmailLog created
    |       → SendWelcomeAccountJob dispatched

    +── Exam security warning (violation 2)
    |       → ExamSecurityService::sendSecurityEmail(attempt, recipient, 'warning', false)
    |       → Renders emails/security-warning.blade.php
    |       → EmailService::send(..., 'security_warning', ..., queue=true)
    |       → SendEmailJob dispatched

    +── Exam security termination (violation 3 — DB::afterCommit)
    |       → ExamSecurityService::sendSecurityEmail(attempt, recipient, 'terminated', true)
    |       → Renders emails/security-terminated.blade.php
    |       → EmailService::send(..., 'security_incident_high', ..., queue=true)
    |       → SendEmailJob dispatched

    +── Admin bulk compose
            → EmailController::sendCompose() or sendCustom()
            → EmailService::sendBulk(group, subject, bodyHtml)
            → Substitutes {{variable}} placeholders per recipient
            → SendEmailJob for each recipient
```

### 7.4 Incoming Email (IMAP) Architecture

```
GMAIL / EMAIL PROVIDER
    |   (holds emails for admin inbox)
    v
IMAP Protocol (SSL port 993)
    |
    v
webklex/laravel-imap Client
    |
    v
InboxSyncService::sync()
    |
    +── Read last_uid from inbox_sync_state
    |
    +── UID SEARCH 'UID {last_uid+1}:*' (direct protocol call — bypasses quoting bug)
    |
    +── For each new UID (ascending order):
    |       processMessage(message, uid):
    |           → Dedup check (uid-key + RFC Message-ID)
    |           → Filter: only student email senders
    |           → Parse body (HTML + plain text)
    |           → Resolve thread (md5 of root message ID)
    |           → InboxEmail::create({...})
    |           → event(new NewEmailReceived($stored))
    |           → Advance InboxSyncState::saveLastUid(uid)
    |
    v
inbox_emails TABLE
    |
    v
Admin Inbox UI (/admin/email/inbox)
```

**Thread Resolution Algorithm:**
```
For a new email:
1. Parse References header → list of ancestor Message-IDs
2. Add In-Reply-To to the list
3. Walk ancestors oldest→newest: find first one in inbox_emails
4. thread_id = md5(root_message_id) — stable key
5. parent_id = inbox_emails.id of most recent known ancestor
6. If no ancestors found → new thread (md5 of own Message-ID)
```

### 7.5 Admin Email Management UI

The `Admin\EmailController` provides a complete email management panel:

| Route | Method | Purpose |
|-------|--------|---------|
| `GET /admin/email/inbox` | `inbox()` | View paginated inbox emails |
| `POST /admin/email/inbox/sync` | `syncInbox()` | Manually trigger InboxSyncJob |
| `GET /admin/email/inbox/poll` | `pollInbox()` | AJAX poll for new inbox count |
| `GET /admin/email/inbox/{email}` | `showInbox()` | View one email + thread |
| `POST /admin/email/inbox/{email}/reply` | `replyInbox()` | Reply via SMTP |
| `POST /admin/email/inbox/{email}/read` | `markInboxRead()` | Mark as read |
| `DELETE /admin/email/inbox/{email}` | `archiveInbox()` | Archive (soft status change) |
| `GET /admin/email/compose` | `compose()` | Compose new email |
| `POST /admin/email/compose` | `sendCompose()` | Send bulk/group email |
| `POST /admin/email/compose/custom` | `sendCustom()` | Send custom recipient email |
| `GET /admin/email/sent` | `sent()` | View sent emails |
| `GET /admin/email/logs` | `logs()` | View all email logs |
| `POST /admin/email/logs/{log}/retry` | `retryLog()` | Retry a failed email |
| `GET /admin/email/timetable/schedules` | `timetableSchedules()` | Exam timetable notification |
| `POST /admin/email/timetable/send` | `sendTimetableNotification()` | Send exam timetable emails |

### 7.6 Email Template Variable System

The `EmailService::substituteVars()` method replaces `{{variable}}` placeholders:

| Variable | Value Source |
|----------|-------------|
| `{{student_name}}` | `user.name` |
| `{{teacher_name}}` | `user.name` |
| `{{name}}` | `user.name` |
| `{{email}}` | `user.email` |
| `{{student_id}}` | `STU-` + zero-padded user.id |
| `{{app_name}}` | `config('app.name')` |
| `{{app_url}}` | `config('app.url')` |
| `{{year}}` | Current year |
| `{{year_level}}` | From StudentYearRecord |
| `{{academic_year}}` | From StudentYearRecord |
| `{{course_name}}` | Comma-joined enrolled courses |
| `{{semester}}` | "Semester 1" or "Semester 2" |

---

## 8. QUEUE & BACKGROUND PROCESSING SYSTEM

### 8.1 Queue Architecture

```
APPLICATION
    |
    v
Job::dispatch(params)
    → Serializes job to JSON
    → Inserts row into `jobs` table with queue='emails'
    |
    v
jobs TABLE (database — persists across restarts)
    |
    v (queue worker polls this table)
php artisan queue:work --queue=emails
    → Reads from jobs table
    → Deserializes job payload
    → Resolves dependencies via Laravel IoC container
    → Calls job->handle()
    → On success: deletes row from jobs table
    → On failure: increments attempts, re-queues with backoff delay
    → After $tries exhausted: moves to failed_jobs
```

**Queue configuration:**
```
QUEUE_CONNECTION=database   ← stored in jobs table (not Redis, not SQS)
Queue name: 'emails'        ← all jobs use this queue
```

### 8.2 Why Each Feature Uses the Queue

| Feature | Why Queued |
|---------|-----------|
| Welcome email | Sending SMTP takes 1–3 seconds — user should not wait |
| Security violation email | Exam page must respond in milliseconds; email can be async |
| Inbox IMAP sync (manual) | IMAP connection can take 10–60 seconds — would timeout HTTP |
| OTP email | Sends during login flow — must not block the response |
| Timetable notification | Sends to many students — bulk operation |

### 8.3 Scheduled Commands (Background Tasks)

These run via Laravel Scheduler (every minute via system cron):

```
php artisan schedule:run
    |
    +── inbox:sync (SyncInbox command)
    |       → Calls InboxSyncService::sync()
    |       → withoutOverlapping(5) — max one instance running
    |
    +── results:notify-students (NotifyStudentResults command)
            → Finds ended exam schedules
            → Sends result notifications to eligible students
            → withoutOverlapping(5) — max one instance running
```

### 8.4 Failed Jobs Handling

```
Failed job in failed_jobs table:
    → Inspect: php artisan queue:failed
    → Retry one: php artisan queue:retry {uuid}
    → Retry all: php artisan queue:retry all
    → Delete: php artisan queue:forget {uuid}
    → Clear all: php artisan queue:flush

Admin UI: Admin\EmailController::retryLog()
    → Sets email_log.status = 'queued'
    → Dispatches new SendEmailJob
```

### 8.5 Starting the Queue Worker

```bash
# Development (process 1 job at a time, shows output):
php artisan queue:work --queue=emails

# With timeout and memory limit:
php artisan queue:work --queue=emails --timeout=120 --memory=256

# Production (use Supervisor to keep it running):
# /etc/supervisor/conf.d/blc-worker.conf:
# [program:blc-worker]
# command=php /path/to/artisan queue:work --queue=emails --sleep=3 --tries=3
# autostart=true
# autorestart=true
```

---

## 9. ROUTE DOCUMENTATION

### 9.1 Public Routes (No Authentication Required)

| Method | Route | Controller | Function | Purpose |
|--------|-------|------------|----------|---------|
| GET | `/` | *(closure)* | — | Welcome/landing page |
| GET | `/login` | `AuthController` | `showLogin` | Login form |
| POST | `/login` | `AuthController` | `login` | Process login |
| GET | `/register` | `AuthController` | `showRegister` | Registration form |
| POST | `/register` | `AuthController` | `register` | Create student account |
| POST | `/login/request-new-password` | `AuthController` | `requestNewTemporaryPassword` | Request new temp password |
| GET | `/forgot-password` | `ForgotPasswordController` | `showEmailForm` | Forgot password form |
| POST | `/forgot-password/send` | `ForgotPasswordController` | `sendOtp` | Send OTP |
| GET | `/forgot-password/verify` | `ForgotPasswordController` | `showVerifyForm` | OTP input form |
| POST | `/forgot-password/check-otp` | `ForgotPasswordController` | `checkOtp` | Verify OTP |
| POST | `/forgot-password/verify` | `ForgotPasswordController` | `resetPassword` | Reset password |
| POST | `/forgot-password/resend` | `ForgotPasswordController` | `resendOtp` | Resend OTP |

### 9.2 Authenticated Routes (Any Role)

| Method | Route | Controller | Function | Purpose |
|--------|-------|------------|----------|---------|
| POST | `/logout` | `AuthController` | `logout` | Log out |
| GET | `/password/change` | `AuthController` | `showForcePasswordChange` | Force change form |
| POST | `/password/change` | `AuthController` | `updateForcePasswordChange` | Process forced change |
| GET | `/notifications` | `NotificationController` | `index` | All notifications |
| POST | `/notifications/{id}/read` | `NotificationController` | `markRead` | Mark one read |
| POST | `/notifications/read-all` | `NotificationController` | `markAllRead` | Mark all read |
| GET | `/notifications/unread-count` | `NotificationController` | `unreadCount` | AJAX badge count |
| GET | `/notifications/unread-by-category` | `NotificationController` | `unreadCountsByCategory` | AJAX per-category counts |
| GET | `/profile` | `ProfileController` | `show` | View profile |
| POST | `/profile/photo` | `ProfileController` | `updatePhoto` | Upload profile photo |
| DELETE | `/profile/photo` | `ProfileController` | `deletePhoto` | Remove photo |
| POST | `/profile/password` | `ProfileController` | `changePassword` | Change own password |

### 9.3 Admin Routes (`/admin/*`, role: admin)

| Method | Route | Controller | Function | Purpose |
|--------|-------|------------|----------|---------|
| GET | `/admin/dashboard` | `DashboardController` | `admin` | Admin dashboard |
| GET/POST | `/admin/users` | `UserController` | `index`/`store` | List/create users |
| GET/PUT/DELETE | `/admin/users/{id}` | `UserController` | `edit`/`update` | Edit user |
| POST | `/admin/users/{id}/terminate` | `UserController` | `terminate` | Soft-delete user |
| POST | `/admin/users/{id}/restore` | `UserController` | `restore` | Restore user |
| GET/POST | `/admin/courses` | `CourseController` | `index`/`store` | Courses |
| GET | `/admin/courses-by-year-level` | `CourseController` | `byYearLevel` | AJAX filter |
| GET/POST | `/admin/majors` | `MajorController` | `index`/`store` | Majors |
| GET | `/admin/enrollments` | `EnrollmentController` | `index` | Enrollments |
| POST | `/admin/enrollments` | `EnrollmentController` | `store` | Enroll student |
| DELETE | `/admin/enrollments/{id}` | `EnrollmentController` | `destroy` | Remove enrollment |
| GET | `/admin/exams` | `ExamController` | `index` | All exams (grouped) |
| GET | `/admin/exams/{exam}` | `ExamController` | `show` | Exam detail |
| GET | `/admin/exams/{exam}/results` | `ExamController` | `results` | Exam results |
| POST | `/admin/exams/{exam}/approve` | `ExamController` | `approve` | Approve exam |
| POST | `/admin/exams/{exam}/schedule` | `ExamController` | `schedule` | Set schedule |
| POST | `/admin/exams/{exam}/publish` | `ExamController` | `publish` | Publish to students |
| POST | `/admin/exams/{exam}/close` | `ExamController` | `close` | Close exam |
| POST | `/admin/exams/{exam}/open` | `ExamController` | `open` | Reopen closed exam |
| GET | `/admin/cheating-logs` | `CheatingLogController` | `index` | Security violations |
| GET | `/admin/results` | `ResultController` | `index` | All results |
| GET | `/admin/results/student/{id}` | `ResultController` | `student` | One student's results |
| GET/POST | `/admin/academic/years` | `AcademicYearController` | `index`/`store` | Academic years |
| GET/POST | `/admin/academic/years/{id}/students` | `AcademicYearController` | `students`/`assignStudents` | Assign students to year |

### 9.4 Admin Email Routes (`/admin/email/*`)

| Method | Route | Controller | Function | Purpose |
|--------|-------|------------|----------|---------|
| GET | `/admin/email/inbox` | `EmailController` | `inbox` | View inbox |
| POST | `/admin/email/inbox/sync` | `EmailController` | `syncInbox` | Trigger IMAP sync |
| GET | `/admin/email/inbox/poll` | `EmailController` | `pollInbox` | AJAX new count |
| GET | `/admin/email/inbox/rows` | `EmailController` | `inboxRows` | AJAX rows refresh |
| GET | `/admin/email/inbox/{email}` | `EmailController` | `showInbox` | View single email |
| POST | `/admin/email/inbox/{email}/reply` | `EmailController` | `replyInbox` | Reply to email |
| POST | `/admin/email/inbox/{email}/read` | `EmailController` | `markInboxRead` | Mark read |
| DELETE | `/admin/email/inbox/{email}` | `EmailController` | `archiveInbox` | Archive email |
| GET | `/admin/email/compose` | `EmailController` | `compose` | Compose form |
| POST | `/admin/email/compose` | `EmailController` | `sendCompose` | Send bulk email |
| POST | `/admin/email/compose/custom` | `EmailController` | `sendCustom` | Send custom email |
| GET | `/admin/email/sent` | `EmailController` | `sent` | Sent emails |
| GET | `/admin/email/logs` | `EmailController` | `logs` | All email logs |
| GET | `/admin/email/logs/{log}` | `EmailController` | `showLog` | Single log detail |
| POST | `/admin/email/logs/{log}/retry` | `EmailController` | `retryLog` | Retry failed email |
| GET | `/admin/email/timetable/schedules` | `EmailController` | `timetableSchedules` | Timetable notification page |
| POST | `/admin/email/timetable/send` | `EmailController` | `sendTimetableNotification` | Send timetable emails |

### 9.5 Teacher Routes (`/teacher/*`, role: teacher or admin)

| Method | Route | Controller | Function | Purpose |
|--------|-------|------------|----------|---------|
| GET | `/teacher/dashboard` | `DashboardController` | `teacher` | Teacher dashboard |
| GET | `/teacher/exams` | `TeacherExamController` | `index` | My exams (grouped) |
| GET/POST | `/teacher/exams/create` | `TeacherExamController` | `create`/`store` | Create exam |
| GET | `/teacher/exams/{exam}` | `TeacherExamController` | `show` | Exam detail + questions |
| POST | `/teacher/exams/{exam}/questions` | `TeacherExamController` | `addQuestion` | Add question |
| GET/PUT | `/teacher/exams/{exam}/questions/{q}/edit` | `TeacherExamController` | `editQuestion`/`updateQuestion` | Edit question |
| DELETE | `/teacher/exams/{exam}/questions/{q}` | `TeacherExamController` | `deleteQuestion` | Delete question |
| POST | `/teacher/exams/{exam}/submit` | `TeacherExamController` | `submitForApproval` | Submit for approval |
| DELETE | `/teacher/exams/{exam}` | `TeacherExamController` | `destroy` | Delete draft exam |
| GET | `/teacher/exams/{exam}/results` | `TeacherExamController` | `results` | View exam results |
| POST | `/teacher/exams/{exam}/import` | `TeacherExamController` | `importQuestions` | Import questions from file |
| GET | `/teacher/results` | `TeacherResultController` | `index` | All results for teacher |

### 9.6 Student Routes (`/student/*`, role: student)

| Method | Route | Controller | Function | Purpose |
|--------|-------|------------|----------|---------|
| GET | `/student/dashboard` | `DashboardController` | `student` | Student dashboard |
| GET | `/student/courses` | `StudentCourseController` | `index` | My enrolled courses |
| GET | `/student/exams` | `StudentExamController` | `index` | Available exams |
| GET | `/student/exams/{exam}` | `StudentExamController` | `show` | Exam detail/info |
| POST | `/student/exams/{exam}/start` | `StudentExamController` | `start` | Start exam (creates attempt) |
| GET | `/student/attempt/{attempt}/take` | `ExamSessionController` | `take` | Live exam page |
| POST | `/student/attempt/{attempt}/save` | `ExamSessionController` | `saveAnswer` | AJAX auto-save answer |
| POST | `/student/attempt/{attempt}/violation` | `ExamSessionController` | `violation` | AJAX report violation |
| POST | `/student/attempt/{attempt}/disconnect` | `ExamSessionController` | `disconnect` | Browser close event |
| POST | `/student/attempt/{attempt}/submit` | `ExamSessionController` | `submit` | Manual submit |
| GET | `/student/results` | `StudentResultController` | `index` | My results |

### 9.7 API Routes (`/api/*`, Sanctum token required)

| Method | Route | Purpose |
|--------|-------|---------|
| GET | `/api/user` | Get authenticated user with role |
| GET | `/api/courses` | Paginated courses with teacher |
| GET | `/api/exams` | Paginated exams with course and schedule |

---

## 10. DEVELOPER CHANGE GUIDE

### 10.1 Adding a New Database Feature

**Example: Adding a "Feedback" table for students to leave exam feedback**

**Step 1 — Migration:**
```bash
php artisan make:migration create_exam_feedbacks_table
```
```php
// database/migrations/YYYY_MM_DD_create_exam_feedbacks_table.php
Schema::create('exam_feedbacks', function (Blueprint $table) {
    $table->id();
    $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
    $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
    $table->text('feedback');
    $table->integer('rating')->nullable();
    $table->timestamps();
    $table->unique(['exam_id', 'student_id']);
});
```

**Step 2 — Model:**
```php
// app/Models/ExamFeedback.php
class ExamFeedback extends Model {
    protected $fillable = ['exam_id', 'student_id', 'feedback', 'rating'];
    public function exam(): BelongsTo { return $this->belongsTo(Exam::class); }
    public function student(): BelongsTo { return $this->belongsTo(User::class, 'student_id'); }
}
```

**Step 3 — Add relationship to Exam model:**
```php
// app/Models/Exam.php
public function feedbacks(): HasMany {
    return $this->hasMany(ExamFeedback::class);
}
```

**Step 4 — Service (if business logic needed):**
```php
// app/Services/FeedbackService.php — create if logic is complex
// Or put simple logic directly in controller
```

**Step 5 — Controller:**
```php
// app/Http/Controllers/Student/FeedbackController.php
public function store(Request $request, Exam $exam) {
    $data = $request->validate(['feedback' => 'required|string', 'rating' => 'nullable|integer|min:1|max:5']);
    ExamFeedback::create([...$data, 'exam_id' => $exam->id, 'student_id' => auth()->id()]);
    return back()->with('success', 'Feedback submitted.');
}
```

**Step 6 — Route:**
```php
// routes/web.php — inside student prefix group
Route::post('exams/{exam}/feedback', [FeedbackController::class, 'store'])->name('student.exams.feedback');
```

**Step 7 — View:** Add a feedback form to `resources/views/student/exams/show.blade.php`

---

### 10.2 Adding a New Email Type

**Example: Adding a "Result Published" email**

**Step 1 — Create Blade email template:**
```
resources/views/emails/result-published.blade.php
```

**Step 2 — Use EmailService in the right place:**
```php
// In NotifyStudentResults command, after notify():
$bodyHtml = view('emails.result-published', [
    'studentName' => $student->name,
    'examTitle'   => $exam->title,
])->render();

app(EmailService::class)->send(
    $student->email, $student->name,
    'Your Exam Result is Ready',
    $bodyHtml, 'exam_result', null, $student->id, true
);
```

**Step 3 — No new job needed** — `EmailService::send()` dispatches `SendEmailJob` automatically.

---

### 10.3 Adding a New Exam Security Rule

**Example: Adding a rule that blocks copy events as a counted violation (currently it blocks silently)**

**Step 1 — JavaScript (`exam-anticheat.js`):**
```javascript
// Change the copy handler to report a violation instead of just blocking:
function onCopy(e) {
    e.preventDefault();
    reportViolation('copy_attempt', 'Student attempted to copy content');
}
document.addEventListener('copy', onCopy);
```

**Step 2 — PHP (`ExamSecurityService`):**
No change needed — `recordViolation()` accepts any `type` string. The 3-strike logic applies universally.

**Step 3 — Controller (`ExamSessionController::renderExamView()`):**
Add `'copy_detection_enabled' => true` to the `securityPolicy` array (already present).

**Step 4 — Blade view (`student/exam/take.blade.php`):**
Pass the policy flag to the JS via `data-policy-copy` attribute — already handled.

---

### 10.4 Adding a New User Role

**Example: Adding an "Examiner" role**

**Step 1 — Add to roles table:**
```php
// In a seeder or migration:
Role::create(['name' => 'Examiner', 'slug' => 'examiner']);
```

**Step 2 — Add to RoleSlug enum:**
```php
// app/Enums/RoleSlug.php
public const EXAMINER = 'examiner';
```

**Step 3 — Add helper to User model:**
```php
public function isExaminer(): bool { return $this->role?->slug === RoleSlug::EXAMINER; }
```

**Step 4 — Create routes:**
```php
// routes/web.php
Route::prefix('examiner')->middleware('role:examiner')->name('examiner.')->group(function () {
    Route::get('dashboard', [ExaminerDashboardController::class, 'index'])->name('dashboard');
});
```

**Step 5 — Create controllers, views, sidebar partial**

**Step 6 — Update `AuthController::dashboardRoute()`:**
```php
private function dashboardRoute(User $user): string {
    return match(true) {
        $user->isAdmin()    => route('admin.dashboard'),
        $user->isTeacher()  => route('teacher.dashboard'),
        $user->isExaminer() => route('examiner.dashboard'),
        default             => route('student.dashboard'),
    };
}
```

**Step 7 — Update `ForcePasswordChange` middleware** if examiners should also be excluded from forced changes.


### 10.5 Adding a New Page

**Example: Adding an "Announcements" page for students**

```
1. Migration: create announcements table
   php artisan make:migration create_announcements_table

2. Model: app/Models/Announcement.php

3. Controller: app/Http/Controllers/Student/AnnouncementController.php
   public function index() {
       $announcements = Announcement::latest()->get();
       return view('student.announcements.index', compact('announcements'));
   }

4. Route (inside student prefix group in web.php):
   Route::get('announcements', [AnnouncementController::class, 'index'])->name('student.announcements.index');

5. View: resources/views/student/announcements/index.blade.php
   @extends('layouts.app')
   @section('title', 'Announcements')
   @section('sidebar') @include('partials.student-sidebar') @endsection
   @section('content')
       {{-- list announcements --}}
   @endsection

6. Add to student sidebar: resources/views/partials/student-sidebar.blade.php
   <a href="{{ route('student.announcements.index') }}" class="nav-link">
       <i class="bi bi-megaphone"></i> Announcements
   </a>
```

### 10.6 Modifying the Exam Attempt Limit Logic

**Where to change:** `ExamAccessService::studentCanTakeExam()`

```php
// Current logic:
$allowedAttempts = max(1, (int) ($schedule->attempt_limit ?? 1));
$usedAttempts = ExamAttempt::where('exam_id', $exam->id)
    ->where('student_id', $user->id)
    ->whereIn('status', ['submitted', 'terminated', 'suspicious', 'rejected'])
    ->count();
if ($usedAttempts >= $allowedAttempts) { return false; }
```

To allow re-attempts after ABSENT marking:  
Add `ABSENT` results logic and exclude them from `usedAttempts` count.

### 10.7 Adding a New Question Type

**Example: Adding a "Short Answer" question type**

```
1. Migration: Add 'short_answer' to questions.type enum
   $table->enum('type', ['mcq', 'true_false', 'essay', 'fill_blank', 'short_answer']);

2. Teacher exam creation view:
   Add 'Short Answer' option to question type dropdown
   resources/views/teacher/exams/show.blade.php

3. GradingService::gradeAttempt():
   Add elseif for 'short_answer' — decide if manual or auto-graded

4. Student exam take view:
   Add a text input for 'short_answer' type questions
   resources/views/student/exam/take.blade.php

5. ExamSessionController::saveAnswer():
   Already handles answer_text — short_answer would use same mechanism

6. Teacher\ExamController::addQuestion():
   Add 'short_answer' to the allowed type validation list
```

---

## 11. DEBUGGING MANUAL

### 11.1 Application Logs

**Primary log file:**
```
storage/logs/laravel.log
```

**Log levels in this project:** `debug`, `info`, `warning`, `error`

**Log entries to look for:**

| Message Pattern | Meaning |
|----------------|---------|
| `InboxSyncService: sync already running` | Concurrent sync blocked — normal |
| `InboxSyncService: IMAP connection error` | IMAP credentials wrong or server unreachable |
| `InboxSyncService: UID SEARCH returned UIDs = []` | No new emails — normal |
| `EmailService::deliver failed for log #N` | SMTP delivery failed — check credentials |
| `ExamSecurityService: email failed for recipient #N` | Security email send failed — non-fatal |
| `InboxSyncJob failed after max retries` | Queue job exhausted retries |
| `CheatingDetectedMail failed` | Legacy email path failed |

### 11.2 Database Debugging

**Check a student's exam attempt:**
```sql
SELECT ea.*, u.name, e.title
FROM exam_attempts ea
JOIN users u ON u.id = ea.student_id
JOIN exams e ON e.id = ea.exam_id
WHERE ea.student_id = {student_id}
ORDER BY ea.created_at DESC;
```

**Check pending queue jobs:**
```sql
SELECT id, queue, attempts, payload, created_at FROM jobs WHERE queue = 'emails';
```

**Check failed jobs:**
```sql
SELECT id, queue, exception, failed_at FROM failed_jobs ORDER BY failed_at DESC;
```

**Check email log for a user:**
```sql
SELECT id, to_email, subject, status, error, sent_at
FROM email_logs WHERE user_id = {user_id} ORDER BY created_at DESC;
```

**Check cheating violations for an attempt:**
```sql
SELECT cl.*, u.name
FROM cheating_logs cl
JOIN users u ON u.id = cl.student_id
WHERE cl.attempt_id = {attempt_id};
```

**Check inbox sync state:**
```sql
SELECT * FROM inbox_sync_state;
```

**Reset inbox sync (force full re-sync):**
```sql
UPDATE inbox_sync_state SET last_uid = 0 WHERE account = 'default';
```

### 11.3 Common Problems and Solutions

---

**Problem: Student cannot start exam — "exam not available"**

Check in order:
1. Is exam status `published` or `approved`?
   ```sql
   SELECT id, title, status FROM exams WHERE id = {exam_id};
   ```
2. Does exam have a schedule?
   ```sql
   SELECT * FROM exam_schedules WHERE exam_id = {exam_id};
   ```
3. Is schedule active right now (starts_at ≤ now ≤ ends_at)?
4. Is student enrolled in the course?
   ```sql
   SELECT * FROM enrollments WHERE course_id = {course_id} AND student_id = {student_id};
   ```
5. Does student have a matching StudentYearRecord for this exam's academic year + year level?
   ```sql
   SELECT * FROM student_year_records WHERE student_id = {id} AND academic_year_id = {ay_id};
   ```
6. Has student already used all allowed attempts?
   ```sql
   SELECT COUNT(*) FROM exam_attempts
   WHERE exam_id = {id} AND student_id = {id}
   AND status IN ('submitted','terminated','suspicious','rejected');
   ```

---

**Problem: Queue emails not sending**

1. Is the queue worker running?
   ```bash
   php artisan queue:work --queue=emails
   ```
2. Are there jobs in the queue?
   ```sql
   SELECT COUNT(*) FROM jobs WHERE queue = 'emails';
   ```
3. Are there failed jobs?
   ```bash
   php artisan queue:failed
   ```
4. Check SMTP credentials in `.env`:
   `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`
5. Check `storage/logs/laravel.log` for SMTP error messages

---

**Problem: IMAP inbox sync not working**

1. Check IMAP environment variables in `.env`:
   `IMAP_HOST`, `IMAP_PORT`, `IMAP_USERNAME`, `IMAP_PASSWORD`, `IMAP_ENCRYPTION`
2. For Gmail: ensure App Password is used (not account password), IMAP must be enabled in Gmail settings
3. Test manually:
   ```bash
   php artisan inbox:sync
   ```
4. Check logs for IMAP connection error messages
5. Check `inbox_sync_state` to see last synced UID
6. If sync says "already running", the cache lock is stuck:
   ```php
   // In Tinker: php artisan tinker
   Cache::forget('imap_inbox_sync');
   ```

---

**Problem: Exam auto-submits immediately when student opens it**

1. Check `expires_at` on the attempt:
   ```sql
   SELECT started_at, expires_at, status FROM exam_attempts WHERE id = {attempt_id};
   ```
2. `expires_at` = `MIN(started_at + duration_minutes, schedule.ends_at)`
3. If `expires_at` is in the past → exam correctly auto-submits
4. Likely cause: Student started very close to `ends_at` of the schedule
5. Admin needs to check schedule timing

---

**Problem: "Another active exam session was detected" on login**

Cause: `EnsureSingleExamSession` middleware detected token mismatch.  
This happens when a student is logged in on two browsers/devices.

Fix (admin):
```sql
UPDATE users SET exam_session_token = NULL WHERE id = {student_id};
```
Student can then log in normally on their device.

---

**Problem: Migration error on `php artisan migrate`**

Common causes:
1. Migration already ran — check `migrations` table:
   ```sql
   SELECT migration FROM migrations WHERE migration LIKE '%table_name%';
   ```
2. Duplicate column/key — migration conflicts with existing schema
3. Foreign key reference to non-existent table — run migrations in correct order

Fix for "already exists" error:
```bash
php artisan migrate --pretend  # shows SQL without running
php artisan migrate:status     # shows which migrations ran
```

---

**Problem: JavaScript console errors on exam page**

Key things to check in browser DevTools:
1. `console.log` of `body.dataset` — verify all data attributes are present
2. Network tab → check failed AJAX requests to `/save`, `/violation`
3. `csrf-token` meta tag present in page source?
4. `violationUrl`, `saveUrl`, `submitUrl`, `disconnectUrl` set on `#examBody`?
5. Check for 419 (CSRF mismatch) or 403 (session expired) responses

---

**Problem: Student answer not saving**

1. Check network tab in DevTools — is POST to `/save` returning 200?
2. Check response body — `{success: true}` expected
3. Check if `exam.active` middleware blocked the request (attempt not `in_progress`)
4. Check `student_answers` table directly:
   ```sql
   SELECT * FROM student_answers WHERE attempt_id = {attempt_id};
   ```

---

**Problem: Notification bell not showing new notifications**

1. Notification polling runs every 30 seconds in `layouts/app.blade.php`
2. Check `/notifications/unread-count` endpoint manually in browser
3. Check `user_notifications` table:
   ```sql
   SELECT * FROM user_notifications WHERE user_id = {id} AND is_read = 0 ORDER BY created_at DESC;
   ```
4. Check browser console for fetch errors

---

**Problem: Result shows as NULL or missing**

1. Check if `results:mark-absent` command ran for ABSENT students
2. Check `GradingService` — was `gradeAttempt()` called?
3. Check `results` table:
   ```sql
   SELECT * FROM results WHERE exam_id = {id} AND student_id = {id};
   ```
4. If result exists but `is_published = false` — student cannot see it
   (Note: is_published is always set to true by GradingService, but DISQUALIFIED results have `is_passed=false`)

---

## 12. VISUAL DOCUMENTATION

### 12.1 Complete System Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                    BELIEVE EXAM PORTAL                              │
│                    Laravel 9 / PHP 8 / MySQL                        │
├───────────────┬─────────────────────┬───────────────────────────────┤
│   ADMIN       │      TEACHER        │          STUDENT              │
│  /admin/*     │    /teacher/*       │        /student/*             │
├───────────────┴─────────────────────┴───────────────────────────────┤
│                    MIDDLEWARE STACK                                  │
│  auth → exam.session → force.password.change → role:X              │
│  [live exam routes also: exam.active]                               │
├─────────────────────────────────────────────────────────────────────┤
│                    CONTROLLER LAYER                                  │
│  Admin/ | Teacher/ | Student/ | Auth/ | Dashboard | Notification    │
├─────────────────────────────────────────────────────────────────────┤
│                     SERVICE LAYER                                    │
│  ExamSecurity | Grading | SessionRecovery | EmailService            │
│  InboxSync | ActivityLog | Notification | ExamAccess | Encryption   │
├─────────────────────────────────────────────────────────────────────┤
│                    ELOQUENT ORM LAYER                                │
│  User | Exam | ExamAttempt | Question | Answer | Result | ...       │
├─────────────────────────────────────────────────────────────────────┤
│                    MySQL DATABASE (b_exam)                           │
│  30+ tables — users, exams, attempts, results, email_logs, ...      │
├─────────────────────────────────────────────────────────────────────┤
│                   BACKGROUND SYSTEMS                                 │
│  Queue Worker (jobs table)    │  Scheduler (cron every minute)      │
│  SendEmailJob                 │  inbox:sync                         │
│  InboxSyncJob                 │  results:notify-students            │
│  SendWelcomeAccountJob        │                                     │
├─────────────────────────────────────────────────────────────────────┤
│                   EXTERNAL SERVICES                                  │
│  Brevo SMTP (outgoing email)  │  IMAP Server (incoming email)       │
└─────────────────────────────────────────────────────────────────────┘
```

### 12.2 Technology Architecture Diagram

```
BROWSER
    │
    ├── HTML/CSS: Bootstrap 5.3 + Custom BLC Theme + Bootstrap Icons
    ├── JS: Vanilla JS + jQuery (DataTables only) + exam-anticheat.js
    └── AJAX: Fetch API (notifications, answer-save, violation-report)
    │
    ▼ HTTP/HTTPS
PHP 8 / Laravel 9
    │
    ├── Routing (routes/web.php + routes/api.php)
    ├── Middleware (7 custom + Laravel built-ins)
    ├── Controllers (20+ controllers)
    ├── Services (15 services)
    ├── Eloquent ORM (24 models)
    ├── Blade Templates (resources/views/)
    ├── Queue System (database driver, 'emails' queue)
    ├── Scheduler (Kernel.php — inbox:sync, notify-students)
    ├── Mail (Laravel Mail → Brevo SMTP)
    └── IMAP (webklex/laravel-imap → Gmail IMAP)
    │
    ▼ PDO/MySQL
MySQL Database (b_exam)
    │
    └── 30+ tables, relationships, indexes, soft deletes
```

### 12.3 Database ER Diagram (Simplified)

```
roles ──────────────────────── users ──────────────────────── profile_otps
                                 │
           ┌─────────────────────┼───────────────────────────┐
           │                     │                           │
        courses              enrollments            student_year_records
           │                     │                           │
        ┌──┴───┐            year_levels                 academic_years
        │      │                 │
      exams  exams            majors
        │
    ┌───┴──────────┬─────────────┐
    │              │             │
exam_schedules  questions    exam_attempts
                   │              │
                answers      ┌────┴──────────────────┐
                             │           │            │
                       student_answers  results  cheating_logs
                                          │
                                   session_recovery_logs

── EMAIL SYSTEM ──
email_logs ◄── SendEmailJob ◄── EmailService
inbox_emails ◄── InboxSyncService ◄── inbox_sync_state

── NOTIFICATION SYSTEM ──
user_notifications ◄── NotificationService

── AUDIT SYSTEM ──
activity_logs ◄── ActivityLogService
```

### 12.4 Authentication Flow Diagram

```
GET /login
    │
    ▼
Login Form (auth/login.blade.php)
    │ POST email + password
    ▼
AuthController::login()
    │
    ├─(locked?)────────────────► Error: "Account locked, N minutes remaining"
    │
    ├─(wrong password?)─────────► incrementFailedLogins()
    │                                  │
    │                             ─(3rd failure?)──► Lock account 10 min
    │                                                 Error with countdown
    │
    ├─(correct password)────────►
    │   │
    │   ├─(not active?)──────────► Logout + Error: "Account deactivated"
    │   │
    │   ├─(temp password expired?)► Logout + Error + Show "Request New Password" link
    │   │
    │   └─(all checks pass)──────►
    │       resetFailedLogins()
    │       update last_login_at
    │       log 'login'
    │       session()->regenerate()
    │           │
    │           ├─(force_password_change?)── Redirect /password/change
    │           │
    │           ├─(admin)──── Redirect /admin/dashboard
    │           ├─(teacher)── Redirect /teacher/dashboard
    │           └─(student)── Redirect /student/dashboard
```

### 12.5 Student Exam Flow Diagram

```
Student Dashboard
    │
    ▼ GET /student/exams
Exam List
    │ Exam is PUBLISHED, schedule is active
    ▼ POST /student/exams/{exam}/start
ExamAccessService::studentCanTakeExam() — all checks pass
    │
ExamAttempt created (status=in_progress, expires_at=MIN(start+duration, ends_at))
    │
    ▼ Redirect GET /student/attempt/{attempt}/take
Live Exam Page (student/exam/take.blade.php)
    │ Questions decrypted and rendered
    │ exam-anticheat.js initializes
    │ Countdown timer starts
    │
    ├─ Student selects answer ──► AJAX POST /save ──► StudentAnswer::updateOrCreate()
    │
    ├─ Anti-cheat event ────────► AJAX POST /violation ──► ExamSecurityService
    │                                 │
    │                            Warning 1 (continue)
    │                            Warning 2 (continue + notify)
    │                            Warning 3 (TERMINATE + DISQUALIFY)
    │
    ├─ Browser close ───────────► navigator.sendBeacon /disconnect
    │                            attempt.disconnected_at = now (status stays in_progress)
    │                            Student returns within 5 min → session restored
    │
    ├─ Timer expires ───────────► submitAttempt() → auto-submit
    │
    └─ Student clicks Submit ───► POST /submit
                                    attempt.status = submitted
                                    GradingService::gradeAttempt()
                                    Result created (PASSED/FAILED)
                                    Redirect to exam show page
                                    
After ends_at passes (next minute):
    results:notify-students command ──► UserNotification created ──► Badge appears
```

### 12.6 Anti-Cheat System Flow Diagram

```
BROWSER (exam-anticheat.js)          SERVER (ExamSecurityService)
─────────────────────────            ────────────────────────────
Exam page loads
    │
    ▼
Fullscreen requested (user clicks button)
    │ activateExamSession()
    ▼
Exam session active (examStarted = true)

EVENT DETECTION:
─────────────────
Tab switch / window blur ──────────────────────────────────────────────┐
Fullscreen exit (10s grace) ────────────────────────────────────────── │
DevTools shortcut ────────────────────────────────────────────────── ─ │
                                                                        │
    All events → reportViolation(type, details)                         │
    → fetch POST /student/attempt/{id}/violation                        │
                              ▼                                         │
                   ExamSecurityService::recordViolation()               │
                              │                                         │
                    ┌─────────┼──────────────────────┐                 │
                    │         │                       │                 │
                warning_count warning_count     warning_count          │
                    = 1           = 2               = 3                 │
                    │             │                   │                 │
              Warn only    Warn + Email      DB::transaction:           │
              Continue     + Notify          lockForUpdate()            │
              {warned:1}   teacher+admins    terminate attempt          │
                           Continue          disqualify result          │
                           {warned:2}        clear session token        │
                                             DB::afterCommit:           │
                                             email all parties          │
                                             {terminated:true}          │
                                                    │                   │
RESPONSE HANDLING:                                  │                   │
──────────────────                                  ▼                   │
handleViolationResponse(data)          lockExamInterface()             ◄┘
    │                                  stop timers, disable controls
    ├─ Show warning box 8s             show lock overlay
    │                                  redirect in 3s
    └─ If terminated:
         lockExamInterface()
         setTimeout 3s → redirect
```

### 12.7 Email System Flow Diagram

```
OUTGOING EMAIL FLOW:
────────────────────
Application Event (user created, security violation, etc.)
    │
    ▼
EmailService::send()
    → EmailLog::create({status: 'queued'})
    → SendEmailJob::dispatch(logId)
    │
    ▼ (queue worker)
jobs TABLE → SendEmailJob::handle()
    → EmailLog::find(logId)
    → EmailService::deliver()
    → Mail::send() → Laravel Mailer
    │
    ▼
Brevo SMTP Server (smtp-relay.brevo.com:587)
    │
    ▼
Recipient's Inbox
    │
    └── EmailLog.status = 'sent' (or 'failed' with error message)

INCOMING EMAIL FLOW:
────────────────────
Gmail / Email Provider INBOX
    │ (IMAP SSL port 993)
    ▼
Scheduler → inbox:sync command (every minute)
    → InboxSyncService::sync()
    │
    ├── Acquire cache lock ('imap_inbox_sync')
    ├── Read last_uid from inbox_sync_state
    ├── IMAP SELECT → read uidnext
    ├── UID SEARCH 'UID {last+1}:*'
    │
    └── For each new UID:
            Check from_email → must be registered student
            Parse body, subject, threading headers
            InboxEmail::create({thread_id, parent_id, ...})
            Advance last_uid checkpoint
            Broadcast NewEmailReceived event
    │
    ▼
inbox_emails TABLE
    │
    ▼
Admin Email Inbox UI (/admin/email/inbox)
    Admin can: read, reply (via SMTP), archive

ADMIN REPLY FLOW:
─────────────────
Admin clicks Reply → POST /admin/email/inbox/{email}/reply
    → EmailController::replyInbox()
    → EmailService::send(student.email, ..., bodyHtml, queue=true)
    → SendEmailJob → SMTP → Student's inbox
    → InboxEmail.status = 'replied'
```

### 12.8 Queue System Flow Diagram

```
HTTP Request (user action)
    │
    ▼
Job::dispatch(params)
    │ Serializes to JSON
    ▼
INSERT INTO jobs (queue='emails', payload=..., available_at=now())
    │
    ▼ (Background — queue worker process)
php artisan queue:work --queue=emails
    │ Polls jobs table every second
    ▼
SELECT * FROM jobs WHERE queue='emails' AND available_at <= now() LIMIT 1 FOR UPDATE
    │
    ▼ (for example: SendEmailJob)
SendEmailJob::handle(EmailService $emailService)
    → $emailService->deliver(EmailLog::find($this->logId))
    → Mail::send() → SMTP
    │
    ├── SUCCESS → DELETE FROM jobs WHERE id = {id}
    │              EmailLog.status = 'sent'
    │
    └── FAILURE (exception thrown)
            attempts++
            If attempts < $tries (3):
                UPDATE jobs SET available_at = now() + backoff(30s), reserved_at = null
                (job retried after 30 seconds)
            If attempts >= $tries:
                INSERT INTO failed_jobs (payload, exception, failed_at)
                DELETE FROM jobs
                EmailLog.status = 'failed'
                → Admin can retry from /admin/email/logs
```

### 12.9 Data Flow Diagram — Complete Request Lifecycle

```
Browser (Student)
    │
    │  GET /student/attempt/42/take
    ▼
public/index.php (Laravel entry point)
    │
    ▼
bootstrap/app.php → Application instance
    │
    ▼
app/Http/Kernel.php — Global middleware applied
    (EncryptCookies, StartSession, VerifyCsrfToken, ...)
    │
    ▼
routes/web.php → Match route: student.exam.take
    Route middleware: auth, exam.session, force.password.change, role:student, exam.active
    │
    ▼
Middleware 1: auth → check session, user is authenticated
Middleware 2: exam.session → validate exam_session_token matches
Middleware 3: force.password.change → skip if flag is false
Middleware 4: role:student → user.role.slug === 'student'
Middleware 5: exam.active → attempt.status === 'in_progress'
    │
    ▼
ExamSessionController::take(ExamAttempt $attempt)
    [Route model binding: ExamAttempt::find(42)]
    │
    ├── authorizeAttempt() → attempt.student_id === auth().id
    │
    ├── Check disconnected_at → SessionRecoveryService::handleReconnect()
    │
    ├── Check expires_at → submitAttempt() if expired
    │
    ├── computeNormalSeconds() → remaining timer
    │
    └── renderExamView():
            ExamAccessService::canDecryptQuestions()
            EncryptionService::decrypt() for each question + answer
            →  view('student.exam.take', [...data...])
    │
    ▼
Blade template compiled → HTML response
    │
    ▼
Browser receives HTML
    → exam-anticheat.js initializes
    → Countdown timer starts
    → Anti-cheat listeners attached
```

### 12.10 IMAP Sync Flow Diagram

```
SCHEDULER (every minute)
    │
    ▼
php artisan inbox:sync
    → SyncInbox::handle()
    → InboxSyncService::sync()
    │
    ▼ Try to acquire Cache lock
    ├─ LOCKED (another sync running) → Return "Sync already running"
    └─ ACQUIRED ─────────────────────────────────────────────────────┐
                                                                      │
    ▼                                                                 │
Read last_uid from inbox_sync_state                                   │
    │                                                                 │
    ▼                                                                 │
Webklex IMAP Client::account('default')                               │
    → Connect to IMAP server (ssl:993)                                │
    → getFolderByName('INBOX')                                        │
    → openFolder() → get uidnext from SELECT response                 │
    │                                                                 │
    ├─ nextUid >= uidnext → No new emails → Release lock → Return     │
    │                                                                 │
    ▼                                                                 │
connection->search(["UID {nextUid}:*"])                               │
    → Returns array of UIDs above last_uid                            │
    │                                                                 │
    ▼                                                                 │
Sort UIDs ascending (oldest first)                                    │
    │                                                                 │
    ▼ For each UID:                                                   │
    │                                                                 │
    ├─ Dedup check (uid-key in inbox_emails.message_id)               │
    │     EXISTS → skipped → advance checkpoint → next UID            │
    │                                                                 │
    ├─ Extract from_email from IMAP message                           │
    │     EMPTY → filtered → do NOT advance checkpoint                │
    │                                                                 │
    ├─ User::where('email', from_email) → must be registered student  │
    │     NOT FOUND or NOT student → filtered → do NOT advance        │
    │                                                                 │
    ├─ message->parseBody() → get body_html, body_text               │
    │                                                                 │
    ├─ Extract In-Reply-To, References headers                        │
    │                                                                 │
    ├─ resolveThread(messageId, inReplyTo, references)                │
    │     → thread_id = md5(root_message_id)                          │
    │     → parent_id = FK to parent inbox_email row                  │
    │                                                                 │
    ├─ InboxEmail::create({...all fields...})                         │
    │                                                                 │
    ├─ event(new NewEmailReceived($stored))                           │
    │                                                                 │
    └─ InboxSyncState::saveLastUid(uid) → advance checkpoint          │
         On error: break loop (retry from last good UID)              │
                                                                      │
    ▼                                                                 │
IMAP disconnect                                                       │
Release Cache lock ◄──────────────────────────────────────────────────┘
    │
    ▼
Return {imported: N, skipped: M, errors: K, message: "..."}
```

---

## APPENDIX A: ENVIRONMENT VARIABLES REFERENCE

| Variable | Default | Purpose |
|----------|---------|---------|
| `APP_NAME` | `Believe Exam` | Application display name |
| `APP_ENV` | `local` | Environment (local/production) |
| `APP_KEY` | *(generated)* | 32-char encryption key for Crypt |
| `APP_DEBUG` | `true` | Show detailed errors |
| `APP_URL` | `http://localhost` | Base URL for route generation |
| `DEFAULT_ADMIN_EMAIL` | `admin@blc.edu.mm` | Auto-created admin email |
| `DEFAULT_ADMIN_PASSWORD` | `password` | Auto-created admin password |
| `DB_CONNECTION` | `mysql` | Database driver |
| `DB_HOST` | `127.0.0.1` | Database host |
| `DB_PORT` | `3306` | Database port |
| `DB_DATABASE` | `b_exam` | Database name |
| `QUEUE_CONNECTION` | `database` | Queue driver (jobs table) |
| `SESSION_DRIVER` | `file` | Session storage |
| `SESSION_LIFETIME` | `120` | Session duration (minutes) |
| `MAIL_MAILER` | `smtp` | Mail driver |
| `MAIL_HOST` | `smtp-relay.brevo.com` | SMTP server |
| `MAIL_PORT` | `587` | SMTP port |
| `MAIL_USERNAME` | *(your Brevo email)* | SMTP username |
| `MAIL_PASSWORD` | *(your Brevo SMTP key)* | SMTP password |
| `MAIL_ENCRYPTION` | `tls` | SMTP encryption |
| `MAIL_FROM_ADDRESS` | *(your domain)* | From email address |
| `IMAP_HOST` | `localhost` | IMAP server host |
| `IMAP_PORT` | `993` | IMAP port (993 = SSL) |
| `IMAP_USERNAME` | *(inbox email)* | IMAP login |
| `IMAP_PASSWORD` | *(inbox password)* | IMAP password |
| `IMAP_ENCRYPTION` | `ssl` | IMAP encryption |
| `EXAM_RECOVERY_TIME_LIMIT` | `300` | Session recovery window (seconds) |

---

## APPENDIX B: FILE STRUCTURE QUICK REFERENCE

```
app/
├── Console/Commands/          Custom Artisan commands
├── Enums/                     RoleSlug, RecordType constants
├── Events/                    NewEmailReceived
├── Http/Controllers/          Admin/, Teacher/, Student/, Auth/
├── Http/Middleware/           7 custom middleware classes
├── Jobs/                      7 queued job classes
├── Mail/                      Legacy Mailable classes (mostly replaced by EmailService)
├── Models/                    24 Eloquent models
├── Providers/                 Service providers
├── Services/                  15 business logic services
└── Support/                   AcademicYear options helper

config/
├── app.php                    App config
├── auth.php                   Auth guards and providers
├── believe.php                Custom BLC config
├── exam_security.php          Recovery time limit config
├── imap.php                   Webklex IMAP config
├── mail.php                   Mail/SMTP config
└── queue.php                  Queue driver config

database/
├── migrations/                44 migration files
├── seeders/                   Database seeders
└── factories/                 Model factories for testing

resources/
├── views/
│   ├── admin/                 Admin panel views
│   ├── auth/                  Login, register, password pages
│   ├── emails/                Email templates (Blade)
│   ├── layouts/app.blade.php  Master layout
│   ├── partials/              Sidebar components
│   ├── student/               Student views
│   └── teacher/               Teacher views

public/
├── css/believe-theme.css      Custom BLC stylesheet
├── js/
│   ├── exam-anticheat.js      Anti-cheat system (808 lines)
│   ├── profile.js             Profile page JS
│   └── question-builder.js    Question creation UI

routes/
├── web.php                    All web routes
└── api.php                    Sanctum API routes
```

---

## APPENDIX C: EXAM ATTEMPT STATUS REFERENCE

| Status | Set By | Meaning | Student Can Continue? |
|--------|--------|---------|----------------------|
| `in_progress` | `Student\ExamController::start()` | Exam is active | YES |
| `submitted` | `ExamSessionController::submit()` or auto-submit | Normally completed | NO |
| `terminated` | `ExamSecurityService` (3rd violation) | Hard terminated by system | NO |
| `suspicious` | Legacy `CheatingDetectionService` | Old termination path | NO |
| `terminated_pending_review` | Legacy path [Needs verification] | Awaiting admin review | NO (until approved) |
| `rejected` | `ExamSecurityService::reject()` | Admin rejected after review | NO |

---

*End of BLC Complete Developer Knowledge Manual*  
*Generated from full codebase analysis of Believe Exam Portal — Laravel 9 / PHP 8*  
*All implementation details verified from actual source code.*  
*Sections marked [Needs verification] indicate areas requiring manual confirmation.*
