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
                    <span class="status-pill status-{{ in_array($exam->status,['approved','published']) ? 'approved' : $exam->status }}">
                        {{ $exam->status === 'approved' ? 'Ready' : ucfirst($exam->status) }}
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

        {{-- ── RESULT (only after schedule ends) ── --}}
        @if($scheduleEnded && $result)
        <div class="card mb-3"
             style="border-color:{{ $result->is_passed ? '#bbf7d0' : '#fecaca' }} !important">
            <div class="card-header"
                 style="background:{{ $result->is_passed ? '#f0fdf4' : '#fef2f2' }}">
                <i class="bi bi-{{ $result->is_passed ? 'trophy-fill text-success' : 'x-circle-fill text-danger' }} me-2"></i>
                Your Result
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-4 flex-wrap">
                    <div class="result-grade-circle {{ $result->is_passed ? 'pass' : 'fail' }}">
                        {{ $result->grade }}
                    </div>
                    <div class="d-flex flex-wrap gap-4">
                        <div>
                            <div class="text-muted small">Score</div>
                            <div style="font-size:1.5rem;font-weight:800;color:var(--blc-navy)">
                                {{ $result->obtained_marks }}<span class="text-muted" style="font-size:1rem;font-weight:400">/{{ $result->total_marks }}</span>
                            </div>
                        </div>
                        <div>
                            <div class="text-muted small">Percentage</div>
                            <div style="font-size:1.3rem;font-weight:700">{{ $result->percentage }}%</div>
                        </div>
                        <div>
                            <div class="text-muted small">Status</div>
                            <span class="status-pill {{ $result->is_passed ? 'status-approved' : 'status-closed' }}"
                                  style="font-size:0.9rem;margin-top:4px;display:inline-flex">
                                {{ $result->is_passed ? '✓ PASSED' : '✗ FAILED' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── ANSWER REVIEW (only after schedule ends) ── --}}
        @if($canViewAnswers)
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-eye-fill" style="color:var(--blc-gold)"></i>
                Answer Review
                <span class="badge ms-auto" style="background:#f0fdf4;color:#166534;font-size:0.72rem">
                    Exam ended — answers revealed
                </span>
            </div>
            <div class="card-body">
                @php
                    $attemptAnswers = $attempts->first()
                        ? $attempts->first()->studentAnswers->keyBy('question_id')
                        : collect();
                @endphp

                @foreach($exam->questions as $i => $q)
                @php
                    $studentAnswer = $attemptAnswers->get($q->id);
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
                        <span class="ms-auto badge {{ $isCorrect ? 'bg-success' : 'bg-danger' }}">
                            {{ $isCorrect ? '✓ Correct' : '✗ Wrong' }}
                        </span>
                    </div>

                    <div class="q-text mb-3">{{ $q->decrypted_content }}</div>

                    {{-- Student's answer --}}
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
                    @endif

                    {{-- Correct answer --}}
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

        @elseif($scheduleEnded && !$result)
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
        {{-- Active: Start button --}}
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
                <div class="text-muted small">Ended {{ $schedule->ends_at->diffForHumans() }}</div>
                @if($result)
                <div class="mt-3 p-2 rounded" style="background:#f0fdf4;border:1px solid #bbf7d0">
                    <div style="font-size:0.82rem;font-weight:700;color:#166534">
                        Score: {{ $result->obtained_marks }}/{{ $result->total_marks }}
                        ({{ $result->percentage }}%) — {{ $result->grade }}
                    </div>
                </div>
                @endif

                @if($attempts->count() === 0)
                <a href="{{ route('student.reattempts.create', $exam) }}" class="btn btn-outline-primary w-100 mt-3">
                    <i class="bi bi-arrow-repeat me-1"></i> Request Re-Attempt (Missed Exam)
                </a>
                @endif
            </div>
        </div>

        @elseif(now()->lt($schedule->starts_at))
        <div class="card mb-3">
            <div class="card-body text-center py-4">
                <i class="bi bi-hourglass-split d-block mb-2" style="font-size:2rem;color:var(--blc-gold)"></i>
                <div style="font-weight:600;color:var(--blc-navy)" class="mb-1">Exam Not Started Yet</div>
                <div class="text-muted small">Starts {{ $schedule->starts_at->diffForHumans() }}</div>
                <div class="text-muted small mt-1">{{ $schedule->starts_at->format('M d, Y — H:i') }}</div>
            </div>
        </div>
        @endif

        {{-- Attempt history --}}
        @if($attempts->count())
        <div class="card">
            <div class="card-header"><i class="bi bi-clock-history me-2"></i>Your Attempts</div>
            <div class="card-body p-0">
                @foreach($attempts as $att)
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small" style="font-weight:600">Attempt #{{ $att->attempt_number }}</div>
                        <div class="text-muted" style="font-size:0.72rem">
                            {{ $att->started_at?->format('M d, H:i') }}
                        </div>
                    </div>
                    <span class="status-pill status-{{ $att->status === 'submitted' ? 'approved' : ($att->status === 'in_progress' ? 'pending' : 'closed') }}">
                        {{ ucfirst($att->status) }}
                    </span>
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

.result-grade-circle {
    width:64px;height:64px;border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    font-size:1.75rem;font-weight:900;flex-shrink:0;
}
.result-grade-circle.pass { background:#dcfce7;color:#166534; }
.result-grade-circle.fail { background:#fee2e2;color:#991b1b; }

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
</style>
@endpush
