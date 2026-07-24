<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $emailSubject }}</title>
<style>
  body { margin: 0; padding: 0; background: #f4f6fb; font-family: 'Inter', Arial, sans-serif; }
  .wrap { max-width: 560px; margin: 40px auto; background: #fff; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 24px rgba(11,42,91,0.10); }
  .header { background: linear-gradient(135deg, #071d40, #2d27a0); padding: 32px 36px 24px; text-align: center; }
  .header h1 { color: #fff; font-size: 1.3rem; font-weight: 800; margin: 0; letter-spacing: 0.01em; }
  .header .subtitle { color: rgba(255,255,255,0.80); font-size: 0.84rem; margin: 8px 0 0; }
  .body { padding: 32px 36px; }
  .greeting { font-size: 0.95rem; color: #1a2540; font-weight: 600; margin-bottom: 12px; }
  .intro-text { font-size: 0.88rem; color: #4b5563; line-height: 1.7; margin-bottom: 24px; }
  .exam-card { background: #f0f4ff; border: 2px solid #c7d2fe; border-radius: 12px; padding: 20px 22px; margin-bottom: 20px; }
  .exam-title { font-size: 1.05rem; font-weight: 800; color: #2d27a0; margin-bottom: 14px; }
  .detail-table { width: 100%; border-collapse: collapse; }
  .detail-table tr td { font-size: 0.83rem; padding: 7px 10px; border-bottom: 1px solid #dde5f7; }
  .detail-table tr:last-child td { border-bottom: none; }
  .detail-table tr td:first-child { font-weight: 700; color: #374151; width: 38%; background: #e8eeff; border-radius: 4px; }
  .detail-table tr td:last-child { color: #1f2937; }
  .policy-box { background: #fffbeb; border: 1.5px solid #fde68a; border-radius: 12px; padding: 18px 20px; margin-bottom: 20px; }
  .policy-title { font-size: 0.85rem; font-weight: 700; color: #92400e; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 10px; }
  .policy-list { margin: 0; padding: 0 0 0 18px; }
  .policy-list li { font-size: 0.85rem; color: #4b5563; line-height: 1.75; }
  .reminder-box { background: #f0fdf4; border: 1.5px solid #bbf7d0; border-radius: 12px; padding: 18px 20px; text-align: center; margin-bottom: 20px; }
  .reminder-icon { font-size: 2rem; margin-bottom: 8px; }
  .reminder-text { font-size: 0.88rem; color: #166534; font-weight: 600; margin-bottom: 6px; }
  .reminder-detail { font-size: 0.82rem; color: #4b5563; }
  .cta-wrap { text-align: center; margin: 24px 0; }
  .cta-btn { display: inline-block; background: linear-gradient(135deg, #071d40, #2d27a0); color: #fff; padding: 12px 32px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 0.9rem; letter-spacing: 0.01em; }
  .sign-off { font-size: 0.82rem; color: #6b7280; margin-top: 24px; }
  .footer { border-top: 1px solid #f0f0f0; padding: 18px 36px; text-align: center; font-size: 0.73rem; color: #9ca3af; }
  .badge-type { display: inline-block; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; padding: 3px 10px; border-radius: 20px; background: rgba(255,255,255,0.20); color: #fff; margin-top: 6px; }
</style>
</head>
<body>
<div class="wrap">

  {{-- ── Header ─────────────────────────────────────────────────────── --}}
  <div class="header">
    <h1>{{ config('app.name', 'Believe Exam') }}</h1>
    @if($notificationType === 'exam_time')
      <div class="subtitle">📅 Exam Schedule Notification</div>
      <div class="badge-type">Exam Time</div>
    @elseif($notificationType === 'exam_policy')
      <div class="subtitle">📋 Exam Policy &amp; Instructions</div>
      <div class="badge-type">Exam Policy</div>
    @else
      <div class="subtitle">⏰ Exam Reminder</div>
      <div class="badge-type">Reminder</div>
    @endif
  </div>

  {{-- ── Body ─────────────────────────────────────────────────────────── --}}
  <div class="body">

    <div class="greeting">Hello, {{ $studentName }}</div>

    {{-- ── Exam Time ────────────────────────────────────────────────── --}}
    @if($notificationType === 'exam_time')

      <p class="intro-text">
        Please find the exam schedule details below. Make sure you arrive on time and bring your student ID.
      </p>

      @foreach($exams as $exam)
      <div class="exam-card">
        <div class="exam-title">{{ $exam['title'] }}</div>
        <table class="detail-table">
          <tr>
            <td>Course</td>
            <td>{{ $exam['course'] }}</td>
          </tr>
          @if($exam['date'])
          <tr>
            <td>Date</td>
            <td>{{ $exam['date'] }}</td>
          </tr>
          @endif
          @if($exam['time'])
          <tr>
            <td>Time</td>
            <td>{{ $exam['time'] }}</td>
          </tr>
          @endif
          @if($exam['duration'])
          <tr>
            <td>Duration</td>
            <td>{{ $exam['duration'] }} minutes</td>
          </tr>
          @endif
          @if($exam['room'])
          <tr>
            <td>Room</td>
            <td>{{ $exam['room'] }}</td>
          </tr>
          @endif
          <tr>
            <td>Total Marks</td>
            <td>{{ $exam['total_marks'] }}</td>
          </tr>
          <tr>
            <td>Passing Marks</td>
            <td>{{ $exam['passing_marks'] }}</td>
          </tr>
        </table>
      </div>
      @endforeach

    {{-- ── Exam Policy ──────────────────────────────────────────────── --}}
    @elseif($notificationType === 'exam_policy')

      <p class="intro-text">
        Please read the following exam rules and instructions carefully.
        Failure to comply may result in disqualification.
      </p>

      @foreach($exams as $exam)
      <div class="exam-card">
        <div class="exam-title">{{ $exam['title'] }}</div>
        @if($exam['description'])
        <p style="font-size:0.85rem;color:#374151;line-height:1.7;margin-bottom:0">
          {{ $exam['description'] }}
        </p>
        @endif
      </div>
      @endforeach

      <div class="policy-box">
        <div class="policy-title">📋 General Exam Rules</div>
        <ul class="policy-list">
          <li>Arrive at least 10 minutes before the exam starts.</li>
          <li>Bring a valid student ID card.</li>
          <li>Mobile phones and electronic devices must be switched off and stored away.</li>
          <li>No communication with other students during the exam.</li>
          <li>Any form of cheating will result in immediate disqualification.</li>
          <li>Read all questions carefully before answering.</li>
          <li>Submit your exam before the time limit expires.</li>
          <li>Contact your teacher if you experience any technical issues.</li>
        </ul>
      </div>

    {{-- ── Exam Reminder ─────────────────────────────────────────────── --}}
    @else

      <div class="reminder-box">
        <div class="reminder-icon">⏰</div>
        <div class="reminder-text">Your exam is coming up soon!</div>
        <div class="reminder-detail">Make sure you are prepared and ready.</div>
      </div>

      @foreach($exams as $exam)
      <div class="exam-card">
        <div class="exam-title">{{ $exam['title'] }}</div>
        <table class="detail-table">
          <tr>
            <td>Course</td>
            <td>{{ $exam['course'] }}</td>
          </tr>
          @if($exam['date'])
          <tr>
            <td>Date</td>
            <td>{{ $exam['date'] }}</td>
          </tr>
          @endif
          @if($exam['time'])
          <tr>
            <td>Time</td>
            <td>{{ $exam['time'] }}</td>
          </tr>
          @endif
          @if($exam['duration'])
          <tr>
            <td>Duration</td>
            <td>{{ $exam['duration'] }} minutes</td>
          </tr>
          @endif
        </table>
      </div>
      @endforeach

      <p class="intro-text">
        This is a reminder to prepare well for your upcoming exam.
        Review your course materials and ensure you know the exam schedule.
        Good luck!
      </p>

    @endif

    <div class="cta-wrap">
      <a href="{{ config('app.url') }}/student/exams" class="cta-btn">
        View My Exams →
      </a>
    </div>

    <div class="sign-off">
      Best regards,<br>
      — The {{ config('app.name', 'Believe Exam') }} Team
    </div>

  </div>

  <div class="footer">
    {{ config('app.name', 'Believe Exam') }}<br>
    This is an automated academic notification. Please do not reply directly to this email.
  </div>

</div>
</body>
</html>
