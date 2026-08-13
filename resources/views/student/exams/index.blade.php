@extends('layouts.app')
@section('title', 'Available Exams')
@section('page-title', 'Available Exams')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Student', 'url' => route('student.dashboard')],
        ['label' => 'Exams'],
    ]])
@endsection
@section('sidebar')
@include('partials.student-sidebar')

@endsection

@section('content')
@if(isset($securityTerminatedAttempts) && $securityTerminatedAttempts->isNotEmpty())
    @foreach($securityTerminatedAttempts as $terminatedAttempt)
        @php
            $lastViolation = $terminatedAttempt->cheatingLogs->sortByDesc('warning_number')->first();
            $reason = $lastViolation?->details
                ?: ($lastViolation ? str_replace('_', ' ', ucfirst($lastViolation->violation_type)) : 'Repeated security violations');
        @endphp
        <div class="alert alert-danger d-flex align-items-start gap-2 mb-3" role="alert">
            <i class="bi bi-exclamation-octagon-fill flex-shrink-0 mt-1"></i>
            <div>
                <strong>Exam Terminated — Result Invalidated</strong><br>
                <span class="small">
                    <strong>{{ $terminatedAttempt->exam->title ?? 'Exam' }}</strong>:
                    Your exam was terminated due to security violations.
                    Reason: {{ $reason }}.
                    Your result has been invalidated and your session is locked pending review.
                </span>
            </div>
        </div>
    @endforeach
@endif

