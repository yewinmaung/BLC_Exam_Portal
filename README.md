# Believe Exam - Advanced Online Examination System

Laravel 9 online examination platform with encrypted questions, role-based access, anti-cheating, live chat, and automated grading.

## Features

- **Roles:** Admin, Teacher, Student (RBAC)
- **Encrypted Q&A** in database (Laravel Crypt)
- **Exam workflow:** Draft → Approval → Schedule → Publish
- **Anti-cheat:** Fullscreen, tab detection, 3-warning auto-terminate
- **Auto-save** answers via AJAX
- **Live chat** with polling
- **Email alerts** for cheating and exam publish
- **Bootstrap 5** responsive UI with dark mode

## Quick Start

See [documents/INSTALLATION.md](documents/INSTALLATION.md) for full setup with XAMPP.

```bash
composer install
cp .env.example .env   # Windows: copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Visit `http://127.0.0.1:8000/login`

**Demo accounts:** `admin@believeexam.com` / `password` (also teacher & student variants)

## Project Structure

- `app/Services/` - Encryption, grading, cheating detection, exam access
- `app/Http/Controllers/` - Admin, Teacher, Student, Auth, Chat
- `database/migrations/` - Full examination schema
- `public/js/exam-anticheat.js` - Browser security during exams
- `resources/views/` - Blade UI templates

## Specification

Built from `documents/full.md`.
