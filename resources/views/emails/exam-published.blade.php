<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>New Exam Available</title>
<style>
  body { margin: 0; padding: 0; background: #f4f6fb; font-family: 'Inter', Arial, sans-serif; }
  .wrap { max-width: 520px; margin: 40px auto; background: #fff; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 24px rgba(11,42,91,0.10); }
  .header { background: linear-gradient(135deg, #071d40, #2d27a0); padding: 32px 36px 24px; text-align: center; }
  .header h1 { color: #fff; font-size: 1.3rem; font-weight: 800; margin: 0; letter-spacing: 0.01em; }
  .header p  { color: rgba(255,255,255,0.75); font-size: 0.82rem; margin: 6px 0 0; }
  .body  { padding: 32px 36px; }
  .greeting { font-size: 0.95rem; color: #1a2540; font-weight: 600; margin-bottom: 12px; }
  .text  { font-size: 0.88rem; color: #4b5563; line-height: 1.65; margin-bottom: 24px; }
  .exam-box { background: #f0f4ff; border: 2px solid #c7d2fe; border-radius: 12px; padding: 20px; margin-bottom: 24px; }
  .exam-title { font-size: 1.1rem; font-weight: 800; color: #2d27a0; margin-bottom: 8px; }
  .exam-meta  { font-size: 0.82rem; color: #6b7280; line-height: 1.7; }
  .exam-meta strong { color: #374151; }
  .detail-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
  .detail-table tr td { font-size: 0.83rem; padding: 8px 12px; border-bottom: 1px solid #f1f5f9; }
  .detail-table tr:last-child td { border-bottom: none; }
  .detail-table tr td:first-child { font-weight: 700; color: #374151; width: 40%; background: #f8fafc; }
  .detail-table tr td:last-child  { color: #4b5563; }
  .cta-wrap { text-align: center; margin-bottom: 24px; }
  .cta-btn { display: inline-block; background: linear-gradient(135deg, #071d40, #2d27a0); color: #fff; padding: 12px 32px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 0.9rem; letter-spacing: 0.01em; }
  .footer { border-top: 1px solid #f0f0f0; padding: 18px 36px; text-align: center; font-size: 0.73rem; color: #9ca3af; }
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h1>{{ config('app.name', 'Believe Exam') }}</h1>
    <p>New Exam Available 📝</p>
  </div>
  <div class="body">
    <div class="greeting">Hello,</div>

    <div class="text">
      A new exam has been published and is now available for you to take. Please review
      the details below and log in to begin at your earliest convenience.
    </div>

    <div class="exam-box">
      <div class="exam-title">{{ $exam->title }}</div>
      <div class="exam-meta">
        <strong>Course:</strong> {{ $exam->course->title ?? '—' }}<br>
        <strong>Teacher:</strong> {{ $exam->teacher->name ?? '—' }}
      </div>
    </div>

    <table class="detail-table">
      <tr>
        <td>Total Marks</td>
        <td>{{ $exam->total_marks }}</td>
      </tr>
      <tr>
        <td>Passing Marks</td>
        <td>{{ $exam->passing_marks }}</td>
      </tr>
      @if($exam->activeSchedule)
      <tr>
        <td>Starts At</td>
        <td>{{ $exam->activeSchedule->starts_at->format('d M Y H:i') }}</td>
      </tr>
      <tr>
        <td>Ends At</td>
        <td>{{ $exam->activeSchedule->ends_at->format('d M Y H:i') }}</td>
      </tr>
      <tr>
        <td>Duration</td>
        <td>{{ $exam->activeSchedule->duration_minutes }} minutes</td>
      </tr>
      @endif
    </table>

    <div class="cta-wrap">
      <a href="{{ config('app.url') }}/student/exams" class="cta-btn">
        Go to My Exams →
      </a>
    </div>

    <div class="text" style="margin-bottom:0;font-size:0.82rem;color:#6b7280;">
      Good luck! — The {{ config('app.name', 'Believe Exam') }} Team
    </div>
  </div>
  <div class="footer">
    This is an automated message. Please do not reply directly to this email.
  </div>
</div>
</body>
</html>
