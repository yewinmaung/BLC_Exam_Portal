<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cheating Alert</title>
<style>
  body { margin: 0; padding: 0; background: #f4f6fb; font-family: 'Inter', Arial, sans-serif; }
  .wrap { max-width: 520px; margin: 40px auto; background: #fff; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 24px rgba(11,42,91,0.10); }
  .header { background: linear-gradient(135deg, #431407, #c2410c); padding: 32px 36px 24px; text-align: center; }
  .header h1 { color: #fff; font-size: 1.3rem; font-weight: 800; margin: 0; letter-spacing: 0.01em; }
  .header p  { color: rgba(255,255,255,0.75); font-size: 0.82rem; margin: 6px 0 0; }
  .body  { padding: 32px 36px; }
  .greeting { font-size: 0.95rem; color: #1a2540; font-weight: 600; margin-bottom: 12px; }
  .text  { font-size: 0.88rem; color: #4b5563; line-height: 1.65; margin-bottom: 24px; }
  .alert-box { background: #fff7ed; border: 1.5px solid #fed7aa; border-radius: 12px; padding: 18px 20px; text-align: center; margin-bottom: 24px; }
  .alert-icon { font-size: 2rem; margin-bottom: 8px; }
  .alert-label { font-size: 0.78rem; font-weight: 700; color: #9a3412; text-transform: uppercase; letter-spacing: 0.07em; }
  .detail-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
  .detail-table tr td { font-size: 0.83rem; padding: 8px 12px; border-bottom: 1px solid #f1f5f9; }
  .detail-table tr:last-child td { border-bottom: none; }
  .detail-table tr td:first-child { font-weight: 700; color: #374151; width: 38%; background: #f8fafc; }
  .detail-table tr td:last-child  { color: #4b5563; }
  .warning { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px 16px; font-size: 0.8rem; color: #991b1b; margin-bottom: 24px; }
  .footer { border-top: 1px solid #f0f0f0; padding: 18px 36px; text-align: center; font-size: 0.73rem; color: #9ca3af; }
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h1>{{ config('app.name', 'Believe Exam') }}</h1>
    <p>Security Alert — Exam Terminated</p>
  </div>
  <div class="body">
    <div class="greeting">Cheating / Violation Detected</div>

    <div class="alert-box">
      <div class="alert-icon">⚠️</div>
      <div class="alert-label">Exam Attempt Terminated</div>
    </div>

    <div class="text">
      A security violation has been detected during an exam session. The attempt has been
      flagged and terminated pending administrator review.
    </div>

    <table class="detail-table">
      <tr>
        <td>Student</td>
        <td>{{ $attempt->student->name ?? '—' }}</td>
      </tr>
      <tr>
        <td>Email</td>
        <td>{{ $attempt->student->email ?? '—' }}</td>
      </tr>
      <tr>
        <td>Exam</td>
        <td>{{ $attempt->exam->title ?? '—' }}</td>
      </tr>
      <tr>
        <td>Course</td>
        <td>{{ $attempt->exam->course->title ?? '—' }}</td>
      </tr>
      <tr>
        <td>Attempt Status</td>
        <td>{{ ucfirst(str_replace('_', ' ', $attempt->status)) }}</td>
      </tr>
      <tr>
        <td>Violations</td>
        <td>{{ $attempt->warning_count }} warning(s)</td>
      </tr>
      <tr>
        <td>Detected At</td>
        <td>{{ ($attempt->terminated_at ?? now())->format('d M Y H:i') }}</td>
      </tr>
    </table>

    <div class="warning">
      <strong>Action Required:</strong> Please log in to the admin panel to review this
      security incident and take appropriate action (approve or reject the attempt).
    </div>

    <div class="text" style="margin-bottom:0;">
      — The {{ config('app.name', 'Believe Exam') }} Team
    </div>
  </div>
  <div class="footer">
    This is an automated security alert. Please do not reply directly to this email.
  </div>
</div>
</body>
</html>
