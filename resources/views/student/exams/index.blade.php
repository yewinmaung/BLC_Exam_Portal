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
        $schedule = $e->activeSchedule;
        $activeAttempt = $activeAttempts[$e->id] ?? null;
        $now = now();
        $isEnded = $schedule && $now->gt($schedule->ends_at);
        $isUpcoming = $schedule && $now->lt($schedule->starts_at);
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
                    <a href="{{ route('student.exams.show', $e) }}" class="btn btn-sm btn-outline-primary">
                        View <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                    @elseif($activeAttempt)
                    <a href="{{ route('student.exam.take', $activeAttempt) }}" class="btn btn-sm btn-warning" style="background:#d4a51c;border:none;color:#fff;font-weight:700">
                        In Progress <i class="bi bi-play-fill ms-1"></i>
                    </a>
                    @else
                    {{-- Not started yet (upcoming or live, no active attempt) --}}
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
    function formatRemaining(totalSeconds) {
        if (totalSeconds <= 0) return '00:00:00';
        const h = Math.floor(totalSeconds / 3600);
        const m = Math.floor((totalSeconds % 3600) / 60);
        const s = totalSeconds % 60;
        return [h, m, s].map(n => String(n).padStart(2, '0')).join(':');
    }

    const timers = document.querySelectorAll('[data-countdown-to]');
    if (!timers.length) return;

    function tick() {
        const now = Math.floor(Date.now() / 1000);
        let needsReload = false;

        timers.forEach(el => {
            const target = parseInt(el.dataset.countdownTo, 10);
            const remaining = target - now;
            const valueEl = el.querySelector('.countdown-value');
            if (valueEl) valueEl.textContent = formatRemaining(remaining);
            if (remaining <= 0) needsReload = true;
        });

        if (needsReload) {
            location.reload();
        }
    }

    tick();
    setInterval(tick, 1000);
})();
</script>
@endpush
