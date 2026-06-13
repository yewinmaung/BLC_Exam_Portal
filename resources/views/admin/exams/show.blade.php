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
                <span class="status-pill status-{{ $exam->status === 'pending_approval' ? 'pending' : $exam->status }}">
                    {{ ucfirst(str_replace('_', ' ', $exam->status)) }}
                </span>
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
                <form method="POST" action="{{ route('admin.exams.schedule', $exam) }}" id="scheduleForm">@csrf
                    <div class="mb-3">
                        <label class="form-label">Start Date & Time <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="starts_at" id="starts_at"
                               class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">End Date & Time <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="ends_at" id="ends_at"
                               class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Duration (minutes) <span class="text-danger">*</span></label>
                        <input type="number" name="duration_minutes" id="duration_minutes"
                               class="form-control" value="60" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Attempt Limit <span class="text-danger">*</span></label>
                        <input type="number" name="attempt_limit" class="form-control"
                               value="1" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Target Year
                            <span class="text-muted fw-normal">(optional)</span>
                        </label>
                        <select name="target_year" class="form-select">
                            <option value="">All enrolled years</option>
                            <option value="1">Year 1</option>
                            <option value="2">Year 2</option>
                            <option value="3">Year 3</option>
                            <option value="4">Year 4</option>
                            <option value="5">Year 5</option>
                        </select>
                        <div class="form-text">Restrict this exam to a specific academic year group.</div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-calendar-check me-1"></i> Set Schedule
                    </button>
                </form>
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

        @if($exam->schedules->count())
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-clock-history me-2"></i>Schedules</span>
                <span class="badge" style="background:var(--blc-gold-light);color:var(--blc-navy)">
                    {{ $exam->schedules->count() }}
                </span>
            </div>
            <div class="card-body p-0">
                @foreach($exam->schedules as $s)
                <div class="border-bottom">
                    {{-- Schedule summary row --}}
                    <div class="p-3 d-flex justify-content-between align-items-start gap-2"
                         id="schedule-row-{{ $s->id }}">
                        <div class="flex-grow-1">
                            <div class="small" style="font-weight:700;color:var(--blc-navy)">
                                {{ $s->starts_at->format('M d, Y H:i') }}
                                <span class="text-muted fw-normal">→</span>
                                {{ $s->ends_at->format('M d, Y H:i') }}
                            </div>
                            <div class="text-muted" style="font-size:0.75rem;margin-top:2px">
                                {{ $s->duration_minutes }}min
                                · {{ $s->attempt_limit }} attempt(s)
                                @if($s->target_year)
                                · <strong>Year {{ $s->target_year }} only</strong>
                                @endif
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-1 flex-shrink-0">
                            @if($s->is_published)
                            <span class="status-pill status-published">Live</span>
                            @endif
                            <button type="button"
                                    class="btn btn-sm btn-outline-primary"
                                    title="Edit schedule"
                                    onclick="toggleEditForm({{ $s->id }})">
                                <i class="bi bi-pencil"></i>
                            </button>
                            @if(!$s->is_published)
                            <form method="POST"
                                  action="{{ route('admin.exams.schedule.delete', [$exam, $s]) }}"
                                  onsubmit="return confirm('Delete this schedule?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            @endif
                        </div>
                    </div>

                    {{-- Inline edit form (hidden by default) --}}
                    <div id="edit-form-{{ $s->id }}" style="display:none"
                         class="p-3 pt-0 border-top" style="background:#f8faff">
                        <form method="POST"
                              action="{{ route('admin.exams.schedule.update', [$exam, $s]) }}"
                              class="edit-schedule-form"
                              data-schedule-id="{{ $s->id }}">
                            @csrf @method('PUT')

                            <div class="row g-2 mb-2">
                                <div class="col-sm-6">
                                    <label class="form-label" style="font-size:0.78rem;font-weight:600">Start</label>
                                    <input type="datetime-local"
                                           name="starts_at"
                                           class="form-control form-control-sm edit-starts-at"
                                           value="{{ $s->starts_at->format('Y-m-d\TH:i') }}"
                                           required>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" style="font-size:0.78rem;font-weight:600">End</label>
                                    <input type="datetime-local"
                                           name="ends_at"
                                           class="form-control form-control-sm edit-ends-at"
                                           value="{{ $s->ends_at->format('Y-m-d\TH:i') }}"
                                           required>
                                </div>
                                <div class="col-sm-4">
                                    <label class="form-label" style="font-size:0.78rem;font-weight:600">Duration (min)</label>
                                    <input type="number"
                                           name="duration_minutes"
                                           class="form-control form-control-sm edit-duration"
                                           value="{{ $s->duration_minutes }}"
                                           min="1" required>
                                </div>
                                <div class="col-sm-4">
                                    <label class="form-label" style="font-size:0.78rem;font-weight:600">Attempts</label>
                                    <input type="number"
                                           name="attempt_limit"
                                           class="form-control form-control-sm"
                                           value="{{ $s->attempt_limit }}"
                                           min="1" required>
                                </div>
                                <div class="col-sm-4">
                                    <label class="form-label" style="font-size:0.78rem;font-weight:600">Target Year</label>
                                    <select name="target_year" class="form-select form-select-sm">
                                        <option value="">All years</option>
                                        <option value="1" {{ $s->target_year == 1 ? 'selected' : '' }}>Year 1</option>
                                        <option value="2" {{ $s->target_year == 2 ? 'selected' : '' }}>Year 2</option>
                                        <option value="3" {{ $s->target_year == 3 ? 'selected' : '' }}>Year 3</option>
                                        <option value="4" {{ $s->target_year == 4 ? 'selected' : '' }}>Year 4</option>
                                        <option value="5" {{ $s->target_year == 5 ? 'selected' : '' }}>Year 5</option>
                                    </select>
                                </div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="bi bi-check-circle me-1"></i>Save Changes
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        onclick="toggleEditForm({{ $s->id }})">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

