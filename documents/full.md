# Advanced Online Examination System (Laravel 9)

Build a complete secure Online Examination Management System similar to Moodle using:

- Laravel 9
- PHP 8+
- MySQL
- XAMPP
- Bootstrap 5
- JavaScript
- CSS3
- DataTables
- Modern Responsive UI/UX
- AJAX
- Email Notification System
- Live Chat System

The system must be fully responsive for Desktop, Tablet, and Mobile devices.

---

# MAIN USER ROLES

1. Admin
2. Teacher
3. Student

Use Laravel Authentication with Role-Based Access Control (RBAC).

---

# SYSTEM FLOW

1. Teacher uploads Question and Answer.
2. Questions and Answers must be stored ENCRYPTED in database.
3. Only Teacher and Admin can decrypt/view Questions & Answers before exam schedule time.
4. Teacher submits exam to Admin for approval.
5. Admin sets Exam Schedule:
   - Exam Start Date/Time
   - Exam End Date/Time
   - Duration
   - Attempt Limit
6. Admin publishes exam.
7. Student can only access exam if:
   - Student enrolled/accessed the course
   - Exam schedule is active
8. At exam time:
   - Student can decrypt/view questions
   - Student answers exam
9. After exam schedule ends:
   - All roles instantly can see:
     - Result
     - Correct Answers
     - Score
     - Pass/Fail
10. Teacher can view all students' results.
11. Attempt reset/re-attempt:
     - Teacher approval required
     - Admin grants permission

---

# SECURITY REQUIREMENTS

## VERY IMPORTANT SECURITY FEATURES

### 1. Encrypt Questions & Answers
- Use Laravel Crypt or AES encryption
- Store encrypted content in database
- Decrypt only with proper authorization

### 2. Before Exam Time
- Student must NEVER access decrypted questions
- Protect routes with middleware

### 3. Browser Anti-Cheating System

During exam:
- Detect tab switching
- Detect browser minimize
- Detect leaving fullscreen

Disable:
- Right click
- Copy/Paste
- Text selection
- Developer tools shortcuts
- Print screen attempt detection (if possible)

### 4. Fullscreen Mandatory
Student must enter fullscreen before exam starts.

### 5. Warning System
- First violation → Warning 1
- Second violation → Warning 2
- Third violation:
  - Auto terminate exam
  - Auto logout student from exam
  - Save cheating log
  - Auto email Teacher and Admin
  - Mark exam suspicious

### 6. Auto Save Answers
Save answers every few seconds using AJAX.

### 7. Prevent Multiple Login Sessions
One student = one active exam session.

---

# MODULES

## 1. Authentication Module
- Login/Register
- Forgot Password
- Email Verification
- Role Middleware

## 2. Dashboard
Different dashboards for:
- Admin
- Teacher
- Student

## 3. Course Management
- Create Course
- Enroll Students
- Assign Teacher

## 4. Exam Management

Teacher:
- Create Exam
- Upload Questions
- MCQ
- True/False
- Essay
- File Upload Questions

## 5. Question Bank
- Categorized Questions
- Difficulty Levels
- Random Question Generator

## 6. Exam Schedule Module

Admin can:
- Schedule Exams
- Publish Exams
- Close Exams

## 7. Student Exam Panel
- Timer
- Fullscreen
- Pagination
- Progress Bar
- Submit Confirmation

## 8. Result System
- Instant Result
- Grade Calculation
- PDF Result Export
- Analytics

## 9. Attempt Management
- Teacher approval required
- Admin controls retry

## 10. Notification System
- Email notifications
- In-app notifications

## 11. Live Chat System
- Student ↔ Teacher
- Teacher ↔ Admin
- Real-time messaging

## 12. Audit Logs

Log:
- Login history
- Exam activity
- Cheating attempts
- Schedule changes

---

# DATABASE TABLES

Create normalized MySQL database tables for:

- users
- roles
- courses
- enrollments
- exams
- exam_schedules
- questions
- answers
- student_answers
- results
- exam_attempts
- notifications
- chat_messages
- cheating_logs
- activity_logs

Use proper:
- foreign keys
- indexing
- soft deletes
- timestamps

---

# UI/UX REQUIREMENTS

1. Modern dashboard design
2. Responsive layout
3. Bootstrap 5 cards/tables
4. DataTables integration
5. Dark/Light mode
6. Clean sidebar navigation
7. Toast notifications
8. Loading animations
9. Mobile-friendly exam screen

---

# LARAVEL REQUIREMENTS

Use:
- MVC Architecture
- Repository Pattern
- Service Layer
- Laravel Policies/Gates
- Middleware
- Form Requests Validation
- Eloquent ORM
- Laravel Events/Listeners
- Queued Email Jobs

---

# EMAIL SYSTEM

Send automatic emails for:
- Exam publish
- Exam reminder
- Exam completion
- Cheating detection
- Attempt approval
- Result publication

Use Laravel Mail.

---

# LIVE CHAT

Implement real-time live chat using:
- Pusher OR Laravel WebSockets

Features:
- Online status
- Typing indicator
- File attachments
- Chat history

---

# CHEATING DETECTION LOGIC

Use JavaScript to detect:
- visibilitychange
- blur/focus events
- fullscreen exit
- tab switch

Store violations in database.

If violation count >= 3:
- Auto submit exam
- Lock exam access
- Send email alerts

---

# ADMIN FEATURES

- Manage Users
- Manage Courses
- Manage Exams
- Schedule Exams
- Publish Results
- Reset Attempts
- Monitor Cheating Logs
- System Analytics

---

# TEACHER FEATURES

- Create Questions
- Upload Exams
- View Results
- Approve Re-attempt
- Chat with Students
- View Cheating Reports

---

# STUDENT FEATURES

- Access Enrolled Courses
- Attend Exam
- View Result
- View Correct Answers after exam end
- Receive Notifications
- Chat with Teacher

---

# ADDITIONAL FEATURES

- REST API support
- Search & Filters
- Export CSV/PDF
- Pagination
- Multi-language ready
- Secure session handling
- CSRF protection
- SQL injection prevention
- XSS protection

---

# OUTPUT REQUIREMENTS

Generate:
1. Complete Laravel 9 project structure
2. Migration files
3. Models
4. Controllers
5. Middleware
6. Routes
7. Blade UI
8. JavaScript anti-cheat system
9. Email templates
10. Database schema
11. Responsive frontend
12. AJAX functions
13. DataTables integration
14. Installation guide
15. `.env` example
16. Security implementation details

---

# CODE QUALITY

Code must be:
- Clean
- Modular
- Production-ready
- Secure
- Scalable
- Well-commented
- Professional quality