@extends('layouts.app')
@section('title', $exam->title)
@section('page-title', $exam->title)
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Exams', 'url' => route('admin.exams.index')],
        ['label' => $exam->title],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection
@section('content')
<div class="row g-3">

    {{-- Questions --}}
    <div class="col-md-8">
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-info-circle me-2"></i>Exam Details</span>
                <div class="d-flex gap-2 align-items-center">
                    <span class="status-pill status-{{ $exam->status === 'pending_approval' ? 'pending' : $exam->status }}">
                        {{ ucfirst(str_replace('_', ' ', $exam->status)) }}
                    </span>
                    @if(in_array($exam->status, ['published', 'closed']))
                    <a href="{{ route('admin.exams.results', $exam) }}" class="btn btn-sm btn-primary">
                        <i class="bi bi-bar-chart-fill me-1"></i> View Results
                    </a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <div class="row g-2 text-sm">
                    <div class="col-6"><span class="text-muted small">Course</span><div class="fw-600" style="font-weight:600">{{ $exam->course->title }}</div></div>
                    <div class="col-6"><span class="text-muted small">Teacher</span><div class="fw-600" style="font-weight:600">{{ $exam->teacher->name }}</div></div>
                    <div class="col-6"><span class="text-muted small">Total Marks</span><div class="fw-600" style="font-weight:600">{{ $exam->total_marks }}</div></div>
                    <div class="col-6"><span class="text-muted small">Passing Marks</span><div class="fw-600" style="font-weight:600">{{ $exam->passing_marks }}</div></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-list-ol me-2"></i>Questions</span>
                <span class="badge" style="background:var(--blc-gold-light);color:var(--blc-navy)">{{ $exam->questions->count() }}</span>
            </div>
            <div class="card-body">
                @forelse($exam->questions as $i => $q)
                <div class="question-card">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="q-number">Q{{ $i+1 }}</span>
                        <span class="badge" style="background:#f0f4ff;color:var(--blc-navy-2);font-size:0.7rem">{{ strtoupper(str_replace('_',' ',$q->type)) }}</span>
                        <span class="badge" style="background:#f0fdf4;color:#166534;font-size:0.7rem">{{ $q->marks }} mark{{ $q->marks!==1?'s':'' }}</span>
                    </div>
                    <div class="q-text">{{ $q->decrypted_content }}</div>
                    @if($q->answers->count())
                    <div class="mt-2 d-flex flex-column gap-1">
                        @foreach($q->answers as $a)
                        <div class="answer-option {{ $a->is_correct ? 'correct' : '' }}">
                            <i class="bi {{ $a->is_correct ? 'bi-check-circle-fill' : 'bi-circle' }}" style="font-size:0.8rem"></i>
                            {{ $a->decrypted_content }}
                        </div>
                        @endforeach
                    </div>
                    @endif
                    @if($q->hasAttachment())
                    <div class="mt-2">
                        <a href="{{ $q->attachmentUrl() }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-paperclip me-1"></i>{{ $q->attachment_name }}
                        </a>
                    </div>
                    @endif
                </div>
                @empty
                <p class="text-muted text-center py-3">No questions found.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Actions --}}
    <div class="col-md-4">
        @if($exam->status === 'pending_approval')
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-check2-circle me-2"></i>Approval</div>
            <div class="card-body">
                <p class="text-muted small mb-3">Review the questions above, then approve this exam to proceed.</p>
                <form method="POST" action="{{ route('admin.exams.approve', $exam) }}">@csrf
                    <button class="btn btn-success w-100">
                        <i class="bi bi-check-circle me-1"></i> Approve Exam
                    </button>
                </form>
            </div>
        </div>
        @endif

        @if(in_array($exam->status, ['approved', 'published']))
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-calendar3 me-2"></i>Schedule</div>
            <div class="card-body">
                @if($exam->schedules->isEmpty())
                {{-- No schedule yet — show the set-schedule form --}}

                {{-- Concept explanation --}}
                <div class="alert alert-info py-2 mb-3" style="font-size:0.78rem;line-height:1.55">
                    <div class="d-flex gap-2">
                        <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
                        <div>
                            <strong>Two independent settings:</strong><br>
                            <strong>Open Window</strong> (Start → End) — the period during which students are allowed to <em>begin</em> the exam.<br>
                            <strong>Duration</strong> — the personal countdown each student gets after pressing Start.<br>
                            A student's actual expiry = <code>MIN(student start + duration, open end)</code>.
                            Students who start late receive less time if duration would exceed the open window.
                        </div>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.exams.schedule', $exam) }}" id="scheduleForm">@csrf
                    <div class="mb-3">
                        <label class="form-label">
                            Open Window Start <span class="text-danger">*</span>
                            <small class="text-muted fw-normal">— earliest time a student may begin</small>
                        </label>
                        <input type="datetime-local" name="starts_at" id="starts_at"
                               class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            Open Window End <span class="text-danger">*</span>
                            <small class="text-muted fw-normal">— no new starts after this; active sessions also expire</small>
                        </label>
                        <input type="datetime-local" name="ends_at" id="ends_at"
                               class="form-control" required>
                        <div class="form-text" id="windowDurationHint"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            Student Duration (minutes) <span class="text-danger">*</span>
                            <small class="text-muted fw-normal">— personal countdown per student from their start time</small>
                        </label>
                        <input type="number" name="duration_minutes" id="duration_minutes"
                               class="form-control" value="60" min="1" required>
                        <div class="form-text" id="durationHint"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Attempt Limit <span class="text-danger">*</span></label>
                        <input type="number" name="attempt_limit" class="form-control"
                               value="1" min="1" required>
                    </div>
                    <div class="mb-3">
                        <!-- <label class="form-label">Target Year
                            <span class="text-muted fw-normal">(optional)</span>
                        </label>
                        <select name="target_year" class="form-select">
                            <option value="">All enrolled years</option>
                            <option value="1">Year 1</option>
                            <option value="2">Year 2</option>
                            <option value="3">Year 3</option>
                            <option value="4">Year 4</option>
                            <option value="5">Year 5</option>
                        </select> -->
                        <div class="form-text">Restrict this exam to a specific academic year group.</div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-calendar-check me-1"></i> Set Schedule
                    </button>
                </form>
                @else
                {{-- Schedule already set — view only --}}
                @php $s = $exam->schedules->first(); @endphp
                <p class="text-muted small mb-3">
                    <i class="bi bi-lock-fill me-1"></i>
                    The schedule has been set and cannot be changed.
                </p>
                <div class="row g-2 text-sm">
                    <div class="col-6">
                        <span class="text-muted small">Open Window Start</span>
                        <div class="fw-600" style="font-weight:600">{{ $s->starts_at->format('M d, Y H:i') }}</div>
                    </div>
                    <div class="col-6">
                        <span class="text-muted small">Open Window End</span>
                        <div class="fw-600" style="font-weight:600">{{ $s->ends_at->format('M d, Y H:i') }}</div>
                    </div>
                    <div class="col-4">
                        <span class="text-muted small">Student Duration</span>
                        <div class="fw-600" style="font-weight:600">{{ $s->duration_minutes }} min</div>
                    </div>
                    <div class="col-4">
                        <span class="text-muted small">Attempts</span>
                        <div class="fw-600" style="font-weight:600">{{ $s->attempt_limit }}</div>
                    </div>
                    <!-- <div class="col-4">
                        <span class="text-muted small">Target Year</span>
                        <div class="fw-600" style="font-weight:600">
                            {{ $s->target_year ? 'Year ' . $s->target_year : 'All years' }}
                        </div>
                    </div> -->
                    @if($s->is_published)
                    <div class="col-12 mt-1">
                        <span class="status-pill status-published">Live</span>
                    </div>
                    @endif
                </div>
                @endif
            </div>
        </div>
        @endif

        @if($exam->status === 'approved' && $exam->latestSchedule)
        <div class="card mb-3">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.exams.publish', $exam) }}">@csrf
                    <button class="btn w-100" style="background:var(--blc-gold);color:#1a1a1a;font-weight:600;border:none">
                        <i class="bi bi-broadcast me-1"></i> Publish Exam
                    </button>
                </form>
            </div>
        </div>
        @endif

        @if($exam->status === 'published')
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-toggle-off me-2"></i>Exam Access</div>
            <div class="card-body">
                <p class="text-muted small mb-3">Close the exam to stop students from starting or continuing it.</p>
                <form method="POST" action="{{ route('admin.exams.close', $exam) }}"
                      onsubmit="return confirm('Close this exam? Students will no longer be able to take it.')">@csrf
                    <button class="btn btn-outline-danger w-100">
                        <i class="bi bi-x-circle me-1"></i> Close Exam
                    </button>
                </form>
            </div>
        </div>
        @endif

        @if($exam->status === 'closed')
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-toggle-on me-2"></i>Exam Access</div>
            <div class="card-body">
                <p class="text-muted small mb-3">Reopen the exam so students can access it again during the scheduled window.</p>
                <form method="POST" action="{{ route('admin.exams.open', $exam) }}"
                      onsubmit="return confirm('Reopen this exam? Students will be able to take it again if the schedule is still active.')">@csrf
                    <button class="btn btn-success w-100">
                        <i class="bi bi-unlock me-1"></i> Open Exam
                    </button>
                </form>
            </div>
        </div>
        @endif

        {{-- Schedule history is shown inline in the Schedule card above --}}
    </div>

