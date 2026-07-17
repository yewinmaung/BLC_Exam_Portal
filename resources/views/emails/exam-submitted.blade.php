<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>New Exam Pending Approval</title>
<style>
  body { margin: 0; padding: 0; background: #f4f6fb; font-family: 'Inter', Arial, sans-serif; }
  .wrap { max-width: 520px; margin: 40px auto; background: #fff; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 24px rgba(11,42,91,0.10); }
  .header { background: linear-gradient(135deg, #1e3a5f, #1d4ed8); padding: 32px 36px 24px; text-align: center; }
  .header h1 { color: #fff; font-size: 1.3rem; font-weight: 800; margin: 0; letter-spacing: 0.01em; }
  .header p  { color: rgba(255,255,255,0.75); font-size: 0.82rem; margin: 6px 0 0; }
  .body  { padding: 32px 36px; }
  .greeting { font-size: 0.95rem; color: #1a2540; font-weight: 600; margin-bottom: 12px; }
  .text  { font-size: 0.88rem; color: #4b5563; line-height: 1.65; margin-bottom: 24px; }
  .pending-badge { display: inline-flex; align-items: center; gap: 8px; background: #fef9c3; border: 1px solid #fde68a; border-radius: 8px; padding: 10px 16px; font-size: 0.85rem; color: #854d0e; font-weight: 600; margin-bottom: 24px; }
  .detail-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
  .detail-table tr td { font-size: 0.83rem; padding: 8px 12px; border-bottom: 1px solid #f1f5f9; }
  .detail-table tr:last-child td { border-bottom: none; }
  .detail-table tr td:first-child { font-weight: 700; color: #374151; width: 40%; background: #f8fafc; }
  .detail-table tr td:last-child  { color: #4b5563; }
  .cta-wrap { text-align: center; margin-bottom: 24px; }
  .cta-btn { display: inline-block; background: linear-gradient(135deg, #1e3a5f, #1d4ed8); color: #fff; padding: 12px 32px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 0.9rem; letter-spacing: 0.01em; }
  .footer { border-top: 1px solid #f0f0f0; padding: 18px 36px; text-align: center; font-size: 0.73rem; color: #9ca3af; }
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h1>{{ config('app.name', 'Believe Exam') }}</h1>
    <p>New Exam Pending Your Approval</p>
  </div>
  <div class="body">
    <div class="greeting">Hello, Administrator</div>

    <div class="pending-badge">
      ⏳ A teacher has submitted a new exam for approval.
    </div>

    <div class="text">
      The following exam has been submitted and is waiting for your review and approval
      before it can be scheduled and published to students.
    </div>

    <table class="detail-table">
      <tr>
        <td>Exam Title</td>
        <td><strong>{{ $exam->title }}</strong></td>
      </tr>
      <tr>
        <td>Course</td>
        <td>{{ $exam->course->title ?? '—' }}</td>
      </tr>
      <tr>
        <td>Submitted By</td>
        <td>{{ $exam->teacher->name ?? '—' }}</td>
      </tr>
      <tr>
        <td>Total Questions</td>
        <td>{{ $exam->questions->count() ?? 0 }}</td>
      </tr>
      <tr>
        <td>Total Marks</td>
        <td>{{ $exam->total_marks }}</td>
      </tr>
      <tr>
        <td>Submitted At</td>
        <td>{{ ($exam->submitted_at ?? now())->format('d M Y H:i') }}</td>
      </tr>
    </table>

    <div class="cta-wrap">
      <a href="{{ config('app.url') }}/admin/exams/{{ $exam->id }}" class="cta-btn">
        Review Exam →
      </a>
    </div>

    <div class="text" style="margin-bottom:0;font-size:0.82rem;color:#6b7280;">
      — The {{ config('app.name', 'Believe Exam') }} Team
    </div>
  </div>
  <div class="footer">
    This is an automated message. Please do not reply directly to this email.
  </div>
</div>
</body>
</html>
