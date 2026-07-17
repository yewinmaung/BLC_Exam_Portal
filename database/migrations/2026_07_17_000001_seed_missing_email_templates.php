<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the three missing DB-driven email templates required by Phase 2.
 *
 * Templates added:
 *   - exam_submitted  → sent to admin when teacher submits exam for approval
 *   - exam_published  → sent to enrolled students when admin publishes exam
 *   - account_terminated → sent to user when account is suspended
 *
 * Uses insertOrIgnore so running this twice is safe.
 * The welcome template (slug=welcome) already exists from the previous migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        $appName = config('app.name', 'Believe Learning Center');
        $appUrl  = config('app.url', 'http://localhost:8000');
        $now     = now();

        $templates = [

            // ── exam_submitted ────────────────────────────────────────────
            [
                'name'      => 'Exam Submitted for Approval',
                'slug'      => 'exam_submitted',
                'subject'   => '[{{app_name}}] New Exam Pending Approval: {{exam_name}}',
                'body_html' => <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
  body { margin:0;padding:0;background:#f4f6fb;font-family:'Inter',Arial,sans-serif; }
  .wrap { max-width:520px;margin:40px auto;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 24px rgba(11,42,91,0.10); }
  .header { background:linear-gradient(135deg,#1e3a5f,#1d4ed8);padding:32px 36px 24px;text-align:center; }
  .header h1 { color:#fff;font-size:1.3rem;font-weight:800;margin:0; }
  .header p  { color:rgba(255,255,255,0.75);font-size:0.82rem;margin:6px 0 0; }
  .body { padding:32px 36px; }
  .greeting { font-size:0.95rem;color:#1a2540;font-weight:600;margin-bottom:12px; }
  .text { font-size:0.88rem;color:#4b5563;line-height:1.65;margin-bottom:20px; }
  .info-box { background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 18px;font-size:0.83rem;color:#374151;margin-bottom:24px; }
  .info-box strong { color:#1a2540; }
  .cta-wrap { text-align:center;margin-bottom:24px; }
  .cta-btn { display:inline-block;background:linear-gradient(135deg,#1e3a5f,#1d4ed8);color:#fff;padding:12px 32px;border-radius:10px;text-decoration:none;font-weight:700;font-size:0.9rem; }
  .footer { border-top:1px solid #f0f0f0;padding:18px 36px;text-align:center;font-size:0.73rem;color:#9ca3af; }
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h1>{{app_name}}</h1>
    <p>New Exam Pending Your Approval</p>
  </div>
  <div class="body">
    <div class="greeting">Hello, Administrator</div>
    <div class="text">
      <strong>{{teacher_name}}</strong> has submitted a new exam for review.
      Please log in to approve or reject it before it can be scheduled.
    </div>
    <div class="info-box">
      <strong>Exam:</strong> {{exam_name}}<br>
      <strong>Course:</strong> {{course_name}}<br>
      <strong>Teacher:</strong> {{teacher_name}}<br>
      <strong>Questions:</strong> {{question_count}}<br>
      <strong>Total Marks:</strong> {{total_marks}}
    </div>
    <div class="cta-wrap">
      <a href="{{app_url}}/admin/exams/{{exam_id}}" class="cta-btn">Review Exam →</a>
    </div>
    <div class="text" style="margin-bottom:0;font-size:0.82rem;color:#6b7280;">
      — The {{app_name}} Team
    </div>
  </div>
  <div class="footer">This is an automated message. Please do not reply directly to this email.</div>
</div>
</body>
</html>
HTML,
                'body_text'  => null,
                'event'      => 'exam_submitted',
                'is_active'  => true,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ── exam_published ────────────────────────────────────────────
            [
                'name'      => 'Exam Published Notification',
                'slug'      => 'exam_published',
                'subject'   => '[{{app_name}}] New Exam Available: {{exam_name}}',
                'body_html' => <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
  body { margin:0;padding:0;background:#f4f6fb;font-family:'Inter',Arial,sans-serif; }
  .wrap { max-width:520px;margin:40px auto;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 24px rgba(11,42,91,0.10); }
  .header { background:linear-gradient(135deg,#071d40,#2d27a0);padding:32px 36px 24px;text-align:center; }
  .header h1 { color:#fff;font-size:1.3rem;font-weight:800;margin:0; }
  .header p  { color:rgba(255,255,255,0.75);font-size:0.82rem;margin:6px 0 0; }
  .body { padding:32px 36px; }
  .greeting { font-size:0.95rem;color:#1a2540;font-weight:600;margin-bottom:12px; }
  .text { font-size:0.88rem;color:#4b5563;line-height:1.65;margin-bottom:20px; }
  .exam-box { background:#f0f4ff;border:2px solid #c7d2fe;border-radius:12px;padding:20px;margin-bottom:24px; }
  .exam-title { font-size:1.05rem;font-weight:800;color:#2d27a0;margin-bottom:8px; }
  .exam-meta { font-size:0.82rem;color:#6b7280;line-height:1.7; }
  .exam-meta strong { color:#374151; }
  .cta-wrap { text-align:center;margin-bottom:24px; }
  .cta-btn { display:inline-block;background:linear-gradient(135deg,#071d40,#2d27a0);color:#fff;padding:12px 32px;border-radius:10px;text-decoration:none;font-weight:700;font-size:0.9rem; }
  .footer { border-top:1px solid #f0f0f0;padding:18px 36px;text-align:center;font-size:0.73rem;color:#9ca3af; }
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h1>{{app_name}}</h1>
    <p>New Exam Available 📝</p>
  </div>
  <div class="body">
    <div class="greeting">Hello, {{student_name}}</div>
    <div class="text">
      A new exam has been published and is now available for you to take.
      Please review the details below and log in to begin.
    </div>
    <div class="exam-box">
      <div class="exam-title">{{exam_name}}</div>
      <div class="exam-meta">
        <strong>Course:</strong> {{course_name}}<br>
        <strong>Total Marks:</strong> {{total_marks}}<br>
        <strong>Passing Marks:</strong> {{passing_marks}}
      </div>
    </div>
    <div class="cta-wrap">
      <a href="{{app_url}}/student/exams" class="cta-btn">Go to My Exams →</a>
    </div>
    <div class="text" style="margin-bottom:0;font-size:0.82rem;color:#6b7280;">
      Good luck! — The {{app_name}} Team
    </div>
  </div>
  <div class="footer">This is an automated message. Please do not reply directly to this email.</div>
</div>
</body>
</html>
HTML,
                'body_text'  => null,
                'event'      => 'exam_published',
                'is_active'  => true,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ── account_terminated ────────────────────────────────────────
            [
                'name'      => 'Account Suspended Notification',
                'slug'      => 'account_terminated',
                'subject'   => '[{{app_name}}] Your Account Has Been Suspended',
                'body_html' => <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
  body { margin:0;padding:0;background:#f4f6fb;font-family:'Inter',Arial,sans-serif; }
  .wrap { max-width:520px;margin:40px auto;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 24px rgba(11,42,91,0.10); }
  .header { background:linear-gradient(135deg,#4c0519,#9f1239);padding:32px 36px 24px;text-align:center; }
  .header h1 { color:#fff;font-size:1.3rem;font-weight:800;margin:0; }
  .header p  { color:rgba(255,255,255,0.75);font-size:0.82rem;margin:6px 0 0; }
  .body { padding:32px 36px; }
  .greeting { font-size:0.95rem;color:#1a2540;font-weight:600;margin-bottom:12px; }
  .text { font-size:0.88rem;color:#4b5563;line-height:1.65;margin-bottom:20px; }
  .alert-box { background:#fef2f2;border:1.5px solid #fecaca;border-radius:12px;padding:18px 20px;text-align:center;margin-bottom:24px; }
  .alert-icon { font-size:2rem;margin-bottom:8px; }
  .alert-label { font-size:0.78rem;font-weight:700;color:#991b1b;text-transform:uppercase;letter-spacing:0.07em; }
  .info-box { background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 18px;font-size:0.82rem;color:#4b5563;margin-bottom:24px; }
  .info-box strong { color:#1a2540; }
  .warning { background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:12px 16px;font-size:0.8rem;color:#92400e;margin-bottom:24px; }
  .footer { border-top:1px solid #f0f0f0;padding:18px 36px;text-align:center;font-size:0.73rem;color:#9ca3af; }
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h1>{{app_name}}</h1>
    <p>Account Suspended</p>
  </div>
  <div class="body">
    <div class="greeting">Hello, {{name}}</div>
    <div class="alert-box">
      <div class="alert-icon">🚫</div>
      <div class="alert-label">Your account has been suspended</div>
    </div>
    <div class="text">
      Your account associated with <strong>{{email}}</strong> has been suspended
      by an administrator. You will not be able to log in until it is reinstated.
    </div>
    <div class="info-box">
      <strong>Account:</strong> {{email}}<br>
      <strong>Status:</strong> Suspended<br>
      <strong>Date:</strong> {{date}}
    </div>
    <div class="warning">
      <strong>Believe this is a mistake?</strong> Please contact your administrator
      to appeal this decision and request account reinstatement.
    </div>
    <div class="text" style="margin-bottom:0;">
      — The {{app_name}} Team
    </div>
  </div>
  <div class="footer">This is an automated message. Please do not reply directly to this email.</div>
</div>
</body>
</html>
HTML,
                'body_text'  => null,
                'event'      => 'account_terminated',
                'is_active'  => true,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('email_templates')->insertOrIgnore($templates);
    }

    public function down(): void
    {
        DB::table('email_templates')
            ->whereIn('slug', ['exam_submitted', 'exam_published', 'account_terminated'])
            ->delete();
    }
};
