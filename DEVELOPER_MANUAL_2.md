# BLC_Complete_Final — Developer Manual 2 (Deep Scan)
> Email System · Exam System · Academic Record System
> Table Relations · Function Flow · Queue Architecture · Recovery Mechanisms

---

## Table of Contents
1. [Email System — Deep Scan](#1-email-system--deep-scan)
2. [Exam System — Deep Scan](#2-exam-system--deep-scan)
3. [Academic Record System — Deep Scan](#3-academic-record-system--deep-scan)
4. [Queue Architecture — How & Why](#4-queue-architecture--how--why)
5. [Recovery Systems — How & Why](#5-recovery-systems--how--why)
6. [Cross-System Table Relation Map](#6-cross-system-table-relation-map)

---

## 1. Email System — Deep Scan

### 1.1 Architecture Overview

```
┌──────────────────────────────────────────────────────────────────┐
│                     EMAIL SYSTEM LAYERS                          │
│                                                                  │
│  Trigger Layer     Service Layer    Queue Layer    SMTP Layer    │
│  ─────────────     ─────────────    ───────────    ──────────    │
│  Controllers   →  EmailService  →  SendEmailJob →  Mail::send() │
│  Jobs          →  deliver()     →  email_logs   →  SMTP Server  │
│  Commands      →  send()        →  jobs table   →  Inbox        │
└──────────────────────────────────────────────────────────────────┘
```

### 1.2 Tables ချိတ်ဆက်ပုံ

```
users ──────────────────────────────────────────────────┐
 │ id                                                    │
 │                                                       │
 ▼ user_id (FK)                                          ▼ user_id (FK)
email_logs                                          inbox_emails
 │ id                                                 │ id
 │ to_email                                           │ from_email
 │ from_email                                         │ sender_type (student/external)
 │ subject                                            │ user_id → users.id
 │ body_html                                          │ subject
 │ template_slug                                      │ body_html / body_text
 │ event                                              │ message_id (SMTP header)
 │ email_type                                         │ in_reply_to (threading)
 │ status: queued/sent/failed                         │ references (RFC 2822)
 │ provider: smtp                                     │ thread_id (md5 of root msg)
 │ error                                              │ parent_id → inbox_emails.id
 │ message_id (SMTP header)                           │ status: unread/read/replied/archived
 │ queued_at / sent_at                                │ replied_by → users.id
 └───────────────────────────────────────────────────-┘
           │
           ▼ FK (log_id)
        jobs (queue table)
         │ id
         │ queue: 'emails'
         │ payload (JSON: job class + logId)
         │ attempts
         │ available_at / created_at
         │
         ▼
      failed_jobs (max retries exceeded)

exam_timetable_notifications
 │ id
 │ sent_by → users.id
 │ academic_year_id → academic_years.id
 │ year_level_id → year_levels.id
 │ major_id → majors.id (nullable)
 │ semester
 │ exam_schedule_ids (JSON array)
 │ exam_policy
 │ additional_instructions
 │ recipient_count
 │ status: queued/sent/partial/failed
 │ sent_at
```

### 1.3 Outgoing Email Flow (Function-by-Function)

```
STEP 1: Trigger
═══════════════
Controller/Job calls EmailService::send() or specialized method

EmailService::sendWelcomeEmail(User $user, string $password)
  └─ view('emails.welcome-account')->render()
  └─ EmailService::send(email, name, subject, html, 'welcome_account', ...)

EmailService::send(toEmail, toName, subject, bodyHtml, event, slug, userId, queue)
  ├─ EmailLog::create([
  │    to_email, from_email, subject, body_html,
  │    template_slug, event, email_type,
  │    status = 'queued',
  │    provider = config('mail.default'),
  │    queued_at = now()
  │  ])
  ├─ if queue=true: SendEmailJob::dispatch($log->id)  [→ jobs table]
  └─ if queue=false: EmailService::deliver($log)       [→ immediate SMTP]

STEP 2: Queue Worker
═══════════════════
Queue worker picks up SendEmailJob from 'emails' queue

SendEmailJob::handle(EmailService $emailService)
  ├─ $log = EmailLog::find($this->logId)
  ├─ if !$log → return (guard)
  ├─ if $log->status === 'sent' → return (idempotent guard)
  └─ $emailService->deliver($log)

EmailService::deliver(EmailLog $log)
  ├─ Mail::send([], [], function(Message $msg) {
  │    $msg->to($log->to_email, $log->to_name)
  │       ->from($from, $fromName)
  │       ->subject($log->subject)
  │       ->html($log->body_html)
  │  })
  ├─ SUCCESS: $log->markSent()
  │   → log.status = 'sent', log.sent_at = now()
  └─ FAILURE: $log->markFailed($error)
      → log.status = 'failed', log.error = message

STEP 3: Retry (if failed)
═════════════════════════
Admin clicks "Retry" → EmailController::retryLog()
  └─ EmailService::retry($log)
       → $log->update(['status' => 'queued', 'error' => null, 'queued_at' => now()])
       → SendEmailJob::dispatch($log->id)

Auto-retry (Queue worker):
  SendEmailJob: tries=3, backoff=30s
  After 3 failures → moved to failed_jobs table
  → SendEmailJob::failed() → log->markFailed('Job failed after max retries: ...')
```

### 1.4 Bulk Email Flow

```
Admin selects recipient group (e.g. 'all_students')
  ↓
EmailService::resolveRecipients('all_students')
  └─ User::whereHas('role', slug='student')->where('is_active', true)->get()

For each user:
  EmailService::resolveUserVars(User $user)
    ├─ Basic: name, email, student_id, app_name, app_url, year
    └─ Student extras:
         StudentYearRecord (active, latest) → year_level, academic_year, major, semester
         Enrollment::with('course') → course_name (comma-separated)

  EmailService::substituteVars($text, $vars)
    └─ str_replace('{{key}}', value, $text) for each variable

  EmailService::send(user->email, ...) → EmailLog + SendEmailJob
```

### 1.5 Exam Timetable Notification Flow

```
Admin fills form (academic_year, year_level, major, semester, exam_schedules)
  ↓
EmailController::sendTimetableNotification()
  ├─ Resolve eligible students via:
  │   EmailService::resolveAcademicRecipients(yearIds, levelIds, majorIds)
  │     └─ StudentYearRecord::where(status='active')
  │           ->whereIn(academic_year_id, ...)
  │           ->whereIn(year_level_id, ...)
  │           ->whereIn(major, ...)  [CST expanded to CT+CS]
  │           ->with('student')
  │           ->filter(is_active && email)
  │           ->unique('id')
  │
  ├─ ExamTimetableNotification::create([...]) → batch log record
  │
  ├─ For each student:
  │   SendExamTimetableNotificationJob::dispatch(
  │     userId, studentName, studentEmail,
  │     academicYearName, yearLevelName, majorName, semesterLabel,
  │     exams[], examPolicy, additionalInstructions,
  │     notificationId
  │   )
  │
  └─ Job::handle():
       view('emails.exam-timetable')->render()
       → EmailService::send(..., email_type='exam_timetable')
       → EmailLog::create() + SendEmailJob::dispatch()
```

### 1.6 Inbox (IMAP) System — Complete Flow

```
IMAP Server (Gmail)
      │
      ▼ every 1 minute (Scheduler) OR manual sync (Admin button)
Kernel::schedule() → 'inbox:sync' command
      │
      ▼
SyncInbox::handle()
  └─ InboxSyncService::sync()

InboxSyncService::sync()
  ├─ Cache::lock('imap_inbox_sync', 150s)  [CONCURRENCY GUARD]
  │   └─ if locked → return 'Sync already running'
  │
  ├─ InboxSyncState::readLastUid()
  │   └─ Read checkpoint from inbox_sync_states table
  │
  ├─ Client::account('default')->connect()   [Webklex IMAP]
  │
  ├─ $folder = getFolderByName('INBOX')
  │
  ├─ openFolder(path, force_select=true)
  │   └─ Read uidnext from SELECT response
  │   └─ if nextUid >= uidnext → no new messages → return
  │
  ├─ $connection->search(["UID {$nextUid}:*"])
  │   └─ Returns array of new UIDs (RFC 3501 compliant — no quotes)
  │
  ├─ sort($newUids) ascending  [oldest first]
  │
  └─ foreach $uid:
       processMessage($message, $uid)
         │
         ├─ PRIMARY DEDUP: InboxEmail::where('message_id', "uid:default:INBOX:{$uid}")
         │   → exists? return 'skipped'
         │
         ├─ SECONDARY DEDUP: InboxEmail::where('message_id', $rfcMessageId)
         │   → exists? return 'skipped'
         │
         ├─ STUDENT FILTER: User::where('email', $fromEmail)->isStudent()
         │   → not student? return 'filtered' (checkpoint NOT advanced)
         │
         ├─ Parse subject, date, body (HTML + text)
         │
         ├─ resolveThread($messageId, $inReplyTo, $references)
         │   ├─ Parse References header → array of Message-IDs
         │   ├─ Walk oldest→newest to find thread root in DB
         │   ├─ Walk newest→oldest to find parent_id in DB
         │   └─ Return [thread_id (md5), parent_id (FK or null)]
         │
         ├─ InboxEmail::create([...]) → inbox_emails row
         │
         ├─ event(new NewEmailReceived($stored)) → broadcast
         │
         ├─ InboxSyncState::saveLastUid($uid) → advance checkpoint
         │
         └─ return 'imported'

Thread Resolution Algorithm:
  References: "<msg1@x> <msg2@x> <msg3@x>"
    → Parse → ["msg1@x", "msg2@x", "msg3@x"]
    → Walk oldest (msg1) → if found in DB → thread_id = existing.thread_id
    → Walk newest (msg3) → if found in DB → parent_id = existing.id
    → If none found → new thread: thread_id = md5(own_message_id)
```

### 1.7 Reply Flow

```
Admin clicks Reply on inbox_email
  ↓
EmailController::replyInbox($inboxEmail)
  ├─ Build "Re: " subject (RFC-compliant)
  ├─ Build quoted body HTML (reply + original quoted)
  ├─ Build RFC 2822 threading headers:
  │   replyMessageId = '<reply-<uniqid>@<domain>>'
  │   references = parent.references + parent.message_id
  │   thread_id = parent.thread_id ?? md5(parent.message_id)
  │
  ├─ InboxEmail::create([...]) → stores reply in inbox_emails
  │   parent_id = original.id, status = 'replied'
  │
  ├─ EmailService::send(inboxEmail->from_email, ..., 'inbox_reply')
  │   → EmailLog::create() + SendEmailJob::dispatch()
  │
  └─ inboxEmail->update(['status' => 'replied', ...])
```

### 1.8 SMTP Settings Runtime Update

```
Admin → /admin/email/smtp → smtpUpdate()
  ├─ writeEnvValues([MAIL_HOST, MAIL_PORT, ...])
  │   → Writes to .env file directly
  ├─ Artisan::call('config:clear')
  └─ Applied immediately for future requests
  NOTE: applySmtpConfig() in EmailService sets config() runtime only
        (current request) — NOT persisted.
```


---

## 2. Exam System — Deep Scan

### 2.1 Tables ချိတ်ဆက်ပုံ (Complete ER)

```
academic_years ──────────────────────────────────────────┐
 id, name, start_year, end_year, is_current               │
    │                                                      │
    │ academic_year_id (FK)                                │
    ▼                                                      │
  exams ◄─────────────── courses ◄──────── majors         │
   id                       id              id             │
   course_id → courses.id   title           name          │
   academic_year_id ────────────────────────────────────► │
   teacher_id → users.id    code                          │
   title                    teacher_id → users.id         │
   status                   major_id → majors.id          │
   total_marks              year_level (int 0-5)          │
   passing_marks            semester (int 0,1,2)          │
   shuffle_questions        is_active                     │
   submitted_at                                           │
   approved_at                │                           │
   approved_by → users.id     ▼                           │
    │                    enrollments                       │
    │                      course_id → courses.id         │
    │                      student_id → users.id          │
    │                      year (legacy int)              │
    │                      year_level_id → year_levels.id │
    │                      major_id → majors.id           │
    ▼
exam_schedules
   id
   exam_id → exams.id
   starts_at / ends_at
   duration_minutes
   attempt_limit
   is_published
   published_at / published_by → users.id
    │
    ├──────────────────────────────────────────────────────┐
    ▼                                                      │
questions                                                  │
   exam_id → exams.id                                      │
   type: mcq/true_false/essay/file_upload/fill_blank       │
   content_encrypted (AES-256-CBC)                         │
   attachment_path/name/mime                               │
   marks / order                                           │
    │                                                      │
    ▼                                                      │
answers                                                    │
   question_id → questions.id                             │
   content_encrypted (AES-256-CBC)                        │
   is_correct                                             │
   is_blank_answer (fill_blank)                           │
   order                                                  │
    │                                                      │
    │                         ┌──────────────────────────-┘
    ▼                         ▼
exam_attempts ◄──────── exam_schedules
   id                    schedule_id → exam_schedules.id
   exam_id → exams.id
   schedule_id → exam_schedules.id
   student_id → users.id
   attempt_number
   status: in_progress/submitted/terminated/
           suspicious/terminated_pending_review/rejected
   warning_count (0-3)
   started_at / submitted_at / expires_at
   terminated_at / disconnected_at / last_question_id
   question_order (JSON: [id1, id2, id3...])
   session_token
   approved_by/approved_at/approval_comment → users.id
   rejected_by/rejected_at/rejection_comment → users.id
    │
    ├──────────────────┐
    ▼                  ▼
student_answers    cheating_logs
   attempt_id         attempt_id → exam_attempts.id
   question_id        student_id → users.id
   answer_id          violation_type
   answer_text        warning_number
   file_path          user_agent/browser/device/os
   is_correct         screen_resolution/timezone/ip_address
   marks_awarded
    │
    ▼
results
   attempt_id → exam_attempts.id (NULL = ABSENT)
   exam_id → exams.id
   student_id → users.id
   total_marks / obtained_marks / percentage
   is_passed / is_published
   exam_result_status: PASSED/FAILED/ABSENT/DISQUALIFIED
   attendance_status: attended/absent
   violation_reason / disqualified_at / exam_finished_at
    │
    └──────────────────────────────────────────────────────┐
                                                           ▼
                                                  session_recovery_logs
                                                     attempt_id → exam_attempts.id
                                                     student_id → users.id
                                                     exam_id → exams.id
                                                     disconnected_at / reconnected_at
                                                     disconnected_duration_seconds
                                                     disconnect_reason
                                                     last_question_id
                                                     browser_info (JSON)
                                                     recovery_status: pending/recovered/expired
```

### 2.2 Exam Lifecycle — Complete State Machine

```
TEACHER CREATES EXAM
=====================
POST /teacher/exams
  TeacherExamController::store()
  ├─ Validate: course_id, academic_year_id, title, passing_marks, total_marks
  ├─ Exam::create([status = 'draft', teacher_id = auth()->id()])
  └─ Redirect to exam show page

ADD QUESTIONS
=============
POST /teacher/exams/{exam}/questions
  TeacherExamController::addQuestion()
  ├─ ensureEditable() → status must be 'draft' or 'pending_approval'
  ├─ Validate question type + content
  ├─ FILL_BLANK: validate blank_answers[] not empty
  ├─ MCQ/TRUE_FALSE: validate at least one is_correct=true answer
  ├─ Question::create([content_encrypted = EncryptionService::encrypt(content)])
  ├─ For fill_blank: saveBlankAnswers() → Answer::create(is_blank_answer=true)
  ├─ For mcq/true_false: saveAnswers() → Answer::create(is_correct=bool)
  └─ If exam.status='pending_approval': notify admins

SUBMIT FOR APPROVAL
===================
POST /teacher/exams/{exam}/submit
  TeacherExamController::submitForApproval()
  ├─ Guard: status must be 'draft' or 'pending_approval'
  ├─ Guard: questions count > 0
  ├─ BACKEND MARKS VALIDATION:
  │   currentMarks = SUM(questions.marks)
  │   if currentMarks < total_marks → error (show remaining)
  │   if currentMarks > total_marks → error (show excess)
  │   if currentMarks === total_marks → proceed
  ├─ DB::transaction:
  │   exam.update([status='pending_approval', submitted_at=now()])
  │   For each Admin: NotificationService::notify('exam_submitted', ...)
  └─ Redirect with success message

ADMIN APPROVES
==============
POST /admin/exams/{exam}/approve
  AdminExamController::approve()
  ├─ Guard: status must be 'pending_approval'
  ├─ exam.update([status='approved', approved_at=now(), approved_by=auth()->id()])
  ├─ NotificationService::notify(teacher, 'exam_approved', ...)
  ├─ ActivityLogService::log('exam_approved', ...)
  └─ Redirect back

SET SCHEDULE
============
POST /admin/exams/{exam}/schedule
  AdminExamController::schedule()
  ├─ Guard: NO existing schedule (set-once rule)
  ├─ Validate: starts_at, ends_at, duration_minutes, attempt_limit
  ├─ ExamSchedule::create([exam_id, starts_at, ends_at, duration_minutes, attempt_limit])
  └─ NOTE: duration_minutes is INDEPENDENT of ends_at
           expires_at = MIN(started_at + duration, ends_at) — computed at attempt time

PUBLISH
=======
POST /admin/exams/{exam}/publish
  AdminExamController::publish()
  ├─ Guard: schedule must exist
  ├─ exam.update([status='published'])
  ├─ schedule.update([is_published=true, published_at=now(), published_by=auth()->id()])
  ├─ For each enrolled student:
  │   NotificationService::notify('exam_published', 'New Exam Available 📝')
  ├─ NotificationService::notify(teacher, 'exam_published', 'Your Exam is Now Live 🎉')
  └─ ActivityLogService::log('exam_published', ...)

CLOSE
=====
POST /admin/exams/{exam}/close
  AdminExamController::close()
  ├─ Guard: status must be 'published'
  ├─ exam.update([status='closed'])
  ├─ activeSchedule.update([is_published=false])
  └─ ActivityLogService::log('exam_closed', ...)
```


### 2.3 Student Exam Taking — Complete Flow

```
STUDENT VIEWS EXAMS
===================
GET /student/exams
  StudentExamController::index()
  ├─ Load student's StudentYearRecords (academic_year_id + year_level_id pairs)
  ├─ Query Exams:
  │   status = 'published'
  │   AND academic_year_id matches student's record
  │   AND course.yearLevel.id matches student's record year_level_id
  │   AND course.enrollments has student_id
  ├─ Load activeAttempts (in_progress, includes disconnected_at for recovery badge)
  ├─ Load finalizedAttempts (submitted/terminated/rejected)
  ├─ Load usedAttemptCounts (per exam attempt usage)
  └─ Group exams: [ayId][ylLevel][semester]['exams'][]

STUDENT STARTS EXAM
===================
POST /student/exams/{exam}/start
  StudentExamController::start()
  ├─ Guard: exam.status must be 'published'
  ├─ ExamAccessService::studentCanTakeExam(user, exam):
  │   ├─ user is student
  │   ├─ exam.status in ['approved', 'published']
  │   ├─ schedule exists (student_schedule accessor)
  │   ├─ enrolled in exam's course
  │   ├─ has StudentYearRecord for exam.academic_year_id + course year_level
  │   ├─ usedAttempts < schedule.attempt_limit
  │   └─ isScheduleActive(schedule): now() between starts_at and ends_at
  │
  ├─ Check for existing in_progress attempt → redirect to take (resume)
  │
  ├─ Generate session token (Str::random(60))
  ├─ user.update([exam_session_token = token])
  ├─ session(['exam_session_token' => token])
  │
  ├─ QUESTION ORDER:
  │   $questionIds = exam->questions()->orderBy('order')->pluck('id')
  │   if exam.shuffle_questions: shuffle($questionIds)
  │   [server-side only, client never controls order]
  │
  ├─ EXPIRES_AT CALCULATION:
  │   $durationExpiry = now()->addMinutes(schedule.duration_minutes)
  │   $finalExpiry = MIN($durationExpiry, $schedule->ends_at)
  │   [whichever is sooner — protects against late starters]
  │
  ├─ ExamAttempt::create([
  │    exam_id, schedule_id, student_id,
  │    attempt_number, status='in_progress',
  │    started_at=now(), expires_at=$finalExpiry,
  │    session_token=$token,
  │    question_order=$questionIds (JSON)
  │  ])
  └─ Redirect to student.exam.take

STUDENT TAKES EXAM
==================
GET /student/attempt/{attempt}/take   [middleware: exam.active]
  ExamSessionController::take()
  ├─ authorizeAttempt(): attempt.student_id === auth()->id()
  │
  ├─ DISCONNECT RECOVERY CHECK:
  │   if status='in_progress' AND disconnected_at !== null:
  │     SessionRecoveryService::handleReconnect($attempt)
  │       ├─ canAutoRecover():
  │       │   elapsed since disconnect ≤ 300s AND now() < expires_at AND schedule not ended
  │       │   PATH A (recoverable):
  │       │     computeFrozenSeconds = expires_at - disconnected_at [time NOT consumed]
  │       │     attempt.update([disconnected_at=null])
  │       │     SessionRecoveryLog.update([recovery_status='recovered', reconnected_at=now()])
  │       │     Return: {success=true, frozen_seconds=N}
  │       │     → renderExamView(isSessionRecovery=true, resumeQuestionId=last_question_id)
  │       └─ PATH B (window expired):
  │           finalizeExpiredSession()
  │             → attempt.status='submitted'
  │             → GradingService::gradeAttempt()
  │             → redirect with 'info' message
  │
  ├─ TIMER EXPIRY CHECK: now() >= attempt.expires_at → submitAttempt() → redirect
  │
  ├─ SCHEDULE END CHECK: now() >= schedule.ends_at → submitAttempt() → redirect
  │
  ├─ computeNormalSeconds(attempt, schedule):
  │   MIN(expires_at - now, schedule.ends_at - now)
  │
  └─ renderExamView(attempt, schedule, effectiveEndsAt):
      ├─ ExamAccessService::canDecryptQuestions() check
      ├─ Apply question_order (JSON array → sorted questions)
      ├─ EncryptionService::decrypt(content_encrypted) for each question + answer
      ├─ Load savedAnswers (attempt->studentAnswers->pluck('answer_id', 'question_id'))
      └─ Pass securityPolicy config to view (all enabled=true)

SAVE ANSWER (Auto-save)
=======================
POST /student/attempt/{attempt}/save   [middleware: exam.active]
  ExamSessionController::saveAnswer()
  ├─ Validate: question_id, answer_id?, answer_text?, answer_file?
  ├─ If file upload: store to exams/{exam_id}/attempts/{attempt_id}/
  └─ StudentAnswer::updateOrCreate(
       [attempt_id, question_id],
       [answer_id, answer_text, file_path]
     )

VIOLATION REPORTING
===================
POST /student/attempt/{attempt}/violation   [middleware: exam.active]
  ExamSessionController::violation()
  ├─ Validate: type (max 80), details (max 500)
  └─ ExamSecurityService::recordViolation(attempt, type, details, [], ip)
     [See Anti-Cheat section in Manual 1 for full 3-tier flow]

DISCONNECT (Browser close / Network drop)
==========================================
POST /student/attempt/{attempt}/disconnect   [NO middleware — must work on unload]
  ExamSessionController::disconnect()
  ├─ Guard: status must be 'in_progress'
  ├─ Validate: question_id?, reason?
  └─ SessionRecoveryService::recordDisconnect(attempt, questionId, reason, browserInfo)
       ├─ attempt.update([disconnected_at=now(), last_question_id=questionId])
       │  [status stays 'in_progress' — NOT changed]
       └─ SessionRecoveryLog::create([recovery_status='pending', ...])

SUBMIT EXAM
===========
POST /student/attempt/{attempt}/submit   [middleware: exam.active]
  ExamSessionController::submit()
  └─ submitAttempt(attempt):
       ├─ attempt.update([status='submitted', submitted_at=now()])
       ├─ user.update([exam_session_token=null])
       ├─ session()->forget('exam_session_token')
       └─ GradingService::gradeAttempt(attempt->fresh([studentAnswers...]))
```

### 2.4 GradingService — Detailed Logic

```
GradingService::gradeAttempt(ExamAttempt $attempt): Result

GUARD CHECK:
  if Result::where(attempt_id)->exam_result_status === 'DISQUALIFIED':
    return existing Result unchanged  [never re-grade disqualified]

STEP 1: total_marks = exam->questions->sum('marks')
  [Sum of ALL questions, not just answered ones]

STEP 2: obtained_marks = 0
  foreach attempt->studentAnswers as $answer:
    
    MCQ / TRUE_FALSE:
      $correct = $answer->answer && $answer->answer->is_correct
      $marks = $correct ? question->marks : 0
      StudentAnswer.update([is_correct=$correct, marks_awarded=$marks])
      obtained_marks += $marks
    
    FILL_BLANK:
      $studentText = trim($answer->answer_text)
      $accepted = question->answers()
                    ->where('is_blank_answer', true)
                    ->get()->map(fn $a => trim($a->decrypted_content))
      $correct = $studentText !== '' && $accepted->contains($studentText)
      [CASE-SENSITIVE: "A" ≠ "a"]
      $marks = $correct ? question->marks : 0
      StudentAnswer.update([is_correct=$correct, marks_awarded=$marks])
      obtained_marks += $marks
    
    ESSAY / FILE_UPLOAD:
      [NOT auto-graded — marks_awarded stays null]

STEP 3: percentage = (obtained_marks / total_marks) × 100
  [0 if total_marks = 0]

STEP 4: is_passed = obtained_marks >= exam->passing_marks

STEP 5: Result::updateOrCreate(
    [attempt_id = attempt->id],
    [
      exam_id, student_id,
      total_marks, obtained_marks, percentage,
      is_passed,
      is_published = true,
      exam_result_status = isPassed ? 'PASSED' : 'FAILED',
      attendance_status = 'attended',
      exam_finished_at = attempt->submitted_at ?? now()
    ]
  )

NOTE: result notification is NOT sent here.
  → Notification sent by 'results:notify-students' command
  → Fires only AFTER schedule.ends_at (all students' window closes)
  → Prevents early-submit students from seeing result before others finish
```

### 2.5 Eligible Students Determination

```
Admin & Teacher Results pages share this logic:
eligibleStudentIds(Exam $exam):
  ├─ enrolledIds = course->enrollments->pluck('student_id')
  ├─ if no enrollments OR no academic_year_id → return enrolledIds
  ├─ yearLevelId = YearLevel::where('level', course->year_level)->value('id')
  └─ StudentYearRecord::whereIn(student_id, enrolledIds)
         ->where(academic_year_id, exam->academic_year_id)
         ->where(year_level_id, yearLevelId)
         ->pluck('student_id')

WHY: Prevents students from past academic years appearing as ABSENT
for exams they never had access to. Same year level, different year
→ filtered out.
```

### 2.6 Question Encryption

```
Teacher adds question:
  TeacherExamController::addQuestion()
    EncryptionService::encrypt($content) → Crypt::encryptString()
    stored as questions.content_encrypted

Student takes exam:
  ExamSessionController::renderExamView()
    ExamAccessService::canDecryptQuestions(user, exam)
    ExamAccessService::decryptContent(user, exam, content_encrypted)
      → EncryptionService::decrypt() → Crypt::decryptString()
      → returns null on failure (graceful degradation)

Admin/Teacher view:
  ExamAccessService::canDecryptQuestions():
    Admin → always true
    Teacher && exam.teacher_id === user.id → true
    Student → only during schedule window OR has in_progress attempt
```


---

## 3. Academic Record System — Deep Scan

### 3.1 Tables ချိတ်ဆက်ပုံ (Complete ER)

```
academic_years
  id, name, start_year, end_year, is_current
    │
    │ academic_year_id (FK)
    ├──────────────────────────────────────────────────┐
    ▼                                                  │
student_year_records                                   │
  id                                                   │
  student_id → users.id                               │
  academic_year_id → academic_years.id                │
  year_level_id → year_levels.id                      │
  semester: '1' | '2'                                 │
  department (text)                                    │
  major (text — name string, NOT FK)                  │
  gpa (decimal 4,2)                                   │
  status: active/promoted/failed/withdrawn             │
  promoted_at                                         │
  record_type (RecordType enum)                       │
  remark                                              │
  UNIQUE(student_id, academic_year_id, year_level_id, semester)
    │
    │ JOIN via student_id + academic_year_id + year_level_id
    ▼
year_levels                    users (students)
  id                             id
  level: 1-5                     role_id → roles.id
  name: "First Year"             is_active
  department                     academic_year (legacy int)
  major                          force_password_change
                                 profile_photo

majors
  id, name, code (unique), is_active
    │
    │ major_id (FK, nullable)
    ├──────────────────────────────────┐
    ▼                                  ▼
courses                          enrollments
  id                               major_id → majors.id (nullable)
  major_id → majors.id             year_level_id → year_levels.id
  year_level (int 0-5)
  semester (int 0,1,2)
  academic_year_id

yearly_exam_results (Archive)
  student_id → users.id
  academic_year_id → academic_years.id
  year_level_id → year_levels.id
  exam_id → exams.id
  result_id → results.id (nullable)
  obtained_marks / total_marks / percentage / grade
  is_passed / semester

certificate_logs
  serial_number (unique)
  student_id → users.id
  academic_year_id → academic_years.id
  year_level_id → year_levels.id
  type: transcript/completion/promotion/achievement
  qr_token (unique)
  issued_by / issued_at / created_by → users.id
```

### 3.2 Academic Enrollment Flow

```
STEP 1: Create Academic Year
  POST /admin/academic/years
  AcademicYearController::store()
  ├─ if is_current=true: AcademicYear::where(is_current=true)->update([is_current=false])
  └─ AcademicYear::create([name, start_year, end_year, is_current])

STEP 2: Create Year Levels (1-5)
  [Pre-seeded in database — no UI create needed]
  year_levels: level=1 "First Year", level=2 "Second Year", ...

STEP 3: Create Majors
  POST /admin/majors
  MajorController::store()
  └─ Major::create([name, code, description, is_active])
  Special case: code='CST' = combined CS+CT selection
  → EmailService::resolveAcademicRecipients() expands CST → [CT, CS]

STEP 4: Assign Students to Academic Year
  POST /admin/academic/years/{year}/students
  AcademicYearController::assignStudents()
  ├─ For each student_id:
  │   YearLevelProgressionValidator::validate(studentId, newLevel, yearId, recordType)
  │   [PROGRESSION RULES checked here]
  │   └─ StudentYearRecord::create([
  │        student_id, academic_year_id, year_level_id, semester,
  │        department, major (text), status='active',
  │        record_type, remark
  │      ])
  ├─ Skip if already enrolled (UNIQUE constraint)
  └─ Collect rejected[] list for UI feedback

PROGRESSION VALIDATOR RULES:
  YearLevelProgressionValidator::validate($studentId, $newLevel, $yearId, $recordType)
  [Checks previous records to ensure valid progression]
  NORMAL: can only go to nextLevel from previous
  TRANSFER: allowed with remark (bypass validation)
  READMISSION: allowed with remark (bypass validation)
```

### 3.3 Result Grouping Algorithm (Admin View)

```
AdminResultController::index()

GOAL: Group results by [AcademicYear][YearLevel][Semester][Course][Exam][Student]
SOURCE OF TRUTH: student_year_records (NOT course dates or exam dates)

MATCHING PRIORITY for student → course → academic record:
  Pass 1: record.yearLevel.level === course.year_level
          AND record.semester === course.semester
  Pass 2: record.yearLevel.level === course.year_level (semester wildcard)
  Pass 3: record.semester === course.semester (year_level wildcard)
  Pass 4: most recent record (last resort)

ADDITIONAL FILTERS applied:
  ├─ Skip if record.academic_year_id !== exam.academic_year_id
  └─ Skip if course.year_level !== 0 AND record.yearLevel.level !== course.year_level

RESULT STATUS DETERMINATION per student:
  if Result row exists:
    → exam_result_status (PASSED/FAILED/DISQUALIFIED)
    → violations list from cheating_logs
    → warningCount from attempt.warning_count
  else:
    → status = 'ABSENT' (virtual — no Result row)

OUTPUT STRUCTURE:
  $summary[$ayId][$yl][$sem][] = [
    'course', 'exams' → [
      'exam', 'schedule', 'studentRows' → [
        {student, result, status, score, percentage, violations, warningCount}
      ],
      'students', 'passed', 'failed', 'cheating', 'absent'
    ],
    'students', 'passed', 'failed', 'cheating', 'absent'
  ]
```

### 3.4 Student Result View Algorithm

```
StudentResultController::index()

GOAL: Show student's own results grouped by enrollment history
SOURCE OF TRUTH: student_year_records

BRIDGE STRATEGY:
  enrollment.year_level_id → links course → year record
  course.semester → routing hint (display only)

MATCHING PRIORITY for result → year record:
  Pass 1: compound key "{year_level_id}:{semester}" (exact)
  Pass 2: year_level_id matches, course.semester = 0 (wildcard sem)
  Pass 3: legacy integer year → year_level.level
  Pass 4: most recent record (last resort)

VISUAL SEMESTER SPLIT (display only):
  sem1: course.semester in [0, 1]
  sem2: course.semester === 2

ELIGIBILITY for display:
  result.is_published = true
  exam.schedules.ends_at <= now()  [schedule must have ended]

STATS:
  totalExams = allResults.count()
  passedCount = allResults.where(is_passed=true).count()
  avgPct = allResults.avg('percentage')
```

### 3.5 AcademicService::getStudentHistory() Flow

```
AcademicService::getStudentHistory(User $student)

1. Load StudentYearRecords ordered by academic_year_id
2. For each record:
   └─ Query Results:
       where(student_id = student->id)
       where(is_published = true)
       whereHas(exam.schedules, ends_at <= now)
       whereHas(exam.course.enrollments, student_id)
       when(recordYl) → whereHas(course, year_level IN [0, recordYl])
       when(recordSem != 0) → whereHas(course, semester IN [0, recordSem])
3. Return: [['record' => $record, 'results' => $results], ...]

USED BY:
  AdminResultController::student() → drill-down view
  Result pages that need full historical context
```

### 3.6 ABSENT Result Creation

```
'results:mark-absent' Artisan command
MarkAbsentResults::handle()

ELIGIBLE ABSENT:
  ├─ schedule.ends_at < now() (window closed)
  ├─ student is enrolled in exam's course (enrollments table)
  ├─ NO ExamAttempt with started_at IS NOT NULL
  └─ NO existing Result row for this exam + student

CREATES:
  Result::create([
    attempt_id = NULL,              [never started]
    exam_id, student_id,
    total_marks = exam->total_marks,
    obtained_marks = 0,
    percentage = 0.00,
    grade = 'F',
    is_passed = false,
    is_published = true,
    exam_result_status = 'ABSENT',
    attendance_status = 'absent',
    exam_finished_at = NULL
  ])

SAFETY: Fully idempotent (skips if result already exists)
SCOPE: --exam=ID flag to target specific exam
DRYRUN: --dry-run flag to preview without writing
```


---

## 4. Queue Architecture — How & Why

### 4.1 Queue Design Overview

```
WHY QUEUE?
══════════
Exam requests must return in <200ms.
Email sending via SMTP can take 1-30 seconds.
If SMTP blocks the exam request → student session times out → unfair.

SOLUTION: All emails are queued (never synchronous during exam flow).
DB::afterCommit() → email fires only AFTER transaction commits.
This prevents: send email → DB rollback → orphaned email with no matching data.

QUEUE CHANNEL: 'emails'
  All email jobs go to the same 'emails' channel.
  Worker command: php artisan queue:work --queue=emails
  InboxSyncJob also uses 'emails' channel (same worker — no new infra).
```

### 4.2 Jobs Inventory & Purpose

| Job Class | Queue | Tries | Backoff | Purpose |
|-----------|-------|-------|---------|---------|
| `SendEmailJob` | emails | 3 | 30s | Deliver one EmailLog via SMTP |
| `SendWelcomeAccountJob` | emails | 3 | 30s | Render + queue welcome email |
| `SendNewTemporaryPasswordJob` | emails | 3 | 30s | Render + queue temp password email |
| `SendPasswordChangedJob` | emails | 3 | 30s | Password changed notification |
| `SendProfileOtpJob` | emails | 3 | 30s | OTP code email |
| `SendExamTimetableNotificationJob` | emails | 3 | 30s | Timetable notification per student |
| `InboxSyncJob` | emails | 2 | 60s | IMAP sync (fallback if scheduler fails) |

### 4.3 Job Layer Architecture

```
LAYER 1: Specialized Jobs (render + hand off to EmailService)
  SendWelcomeAccountJob::handle()
    └─ view('emails.welcome-account')->render()
    └─ EmailService::send() → creates EmailLog + dispatches SendEmailJob

LAYER 2: SendEmailJob (actual SMTP delivery)
  SendEmailJob::handle()
    └─ EmailLog::find(logId)
    └─ if already sent → return (idempotent)
    └─ EmailService::deliver($log)
         └─ Mail::send() → SMTP → markSent() or markFailed()

RATIONALE: Two-layer design
  Layer 1 jobs: hold plaintext password (must be short-lived, specific)
  Layer 2 (SendEmailJob): only holds logId (safe, generic, retryable)
  EmailLog: stores rendered HTML body (permanent audit trail)

PLAINTEXT PASSWORD HANDLING:
  SendWelcomeAccountJob receives $temporaryPassword in constructor
  → stored in jobs table payload temporarily
  → rendered into email HTML
  → job completes → password no longer in queue
  → EmailLog stores rendered HTML (password visible in HTML body — by design)
  → After delivery, plaintext no longer needed
```

### 4.4 Queue Failure & Recovery

```
FAILURE SCENARIO 1: SMTP server down
  SendEmailJob tries 3×, 30s apart
  After 3 failures → failed_jobs table
  SendEmailJob::failed() → EmailLog.status = 'failed', error = message
  Admin → /admin/email/logs → sees failed emails → clicks Retry
  EmailService::retry() → status='queued', error=null → dispatch again

FAILURE SCENARIO 2: Job payload corrupted
  SendEmailJob::handle() → $log = null → return (guard, no crash)

FAILURE SCENARIO 3: Duplicate delivery
  SendEmailJob::handle():
    if $log->status === 'sent' → return immediately
  [idempotent guard prevents double-delivery]

FAILURE SCENARIO 4: InboxSyncJob fails
  InboxSyncJob: tries=2, backoff=60s
  InboxSyncService checkpoint stays at last successful UID
  On next run (next minute via scheduler) → retries from checkpoint
  Missed UIDs are never lost (UID-based incremental cursor)

CONCURRENCY GUARD:
  InboxSyncService uses Cache::lock('imap_inbox_sync', 150s)
  If scheduler fires while previous sync is still running → second call skipped
  Lock auto-expires after 150s (dead-man's switch)
```

### 4.5 DB::afterCommit() — Why It's Used

```
ExamSecurityService::recordViolationThree():
  DB::transaction(function() {
    // 1. Lock attempt row (lockForUpdate)
    // 2. Write CheatingLog
    // 3. Update attempt.status = 'terminated'
    // 4. Call GradingService::gradeAttempt()
    // 5. Update Result.exam_result_status = DISQUALIFIED
    // ↑ ALL of the above must commit before email fires ↑
    
    DB::afterCommit(function() {
      // 6. Load fresh attempt with all relations
      // 7. EmailService::send() → queued email
      // 8. NotificationService::notify() → notification row
    });
  });

WHY afterCommit:
  If email fired BEFORE commit → admin sees email about terminated exam
  but DB still shows in_progress (DB rolled back) → inconsistent state.
  afterCommit() guarantees: DB is the source of truth, always.

SAME PATTERN in approve() and reject():
  Transaction → DB updates → afterCommit → student notification
```

### 4.6 Scheduled Commands vs Queue Jobs

```
SCHEDULER (Kernel.php — every minute):
  inbox:sync → SyncInbox::handle() → InboxSyncService::sync() [direct]
  results:notify-students → NotifyStudentResults::handle() [direct]

QUEUE JOBS (dispatched by controllers/jobs):
  SendEmailJob → dispatched by EmailService::send()
  InboxSyncJob → dispatched by admin manual sync button (HTTP returns fast)
  SendExamTimetableNotificationJob → dispatched per student in loop

KEY DIFFERENCE:
  Scheduler → runs every minute, blocking (CLI process)
  Queue Jobs → async, run by worker process, HTTP returns immediately

MANUAL vs SCHEDULED:
  inbox:sync: BOTH scheduled (every minute) AND manual (admin button)
  Manual sync → dispatches InboxSyncJob to queue → HTTP returns fast
  Scheduled sync → SyncInbox command → runs InboxSyncService::sync() directly
  Both paths enter InboxSyncService::sync() → concurrency lock prevents overlap
```

---

## 5. Recovery Systems — How & Why

### 5.1 Session Recovery System — Full Breakdown

```
PROBLEM SOLVED:
  Student takes exam. Network drops or browser closes accidentally.
  Without recovery → exam treated as abandoned → ABSENT or failed.
  This is unfair for genuine technical issues.

DESIGN DECISION:
  Status NEVER changes during a disconnect (stays 'in_progress').
  This is different from security violations (which change status).
  A disconnect is NOT a security event — it's a technical interruption.

TIMING CONTRACT (invariant — never violated):
  expires_at = MIN(started_at + duration_minutes, schedule.ends_at)
  Set ONCE at attempt creation. NEVER modified after.
  All timer calculations derive from this single value.

RECOVERY WINDOW: 5 minutes (config: exam_security.recovery_time_limit)
```

#### Disconnect Event (recordDisconnect)

```
Student browser fires 'beforeunload' event
  → JS sends POST /student/attempt/{attempt}/disconnect
  → ExamSessionController::disconnect()
  → SessionRecoveryService::recordDisconnect(attempt, questionId, reason, browserInfo)

WRITES:
  attempt.disconnected_at = now()        [timestamp of disconnect]
  attempt.last_question_id = questionId  [resume point]
  attempt.status = UNCHANGED             [still 'in_progress']
  attempt.expires_at = UNCHANGED         [timer not touched]
  student_answers = UNCHANGED            [answers preserved]

  SessionRecoveryLog::create([
    attempt_id, student_id, exam_id,
    disconnect_reason, disconnected_at,
    last_question_id,
    browser_info (JSON: platform, mobile headers),
    user_agent, ip_address,
    recovery_status = 'pending'
  ])

DESIGN CHOICE: No new attempt created.
  Why: Creating a new attempt would reset the timer and lose answers.
  Better: Keep same attempt, track disconnect state in two fields.
```

#### Reconnect Event (handleReconnect)

```
Student navigates back to /student/attempt/{attempt}/take
  → ExamSessionController::take()
  → Detects: status='in_progress' AND disconnected_at !== null
  → SessionRecoveryService::handleReconnect($attempt)

ELIGIBILITY CHECK (canAutoRecover):
  1. status === 'in_progress'
  2. disconnected_at !== null
  3. elapsed since disconnect ≤ recovery_time_limit (300s)
  4. now() < expires_at (Final Expiry Time not passed)
  5. Belt-and-braces: now() < schedule.ends_at

PATH A: RECOVERABLE (all conditions pass)
  ├─ computeFrozenSeconds():
  │   frozen = expires_at - disconnected_at
  │   [disconnect time NOT consumed from exam]
  │   cap by schedule.ends_at remaining (legacy safety)
  │
  ├─ duration = now() - disconnected_at  [how long were they disconnected]
  │
  ├─ attempt.update([disconnected_at = null])
  │   [session is live again — no status change needed]
  │
  ├─ SessionRecoveryLog.update([
  │    recovery_status = 'recovered',
  │    reconnected_at = now(),
  │    disconnected_duration_seconds = duration
  │  ])
  │
  └─ Return {success: true, frozen_seconds: N}
     → Exam rendered with frozen timer (student gets back their time)

PATH B: EXPIRED (recovery window passed OR exam expired)
  └─ finalizeExpiredSession(attempt, message)
       ├─ SessionRecoveryLog.update([recovery_status='expired', ...])
       ├─ attempt.update([status='submitted', submitted_at=now()])
       ├─ User.update([exam_session_token=null])
       ├─ GradingService::gradeAttempt()
       │   [grades using auto-saved answers — unanswered = 0 marks]
       └─ Return {success: false, message: '...'}
          → redirect to exam show with info message
```

#### Why This Design Works

```
INVARIANTS (CRITICAL — must never be violated):
  ✓ No new ExamAttempt created
  ✓ expires_at never modified
  ✓ student_answers never deleted
  ✓ warning_count never touched
  ✓ Auto-save logic never modified

TIMER FAIRNESS:
  Student disconnects at T=10min remaining
  Recovery takes 3 minutes
  When reconnected: timer shows 10min (not 7min)
  Disconnect time is NOT penalised
  WHY: Student didn't waste time intentionally — network dropped

GRADE FAIRNESS:
  If recovery window expires: auto-submit with saved answers
  Student who saved 8/10 answers gets credit for those 8
  Unanswered questions = 0 marks (not null, not missing)
  WHY: Student participated — answers should count
```

### 5.2 Security Violation Recovery (approve/reject)

```
PROBLEM SOLVED:
  Student triggers 3 violations (e.g., screen went to background by accident).
  terminated_pending_review → locks exam pending human review.
  Admin/Teacher decides: was it intentional? Can they resume?

NOTE: In current implementation, violation 3 → status='terminated' (FINAL)
      terminated_pending_review is kept for backward compatibility.
      The approve/reject flow still works for any terminated_pending_review attempts.

APPROVE FLOW:
  ExamSecurityService::approve(attempt, actor, comment)
  └─ DB::transaction + lockForUpdate() [race condition guard]
     ├─ Guard: status must be 'terminated_pending_review'
     │
     ├─ TIMER EXTENSION:
     │   lockedSeconds = now() - terminated_at [how long was exam locked]
     │   cap by maxResumeExtensionMinutes (default 120 min)
     │   newExpiresAt = expires_at + lockedSeconds
     │   [student gets back the time they lost during the review]
     │
     ├─ attempt.update([
     │    status = 'in_progress',
     │    terminated_at = null,        [lock lifted]
     │    expires_at = newExpiresAt,   [time restored]
     │    approved_by = actor->id,
     │    approved_at = now(),
     │    approval_comment = comment
     │    // rejected_* columns stay null (mutually exclusive)
     │  ])
     │
     ├─ ActivityLogService::log('security_approved', ...)
     │
     └─ DB::afterCommit → notify student:
          'Exam Session Approved ✅ — You may now resume.'

REJECT FLOW:
  ExamSecurityService::reject(attempt, actor, comment)
  └─ DB::transaction + lockForUpdate()
     ├─ Guard: status must be 'terminated_pending_review'
     │
     ├─ attempt.update([
     │    status = 'rejected',
     │    rejected_by = actor->id,
     │    rejected_at = now(),
     │    rejection_comment = comment
     │    // terminated_at PRESERVED (forensic timestamp)
     │    // approved_* columns stay null
     │  ])
     │
     ├─ ActivityLogService::log('security_rejected', ...)
     │
     └─ DB::afterCommit → notify student:
          'Exam Session Rejected ❌ — Contact instructor.'
```

### 5.3 Login Security Recovery

```
PROBLEM: Brute force login attempts
SOLUTION: Progressive lockout + temporary password system

LOCKOUT:
  AuthController::login()
    → Auth::attempt() fails
    → user->incrementFailedLogins()
       attempts++
       if attempts >= 3: locked_until = now() + 10 minutes
    → Show: "X attempts remaining before lockout"

  AuthController::login() pre-check:
    → if user->isLocked(): show "Account locked. Try again in N minutes."

ON SUCCESSFUL LOGIN:
  user->resetFailedLogins()
    → failed_login_attempts = 0
    → locked_until = null

EXPIRED TEMPORARY PASSWORD:
  POST /login/request-new-password
  AuthController::requestNewTemporaryPassword()
  ├─ Find user by email
  ├─ user->canRequestNewTempPassword():
  │   ├─ force_password_change must be true
  │   ├─ account must NOT be locked
  │   └─ 60s elapsed since temp_password_last_requested_at
  ├─ Generate new temp password
  ├─ user->update([
  │    password = bcrypt(newPassword),
  │    temp_password_last_requested_at = now()
  │  ])
  └─ SendNewTemporaryPasswordJob::dispatch(userId, plainPassword, expiresAt)

FORCE PASSWORD CHANGE:
  ForcePasswordChange middleware fires on every request
  → if force_password_change=true AND isTemporaryPasswordExpired():
       redirect to /password/change
  → POST /password/change → AuthController::updateForcePasswordChange()
       update password, clear force_password_change=false
```

### 5.4 OTP Recovery (Profile Password Change)

```
POST /profile/password
  ProfileController::changePassword()
    ├─ Validate: current_password, new_password (confirmed)
    ├─ Hash::check(current_password, user->password) fails? → error
    ├─ ProfileOtp::create([
    │    user_id,
    │    code_hash = bcrypt(6-digit random),
    │    new_password_hash = bcrypt(new_password),  [stored, not plaintext]
    │    attempts = 0,
    │    expires_at = now() + 5 minutes,
    │    used_at = null
    │  ])
    └─ SendProfileOtpJob::dispatch() → email OTP to user

POST /profile/verify-otp
  ProfileController::verifyOtp()
  ├─ ProfileOtp::where(user_id, used_at=null)->latest()
  ├─ if expired (expires_at < now()) → error 'OTP expired'
  ├─ if attempts >= 5 → error 'Too many attempts'
  ├─ otp->increment('attempts')
  ├─ if NOT Hash::check(code, otp->code_hash) → error 'Invalid OTP'
  ├─ user->update([password = otp->new_password_hash])
  │   [uses pre-hashed new password — plaintext never stored in OTP]
  └─ otp->update([used_at = now()])
```

---

## 6. Cross-System Table Relation Map

### 6.1 Complete Foreign Key Web

```
USERS TABLE — Hub of all relations
═══════════════════════════════════
users.role_id                → roles.id
users.id ← student_year_records.student_id
users.id ← enrollments.student_id
users.id ← exam_attempts.student_id
users.id ← exam_attempts.approved_by
users.id ← exam_attempts.rejected_by
users.id ← results.student_id
users.id ← cheating_logs.student_id
users.id ← session_recovery_logs.student_id
users.id ← exams.teacher_id
users.id ← exams.approved_by
users.id ← courses.teacher_id
users.id ← courses.created_by
users.id ← exam_schedules.published_by
users.id ← user_notifications.user_id
users.id ← activity_logs.user_id
users.id ← email_logs.user_id
users.id ← inbox_emails.user_id
users.id ← inbox_emails.replied_by
users.id ← profile_otps.user_id
users.id ← exam_timetable_notifications.sent_by
users.id ← certificate_logs.student_id
users.id ← certificate_logs.created_by
users.id ← yearly_exam_results.student_id

EXAMS TABLE — Exam chain
═════════════════════════
exams.course_id              → courses.id
exams.academic_year_id       → academic_years.id
exams.teacher_id             → users.id
exams.approved_by            → users.id
exams.id ← exam_schedules.exam_id
exams.id ← questions.exam_id
exams.id ← exam_attempts.exam_id
exams.id ← results.exam_id
exams.id ← session_recovery_logs.exam_id
exams.id ← yearly_exam_results.exam_id

EXAM_ATTEMPTS — Security + Recovery hub
════════════════════════════════════════
exam_attempts.exam_id        → exams.id
exam_attempts.schedule_id    → exam_schedules.id
exam_attempts.student_id     → users.id
exam_attempts.approved_by    → users.id
exam_attempts.rejected_by    → users.id
exam_attempts.id ← student_answers.attempt_id
exam_attempts.id ← results.attempt_id
exam_attempts.id ← cheating_logs.attempt_id
exam_attempts.id ← session_recovery_logs.attempt_id

ACADEMIC SYSTEM
═══════════════
academic_years.id ← student_year_records.academic_year_id
academic_years.id ← yearly_exam_results.academic_year_id
academic_years.id ← exam_timetable_notifications.academic_year_id
academic_years.id ← exams.academic_year_id

year_levels.id ← student_year_records.year_level_id
year_levels.id ← enrollments.year_level_id
year_levels.id ← yearly_exam_results.year_level_id
year_levels.id ← certificate_logs.year_level_id
year_levels.id ← exam_timetable_notifications.year_level_id

majors.id ← courses.major_id
majors.id ← enrollments.major_id
majors.id ← exam_timetable_notifications.major_id

EMAIL SYSTEM
═════════════
email_logs.user_id           → users.id
inbox_emails.user_id         → users.id (sender if student)
inbox_emails.replied_by      → users.id
inbox_emails.parent_id       → inbox_emails.id (self-referential for threads)
```

### 6.2 Query Patterns Reference

```
FIND ELIGIBLE STUDENTS FOR AN EXAM:
  StudentYearRecord
    ->whereIn('student_id', course->enrollments->pluck('student_id'))
    ->where('academic_year_id', exam->academic_year_id)
    ->where('year_level_id', YearLevel::where('level', course->year_level)->value('id'))

FIND ACTIVE ATTEMPT FOR A STUDENT:
  ExamAttempt
    ->where('exam_id', $examId)
    ->where('student_id', $studentId)
    ->where('status', 'in_progress')
    ->first()

CHECK ATTEMPT LIMIT:
  ExamAttempt
    ->where('exam_id', $examId)
    ->where('student_id', $studentId)
    ->whereIn('status', ['submitted', 'terminated', 'suspicious', 'rejected'])
    ->count() >= schedule->attempt_limit

FIND CHEATING LOGS FOR ADMIN:
  CheatingLog
    ->with(['attempt.exam', 'student'])
    ->orderByDesc('created_at')

GET STUDENT RESULT HISTORY:
  StudentYearRecord (ordered by academic_year_id)
    → For each record: Result with matching year_level + semester

DEDUP CHECK FOR NOTIFICATIONS:
  UserNotification
    ->where('user_id', $studentId)
    ->where('type', 'exam_result')
    ->where('link', route('student.exams.show', $examId))
    ->exists()

INBOX THREAD RESOLUTION:
  InboxEmail::where('message_id', $refMsgId)->first(['id', 'thread_id'])
  [Walk References header oldest→newest]
```

### 6.3 System Interaction Summary

```
┌─────────────────────────────────────────────────────────────────┐
│                    SYSTEM INTERACTIONS                          │
│                                                                 │
│  Academic System → Exam System                                  │
│    student_year_records.academic_year_id + year_level_id        │
│    → gates which exams a student can see and take               │
│    → used in eligibleStudentIds() for result display            │
│                                                                 │
│  Exam System → Email System                                     │
│    ExamSecurityService → EmailService::send()                   │
│    AdminExamController::publish() → notifications (not email)   │
│    Teacher submit → notifications (not email)                   │
│    ExamTimetableNotification → SendExamTimetableNotificationJob │
│                                                                 │
│  Academic System → Email System                                 │
│    EmailService::resolveAcademicRecipients()                    │
│    → StudentYearRecord → majors → filter by academic criteria   │
│                                                                 │
│  Exam System → Academic System                                  │
│    results → yearly_exam_results (archive — if implemented)     │
│    GradingService → Result (feeds into academic history)        │
│                                                                 │
│  Email System ← All Systems                                     │
│    email_logs: every outgoing email from every trigger          │
│    inbox_emails: incoming student emails via IMAP               │
│    exam_timetable_notifications: batch log for timetable sends  │
└─────────────────────────────────────────────────────────────────┘
```

---

*DEVELOPER_MANUAL_2.md — Deep Scan Complete*
*Source: Full code analysis of BLC_Complete_Final*
*Covers: All controllers, services, jobs, migrations, models*