<div class="row g-3">
    @forelse($exams as $e)
    @php
        $schedule        = $e->activeSchedule;
        $activeAttempt   = $activeAttempts[$e->id]    ?? null;
        $finalizedAttempt= $finalizedAttempts[$e->id] ?? null;
        $now             = now();
        $isEnded         = $schedule && $now->gt($schedule->ends_at);
        $isUpcoming      = $schedule && $now->lt($schedule->starts_at);
    @endphp
    <div class="col-md-6 col-xl-4">
        <div class="card h-100" style="transition:transform 0.2s,box-shadow 0.2s" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform=''">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-start justify-content-between mb-2">
                    <span class="status-pill status-published">Published</span>
                    @if($schedule)
                        @if($isUpcoming)
                        <span class="text-muted small exam-card-timer"
                              data-countdown-to="{{ $schedule->starts_at->timestamp }}">
                            <i class="bi bi-hourglass-split me-1"></i>
                            <span class="countdown-label">Starts in</span>
                            <span class="countdown-value" style="font-weight:700;color:var(--blc-navy)">--:--:--</span>
                        </span>
                        @elseif($isEnded)
                        <span class="text-muted small"><i class="bi bi-check-circle me-1"></i>Ended</span>
                        @elseif($activeAttempt)
                        {{--
                            Two states:
                            A) disconnected_at is NULL  → student is actively in the exam.
                               Show nothing in the top-right badge — just the button below.
                            B) disconnected_at is SET   → student has disconnected.
                               Show a recovery countdown: disconnected_at + recovery_limit.
                               When it hits zero, reload the page so the card reflects the
                               new state (attempt will have been auto-submitted by the server).
                        --}}
                        @php
                            $recoveryLimit    = (int) config('exam_security.recovery_time_limit', 600);
                            $disconnectedBase = $activeAttempt->disconnected_at ?? now();
                            $recoveryDeadline = $disconnectedBase->copy()->addSeconds($recoveryLimit);
                        @endphp
                        <span class="small"
                              id="recovery-badge-{{ $e->id }}"
                              data-recovery-deadline="{{ $recoveryDeadline->timestamp }}"
                              data-exam-id="{{ $e->id }}"
                              data-attempt-url="{{ route('student.exam.take', $activeAttempt) }}"
                              data-exam-url="{{ route('student.exams.show', $e) }}"
                              style="font-weight:700;color:#d97706">
                            <i class="bi bi-wifi-off me-1"></i>
                            <span class="recovery-value">--:--</span>
                        </span>
                        @else
                        <span class="text-muted small"><i class="bi bi-clock me-1"></i>{{ $schedule->duration_minutes }}min</span>
                        @endif
                    @endif
                </div>
                <h6 class="fw-700 mb-1" style="font-weight:700;color:var(--blc-navy)">{{ $e->title }}</h6>
                <p class="text-muted small mb-3"><i class="bi bi-book me-1"></i>{{ $e->course->title }}</p>
                @if($schedule)
                <div class="mt-auto pt-2 border-top d-flex align-items-center justify-content-between gap-2">
                    <small class="text-muted">
                        @if($isUpcoming)
                        <i class="bi bi-calendar3 me-1"></i>Starts {{ $schedule->starts_at->format('M d, H:i') }}
                        @elseif($isEnded)
                        <i class="bi bi-calendar3 me-1"></i>Ended {{ $schedule->ends_at->format('M d, H:i') }}
                        @else
                        <i class="bi bi-calendar3 me-1"></i>Ends {{ $schedule->ends_at->format('M d, H:i') }}
                        @endif
                    </small>

                    @if($isEnded)
                    {{-- Schedule ended — differentiate by attempt state --}}
                    @if($finalizedAttempt)
                    {{-- Student submitted or was terminated → let them view --}}
                    <a href="{{ route('student.exams.show', $e) }}" class="btn btn-sm btn-outline-primary">
                        View <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                    @elseif($activeAttempt)
                    {{-- Attempt stuck in_progress when window closed → still linkable --}}
                    <a href="{{ route('student.exams.show', $e) }}" class="btn btn-sm btn-outline-secondary" style="font-weight:600">
                        View <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                    @else
                    {{-- Never started the exam at all --}}
                    <span class="text-muted small fst-italic" style="font-size:0.75rem">
                        Not attempted
                    </span>
                    @endif

                    @elseif($activeAttempt)
                    {{-- Student is currently in_progress --}}
                    {{--
                        The exam list page is never shown while a student is actively
                        inside the exam (they would be on /take). Any in_progress attempt
                        visible here means the student has navigated away or disconnected.
                        Always show Reconnect/Finalize — never "Continue Exam".
                    --}}
                    @php
                        $recoveryLimit    = (int) config('exam_security.recovery_time_limit', 600);
                        $disconnectedBase = $activeAttempt->disconnected_at ?? now();
                        $recoveryDeadline = $disconnectedBase->copy()->addSeconds($recoveryLimit);
                        $inRecoveryWindow = now()->lt($recoveryDeadline) && now()->lt($activeAttempt->expires_at);
                    @endphp
                    <span id="action-btn-{{ $e->id }}">
                        @if($inRecoveryWindow)
                        {{--
                            Recovery window open — student CAN reconnect.
                            Clicking goes to take() → handleReconnect() path A →
                            clears disconnected_at and resumes the exam with frozen timer.
                        --}}
                        <a href="{{ route('student.exam.take', $activeAttempt) }}"
                           class="btn btn-sm btn-danger"
                           style="border:none;font-weight:700">
                            <i class="bi bi-wifi-off me-1"></i>Reconnect
                        </a>
                        @else
                        {{--
                            Recovery window has expired — clicking goes to take() →
                            handleReconnect() path B → auto-submits and shows result.
                        --}}
                        <a href="{{ route('student.exam.take', $activeAttempt) }}"
                           class="btn btn-sm btn-outline-secondary"
                           style="font-weight:600">
                            <i class="bi bi-hourglass-split me-1"></i>Finalize
                        </a>
                        @endif
                    </span>

                    @elseif($finalizedAttempt)
                    {{--
                        Student has a finalized attempt (submitted / terminated / suspicious).
                        Schedule is still open → result not yet published → "Waiting Result".
                        Schedule has ended   → falls into $isEnded branch above → "View".
                        If attempt_limit > 1 and remaining attempts > 0, also show "Start Again".
                    --}}
                    @php
                        $attemptLimit      = max(1, (int) ($schedule?->attempt_limit ?? 1));
                        $usedAttempts      = $usedAttemptCounts[$e->id] ?? 0;
                        $remainingAttempts = $attemptLimit - $usedAttempts;
                    @endphp
                    <div class="d-flex flex-column align-items-end gap-1">
                        <a href="{{ route('student.exams.show', $e) }}"
                           class="btn btn-sm btn-outline-secondary"
                           style="font-weight:600">
                            <i class="bi bi-hourglass-split me-1"></i>Waiting Result
                        </a>
                        @if($remainingAttempts > 0)
                        <span class="text-muted" style="font-size:0.72rem;white-space:nowrap">
                            Remaining Attempts: {{ $remainingAttempts }}
                        </span>
                        <a href="{{ route('student.exams.show', $e) }}"
                           class="btn btn-sm btn-primary"
                           style="font-weight:600">
                            <i class="bi bi-arrow-repeat me-1"></i>Start Again
                        </a>
                        @endif
                    </div>

                    @else
                    {{-- No attempt at all and schedule is open — first time start --}}
                    <a href="{{ route('student.exams.show', $e) }}" class="btn btn-sm btn-primary">
                        Start <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                    @endif
                </div>
                @else
                <div class="mt-auto pt-2 border-top">
                    <a href="{{ route('student.exams.show', $e) }}" class="btn btn-sm btn-outline-primary w-100">View</a>
                </div>
                @endif
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center py-5 text-muted">
                <i class="bi bi-file-earmark-x d-block mb-3" style="font-size:3rem;opacity:0.35"></i>
                <h6>No exams available</h6>
                <p class="small mb-0">Exams will appear here once your teacher submits and admin approves them.</p>
            </div>
        </div>
    </div>
    @endforelse
