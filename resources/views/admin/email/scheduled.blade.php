@extends('layouts.app')
@section('title', 'Academic Notification Scheduler')
@section('page-title', 'Academic Notification Scheduler')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Email', 'url' => route('admin.email.index')],
        ['label' => 'Notification Scheduler'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@push('styles')
<style>
  /* ── Filter section ─────────────────────────────────────────────── */
  .filter-section { background:#f8f9fc; border:1.5px solid #e8eaf2; border-radius:10px; padding:18px 20px; margin-bottom:10px; }
  .filter-section-title { font-size:0.72rem; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:0.06em; margin-bottom:12px; display:flex; align-items:center; gap:6px; }
  /* ── Checkbox grid ──────────────────────────────────────────────── */
  .cb-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(170px,1fr)); gap:7px; }
  .cb-item { display:flex; align-items:center; gap:8px; padding:7px 10px; background:#fff; border:1.5px solid #e5e7eb; border-radius:8px; cursor:pointer; transition:border-color .15s,background .15s; font-size:0.83rem; color:#374151; }
  .cb-item:hover { border-color:#6366f1; background:#f5f3ff; }
  .cb-item input[type="checkbox"]:checked + span { font-weight:600; color:#2d27a0; }
  .cb-item:has(input:checked) { border-color:#6366f1; background:#eef2ff; }
  /* ── Radio buttons ──────────────────────────────────────────────── */
  .radio-card { display:flex; flex-direction:column; align-items:flex-start; padding:12px 14px; background:#fff; border:2px solid #e5e7eb; border-radius:10px; cursor:pointer; transition:border-color .15s,background .15s; min-width:150px; flex:1; }
  .radio-card:hover { border-color:#6366f1; background:#f5f3ff; }
  .radio-card:has(input:checked) { border-color:#2d27a0; background:#eef2ff; }
  .radio-card input[type="radio"] { width:14px; height:14px; accent-color:#2d27a0; margin-bottom:6px; }
  .radio-label { font-size:0.87rem; font-weight:700; color:#1a2540; margin-bottom:3px; }
  .radio-desc  { font-size:0.75rem; color:#6b7280; line-height:1.4; }
  /* ── Preview badges ─────────────────────────────────────────────── */
  #filter-preview { min-height:36px; }
  .preview-pill { display:inline-flex; align-items:center; gap:5px; font-size:0.75rem; padding:3px 10px; border-radius:20px; font-weight:600; margin:2px; }
  /* ── Status badges ──────────────────────────────────────────────── */
  .badge-sent    { background:#f0fdf4;color:#059669;padding:3px 9px;border-radius:5px;font-size:0.72rem;font-weight:700; }
  .badge-pending { background:#fffbeb;color:#d97706;padding:3px 9px;border-radius:5px;font-size:0.72rem;font-weight:700; }
  .badge-type    { padding:2px 8px;border-radius:4px;font-size:0.7rem;font-weight:700; }
</style>
@endpush

@section('content')

{{-- ── Alert messages ──────────────────────────────────────────────────── --}}
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 mb-3" role="alert">
    <i class="bi bi-check-circle-fill"></i>
    {{ session('success') }}
    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
</div>
@endif
@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <strong>Please fix the errors below:</strong>
    <ul class="mb-0 mt-1 ps-3">
        @foreach($errors->all() as $e)<li style="font-size:0.85rem">{{ $e }}</li>@endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- ══════════════════════════════════════════════════════════════════════
     SECTION 1 — Schedule New Notification
     ══════════════════════════════════════════════════════════════════════ --}}
<div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-calendar-plus" style="color:var(--blc-royal,#2d27a0)"></i>
        <span>Schedule Academic Notification</span>
    </div>
    <div class="card-body">

        <form method="POST" action="{{ route('admin.email.scheduled.store') }}" id="scheduleForm">
            @csrf

            {{-- ── Row 1: Name + Schedule Date/Time ──────────────────── --}}
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold" style="font-size:0.83rem">
                        <i class="bi bi-tag me-1 text-muted"></i>Label / Name
                    </label>
                    <input type="text"
                           name="name"
                           class="form-control form-control-sm @error('name') is-invalid @enderror"
                           value="{{ old('name') }}"
                           placeholder="e.g. Midterm Exam Reminder – CS Year 2"
                           required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold" style="font-size:0.83rem">
                        <i class="bi bi-calendar3 me-1 text-muted"></i>Schedule Date
                    </label>
                    <input type="date"
                           name="schedule_date"
                           id="schedule_date"
                           class="form-control form-control-sm @error('send_at') is-invalid @enderror"
                           value="{{ old('schedule_date', now()->addDay()->format('Y-m-d')) }}"
                           min="{{ now()->format('Y-m-d') }}"
                           required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold" style="font-size:0.83rem">
                        <i class="bi bi-clock me-1 text-muted"></i>Schedule Time
                    </label>
                    <input type="time"
                           name="schedule_time"
                           id="schedule_time"
                           class="form-control form-control-sm"
                           value="{{ old('schedule_time', '08:00') }}"
                           required>
                    @error('send_at')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                {{-- Hidden combined send_at field --}}
                <input type="hidden" name="send_at" id="send_at_combined"
                       value="{{ old('send_at') }}">
            </div>

            {{-- ── Row 2: Notification Type ──────────────────────────── --}}
            <div class="mb-4">
                <label class="form-label fw-semibold d-block mb-2" style="font-size:0.83rem">
                    <i class="bi bi-bell me-1 text-muted"></i>Notification Type
                    <span class="text-danger">*</span>
                </label>
                <div class="d-flex flex-wrap gap-3">
                    @foreach(\App\Models\ScheduledEmail::$notificationTypes as $typeKey => $typeLabel)
                    @php
                        $descriptions = \App\Models\ScheduledEmail::$notificationDescriptions;
                        $icons = ['exam_time'=>'bi-calendar-event','exam_policy'=>'bi-shield-check','exam_reminder'=>'bi-alarm'];
                    @endphp
                    <label class="radio-card">
                        <input type="radio"
                               name="notification_type"
                               value="{{ $typeKey }}"
                               {{ old('notification_type', 'exam_reminder') === $typeKey ? 'checked' : '' }}
                               required>
                        <span class="radio-label">
                            <i class="bi {{ $icons[$typeKey] ?? 'bi-bell' }} me-1"></i>{{ $typeLabel }}
                        </span>
                        <span class="radio-desc">{{ $descriptions[$typeKey] ?? '' }}</span>
                    </label>
                    @endforeach
                </div>
                @error('notification_type')<div class="text-danger mt-1" style="font-size:0.8rem">{{ $message }}</div>@enderror
            </div>

            {{-- ── Row 3: Exams ─────────────────────────────────────── --}}
            <div class="filter-section mb-3">
                <div class="filter-section-title">
                    <i class="bi bi-journal-text text-primary"></i>
                    Exams
                    <span class="text-muted fw-normal">(leave empty to omit exam details)</span>
                </div>
                @if($exams->isEmpty())
                    <p class="text-muted mb-0" style="font-size:0.83rem">
                        <i class="bi bi-info-circle me-1"></i>No published or approved exams found.
                    </p>
                @else
                <div class="cb-grid">
                    @foreach($exams as $exam)
                    <label class="cb-item">
                        <input type="checkbox"
                               name="exam_ids[]"
                               value="{{ $exam->id }}"
                               class="filter-check"
                               data-group="exams"
                               data-label="{{ $exam->title }}"
                               {{ in_array($exam->id, old('exam_ids', [])) ? 'checked' : '' }}>
                        <span>
                            {{ $exam->title }}
                            <small class="d-block text-muted" style="font-size:0.7rem">{{ $exam->course?->title }}</small>
                        </span>
                    </label>
                    @endforeach
                </div>
                @endif
                @error('exam_ids')<div class="text-danger mt-1" style="font-size:0.8rem">{{ $message }}</div>@enderror
            </div>

            {{-- ── Row 4: Audience Filters ───────────────────────────── --}}
            <div class="row g-3 mb-3">
                {{-- Academic Year --}}
                <div class="col-md-4">
                    <div class="filter-section h-100">
                        <div class="filter-section-title">
                            <i class="bi bi-mortarboard text-primary"></i>Academic Year
                            <span class="text-muted fw-normal">(all if none selected)</span>
                        </div>
                        @if($academicYears->isEmpty())
                            <p class="text-muted mb-0" style="font-size:0.82rem">No academic years found.</p>
                        @else
                        <div class="d-flex flex-column gap-2">
                            @foreach($academicYears as $ay)
                            <label class="cb-item">
                                <input type="checkbox"
                                       name="filter_academic_years[]"
                                       value="{{ $ay->id }}"
                                       class="filter-check"
                                       data-group="academic_years"
                                       data-label="{{ $ay->name }}"
                                       {{ in_array($ay->id, old('filter_academic_years', [])) ? 'checked' : '' }}>
                                <span>
                                    {{ $ay->name }}
                                    @if($ay->is_current)
                                        <span class="badge bg-success ms-1" style="font-size:0.62rem">Current</span>
                                    @endif
                                </span>
                            </label>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Year Level --}}
                <div class="col-md-4">
                    <div class="filter-section h-100">
                        <div class="filter-section-title">
                            <i class="bi bi-layers text-primary"></i>Year Level
                            <span class="text-muted fw-normal">(all if none selected)</span>
                        </div>
                        @if($yearLevels->isEmpty())
                            <p class="text-muted mb-0" style="font-size:0.82rem">No year levels found.</p>
                        @else
                        <div class="d-flex flex-column gap-2">
                            @foreach($yearLevels as $yl)
                            <label class="cb-item">
                                <input type="checkbox"
                                       name="filter_year_levels[]"
                                       value="{{ $yl->id }}"
                                       class="filter-check"
                                       data-group="year_levels"
                                       data-label="{{ $yl->name }}"
                                       {{ in_array($yl->id, old('filter_year_levels', [])) ? 'checked' : '' }}>
                                <span>{{ $yl->name }}</span>
                            </label>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Major --}}
                <div class="col-md-4">
                    <div class="filter-section h-100">
                        <div class="filter-section-title">
                            <i class="bi bi-diagram-3 text-primary"></i>Major
                            <span class="text-muted fw-normal">(all if none selected)</span>
                        </div>
                        @if($majors->isEmpty())
                            <p class="text-muted mb-0" style="font-size:0.82rem">No majors found.</p>
                        @else
                        <div class="d-flex flex-column gap-2">
                            @foreach($majors as $major)
                            <label class="cb-item">
                                <input type="checkbox"
                                       name="filter_majors[]"
                                       value="{{ $major->id }}"
                                       class="filter-check"
                                       data-group="majors"
                                       data-label="{{ $major->name }}"
                                       {{ in_array($major->id, old('filter_majors', [])) ? 'checked' : '' }}>
                                <span>{{ $major->name }} <small class="text-muted">({{ $major->code }})</small></span>
                            </label>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ── Filter Preview ────────────────────────────────────── --}}
            <div class="mb-4 p-3" style="background:#f0f4ff;border:1.5px solid #c7d2fe;border-radius:10px">
                <div style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:8px">
                    <i class="bi bi-funnel me-1"></i>Selected Filters Preview
                </div>
                <div id="filter-preview">
                    <span class="text-muted" style="font-size:0.82rem" id="no-filter-msg">
                        No filters selected — notification will be sent to all active students.
                    </span>
                </div>
            </div>

            {{-- ── Submit ────────────────────────────────────────────── --}}
            <div class="d-flex align-items-center gap-3">
                <button type="submit" class="btn btn-primary btn-sm px-4">
                    <i class="bi bi-calendar-check me-2"></i>Save Notification Schedule
                </button>
                <button type="reset" class="btn btn-outline-secondary btn-sm" id="resetBtn">
                    <i class="bi bi-x-circle me-1"></i>Reset
                </button>
                <small class="text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    Recipients are resolved dynamically at send time from active student records.
                </small>
            </div>

        </form>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════════
     SECTION 2 — Scheduled Notifications List
     ══════════════════════════════════════════════════════════════════════ --}}
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span>
            <i class="bi bi-calendar-check me-2"></i>Scheduled Notifications
        </span>
        <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">
            {{ $scheduled->total() }} total
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0" style="font-size:0.845rem">
                <thead style="background:#f8f9fc">
                    <tr>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 1rem;border-bottom:1.5px solid #e8eaf2">Name</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Type</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Audience</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Send At</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Status</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Created By</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($scheduled as $item)
                    <tr>
                        {{-- Name --}}
                        <td style="padding:0.7rem 1rem;font-weight:600;color:#111827">
                            {{ $item->name }}
                        </td>

                        {{-- Notification Type --}}
                        <td style="padding:0.7rem 0.75rem">
                            @php
                                $typeColors = ['exam_time'=>['bg'=>'#eef2ff','color'=>'#2d27a0'],'exam_policy'=>['bg'=>'#fffbeb','color'=>'#92400e'],'exam_reminder'=>['bg'=>'#f0fdf4','color'=>'#059669']];
                                $tc = $typeColors[$item->notification_type] ?? ['bg'=>'#f3f4f6','color'=>'#6b7280'];
                            @endphp
                            <span class="badge-type"
                                  style="background:{{ $tc['bg'] }};color:{{ $tc['color'] }}">
                                {{ \App\Models\ScheduledEmail::$notificationTypes[$item->notification_type] ?? $item->notification_type }}
                            </span>
                        </td>

                        {{-- Audience Filter Summary --}}
                        <td style="padding:0.7rem 0.75rem;color:#6b7280;font-size:0.8rem">
                            {{ $item->filter_summary }}
                        </td>

                        {{-- Send At --}}
                        <td style="padding:0.7rem 0.75rem;color:#374151;white-space:nowrap">
                            {{ $item->send_at->format('d M Y') }}<br>
                            <small class="text-muted">{{ $item->send_at->format('H:i') }}</small>
                            @if(!$item->is_sent && $item->send_at->isPast())
                            <span style="font-size:0.68rem;color:#dc2626;display:block;font-weight:700">⚠ Overdue</span>
                            @endif
                        </td>

                        {{-- Status --}}
                        <td style="padding:0.7rem 0.75rem">
                            @if($item->is_sent)
                                <span class="badge-sent">✓ Sent</span>
                                @if($item->sent_at)
                                <div style="font-size:0.7rem;color:#9ca3af;margin-top:2px">
                                    {{ $item->sent_at->format('d M H:i') }}
                                </div>
                                @endif
                            @else
                                <span class="badge-pending">◷ Pending</span>
                            @endif
                        </td>

                        {{-- Created By --}}
                        <td style="padding:0.7rem 0.75rem;color:#6b7280;font-size:0.8rem">
                            {{ $item->creator?->name ?? '—' }}
                        </td>

                        {{-- Actions --}}
                        <td style="padding:0.7rem 0.75rem">
                            @if(!$item->is_sent)
                            <form action="{{ route('admin.email.scheduled.destroy', $item) }}"
                                  method="POST"
                                  class="d-inline"
                                  onsubmit="return confirm('Cancel scheduled notification \'{{ addslashes($item->name) }}\'?\nThis action cannot be undone.')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="Cancel notification">
                                    <i class="bi bi-x-circle"></i>
                                </button>
                            </form>
                            @else
                            <span class="text-muted" style="font-size:0.75rem" title="Sent at {{ $item->sent_at?->format('d M Y H:i') }}">
                                <i class="bi bi-check2-circle text-success me-1"></i>Delivered
                            </span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-calendar-x d-block mb-2" style="font-size:2rem;opacity:0.3"></i>
                            No scheduled notifications yet. Create one above.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($scheduled->hasPages())
        <div class="px-3 py-2 border-top d-flex align-items-center justify-content-between flex-wrap gap-2"
             style="background:#fafbff">
            <span style="font-size:0.78rem;color:#6b7280">
                Showing <strong>{{ $scheduled->firstItem() }}</strong>–<strong>{{ $scheduled->lastItem() }}</strong>
                of <strong>{{ $scheduled->total() }}</strong>
            </span>
            {{ $scheduled->links() }}
        </div>
        @endif
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    // ── Combine date + time → send_at hidden field ───────────────────────
    const dateInput = document.getElementById('schedule_date');
    const timeInput = document.getElementById('schedule_time');
    const sendAt    = document.getElementById('send_at_combined');

    function updateSendAt() {
        const d = dateInput.value;
        const t = timeInput.value || '08:00';
        if (d) {
            sendAt.value = d + ' ' + t + ':00';
        }
    }

    dateInput?.addEventListener('change', updateSendAt);
    timeInput?.addEventListener('change', updateSendAt);
    updateSendAt(); // set on page load

    // ── Filter preview pills ─────────────────────────────────────────────
    const preview     = document.getElementById('filter-preview');
    const noFilterMsg = document.getElementById('no-filter-msg');

    const groupConfig = {
        academic_years : { color: '#2d27a0', bg: '#eef2ff', icon: '🎓' },
        year_levels    : { color: '#0369a1', bg: '#e0f2fe', icon: '📚' },
        majors         : { color: '#7c3aed', bg: '#f5f3ff', icon: '🏛' },
        exams          : { color: '#059669', bg: '#f0fdf4', icon: '📝' },
    };

    function buildPreviews() {
        const checks = document.querySelectorAll('.filter-check:checked');

        // Clear existing pills (not the no-filter message)
        preview.querySelectorAll('.preview-pill').forEach(p => p.remove());

        if (checks.length === 0) {
            noFilterMsg.style.display = '';
            return;
        }

        noFilterMsg.style.display = 'none';

        checks.forEach(cb => {
            const group  = cb.dataset.group;
            const label  = cb.dataset.label;
            const config = groupConfig[group] || { color: '#374151', bg: '#f3f4f6', icon: '•' };

            const pill = document.createElement('span');
            pill.className = 'preview-pill';
            pill.style.background = config.bg;
            pill.style.color      = config.color;
            pill.innerHTML = config.icon + ' ' + label;
            preview.appendChild(pill);
        });
    }

    document.querySelectorAll('.filter-check').forEach(cb => {
        cb.addEventListener('change', buildPreviews);
    });

    buildPreviews(); // run on page load (in case of old() values)

    // ── Reset button also clears preview ────────────────────────────────
    document.getElementById('resetBtn')?.addEventListener('click', function () {
        setTimeout(buildPreviews, 10);
    });

    // ── Form submit: validate send_at is in the future ───────────────────
    document.getElementById('scheduleForm')?.addEventListener('submit', function (e) {
        updateSendAt();
        const val = sendAt.value;
        if (!val) {
            e.preventDefault();
            alert('Please set a valid schedule date and time.');
            return;
        }
        const sendDate = new Date(val);
        if (sendDate <= new Date()) {
            e.preventDefault();
            alert('Schedule date and time must be in the future.');
        }
    });
})();
</script>
@endpush