</div>
@endsection

@push('scripts')
<script>
(function () {
    const startsAt      = document.getElementById('starts_at');
    const endsAt        = document.getElementById('ends_at');
    const durationInput = document.getElementById('duration_minutes');
    const scheduleForm  = document.getElementById('scheduleForm');
    const windowHint    = document.getElementById('windowDurationHint');
    const durationHint  = document.getElementById('durationHint');

    if (!startsAt) return; // schedule already set — nothing to initialise

    // ── Helpers ──────────────────────────────────────────────────────────

    /** Format a Date to "YYYY-MM-DDTHH:MM" for datetime-local inputs. */
    function toLocalInput(date) {
        const pad = n => String(n).padStart(2, '0');
        return date.getFullYear() + '-' +
               pad(date.getMonth() + 1) + '-' +
               pad(date.getDate()) + 'T' +
               pad(date.getHours()) + ':' +
               pad(date.getMinutes());
    }

    /**
     * Refresh the two informational hints below the fields.
     *
     * windowHint  — shows total open-window length in hours/minutes.
     * durationHint — warns when student duration > open window length,
     *                because the server will cap it to the window length
     *                for students who start right as the window opens.
     *
     * Neither hint modifies any field value — they are read-only feedback.
     */
    function refreshHints() {
        const startVal    = startsAt.value;
        const endVal      = endsAt.value;
        const durationVal = parseInt(durationInput?.value || '0', 10);

        // ── Window length hint ────────────────────────────────────────────
        if (startVal && endVal) {
            const start      = new Date(startVal);
            const end        = new Date(endVal);
            const windowMins = Math.round((end - start) / 60000);

            if (windowMins > 0 && windowHint) {
                const h = Math.floor(windowMins / 60);
                const m = windowMins % 60;
                const label = h > 0
                    ? (h + 'h ' + (m > 0 ? m + 'min' : '')).trim()
                    : m + ' min';
                windowHint.textContent = 'Open window length: ' + label;
                windowHint.style.color = '';

                // ── Duration vs window warning ────────────────────────────
                if (durationHint) {
                    if (!isNaN(durationVal) && durationVal > windowMins) {
                        durationHint.innerHTML =
                            '<i class="bi bi-exclamation-triangle-fill me-1 text-warning"></i>' +
                            'Duration (' + durationVal + ' min) exceeds the open window (' + windowMins + ' min). ' +
                            'Students who start right as the window opens will get ' + windowMins + ' min. ' +
                            'Students who start later will get even less.';
                        durationHint.style.color = '#92400e';
                    } else if (!isNaN(durationVal) && durationVal > 0) {
                        durationHint.innerHTML =
                            '<i class="bi bi-check-circle-fill me-1 text-success"></i>' +
                            'Students who start at window open will receive the full ' + durationVal + ' min.';
                        durationHint.style.color = '#166534';
                    } else {
                        durationHint.textContent = '';
                    }
                }
            } else if (windowHint) {
                windowHint.textContent = '';
            }
        } else if (windowHint) {
            windowHint.textContent = '';
            if (durationHint) durationHint.textContent = '';
        }
    }

    // ── Default start time ────────────────────────────────────────────────
    // Set a sensible default for starts_at (next 5-minute boundary) as a
    // convenience.  ends_at and duration_minutes are left blank / at their
    // default values so the admin must consciously fill them in.
    function setDefaultStart() {
        if (startsAt.value) return; // already has a value — do not overwrite
        const now = new Date();
        now.setSeconds(0, 0);
        const rem = now.getMinutes() % 5;
        now.setMinutes(now.getMinutes() + (rem !== 0 ? (5 - rem) : 5));
        startsAt.value = toLocalInput(now);
        startsAt.min   = toLocalInput(new Date());
    }

    // ── Form validation ───────────────────────────────────────────────────
    // The only hard constraint is ends_at > starts_at.
    // duration_minutes > window length is ALLOWED — the server caps it correctly.
    scheduleForm?.addEventListener('submit', function (e) {
        // Reset custom validity from previous submissions
        endsAt.setCustomValidity('');
        durationInput?.setCustomValidity('');

        if (!startsAt.value || !endsAt.value) return;

        const start = new Date(startsAt.value);
        const end   = new Date(endsAt.value);

        if (end <= start) {
            e.preventDefault();
            endsAt.setCustomValidity('Open window end must be after the start time.');
            endsAt.reportValidity();
            return;
        }

        const durationVal = parseInt(durationInput?.value || '0', 10);
        if (isNaN(durationVal) || durationVal < 1) {
            e.preventDefault();
            durationInput?.setCustomValidity('Duration must be at least 1 minute.');
            durationInput?.reportValidity();
        }
    });

    // ── Event listeners (hints only — no field auto-calculation) ─────────
    startsAt.addEventListener('change', function () {
        endsAt.setCustomValidity('');
        // Enforce starts_at minimum each time it changes
        if (endsAt.value) {
            const start = new Date(this.value);
            endsAt.min  = toLocalInput(new Date(start.getTime() + 60000));
        }
        refreshHints();
    });

    endsAt.addEventListener('change', function () {
        // Validate end > start inline; never back-calculate duration
        if (!startsAt.value) return;
        const start = new Date(startsAt.value);
        const end   = new Date(this.value);
        if (end <= start) {
            this.setCustomValidity('Open window end must be after the start time.');
        } else {
            this.setCustomValidity('');
        }
        refreshHints();
    });

    // Duration changes only refresh hints — never touch ends_at
    durationInput?.addEventListener('input',  refreshHints);
    durationInput?.addEventListener('change', refreshHints);

    // ── Initialise ───────────────────────────────────────────────────────
    setDefaultStart();
    refreshHints();
})();
</script>
@endpush