</div>
@endsection

@push('scripts')
<script>
(function () {
    function formatHMS(totalSeconds) {
        if (totalSeconds <= 0) return '00:00:00';
        const h = Math.floor(totalSeconds / 3600);
        const m = Math.floor((totalSeconds % 3600) / 60);
        const s = totalSeconds % 60;
        return [h, m, s].map(n => String(n).padStart(2, '0')).join(':');
    }

    function formatMS(totalSeconds) {
        if (totalSeconds <= 0) return '00:00';
        const m = Math.floor(totalSeconds / 60);
        const s = totalSeconds % 60;
        return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    }

    // ── 1. Upcoming-exam "Starts in" countdown ───────────────────────────
    // Needs a full page reload when it hits zero — the server state changes.
    const upcomingTimers = document.querySelectorAll('[data-countdown-to]');

    // ── 2. Recovery countdown ────────────────────────────────────────────
    // When it hits zero: swap the badge and the Reconnect button in-place.
    // No page reload — the student is already looking at the card.
    const recoveryTimers = document.querySelectorAll('[data-recovery-deadline]');

    if (!upcomingTimers.length && !recoveryTimers.length) return;

    // Guard: only reload for upcoming timers that were LIVE on page load.
    const alreadyExpiredOnLoad = new Set();
    let reloadScheduled = false;

    const nowOnLoad = Math.floor(Date.now() / 1000);
    upcomingTimers.forEach(el => {
        if (parseInt(el.dataset.countdownTo, 10) <= nowOnLoad) {
            alreadyExpiredOnLoad.add(el);
        }
    });
    // Recovery timers that are already expired on load are also tracked,
    // so they get swapped immediately on first tick instead of reloading.
    recoveryTimers.forEach(el => {
        if (parseInt(el.dataset.recoveryDeadline, 10) <= nowOnLoad) {
            alreadyExpiredOnLoad.add(el);
        }
    });

    // Track which recovery badges have already been swapped to avoid
    // running the DOM swap on every subsequent tick.
    const swappedRecovery = new Set();

    function swapToWaitingResult(badgeEl) {
        const examId      = badgeEl.dataset.examId;
        const attemptUrl  = badgeEl.dataset.attemptUrl;
        if (!examId || swappedRecovery.has(examId)) return;
        swappedRecovery.add(examId);

        // ── Replace the countdown badge with a muted "Expired" label ────
        badgeEl.innerHTML = '<i class="bi bi-clock-history me-1"></i>Expired';
        badgeEl.style.color      = '#6b7280';
        badgeEl.style.fontWeight = '600';

        // ── Swap Reconnect button to Finalize ────────────────────────────
        // take() → handleReconnect() path B → auto-submit + grade
        const btnWrap = document.getElementById('action-btn-' + examId);
        if (btnWrap && attemptUrl) {
            btnWrap.innerHTML =
                '<a href="' + attemptUrl + '" class="btn btn-sm btn-outline-secondary" ' +
                'style="font-weight:600">' +
                '<i class="bi bi-hourglass-split me-1"></i>Finalize' +
                '</a>';
        }
    }

    function scheduleReload() {
        if (reloadScheduled) return;
        reloadScheduled = true;
        setTimeout(() => location.reload(), 1500);
    }

    function tick() {
        const now = Math.floor(Date.now() / 1000);

        // Upcoming timers — full reload when window opens
        upcomingTimers.forEach(el => {
            const target    = parseInt(el.dataset.countdownTo, 10);
            const remaining = target - now;
            const valueEl   = el.querySelector('.countdown-value');
            if (valueEl) valueEl.textContent = formatHMS(Math.max(0, remaining));
            if (remaining <= 0 && !alreadyExpiredOnLoad.has(el)) {
                scheduleReload();
            }
        });

        // Recovery countdown — DOM swap on expiry, never a page reload
        recoveryTimers.forEach(el => {
            const deadline  = parseInt(el.dataset.recoveryDeadline, 10);
            const remaining = deadline - now;
            const valueEl   = el.querySelector('.recovery-value');

            if (remaining <= 0) {
                // Show 00:00 for one tick then swap the card UI
                if (valueEl) valueEl.textContent = '00:00';
                el.style.color = '#dc2626';
                swapToWaitingResult(el);
            } else {
                if (valueEl) valueEl.textContent = formatMS(remaining);
                el.style.color = remaining <= 120 ? '#dc2626' : '#d97706';
            }
        });
    }

    tick();
    setInterval(tick, 1000);
})();
</script>
@endpush
