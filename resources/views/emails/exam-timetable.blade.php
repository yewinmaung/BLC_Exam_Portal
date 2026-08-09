<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Examination Time Table</title>
<style>
  body { margin: 0; padding: 0; background: #f4f6fb; font-family: 'Inter', Arial, sans-serif; }
  .wrap { max-width: 620px; margin: 40px auto; background: #fff; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 24px rgba(11,42,91,0.10); }

  /* ── Header ── */
  .header { background: linear-gradient(135deg, #071d40, #2d27a0); padding: 32px 36px 28px; text-align: center; }
  .header h1 { color: #fff; font-size: 1.3rem; font-weight: 800; margin: 0 0 6px; letter-spacing: 0.01em; }
  .header .subtitle { color: rgba(255,255,255,0.80); font-size: 0.88rem; margin: 4px 0 0; font-weight: 600; }
  .header .badge-type { display: inline-block; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; padding: 3px 12px; border-radius: 20px; background: rgba(255,255,255,0.18); color: #fff; margin-top: 8px; }

  /* ── Body ── */
  .body { padding: 32px 36px; }
  .greeting { font-size: 0.97rem; color: #1a2540; font-weight: 700; margin-bottom: 10px; }
  .intro-text { font-size: 0.87rem; color: #4b5563; line-height: 1.7; margin-bottom: 24px; }

  /* ── Academic info strip ── */
  .academic-strip {
    background: #f0f4ff;
    border: 1.5px solid #c7d2fe;
    border-radius: 10px;
    padding: 14px 18px;
    margin-bottom: 24px;
    display: flex;
    flex-wrap: wrap;
    gap: 18px;
  }
  .acad-item { font-size: 0.78rem; }
  .acad-label { font-weight: 700; color: #6b7280; text-transform: uppercase; font-size: 0.67rem; letter-spacing: 0.07em; display: block; margin-bottom: 3px; }
  .acad-value { color: #1a2540; font-weight: 700; font-size: 0.84rem; }

  /* ── Timetable table ── */
  .section-title { font-size: 0.72rem; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.07em; margin: 0 0 10px; }
  .timetable-wrap { overflow-x: auto; margin-bottom: 24px; border-radius: 10px; border: 1.5px solid #dde5f7; }
  table.timetable { width: 100%; border-collapse: collapse; font-size: 0.80rem; min-width: 480px; }
  table.timetable thead tr { background: linear-gradient(135deg, #071d40, #2d27a0); }
  table.timetable thead th {
    color: #fff; font-size: 0.69rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.06em; padding: 10px 12px; text-align: left; white-space: nowrap;
  }
  table.timetable thead th:first-child { width: 36px; text-align: center; }
  table.timetable tbody tr { border-bottom: 1px solid #e8eef8; }
  table.timetable tbody tr:last-child { border-bottom: none; }
  table.timetable tbody tr:nth-child(even) { background: #f7f9ff; }
  table.timetable tbody td { padding: 10px 12px; color: #1f2937; vertical-align: middle; }
  table.timetable tbody td:first-child { text-align: center; font-weight: 700; color: #6b7280; font-size: 0.75rem; }
  .subject-cell { font-weight: 700; color: #1a2540; }
  .datetime-cell { white-space: nowrap; font-size: 0.80rem; font-weight: 600; color: #1a2540; }
  .duration-pill {
    display: inline-block; font-size: 0.72rem; font-weight: 600;
    background: #f0fdf4; color: #166534; padding: 2px 8px; border-radius: 4px;
  }
  .attempt-pill {
    display: inline-block; font-size: 0.72rem; font-weight: 600;
    background: #fffbeb; color: #92400e; padding: 2px 8px; border-radius: 4px;
  }

  /* ── Policy box ── */
  .policy-box { background: #fffbeb; border: 1.5px solid #fde68a; border-radius: 10px; padding: 18px 20px; margin-bottom: 20px; }
  .policy-box-title { font-size: 0.78rem; font-weight: 700; color: #92400e; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 10px; }
  .policy-text { font-size: 0.84rem; color: #4b5563; line-height: 1.8; white-space: pre-line; }

  /* ── Instructions box ── */
  .instructions-box { background: #f0fdf4; border: 1.5px solid #bbf7d0; border-radius: 10px; padding: 18px 20px; margin-bottom: 20px; }
  .instructions-box-title { font-size: 0.78rem; font-weight: 700; color: #166534; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 10px; }
  .instructions-text { font-size: 0.84rem; color: #374151; line-height: 1.8; white-space: pre-line; }

  /* ── Sign-off ── */
  .sign-off { font-size: 0.84rem; color: #6b7280; margin-top: 24px; line-height: 1.7; }
  .footer { border-top: 1px solid #f0f0f0; padding: 18px 36px; text-align: center; font-size: 0.73rem; color: #9ca3af; }
</style>
</head>
<body>
<div class="wrap">

  {{-- ── Header ── --}}
  <div class="header">
    <h1>{{ config('app.name', 'Believe Exam') }}</h1>
    <div class="subtitle">📅 Examination Time Table</div>
    <div class="badge-type">{{ $semesterLabel }}</div>
  </div>

  {{-- ── Body ── --}}
  <div class="body">

    <div class="greeting">Dear {{ $studentName }},</div>

    <div class="intro-text">
      Please find your examination time table below for the upcoming examination period.
      Review the schedule carefully and ensure you are prepared for each exam on the listed dates.
    </div>

    {{-- ── Academic Info Strip ── --}}
    <div class="academic-strip">
      <div class="acad-item">
        <span class="acad-label">Academic Year</span>
        <span class="acad-value">{{ $academicYearName }}</span>
      </div>
      <div class="acad-item">
        <span class="acad-label">Year Level</span>
        <span class="acad-value">{{ $yearLevelName }}</span>
      </div>
      @if($majorName)
      <div class="acad-item">
        <span class="acad-label">Major</span>
        <span class="acad-value">{{ $majorName }}</span>
      </div>
      @endif
      <div class="acad-item">
        <span class="acad-label">Semester</span>
        <span class="acad-value">{{ $semesterLabel }}</span>
      </div>
    </div>

    {{-- ── Timetable ── --}}
    <div class="section-title">Examination Schedule</div>
    <div class="timetable-wrap">
      <table class="timetable">
        <thead>
          <tr>
            <th>No</th>
            <th>Subject</th>
            <th>Start Date &amp; Time</th>
            <th>End Date &amp; Time</th>
            <th>Allowed Time</th>
            <th>Attempts</th>
          </tr>
        </thead>
        <tbody>
          @forelse($exams as $exam)
          <tr>
            <td>{{ $exam['no'] }}</td>
            <td class="subject-cell">
              <div>{{ $exam['exam_title'] }}</div>
              <div style="font-size:0.72rem;color:#6b7280;font-weight:400;margin-top:2px">{{ $exam['course'] }}</div>
            </td>
            <td class="datetime-cell">{{ $exam['start_datetime'] }}</td>
            <td class="datetime-cell">{{ $exam['end_datetime'] }}</td>
            <td><span class="duration-pill">{{ $exam['allowed_time'] }} min</span></td>
            <td><span class="attempt-pill">{{ $exam['attempt_count'] }}</span></td>
          </tr>
          @empty
          <tr>
            <td colspan="6" style="text-align:center;color:#9ca3af;padding:16px">
              No exam schedules found.
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    {{-- ── Exam Policy ── --}}
    @if(!empty($examPolicy))
    <div class="policy-box">
      <div class="policy-box-title">📋 Exam Policy</div>
      <div class="policy-text">{{ $examPolicy }}</div>
    </div>
    @endif

    {{-- ── Additional Instructions ── --}}
    @if(!empty($additionalInstructions))
    <div class="instructions-box">
      <div class="instructions-box-title">ℹ️ Additional Instructions</div>
      <div class="instructions-text">{{ $additionalInstructions }}</div>
    </div>
    @endif

    <div class="sign-off">
      Good luck with your examinations!<br><br>
      Best regards,<br>
      — The {{ config('app.name', 'Believe Exam') }} Administration Team
    </div>

  </div>

  <div class="footer">
    {{ config('app.name', 'Believe Exam') }}<br>
    This is an official academic notification. Please do not reply directly to this email.
  </div>

</div>
</body>
</html>
