@extends('layouts.app')
@section('title', $exam->title)
@section('page-title', $exam->title)
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Student', 'url' => route('student.dashboard')],
        ['label' => 'Exams', 'url' => route('student.exams.index')],
        ['label' => $exam->title],
    ]])
@endsection
@section('sidebar')
@include('partials.student-sidebar')

@endsection

@section('content')
<div class="row g-3">

    {{-- ── Left: Exam info ── --}}
    <div class="col-lg-8">

        {{-- Exam header --}}
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
                    <div>
                        <h5 class="mb-1" style="font-weight:800;color:var(--blc-navy)">{{ $exam->title }}</h5>
                        <div class="text-muted small"><i class="bi bi-book me-1"></i>{{ $exam->course->title }}</div>
                    </div>
                    <span class="status-pill status-published">
                        Published
                    </span>
                </div>

                @if($exam->description)
                <p class="text-muted mb-3" style="font-size:0.9rem">{{ $exam->description }}</p>
                @endif

                <div class="d-flex flex-wrap gap-2">
                    <div class="exam-stat"><i class="bi bi-question-circle"></i> {{ $exam->questions->count() }} Questions</div>
                    <div class="exam-stat"><i class="bi bi-award"></i> {{ $exam->total_marks }} Total Marks</div>
                    <div class="exam-stat"><i class="bi bi-check-circle"></i> {{ $exam->passing_marks }} to Pass</div>
                    @if($schedule)
                    <div class="exam-stat"><i class="bi bi-clock"></i> {{ $schedule->duration_minutes }} Minutes</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Schedule info --}}
        @if($schedule)
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-calendar3 me-2"></i>Exam Schedule</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="text-muted small mb-1">Start Time</div>
                        <div style="font-weight:600">
                            <i class="bi bi-play-circle me-1 text-success"></i>
                            {{ $schedule->starts_at->format('M d, Y — H:i') }}
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small mb-1">End Time</div>
                        <div style="font-weight:600">
                            <i class="bi bi-stop-circle me-1 text-danger"></i>
                            {{ $schedule->ends_at->format('M d, Y — H:i') }}
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small mb-1">Duration</div>
                        <div style="font-weight:600">{{ $schedule->duration_minutes }} minutes</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small mb-1">Attempts Allowed</div>
                        <div style="font-weight:600">{{ $schedule->attempt_limit }}</div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- ── RESULT(S) and ANSWER REVIEW (only after schedule ends) ── --}}
        @if($scheduleEnded && $finalizedAttempts->count())

        @php
            // Build per-attempt data: answers keyed by question_id
            $attemptData = $finalizedAttempts->map(function($att) {
                return [
                    'attempt'  => $att,
                    'result'   => $att->result,
                    'answers'  => $att->studentAnswers->keyBy('question_id'),
                ];
            });
            $multiAttempt = $attemptData->count() > 1;
        @endphp

        {{-- Tab nav — only shown when multiple finalized attempts --}}
        @if($multiAttempt)
        <div class="mb-3">
            <ul class="nav nav-pills gap-1 flex-wrap" id="attemptTabs" role="tablist">
                @foreach($attemptData as $idx => $data)
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $idx === 0 ? 'active' : '' }} px-3 py-2"
                            id="tab-att-{{ $data['attempt']->id }}"
                            data-bs-toggle="pill"
                            data-bs-target="#pane-att-{{ $data['attempt']->id }}"
                            type="button" role="tab"
                            style="font-size:0.82rem;font-weight:700;border-radius:8px">
                        <i class="bi bi-journal-text me-1"></i>
                        Attempt #{{ $data['attempt']->attempt_number }}
                        @if($data['result'])
                            <span class="ms-1 badge {{ $data['result']->is_passed ? 'bg-success' : 'bg-danger' }}"
                                  style="font-size:0.65rem">
                                {{ $data['result']->is_passed ? 'Pass' : 'Fail' }}
                            </span>
                        @endif
                    </button>
                </li>
                @endforeach
            </ul>
        </div>
        @endif

        {{-- Tab panes --}}
        <div class="tab-content" id="attemptTabContent">
        @foreach($attemptData as $idx => $data)
        @php
            $att    = $data['attempt'];
            $res    = $data['result'];
            $ansMap = $data['answers'];  // keyed by question_id — belongs ONLY to this attempt
        @endphp

        <div class="tab-pane fade {{ $idx === 0 ? 'show active' : '' }}"
             id="pane-att-{{ $att->id }}"
             role="tabpanel">

            {{-- Result card for this attempt --}}
            @if($res && $res->is_published)
            <div class="card mb-3"
                 style="border-color:{{ $res->is_passed ? '#bbf7d0' : '#fecaca' }} !important">
                <div class="card-header"
                     style="background:{{ $res->is_passed ? '#f0fdf4' : '#fef2f2' }}">
                    <i class="bi bi-{{ $res->is_passed ? 'trophy-fill text-success' : 'x-circle-fill text-danger' }} me-2"></i>
                    Attempt #{{ $att->attempt_number }} — Result
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center gap-4 flex-wrap">
                        <div class="result-percentage-circle {{ $res->is_passed ? 'pass' : 'fail' }}">
                            {{ $res->percentage }}%
                        </div>
                        <div class="d-flex flex-wrap gap-4">
                            <div>
                                <div class="text-muted small">Score</div>
                                <div style="font-size:1.5rem;font-weight:800;color:var(--blc-navy)">
                                    {{ $res->obtained_marks }}<span class="text-muted" style="font-size:1rem;font-weight:400">/{{ $res->total_marks }}</span>
                                </div>
                            </div>
                            <div>
                                <div class="text-muted small">Percentage</div>
                                <div style="font-size:1.3rem;font-weight:700">{{ $res->percentage }}%</div>
                            </div>
                            <div>
                                <div class="text-muted small">Status</div>
                                <span class="status-pill {{ $res->isDisqualified() ? 'status-draft' : ($res->is_passed ? 'status-approved' : 'status-closed') }}"
                                      style="font-size:0.9rem;margin-top:4px;display:inline-flex">
                                    @if($res->isDisqualified())
                                        ✗ FAILED (CHEATING)
                                    @elseif($res->is_passed)
                                        ✓ PASSED
                                    @else
                                        ✗ FAILED
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- Answer review for this attempt --}}
            @if($canViewAnswers)
            <div class="card mb-3">
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="bi bi-eye-fill" style="color:var(--blc-gold)"></i>
                    Attempt #{{ $att->attempt_number }} — Answer Review
                    <span class="badge ms-auto" style="background:#f0fdf4;color:#166534;font-size:0.72rem">
                        Exam ended — answers revealed
                    </span>
                </div>
                <div class="card-body">
                    @foreach($exam->questions as $i => $q)
                    @php
                        // Answers are fetched from THIS attempt only — never mixed
                        $studentAnswer = $ansMap->get($q->id);
                        $isCorrect     = $studentAnswer?->is_correct ?? false;
                    @endphp
                    <div class="review-card {{ $isCorrect ? 'review-correct' : 'review-wrong' }}">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="q-number">Q{{ $i+1 }}</span>
                            <span class="badge" style="background:#f0f4ff;color:var(--blc-navy-2);font-size:0.7rem">
                                {{ strtoupper(str_replace('_',' ',$q->type)) }}
                            </span>
                            <span class="badge" style="background:#f0fdf4;color:#166534;font-size:0.7rem">
                                {{ $q->marks }} mark{{ $q->marks!==1?'s':'' }}
                            </span>
                            @if($studentAnswer)
                            <span class="ms-auto badge {{ $isCorrect ? 'bg-success' : 'bg-danger' }}">
                                {{ $isCorrect ? '✓ Correct' : '✗ Wrong' }}
                            </span>
                            @else
                            <span class="ms-auto badge bg-secondary">— Not answered</span>
                            @endif
                        </div>

                        <div class="q-text mb-3">{{ $q->decrypted_content }}</div>

                        {{-- Student's answer for THIS attempt --}}
                        @if($studentAnswer)
                        <div class="mb-2">
                            <div class="text-muted small mb-1" style="font-weight:600">Your Answer:</div>
                            @if($q->type === 'fill_blank')
                                <span class="student-answer-pill {{ $isCorrect ? 'correct' : 'wrong' }}">
                                    {{ $studentAnswer->answer_text ?? '(no answer)' }}
                                </span>
                            @elseif(in_array($q->type, ['mcq','true_false']) && $studentAnswer->answer)
                                <span class="student-answer-pill {{ $isCorrect ? 'correct' : 'wrong' }}">
                                    {{ $studentAnswer->answer->decrypted_content }}
                                </span>
                            @elseif($q->type === 'essay')
                                <div class="p-2 rounded" style="background:#f8faff;border:1px solid #e8edf5;font-size:0.875rem">
                                    {{ $studentAnswer->answer_text ?? '(no answer)' }}
                                </div>
                            @else
                                <span class="text-muted small">(no answer submitted)</span>
                            @endif
                        </div>
                        @else
                        <div class="mb-2">
                            <span class="text-muted small">No answer recorded for this question in this attempt.</span>
                        </div>
                        @endif

                        {{-- Correct answer (shown to all once schedule ends) --}}
                        @if($q->type === 'fill_blank')
                        <div>
                            <div class="text-muted small mb-1" style="font-weight:600">Accepted Answers:</div>
                            <div class="d-flex flex-wrap gap-1">
                                @foreach($q->answers->where('is_blank_answer', true) as $a)
                                <span class="student-answer-pill correct">{{ $a->decrypted_content }}</span>
                                @endforeach
                            </div>
                        </div>
                        @elseif(in_array($q->type, ['mcq','true_false']))
                        <div>
                            <div class="text-muted small mb-1" style="font-weight:600">Correct Answer:</div>
                            @foreach($q->answers->where('is_correct', true) as $a)
                            <span class="student-answer-pill correct">{{ $a->decrypted_content }}</span>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

        </div>{{-- /tab-pane --}}
        @endforeach
        </div>{{-- /tab-content --}}

        @elseif($scheduleEnded && !$finalizedAttempts->count())
        <div class="card mb-3">
            <div class="card-body text-center py-4 text-muted">
                <i class="bi bi-hourglass-bottom d-block mb-2" style="font-size:2rem;opacity:0.4"></i>
                <div class="small">The exam has ended. Results will appear here once published by your teacher.</div>
            </div>
        </div>
        @endif

    </div>

    {{-- ── Right: Action panel ── --}}
    <div class="col-lg-4">

        @if($canTake)
        {{-- Check if there's an active in_progress attempt --}}
        @php
            $activeAttempt = $attempts->firstWhere('status', 'in_progress');
        @endphp

        @if($activeAttempt)
        {{-- Resume exam button for in_progress attempts --}}
        <div class="card mb-3" style="border-color:rgba(212,165,28,0.4) !important;border-width:2px !important">
            <div class="card-body text-center py-4">
                <div style="width:64px;height:64px;border-radius:16px;
                            background:linear-gradient(135deg,#d4a51c,#f2c94c);
                            display:flex;align-items:center;justify-content:center;
                            margin:0 auto 1rem;font-size:1.75rem;color:#fff">
                    <i class="bi bi-play-circle-fill"></i>
                </div>
                <h6 style="font-weight:700;color:var(--blc-navy)" class="mb-1">Exam In Progress!</h6>
                <p class="text-muted small mb-3">
                    You have an active exam session.
                    @if($activeAttempt->expires_at && now()->lt($activeAttempt->expires_at))
                        Time remaining: <strong>{{ now()->diffInMinutes($activeAttempt->expires_at) }} minutes</strong>
                    @endif
                </p>
                <div class="mb-3 p-2 rounded text-start"
                     style="background:#fffbeb;border:1px solid #fde68a;font-size:0.78rem;color:#92400e">
                    <i class="bi bi-info-circle-fill me-1"></i>
                    Click below to continue your exam. Your previous answers are saved.
                </div>
                <a href="{{ route('student.exam.take', $activeAttempt) }}" 
                   class="btn btn-warning w-100 py-2"
                   style="font-size:0.95rem;font-weight:700;background:#d4a51c;border:none;color:#fff">
                    <i class="bi bi-arrow-right-circle-fill me-1"></i> Continue Exam
                </a>
            </div>
        </div>
        @else
        {{-- Active: Start button (no active attempt) --}}
        <div class="card mb-3" style="border-color:rgba(15,58,122,0.25) !important">
            <div class="card-body text-center py-4">
                <div style="width:64px;height:64px;border-radius:16px;
                            background:linear-gradient(135deg,var(--blc-navy),var(--blc-navy-2));
                            display:flex;align-items:center;justify-content:center;
                            margin:0 auto 1rem;font-size:1.75rem;color:#fff">
                    <i class="bi bi-pencil-square"></i>
                </div>
                <h6 style="font-weight:700;color:var(--blc-navy)" class="mb-1">Exam is Live!</h6>
                <p class="text-muted small mb-3">
                    You have <strong>{{ $schedule->duration_minutes }} minutes</strong> once you begin.
                </p>
                <div class="mb-3 p-2 rounded text-start"
                     style="background:#fffbeb;border:1px solid #fde68a;font-size:0.78rem;color:#92400e">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    Exam opens in fullscreen. Tab switching is flagged as a violation.
                </div>
                <form method="POST" action="{{ route('student.exams.start', $exam) }}">@csrf
                    <button type="submit" class="btn btn-primary w-100 py-2"
                            style="font-size:0.95rem;font-weight:700"
                            onclick="return confirm('Start the exam now? The timer begins immediately.')">
                        <i class="bi bi-play-fill me-1"></i> Start Exam Now
                    </button>
                </form>
            </div>
        </div>
        @endif

        @elseif(!$schedule)
        <div class="card mb-3">
            <div class="card-body text-center py-4 text-muted">
                <i class="bi bi-calendar-x d-block mb-2" style="font-size:2rem;opacity:0.4"></i>
                <div class="small">No schedule set yet. Check back later.</div>
            </div>
        </div>

        @elseif($scheduleEnded)
        <div class="card mb-3">
            <div class="card-body text-center py-4">
                <i class="bi bi-calendar-check d-block mb-2" style="font-size:2rem;color:var(--blc-gold)"></i>
                <div style="font-weight:600;color:var(--blc-navy)" class="mb-1">Exam Ended</div>
                <div class="text-muted small mb-3">Ended {{ $schedule->ends_at->diffForHumans() }}</div>
                @if($result)
                <div class="mb-3 p-2 rounded" style="background:#f0fdf4;border:1px solid #bbf7d0">
                    <div style="font-size:0.82rem;font-weight:700;color:#166534">
                        Score: {{ $result->obtained_marks }}/{{ $result->total_marks }}
                        ({{ $result->percentage }}%)
                        @if($result->isDisqualified())
                            — Failed (Cheating)
                        @endif
                    </div>
                </div>
                @endif
                <a href="{{ route('student.results.index') }}" class="btn btn-outline-primary w-100 py-2" style="font-weight:700">
                    <i class="bi bi-eye me-1"></i> View Results
                </a>
            </div>
        </div>

        @elseif(now()->lt($schedule->starts_at))
        <div class="card mb-3">
            <div class="card-body text-center py-4">
                <i class="bi bi-hourglass-split d-block mb-2" style="font-size:2rem;color:var(--blc-gold)"></i>
                <div style="font-weight:600;color:var(--blc-navy)" class="mb-1">Exam Not Started Yet</div>
                <div class="text-muted small mb-2">Starts at {{ $schedule->starts_at->format('M d, Y — H:i') }}</div>
                <div class="mt-3 mb-1 text-muted small" style="font-weight:600;letter-spacing:0.04em;text-transform:uppercase">Time remaining</div>
                <div id="examStartCountdown"
                     data-countdown-to="{{ $schedule->starts_at->timestamp }}"
                     style="font-size:1.75rem;font-weight:800;color:var(--blc-navy);font-variant-numeric:tabular-nums;letter-spacing:0.04em">
                    --:--:--
                </div>
            </div>
        </div>
        @endif

        {{-- Attempt history --}}
        @if($attempts->count())
        <div class="card">
            <div class="card-header"><i class="bi bi-clock-history me-2"></i>Your Attempts</div>
            <div class="card-body p-0">
                @foreach($attempts as $att)
                @php
                    $attStatusClass = match($att->status) {
                        'submitted'  => 'status-approved',
                        'in_progress' => 'status-pending',
                        default      => 'status-closed',
                    };
                @endphp
                <div class="p-3 border-bottom">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <div>
                            <div class="small" style="font-weight:700">
                                Attempt #{{ $att->attempt_number }}
                            </div>
                            <div class="text-muted" style="font-size:0.72rem">
                                {{ $att->started_at?->format('M d, H:i') }}
                            </div>
                        </div>
                        <span class="status-pill {{ $attStatusClass }}" style="font-size:0.7rem">
                            {{ ucfirst(str_replace('_', ' ', $att->status)) }}
                        </span>
                    </div>
                    {{-- Show score if result exists and schedule has ended --}}
                    @if($scheduleEnded && $att->result && $att->result->is_published)
                    <div class="mt-1 d-flex align-items-center gap-2">
                        <span style="font-size:0.78rem;font-weight:700;color:{{ $att->result->is_passed ? '#166534' : '#991b1b' }}">
                            {{ $att->result->obtained_marks }}/{{ $att->result->total_marks }}
                            ({{ $att->result->percentage }}%)
                        </span>
                        @if($canViewAnswers)
                        <a href="#pane-att-{{ $att->id }}"
                           onclick="document.getElementById('tab-att-{{ $att->id }}')?.click()"
                           class="ms-auto"
                           style="font-size:0.72rem;color:var(--blc-navy-2);text-decoration:none;font-weight:600">
                            Review <i class="bi bi-arrow-right"></i>
                        </a>
                        @endif
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endif

    </div>
</div>
@endsection

@push('styles')
<style>
.exam-stat {
    display:flex;align-items:center;gap:0.4rem;
    font-size:0.82rem;font-weight:600;color:#374151;
    background:#f8faff;border:1px solid #e8edf5;
    border-radius:8px;padding:0.35rem 0.75rem;
}
.exam-stat i { color:var(--blc-navy-2,#0f3a7a); }

.result-percentage-circle {
    width:80px;height:80px;border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    font-size:1.2rem;font-weight:900;flex-shrink:0;
}
.result-percentage-circle.pass { background:#dcfce7;color:#166534; }
.result-percentage-circle.fail { background:#fee2e2;color:#991b1b; }

/* Review cards */
.review-card {
    border-radius:12px;padding:1rem 1.1rem;
    margin-bottom:0.85rem;
    border:1.5px solid #e8edf5;
}
.review-correct { background:#f0fdf4;border-color:#bbf7d0; }
.review-wrong   { background:#fef2f2;border-color:#fecaca; }

.student-answer-pill {
    display:inline-block;padding:0.25rem 0.75rem;
    border-radius:20px;font-size:0.82rem;font-weight:600;
}
.student-answer-pill.correct { background:#dcfce7;color:#166534; }
.student-answer-pill.wrong   { background:#fee2e2;color:#991b1b; }

.q-number {
    display:inline-flex;align-items:center;justify-content:center;
    width:26px;height:26px;border-radius:6px;
    background:var(--blc-navy,#0b2a5b);color:#fff;
    font-size:0.72rem;font-weight:800;flex-shrink:0;
}

/* Attempt tabs */
#attemptTabs .nav-link {
    background:#f0f4ff;color:var(--blc-navy-2,#0f3a7a);
    border:1.5px solid transparent;
}
#attemptTabs .nav-link.active {
    background:var(--blc-navy,#0b2a5b);color:#fff;
    border-color:var(--blc-navy);
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    const el = document.getElementById('examStartCountdown');
    if (!el) return;

    const target = parseInt(el.dataset.countdownTo, 10);

    function formatRemaining(totalSeconds) {
        if (totalSeconds <= 0) return '00:00:00';
        const h = Math.floor(totalSeconds / 3600);
        const m = Math.floor((totalSeconds % 3600) / 60);
        const s = totalSeconds % 60;
        return [h, m, s].map(n => String(n).padStart(2, '0')).join(':');
    }

    function tick() {
        const remaining = target - Math.floor(Date.now() / 1000);
        el.textContent = formatRemaining(remaining);
        if (remaining <= 0) {
            location.reload();
        }
    }

    tick();
    setInterval(tick, 1000);
})();
</script>
@endpush