</div>
@endsection

@push('scripts')
<script>
(function () {
    const startsAt      = document.getElementById('starts_at');
    const endsAt        = document.getElementById('ends_at');
    const durationInput = document.getElementById('duration_minutes');

    if (!startsAt) return;

    // Format a Date object to datetime-local string "YYYY-MM-DDTHH:MM"
    function toLocalInput(date) {
        const pad = n => String(n).padStart(2, '0');
        return date.getFullYear() + '-' +
               pad(date.getMonth() + 1) + '-' +
               pad(date.getDate()) + 'T' +
               pad(date.getHours()) + ':' +
               pad(date.getMinutes());
    }

    // Auto-calculate end time from start + duration
    function updateEndTime() {
        if (!startsAt.value) return;
        const start    = new Date(startsAt.value);
        const duration = parseInt(durationInput?.value || '60', 10);
        if (isNaN(start.getTime()) || isNaN(duration) || duration < 1) return;
        const end = new Date(start.getTime() + duration * 60000);
        endsAt.value = toLocalInput(end);
    }

    // Set default start = now rounded up to next 5 minutes
    function setDefaults() {
        const now = new Date();
        now.setSeconds(0, 0);
        const rem = now.getMinutes() % 5;
        if (rem !== 0) now.setMinutes(now.getMinutes() + (5 - rem));
        else now.setMinutes(now.getMinutes() + 5);

        if (!startsAt.value) {
            startsAt.value = toLocalInput(now);
        }
        updateEndTime();
    }

    startsAt.addEventListener('change', updateEndTime);
    durationInput?.addEventListener('input', updateEndTime);

    // Run on page load
    setDefaults();
})();
</script>

<script>
// Toggle inline edit form visibility
function toggleEditForm(scheduleId) {
    const form = document.getElementById('edit-form-' + scheduleId);
    if (!form) return;
    const isHidden = form.style.display === 'none' || form.style.display === '';
    form.style.display = isHidden ? 'block' : 'none';
}

// Auto-update end time in edit forms when start or duration changes
document.querySelectorAll('.edit-schedule-form').forEach(function(form) {
    const startsAt = form.querySelector('.edit-starts-at');
    const endsAt   = form.querySelector('.edit-ends-at');
    const duration = form.querySelector('.edit-duration');

    function pad(n) { return String(n).padStart(2, '0'); }
    function toLocalInput(date) {
        return date.getFullYear() + '-' + pad(date.getMonth()+1) + '-' +
               pad(date.getDate()) + 'T' + pad(date.getHours()) + ':' + pad(date.getMinutes());
    }
    function syncEnd() {
        if (!startsAt.value || !duration.value) return;
        const start = new Date(startsAt.value);
        const mins  = parseInt(duration.value, 10);
        if (isNaN(start.getTime()) || isNaN(mins)) return;
        endsAt.value = toLocalInput(new Date(start.getTime() + mins * 60000));
    }

    startsAt.addEventListener('change', syncEnd);
    duration.addEventListener('input',  syncEnd);
});
</script>
@endpush
