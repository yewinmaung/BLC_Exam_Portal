# Believe Exam - Installation Guide

## Requirements

- PHP 8.0+
- Composer
- MySQL (XAMPP recommended)
- Node.js & NPM (optional, for assets)

## XAMPP Setup

1. Start **Apache** and **MySQL** in XAMPP Control Panel.
2. Create database in phpMyAdmin:
   ```sql
   CREATE DATABASE believe_exam CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

## Install Application

```bash
cd D:\Believe_Exam\Believe_Exam
composer install
copy .env.example .env
php artisan key:generate
```

## Configure `.env`

```env
APP_NAME="Believe Exam"
APP_URL=http://localhost/Believe_Exam/Believe_Exam/public

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=b_exam
DB_USERNAME=root
DB_PASSWORD=

MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
```

## Run Migrations & Seed

```bash
php artisan migrate --seed
php artisan storage:link
```

## Default Login Accounts

| Role    | Email                    | Password  |
|---------|--------------------------|-----------|
| Admin   | admin@believeexam.com    | password  |
| Teacher | teacher@believeexam.com  | password  |
| Student | student@believeexam.com  | password  |

## Apache Virtual Host (Optional)

Point document root to `/public` folder.

## Security Notes

- Questions and answers are encrypted with Laravel `Crypt` (AES-256).
- Students cannot decrypt questions before active exam schedule.
- Anti-cheat: fullscreen, tab switch detection, 3-strike termination.
- Single exam session per student via `exam_session_token`.

## Admin: assign courses to teachers and students

| Who | Where | What admin can edit |
|-----|--------|---------------------|
| **Teacher** | Admin → Teachers → open teacher | Check/uncheck courses the teacher teaches |
| **Student** | Admin → Students → open student | Check/uncheck enrolled courses (requires academic year on the user) |
| **Course** | Admin → Courses → Edit | Pick assigned teacher and enrolled students |

Changes save immediately from the teacher/student detail page or the dedicated **Edit Courses** link in the list.

## Workflow

1. Admin creates course and assigns teacher/students (or uses Teachers / Students pages).
2. Teacher creates exam, adds encrypted questions, submits for approval.
3. Admin approves, sets schedule, publishes exam.
4. Student takes exam during active schedule.
5. Admin can **Close Exam** to block access, or **Open Exam** to restore access (same page as publish/close).
6. Results visible after schedule ends.

## Queue (Email)

For production email, set `QUEUE_CONNECTION=database` and run:

```bash
php artisan queue:table
php artisan migrate
php artisan queue:work
```
