<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $exam->title }} — Believe Exam</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --navy: #0b2a5b; --navy-2: #0f3a7a; --navy-dark: #071d40;
            --gold: #d4a51c; --gold-2: #f2c94c;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f0f4fb;
            color: #1a2540;
            user-select: none;
            -webkit-user-select: none;
            margin: 0;
        }

        /* ── Top bar ── */
        .exam-topbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
            height: 56px;
            background: var(--navy-dark);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 1.5rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.25);
        }
        .exam-topbar-title {
            font-size: 0.95rem; font-weight: 700; color: #fff;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 40%;
        }
        .exam-topbar-center { display: flex; align-items: center; gap: 1rem; }
        .exam-timer {
            display: flex; align-items: center; gap: 0.5rem;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 8px; padding: 0.35rem 0.85rem;
            font-size: 1rem; font-weight: 800; color: #fff;
            font-variant-numeric: tabular-nums;
            min-width: 110px; justify-content: center;
        }
        .exam-timer.warning { background: rgba(220,53,69,0.25); border-color: rgba(220,53,69,0.5); color: #ff8a8a; }
        .exam-progress-wrap { display: flex; align-items: center; gap: 0.5rem; }
        .exam-progress-bar {
            width: 120px; height: 6px; background: rgba(255,255,255,0.15);
            border-radius: 3px; overflow: hidden;
        }
        .exam-progress-fill { height: 100%; background: var(--gold); border-radius: 3px; transition: width 0.3s; }
        .exam-progress-text { font-size: 0.78rem; color: rgba(255,255,255,0.7); white-space: nowrap; }

        /* ── Layout ── */
        .exam-layout {
            display: grid;
            grid-template-columns: 260px 1fr;
            gap: 1rem;
            max-width: 1200px;
            margin: 0 auto;
            padding: 72px 1rem 2rem;
            min-height: 100vh;
        }

        /* ── Left sidebar ── */
        .exam-sidebar {
            position: sticky; top: 68px;
            height: fit-content;
        }
        .exam-sidebar-card {
            background: #fff; border-radius: 14px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 12px rgba(11,42,91,0.07);
            overflow: hidden;
        }
        .exam-sidebar-header {
            background: var(--navy); color: #fff;
            padding: 0.85rem 1rem;
            font-size: 0.82rem; font-weight: 700;
            letter-spacing: 0.3px;
        }
        .nav-grid {
            display: grid; grid-template-columns: repeat(5, 1fr);
            gap: 6px; padding: 0.85rem;
        }
        .q-nav-btn {
            aspect-ratio: 1; border-radius: 8px;
            border: 1.5px solid #d0d8e8;
            background: #fff; color: #374151;
            font-size: 0.78rem; font-weight: 600;
            cursor: pointer; transition: all 0.15s;
            display: flex; align-items: center; justify-content: center;
        }
        .q-nav-btn:hover { border-color: var(--navy-2); color: var(--navy-2); }
        .q-nav-btn.active { background: var(--navy-2); border-color: var(--navy-2); color: #fff; }
        .q-nav-btn.answered { background: #dcfce7; border-color: #86efac; color: #166534; }
        .q-nav-btn.active.answered { background: #166534; border-color: #166534; color: #fff; }

        .nav-legend { padding: 0 0.85rem 0.85rem; display: flex; flex-direction: column; gap: 0.4rem; }
        .legend-item { display: flex; align-items: center; gap: 0.5rem; font-size: 0.72rem; color: #6b7280; }
        .legend-dot { width: 12px; height: 12px; border-radius: 3px; flex-shrink: 0; }

        .sidebar-submit-btn {
            margin: 0 0.85rem 0.85rem;
            width: calc(100% - 1.7rem);
            padding: 0.65rem;
            background: #166534; color: #fff; border: none;
            border-radius: 10px; font-size: 0.875rem; font-weight: 700;
            cursor: pointer; transition: all 0.18s;
            font-family: 'Inter', sans-serif;
        }
        .sidebar-submit-btn:hover { background: #14532d; }

        /* ── Question area ── */
        .question-block { display: none; }
        .question-block.active { display: block; }

        .question-card {
            background: #fff; border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 12px rgba(11,42,91,0.07);
            overflow: hidden; margin-bottom: 1rem;
        }
        .question-card-header {
            background: linear-gradient(135deg, var(--navy-dark), var(--navy));
            padding: 1rem 1.25rem;
            display: flex; align-items: center; justify-content: space-between;
        }
        .question-card-header .q-label {
            font-size: 0.78rem; font-weight: 700; color: rgba(255,255,255,0.7);
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .question-card-header .q-marks {
            font-size: 0.78rem; font-weight: 600;
            background: rgba(212,165,28,0.2); color: var(--gold-2);
            border: 1px solid rgba(212,165,28,0.3);
            border-radius: 20px; padding: 0.2rem 0.65rem;
        }
        .question-card-body { padding: 1.5rem 1.25rem; }
        .question-text {
            font-size: 1rem; font-weight: 600; color: var(--navy);
            line-height: 1.6; margin-bottom: 1.5rem;
        }

        /* MCQ options */
        .mcq-option {
            display: flex; align-items: center; gap: 0.75rem;
            padding: 0.75rem 1rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px; margin-bottom: 0.6rem;
            cursor: pointer; transition: all 0.15s;
            background: #fafbff;
        }
        .mcq-option:hover { border-color: var(--navy-2); background: #f0f4ff; }
        .mcq-option.selected { border-color: var(--navy-2); background: #eff6ff; }
        .mcq-option input[type="radio"] { display: none; }
        .mcq-letter {
            width: 30px; height: 30px; border-radius: 50%;
            background: #e8edf5; color: #374151;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.78rem; font-weight: 800; flex-shrink: 0;
            transition: all 0.15s;
        }
        .mcq-option.selected .mcq-letter { background: var(--navy-2); color: #fff; }
        .mcq-option-text { font-size: 0.9rem; font-weight: 500; color: #374151; }

        /* Fill blank */
        .fill-blank-wrap { position: relative; }
        .fill-blank-input {
            width: 100%; padding: 0.75rem 1rem;
            border: 1.5px solid #e2e8f0; border-radius: 10px;
            font-size: 0.95rem; font-family: 'Inter', sans-serif;
            color: #1a2540; background: #fafbff;
            outline: none; transition: border-color 0.18s, box-shadow 0.18s;
        }
        .fill-blank-input:focus {
            border-color: var(--navy-2);
            box-shadow: 0 0 0 3px rgba(15,58,122,0.10);
            background: #fff;
        }
        .fill-blank-hint {
            font-size: 0.78rem; color: #9ca3af; margin-top: 0.4rem;
        }

        /* Essay */
        .essay-textarea {
            width: 100%; padding: 0.85rem 1rem;
            border: 1.5px solid #e2e8f0; border-radius: 10px;
            font-size: 0.9rem; font-family: 'Inter', sans-serif;
            color: #1a2540; background: #fafbff; resize: vertical;
            outline: none; transition: border-color 0.18s, box-shadow 0.18s;
            min-height: 140px;
        }
        .essay-textarea:focus {
            border-color: var(--navy-2);
            box-shadow: 0 0 0 3px rgba(15,58,122,0.10);
            background: #fff;
        }

        /* Navigation buttons */
        .question-nav-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 1rem 1.25rem;
            border-top: 1px solid #f0f3fa;
        }
        .btn-nav {
            display: flex; align-items: center; gap: 0.4rem;
            padding: 0.55rem 1.1rem;
            border-radius: 9px; font-size: 0.855rem; font-weight: 600;
            cursor: pointer; transition: all 0.15s; border: 1.5px solid;
            font-family: 'Inter', sans-serif;
        }
        .btn-nav-prev { background: #fff; border-color: #d0d8e8; color: #374151; }
        .btn-nav-prev:hover { background: #f4f6fb; }
        .btn-nav-next { background: var(--navy-2); border-color: var(--navy-2); color: #fff; }
        .btn-nav-next:hover { background: var(--navy-dark); }

        /* Warning box */
        .warning-box {
            display: none; background: #fef2f2; border: 1px solid #fecaca;
            border-radius: 10px; padding: 0.75rem 1rem;
            font-size: 0.85rem; color: #991b1b; margin-bottom: 1rem;
            align-items: center; gap: 0.5rem;
        }
        .warning-box.show { display: flex; }

        /* Fullscreen modal */
        .fs-modal-overlay {
            position: fixed; inset: 0; z-index: 9999;
            background: rgba(7,29,64,0.95);
            display: flex; align-items: center; justify-content: center;
        }
        .fs-modal-box {
            background: #fff; border-radius: 20px;
            padding: 2.5rem 2rem; text-align: center;
            max-width: 420px; width: 90%;
            box-shadow: 0 32px 80px rgba(0,0,0,0.4);
        }
        .fs-modal-icon {
            width: 72px; height: 72px; border-radius: 18px;
            background: linear-gradient(135deg, var(--navy), var(--navy-2));
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem; color: #fff; margin: 0 auto 1.25rem;
        }
        .fs-modal-box h4 { font-weight: 800; color: var(--navy); margin-bottom: 0.5rem; }
        .fs-modal-box p { font-size: 0.875rem; color: #6b7280; margin-bottom: 1.5rem; }
        .fs-start-btn {
            width: 100%; padding: 0.85rem;
            background: linear-gradient(135deg, var(--navy-2), var(--navy-dark));
            color: #fff; border: none; border-radius: 12px;
            font-size: 1rem; font-weight: 700; cursor: pointer;
            font-family: 'Inter', sans-serif;
            box-shadow: 0 4px 16px rgba(11,42,91,0.3);
            transition: all 0.2s;
        }
        .fs-start-btn:hover { box-shadow: 0 6px 24px rgba(11,42,91,0.45); transform: translateY(-1px); }

        /* Responsive */
        @media (max-width: 768px) {
            .exam-layout { grid-template-columns: 1fr; padding-top: 68px; }
            .exam-sidebar { position: static; }
            .exam-topbar-title { max-width: 30%; font-size: 0.82rem; }
            .exam-progress-wrap { display: none; }
        }
    </style>
</head>
<body id="examBody"
      data-attempt-id="{{ $attempt->id }}"
      data-save-url="{{ route('student.exam.save', $attempt) }}"
      data-violation-url="{{ route('student.exam.violation', $attempt) }}"
      data-submit-url="{{ route('student.exam.submit', $attempt) }}"
      data-ends-at="{{ $endsAt }}">

{{-- Fullscreen gate --}}
<div class="fs-modal-overlay" id="fsOverlay">
    <div class="fs-modal-box">
        <div class="fs-modal-icon"><i class="bi bi-fullscreen"></i></div>
        <h4>Ready to Begin?</h4>
        <p>
            The exam will open in fullscreen mode.<br>
            Switching tabs or exiting fullscreen will be flagged as a violation.<br>
            <strong>{{ count($questions) }} questions · {{ $attempt->schedule->duration_minutes ?? '?' }} minutes</strong>
        </p>
        <button class="fs-start-btn" id="enterFullscreen">
            <i class="bi bi-play-fill me-2"></i>Start Exam
        </button>
    </div>
</div>

{{-- Top bar --}}
<div class="exam-topbar">
    <div class="exam-topbar-title">
        <i class="bi bi-pencil-square me-2" style="color:var(--gold)"></i>{{ $exam->title }}
    </div>
    <div class="exam-topbar-center">
        <div class="exam-timer" id="timer">
            <i class="bi bi-clock"></i> <span id="timerText">--:--</span>
        </div>
        <div class="exam-progress-wrap">
            <div class="exam-progress-bar">
                <div class="exam-progress-fill" id="progressFill" style="width:0%"></div>
            </div>
            <span class="exam-progress-text" id="progressText">0 / {{ count($questions) }}</span>
        </div>
    </div>
    <button onclick="document.getElementById('submitBtn').click()"
            style="background:var(--gold);color:var(--navy-dark);border:none;border-radius:8px;padding:0.4rem 1rem;font-size:0.82rem;font-weight:700;cursor:pointer;font-family:'Inter',sans-serif">
        <i class="bi bi-check2-circle me-1"></i>Submit
    </button>
</div>

{{-- Main layout --}}
<div class="exam-layout">

    {{-- Sidebar --}}
    <aside class="exam-sidebar">
        <div class="exam-sidebar-card">
            <div class="exam-sidebar-header">
                <i class="bi bi-grid-3x3-gap me-1"></i> Question Navigator
            </div>
            <div class="nav-grid" id="questionNav">
                @foreach($questions as $index => $q)
                <button type="button" class="q-nav-btn" data-target-index="{{ $index }}"
                        title="Question {{ $index + 1 }}">
                    {{ $index + 1 }}
                </button>
                @endforeach
            </div>
            <div class="nav-legend">
                <div class="legend-item">
                    <div class="legend-dot" style="background:#dcfce7;border:1.5px solid #86efac"></div>
                    Answered
                </div>
                <div class="legend-item">
                    <div class="legend-dot" style="background:var(--navy-2)"></div>
                    Current
                </div>
                <div class="legend-item">
                    <div class="legend-dot" style="background:#fff;border:1.5px solid #d0d8e8"></div>
                    Not answered
                </div>
            </div>
            <button class="sidebar-submit-btn" id="submitBtn">
                <i class="bi bi-check2-all me-1"></i> Finish & Submit
            </button>
        </div>
    </aside>

    {{-- Questions --}}
    <main>
        <div class="warning-box" id="warningBox">
            <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
            <span id="warningText"></span>
        </div>

        <form id="examForm" method="POST" action="{{ route('student.exam.submit', $attempt) }}">
            @csrf
            @foreach($questions as $index => $q)
            <div class="question-block {{ $index === 0 ? 'active' : '' }}"
                 data-index="{{ $index }}"
                 data-question-id="{{ $q['id'] }}"
                 data-type="{{ $q['type'] }}"
                 data-marks="{{ $q['marks'] }}">

                <div class="question-card">
                    <div class="question-card-header">
                        <span class="q-label">
                            Question {{ $index + 1 }} of {{ count($questions) }}
                            &nbsp;·&nbsp; {{ strtoupper(str_replace('_',' ',$q['type'])) }}
                        </span>
                        <span class="q-marks">{{ $q['marks'] }} mark{{ $q['marks'] != 1 ? 's' : '' }}</span>
                    </div>
                    <div class="question-card-body">

                        <div class="question-text">{{ $q['content'] }}</div>

                        {{-- MCQ / True-False --}}
                        @if(in_array($q['type'], ['mcq','true_false']))
                        <div class="mcq-options" id="options_{{ $q['id'] }}">
                            @foreach($q['answers'] as $ai => $a)
                            <label class="mcq-option {{ (isset($savedAnswers[$q['id']]) && $savedAnswers[$q['id']] == $a['id']) ? 'selected' : '' }}"
                                   data-answer-id="{{ $a['id'] }}">
                                <input type="radio" class="answer-input"
                                       name="q_{{ $q['id'] }}"
                                       value="{{ $a['id'] }}"
                                       data-question-id="{{ $q['id'] }}"
                                       {{ (isset($savedAnswers[$q['id']]) && $savedAnswers[$q['id']] == $a['id']) ? 'checked' : '' }}>
                                <div class="mcq-letter">{{ chr(65 + $ai) }}</div>
                                <div class="mcq-option-text">{{ $a['content'] }}</div>
                            </label>
                            @endforeach
                        </div>

                        {{-- Fill in the blank --}}
                        @elseif($q['type'] === 'fill_blank')
                        <div class="fill-blank-wrap">
                            <input type="text"
                                   class="fill-blank-input answer-blank"
                                   data-question-id="{{ $q['id'] }}"
                                   placeholder="Type your answer here..."
                                   value="{{ $attempt->studentAnswers->where('question_id',$q['id'])->first()?->answer_text ?? '' }}"
                                   autocomplete="off">
                            <div class="fill-blank-hint">
                                <i class="bi bi-info-circle me-1"></i>
                                Type the word or phrase that fills the blank.
                            </div>
                        </div>

                        {{-- Essay --}}
                        @else
                        <textarea class="essay-textarea answer-text"
                                  data-question-id="{{ $q['id'] }}"
                                  placeholder="Write your answer here...">{{ $attempt->studentAnswers->where('question_id',$q['id'])->first()?->answer_text ?? '' }}</textarea>
                        @endif

                    </div>
                    <div class="question-nav-row">
                        <button type="button" class="btn-nav btn-nav-prev prev-question"
                                {{ $index === 0 ? 'style=visibility:hidden' : '' }}>
                            <i class="bi bi-chevron-left"></i> Previous
                        </button>
                        @if($index < count($questions) - 1)
                        <button type="button" class="btn-nav btn-nav-next next-question">
                            Next <i class="bi bi-chevron-right"></i>
                        </button>
                        @else
                        <button type="button" class="btn-nav btn-nav-next" id="lastNextBtn"
                                onclick="document.getElementById('submitBtn').click()"
                                style="background:#166534;border-color:#166534">
                            <i class="bi bi-check2-all me-1"></i> Finish
                        </button>
                        @endif
                    </div>
                </div>

            </div>
            @endforeach
        </form>
    </main>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/exam-anticheat.js') }}"></script>
</body>
</html>
