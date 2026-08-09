@extends('layouts.app')
@section('title', 'Compose Email')
@section('page-title', 'Compose Email')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Compose'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@push('styles')
<style>
.compose-mode-tabs { display:flex;gap:0;margin-bottom:1.75rem;border-bottom:2px solid #e2e8f0; }
.compose-mode-tab { padding:.55rem 1.25rem;font-size:.83rem;font-weight:600;color:#6b7280;cursor:pointer;border:none;background:none;border-bottom:2px solid transparent;margin-bottom:-2px;transition:color .15s,border-color .15s; }
.compose-mode-tab.active { color:var(--blc-royal,#2d27a0);border-bottom-color:var(--blc-royal,#2d27a0); }
.compose-mode-tab:hover:not(.active) { color:#374151; }
.compose-mode-panel { display:none; }
.compose-mode-panel.active { display:block; }
.compose-steps { display:flex;align-items:center;gap:0;margin-bottom:1.75rem; }
.compose-step { display:flex;align-items:center;gap:.5rem;font-size:.78rem;font-weight:600;color:#9ca3af; }
.compose-step.active { color:var(--blc-royal,#2d27a0); }
.compose-step.done   { color:#16a34a; }
.step-num { width:26px;height:26px;border-radius:50%;border:2px solid #e2e8f0;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:800;background:#fff;flex-shrink:0; }
.compose-step.active .step-num { border-color:var(--blc-royal,#2d27a0);color:var(--blc-royal,#2d27a0);background:#eef2ff; }
.compose-step.done   .step-num { border-color:#16a34a;color:#fff;background:#16a34a; }
.step-divider { flex:1;height:2px;background:#e2e8f0;margin:0 .5rem;min-width:24px;max-width:48px; }
.var-chip { display:inline-flex;align-items:center;gap:4px;font-size:.72rem;font-weight:700;padding:2px 8px;border-radius:4px;font-family:monospace; }
.var-chip.auto   { background:#f0fdf4;color:#166534;border:1px solid #bbf7d0; }
.var-chip.manual { background:#fef3c7;color:#92400e;border:1px solid #fde68a; }
#previewFrame { width:100%;border:none;min-height:480px;display:block;border-radius:0 0 10px 10px; }
.preview-subject-bar { background:#f8f9fc;border:1px solid #e2e8f0;border-bottom:none;border-radius:10px 10px 0 0;padding:.6rem 1rem;font-size:.83rem; }
.preview-subject-bar strong { color:#1a2540; }
.compose-panel { display:none; }
.compose-panel.active { display:block; }
.field-section-label { font-size:.69rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.07em;margin-bottom:.6rem; }

/* ── Timetable Notification Panel ── */
#ttStepLblWrap { display:flex;align-items:center;gap:0;margin-bottom:1.75rem; }
.tt-step { display:flex;align-items:center;gap:.5rem;font-size:.78rem;font-weight:600;color:#9ca3af; }
.tt-step.active { color:var(--blc-royal,#2d27a0); }
.tt-step.done   { color:#16a34a; }
.tt-step .step-num { width:26px;height:26px;border-radius:50%;border:2px solid #e2e8f0;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:800;background:#fff;flex-shrink:0; }
.tt-step.active .step-num { border-color:var(--blc-royal,#2d27a0);color:var(--blc-royal,#2d27a0);background:#eef2ff; }
.tt-step.done   .step-num { border-color:#16a34a;color:#fff;background:#16a34a; }
.tt-panel { display:none; }
.tt-panel.active { display:block; }
.exam-check-row { display:flex;align-items:flex-start;gap:10px;padding:10px 12px;border-radius:8px;border:1.5px solid #e2e8f0;margin-bottom:8px;cursor:pointer;transition:border-color .15s,background .15s; }
.exam-check-row:hover { border-color:#c7d2fe;background:#f7f9ff; }
.exam-check-row.selected { border-color:#2d27a0;background:#eef2ff; }
.exam-check-row input[type="checkbox"] { margin-top:2px;flex-shrink:0; }
.exam-meta { font-size:.75rem;color:#6b7280;margin-top:3px;display:flex;flex-wrap:wrap;gap:10px; }
.exam-meta span { white-space:nowrap; }
.exam-meta strong { color:#1a2540; }
#ttPreviewFrame { width:100%;border:none;min-height:520px;display:block;border-radius:0 0 10px 10px; }
</style>
@endpush

@section('content')

<script id="templateData" type="application/json">
{}
</script>
<script id="ttFilterData" type="application/json">
{!!
    json_encode([
        'academicYears' => $academicYears->map(fn($y) => ['id' => $y->id, 'name' => $y->name])->values(),
        'yearLevels'    => $yearLevels->map(fn($y) => ['id' => $y->id, 'name' => $y->name, 'level' => $y->level])->values(),
        'majors'        => $majors->map(fn($m) => ['id' => $m->id, 'name' => $m->name])->values(),
    ])
!!}
</script>

<div style="max-width:800px">

{{-- ── Mode tabs ── --}}
<div class="compose-mode-tabs">
    <button class="compose-mode-tab active" id="tabCustom" onclick="switchComposeMode('custom')">
        <i class="bi bi-pencil-square me-1"></i> Custom Message
    </button>
    <button class="compose-mode-tab" id="tabTimetable" onclick="switchComposeMode('timetable')">
        <i class="bi bi-calendar2-week me-1"></i> Exam Timetable Notification
    </button>
</div>

{{-- ════ CUSTOM MESSAGE PANEL ════ --}}
<div class="compose-mode-panel active" id="panelCustomMode">
<div class="card">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-pencil-square" style="color:var(--blc-royal,#2d27a0)"></i>
        Single Recipient — Custom Message
    </div>
    <div class="card-body">
        @if(session('success'))
        <div class="alert alert-success d-flex gap-2 align-items-center mb-3" style="font-size:.84rem">
            <i class="bi bi-check-circle-fill"></i><span>{{ session('success') }}</span>
        </div>
        @endif
        @if($errors->any())
        <div class="alert alert-danger mb-3" style="font-size:.83rem">
            <ul class="mb-0 ps-3">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
        </div>
        @endif
        <form method="POST" action="{{ route('admin.email.compose.custom') }}" id="customSendForm">
            @csrf
            <div class="mb-3">
                <label class="form-label fw-semibold" style="font-size:.82rem">
                    To — Email Address <span class="text-danger">*</span>
                </label>
                <input type="email" name="to_email"
                       class="form-control @error('to_email') is-invalid @enderror"
                       placeholder="recipient@example.com"
                       value="{{ old('to_email') }}" required>
                @error('to_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold" style="font-size:.82rem">
                    Subject <span class="text-danger">*</span>
                </label>
                <input type="text" name="subject"
                       class="form-control @error('subject') is-invalid @enderror"
                       placeholder="e.g. Important Reminder"
                       value="{{ old('subject') }}" maxlength="255" required>
                @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold" style="font-size:.82rem">
                    Message Body <span class="text-danger">*</span>
                </label>
                <textarea name="body" rows="10"
                          class="form-control @error('body') is-invalid @enderror"
                          placeholder="Write your message here…" required>{{ old('body') }}</textarea>
                @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text" style="font-size:.75rem;margin-top:.4rem">
                    Plain text. Line breaks are preserved. Wrapped in Believe Learning Center branded template.
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <button type="submit" class="btn btn-primary" id="btnCustomSend">
                    <i class="bi bi-send me-1"></i> Send Email
                </button>
                <span id="customSpinner" style="display:none;font-size:.83rem" class="text-muted">
                    <span class="spinner-border spinner-border-sm me-1"></span> Queuing&hellip;
                </span>
                <span style="font-size:.78rem;color:#9ca3af">
                    <i class="bi bi-shield-check me-1"></i> Queued via SMTP &mdash; saved to email logs
                </span>
            </div>
        </form>
    </div>
</div>
</div>{{-- /panelCustomMode --}}

{{-- ════ EXAM TIMETABLE NOTIFICATION PANEL ════ --}}
<div class="compose-mode-panel" id="panelTimetableMode">

{{-- Step indicators --}}
<div id="ttStepLblWrap">
    <div class="tt-step active" id="ttStepLbl1"><div class="step-num">1</div><span>Academic Group &amp; Filters</span></div>
    <div class="step-divider"></div>
    <div class="tt-step" id="ttStepLbl2"><div class="step-num">2</div><span>Select Exams</span></div>
    <div class="step-divider"></div>
    <div class="tt-step" id="ttStepLbl3"><div class="step-num">3</div><span>Policy &amp; Instructions</span></div>
    <div class="step-divider"></div>
    <div class="tt-step" id="ttStepLbl4"><div class="step-num">4</div><span>Preview &amp; Send</span></div>
</div>

{{-- ── TT STEP 1: Academic Group Filters ── --}}
<div class="tt-panel active" id="ttPanel1">
<div class="card">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-filter-square" style="color:var(--blc-royal,#2d27a0)"></i>
        Step 1 — Select Academic Group
    </div>
    <div class="card-body">
        @if(session('error') || $errors->any())
        <div class="alert alert-danger mb-3" style="font-size:.83rem">
            @if(session('error')){{ session('error') }}@endif
            @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
        </div>
        @endif

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label fw-semibold" style="font-size:.82rem">
                    Academic Year <span class="text-danger">*</span>
                </label>
                <select id="ttAcademicYear" class="form-select">
                    <option value="">— Select Academic Year —</option>
                    @foreach($academicYears as $ay)
                    <option value="{{ $ay->id }}">{{ $ay->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold" style="font-size:.82rem">
                    Year Level <span class="text-danger">*</span>
                </label>
                <select id="ttYearLevel" class="form-select">
                    <option value="">— Select Year Level —</option>
                    @foreach($yearLevels as $yl)
                    <option value="{{ $yl->id }}">{{ $yl->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold" style="font-size:.82rem">
                    Major
                    <span class="text-muted fw-normal" style="font-size:.75rem">(required for Year 2+)</span>
                </label>
                <select id="ttMajor" class="form-select">
                    <option value="">— No Major (First Year) —</option>
                    @foreach($majors as $major)
                    <option value="{{ $major->id }}">{{ $major->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold" style="font-size:.82rem">
                    Semester <span class="text-danger">*</span>
                </label>
                <select id="ttSemester" class="form-select">
                    <option value="">— Select Semester —</option>
                    <option value="1">Semester 1</option>
                    <option value="2">Semester 2</option>
                </select>
            </div>
        </div>

        <button type="button" class="btn btn-primary" id="btnTtStep1Next">
            Load Exam Schedules <i class="bi bi-arrow-right ms-1"></i>
        </button>
        <span id="ttStep1Spinner" style="display:none;font-size:.83rem" class="text-muted ms-2">
            <span class="spinner-border spinner-border-sm me-1"></span> Loading…
        </span>
        <div id="ttStep1Error" class="text-danger mt-2" style="font-size:.83rem"></div>
    </div>
</div>
</div>{{-- /ttPanel1 --}}

{{-- ── TT STEP 2: Select Exam Schedules ── --}}
<div class="tt-panel" id="ttPanel2">
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span>
            <i class="bi bi-calendar2-check" style="color:var(--blc-royal,#2d27a0)"></i>
            Step 2 — Select Exam Schedules
        </span>
        <div style="font-size:.78rem;color:#6b7280" id="ttGroupSummary"></div>
    </div>
    <div class="card-body">
        <div id="ttScheduleList">
            {{-- Populated via AJAX --}}
        </div>
        <div id="ttNoSchedules" style="display:none" class="text-center py-4">
            <i class="bi bi-calendar2-x d-block mb-2" style="font-size:2rem;color:#d1d5db"></i>
            <div style="font-size:.88rem;color:#6b7280">
                No published or approved exam schedules found for this academic group.
            </div>
        </div>

        <div class="d-flex gap-2 mt-3" id="ttStep2Actions" style="display:none">
            <button type="button" class="btn btn-outline-secondary" id="btnTtStep2Back">
                <i class="bi bi-arrow-left me-1"></i> Back
            </button>
            <button type="button" class="btn btn-primary" id="btnTtStep2Next">
                Continue <i class="bi bi-arrow-right ms-1"></i>
            </button>
        </div>
        <div id="ttStep2Error" class="text-danger mt-2" style="font-size:.83rem"></div>
    </div>
</div>
</div>{{-- /ttPanel2 --}}

{{-- ── TT STEP 3: Policy & Instructions ── --}}
<div class="tt-panel" id="ttPanel3">
<div class="card">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-file-text" style="color:var(--blc-royal,#2d27a0)"></i>
        Step 3 — Exam Policy &amp; Additional Instructions
        <span class="text-muted fw-normal ms-1" style="font-size:.77rem">(optional)</span>
    </div>
    <div class="card-body">
        <div class="mb-4">
            <label class="form-label fw-semibold" style="font-size:.82rem">Exam Policy</label>
            <textarea id="ttExamPolicy" rows="6" class="form-control"
                placeholder="e.g.&#10;- Fullscreen Required&#10;- No Tab Switching&#10;- No Copy/Paste&#10;- Mobile devices must be turned off"></textarea>
            <div class="form-text" style="font-size:.75rem;margin-top:.4rem">
                Displayed as a policy section in the email. Leave blank to omit.
            </div>
        </div>
        <div class="mb-4">
            <label class="form-label fw-semibold" style="font-size:.82rem">Additional Instructions</label>
            <textarea id="ttInstructions" rows="5" class="form-control"
                placeholder="e.g. Please bring your student ID. Log in 10 minutes before the exam starts."></textarea>
            <div class="form-text" style="font-size:.75rem;margin-top:.4rem">
                Displayed as an instructions section in the email. Leave blank to omit.
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary" id="btnTtStep3Back">
                <i class="bi bi-arrow-left me-1"></i> Back
            </button>
            <button type="button" class="btn btn-primary" id="btnTtStep3Preview">
                <i class="bi bi-eye me-1"></i> Preview Email
            </button>
            <span id="ttStep3Spinner" style="display:none;font-size:.83rem" class="text-muted ms-1">
                <span class="spinner-border spinner-border-sm me-1"></span> Loading…
            </span>
        </div>
        <div id="ttStep3Error" class="text-danger mt-2" style="font-size:.83rem"></div>
    </div>
</div>
</div>{{-- /ttPanel3 --}}

{{-- ── TT STEP 4: Preview & Send ── --}}
<div class="tt-panel" id="ttPanel4">
<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-eye me-2" style="color:var(--blc-royal,#2d27a0)"></i>Step 4 — Preview &amp; Send</span>
        <span id="ttPreviewBadge" class="badge" style="background:#eef2ff;color:#3730a3;font-size:.75rem"></span>
    </div>
    <div class="card-body pb-2">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="field-section-label">Academic Group</div>
                <div id="ttPreviewGroup" style="font-size:.87rem;color:#374151;font-weight:600"></div>
            </div>
            <div class="col-md-6">
                <div class="field-section-label">Exams Selected</div>
                <div id="ttPreviewExamCount" style="font-size:.87rem;color:#374151;font-weight:600"></div>
            </div>
        </div>
        <div style="font-size:.75rem;color:#9ca3af;margin-top:.6rem">
            <i class="bi bi-info-circle me-1"></i>
            Showing sample preview — each recipient's name will be personalised.
        </div>
    </div>
    <div class="preview-subject-bar">
        <strong>Subject:</strong>
        <span id="ttPreviewSubject" style="color:#374151;margin-left:.4rem"></span>
    </div>
    <iframe id="ttPreviewFrame" title="Exam Timetable Email Preview" sandbox="allow-same-origin"></iframe>
</div>
<div class="card">
    <div class="card-body">
        {{-- The actual send form --}}
        <form method="POST" action="{{ route('admin.email.timetable.send') }}" id="ttSendForm">
            @csrf
            <input type="hidden" name="academic_year_id"        id="ttHiddenAcademicYear">
            <input type="hidden" name="year_level_id"           id="ttHiddenYearLevel">
            <input type="hidden" name="major_id"                id="ttHiddenMajor">
            <input type="hidden" name="semester"                id="ttHiddenSemester">
            <div id="ttHiddenScheduleIds">{{-- schedule_ids[] inputs injected by JS --}}</div>
            <input type="hidden" name="exam_policy"             id="ttHiddenPolicy">
            <input type="hidden" name="additional_instructions" id="ttHiddenInstructions">

            <div class="d-flex gap-2 align-items-center">
                <button type="button" class="btn btn-outline-secondary" id="btnTtStep4Back">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </button>
                <button type="submit" class="btn btn-primary" id="ttBtnSend">
                    <i class="bi bi-send me-1"></i> Send Timetable Notification
                </button>
                <span id="ttSendSpinner" style="display:none;font-size:.83rem" class="text-muted">
                    <span class="spinner-border spinner-border-sm me-1"></span> Queuing…
                </span>
            </div>
            <div style="font-size:.76rem;color:#9ca3af;margin-top:.5rem">
                <i class="bi bi-shield-check me-1"></i>
                One email per student — sent individually via SMTP queue.
            </div>
        </form>
    </div>
</div>
</div>{{-- /ttPanel4 --}}

</div>{{-- /panelTimetableMode --}}

</div>{{-- /max-width wrapper --}}
@endsection

@push('scripts')
<script>window._COMPOSE_CUSTOM_URL         = @json(route('admin.email.compose.custom'));
window._COMPOSE_CUSTOM_PREVIEW_URL = @json(route('admin.email.compose.custom.preview'));
window._TT_SCHEDULES_URL           = @json(route('admin.email.timetable.schedules'));
window._TT_PREVIEW_URL             = @json(route('admin.email.timetable.preview'));
window._APP_NAME                   = @json(config('app.name'));</script>
<script>
(function () {
'use strict';
var CSRF               = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
var CUSTOM_URL         = window._COMPOSE_CUSTOM_URL;
var CUSTOM_PREVIEW_URL = window._COMPOSE_CUSTOM_PREVIEW_URL;

// Spinner for custom send form
var _cf = document.getElementById('customSendForm');
if (_cf) {
    _cf.addEventListener('submit', function() {
        var b = document.getElementById('btnCustomSend');
        var s = document.getElementById('customSpinner');
        if (b) { b.disabled = true; b.style.display = 'none'; }
        if (s) { s.style.display = ''; }
    });
}
})();
</script>
<script>
function switchComposeMode(mode) {
    document.getElementById('panelCustomMode').classList.toggle('active',   mode === 'custom');
    document.getElementById('panelTimetableMode').classList.toggle('active', mode === 'timetable');
    document.getElementById('tabCustom').classList.toggle('active',   mode === 'custom');
    document.getElementById('tabTimetable').classList.toggle('active', mode === 'timetable');
}
(function() {
    var hasErr = document.querySelector('#panelCustomMode .is-invalid, #panelCustomMode .alert-danger');
    if (hasErr) switchComposeMode('custom');
})();
</script>
<script>
/* ══════════════════════════════════════════════════════════════════
   EXAM TIMETABLE NOTIFICATION — Step logic
   ══════════════════════════════════════════════════════════════════ */
(function () {
'use strict';

var CSRF              = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
var SCHEDULES_URL     = window._TT_SCHEDULES_URL;
var PREVIEW_URL       = window._TT_PREVIEW_URL;

// ── State ─────────────────────────────────────────────────────────
var ttState = {
    academicYearId : '',
    yearLevelId    : '',
    majorId        : '',
    semester       : '',
    schedules      : [],   // all loaded schedules from AJAX
    selectedIds    : [],   // checked schedule IDs
    examPolicy     : '',
    instructions   : '',
};

// ── Panel references ──────────────────────────────────────────────
var ttPanels = [null,
    document.getElementById('ttPanel1'),
    document.getElementById('ttPanel2'),
    document.getElementById('ttPanel3'),
    document.getElementById('ttPanel4'),
];
var ttStepLbls = [null,
    document.getElementById('ttStepLbl1'),
    document.getElementById('ttStepLbl2'),
    document.getElementById('ttStepLbl3'),
    document.getElementById('ttStepLbl4'),
];

function ttShowStep(n) {
    // Panels and step labels are 1-indexed (index 0 slot is null placeholder)
    ttPanels.forEach(function(p, i) {
        if (p) p.classList.toggle('active', i === n);
    });
    ttStepLbls.forEach(function(el, i) {
        if (!el) return;
        el.classList.remove('active', 'done');
        if (i === n)      el.classList.add('active');
        else if (i < n)   el.classList.add('done');
        var num = el.querySelector('.step-num');
        if (!num) return;
        if (i < n) {
            num.innerHTML = '<i class="bi bi-check2" style="font-size:.85rem"></i>';
        } else {
            num.textContent = String(i);
        }
    });
}

// ── Helpers ───────────────────────────────────────────────────────
function ttGroupLabel() {
    var ay  = document.getElementById('ttAcademicYear');
    var yl  = document.getElementById('ttYearLevel');
    var maj = document.getElementById('ttMajor');
    var sem = document.getElementById('ttSemester');
    var parts = [];
    if (ay.value)  parts.push(ay.options[ay.selectedIndex].text);
    if (yl.value)  parts.push(yl.options[yl.selectedIndex].text);
    if (maj.value) parts.push(maj.options[maj.selectedIndex].text);
    if (sem.value) parts.push('Semester ' + sem.value);
    return parts.join(' · ');
}

// ── STEP 1 → 2 ───────────────────────────────────────────────────
document.getElementById('btnTtStep1Next').addEventListener('click', async function () {
    var err = document.getElementById('ttStep1Error');
    err.textContent = '';

    var academicYearId = document.getElementById('ttAcademicYear').value;
    var yearLevelId    = document.getElementById('ttYearLevel').value;
    var majorId        = document.getElementById('ttMajor').value;
    var semester       = document.getElementById('ttSemester').value;

    if (!academicYearId) { err.textContent = 'Please select an Academic Year.'; return; }
    if (!yearLevelId)    { err.textContent = 'Please select a Year Level.'; return; }
    if (!semester)       { err.textContent = 'Please select a Semester.'; return; }

    ttState.academicYearId = academicYearId;
    ttState.yearLevelId    = yearLevelId;
    ttState.majorId        = majorId;
    ttState.semester       = semester;

    var btn = document.getElementById('btnTtStep1Next');
    var spinner = document.getElementById('ttStep1Spinner');
    btn.disabled = true; spinner.style.display = '';

    try {
        var params = new URLSearchParams({
            academic_year_id : academicYearId,
            year_level_id    : yearLevelId,
            semester         : semester,
        });
        if (majorId) params.set('major_id', majorId);

        var resp = await fetch(SCHEDULES_URL + '?' + params.toString(), {
            headers : { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }
        });

        if (!resp.ok) {
            var e = await resp.json().catch(function(){return{};});
            err.textContent = e.message || 'Failed to load schedules.';
            return;
        }

        var data = await resp.json();
        ttState.schedules   = data.schedules || [];
        ttState.selectedIds = [];

        // Update group summary in step 2 header
        document.getElementById('ttGroupSummary').textContent = ttGroupLabel();

        // Render schedule checkboxes
        var list = document.getElementById('ttScheduleList');
        var none = document.getElementById('ttNoSchedules');
        var acts = document.getElementById('ttStep2Actions');

        list.innerHTML = '';

        if (ttState.schedules.length === 0) {
            none.style.display = '';
            acts.style.removeProperty('display');
            acts.style.display = 'flex';
            // Show only Back in step 2 actions when no schedules
            document.getElementById('btnTtStep2Next').style.display = 'none';
        } else {
            none.style.display = 'none';
            document.getElementById('btnTtStep2Next').style.display = '';
            acts.style.removeProperty('display');
            acts.style.display = 'flex';

            // Select all / Deselect all
            list.insertAdjacentHTML('beforeend',
                '<div class="d-flex gap-2 mb-2 align-items-center">'
                + '<button type="button" class="btn btn-sm btn-outline-primary" id="ttBtnSelectAll">Select All</button>'
                + '<button type="button" class="btn btn-sm btn-outline-secondary" id="ttBtnDeselectAll">Deselect All</button>'
                + '<span id="ttSelCount" style="font-size:.78rem;color:#6b7280;margin-left:.25rem">0 selected</span>'
                + '</div>');

            ttState.schedules.forEach(function (s) {
                var row = document.createElement('label');
                row.className = 'exam-check-row';
                row.htmlFor   = 'ttSched_' + s.id;
                row.insertAdjacentHTML('beforeend',
                    '<input type="checkbox" id="ttSched_' + s.id + '" value="' + s.id + '" class="tt-sched-cb">'
                    + '<div style="flex:1;min-width:0">'
                    +   '<div style="font-size:.88rem;font-weight:700;color:#1a2540;margin-bottom:2px">' + s.exam_title + '</div>'
                    +   '<div style="font-size:.74rem;color:#6b7280;margin-bottom:8px">' + s.course + '</div>'
                    +   '<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:6px 16px">'
                    +     '<div style="font-size:.74rem">'
                    +       '<div style="font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.04em;font-size:.67rem;margin-bottom:2px">Start</div>'
                    +       '<div style="font-weight:600;color:#2d27a0">' + s.start_datetime + '</div>'
                    +     '</div>'
                    +     '<div style="font-size:.74rem">'
                    +       '<div style="font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.04em;font-size:.67rem;margin-bottom:2px">End</div>'
                    +       '<div style="font-weight:600;color:#2d27a0">' + s.end_datetime + '</div>'
                    +     '</div>'
                    +     '<div style="font-size:.74rem">'
                    +       '<div style="font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.04em;font-size:.67rem;margin-bottom:2px">Allowed Time</div>'
                    +       '<div style="font-weight:600;color:#166534">' + s.allowed_time + ' minutes</div>'
                    +     '</div>'
                    +     '<div style="font-size:.74rem">'
                    +       '<div style="font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.04em;font-size:.67rem;margin-bottom:2px">Attempt Count</div>'
                    +       '<div style="font-weight:600;color:#92400e">' + s.attempt_count + '</div>'
                    +     '</div>'
                    +   '</div>'
                    + '</div>');
                list.appendChild(row);
            });

            // Checkbox listeners
            list.querySelectorAll('.tt-sched-cb').forEach(function (cb) {
                cb.addEventListener('change', function () {
                    var row = this.closest('.exam-check-row');
                    row.classList.toggle('selected', this.checked);
                    ttUpdateSelectedIds();
                });
            });

            document.getElementById('ttBtnSelectAll').addEventListener('click', function () {
                list.querySelectorAll('.tt-sched-cb').forEach(function (cb) {
                    cb.checked = true;
                    cb.closest('.exam-check-row').classList.add('selected');
                });
                ttUpdateSelectedIds();
            });

            document.getElementById('ttBtnDeselectAll').addEventListener('click', function () {
                list.querySelectorAll('.tt-sched-cb').forEach(function (cb) {
                    cb.checked = false;
                    cb.closest('.exam-check-row').classList.remove('selected');
                });
                ttUpdateSelectedIds();
            });
        }

        ttShowStep(2);

    } catch (ex) {
        err.textContent = 'Network error. Please try again.';
    } finally {
        btn.disabled = false; spinner.style.display = 'none';
    }
});

function ttUpdateSelectedIds() {
    ttState.selectedIds = [];
    document.querySelectorAll('.tt-sched-cb:checked').forEach(function (cb) {
        ttState.selectedIds.push(parseInt(cb.value, 10));
    });
    var cntEl = document.getElementById('ttSelCount');
    if (cntEl) cntEl.textContent = ttState.selectedIds.length + ' selected';
}

// ── STEP 2 → 3 ───────────────────────────────────────────────────
document.getElementById('btnTtStep2Next').addEventListener('click', function () {
    var err = document.getElementById('ttStep2Error');
    err.textContent = '';
    if (ttState.selectedIds.length === 0) {
        err.textContent = 'Please select at least one exam schedule.';
        return;
    }
    ttShowStep(3);
});

document.getElementById('btnTtStep2Back').addEventListener('click', function () { ttShowStep(1); });

// ── STEP 3 → 4 (Preview) ─────────────────────────────────────────
document.getElementById('btnTtStep3Preview').addEventListener('click', async function () {
    var err = document.getElementById('ttStep3Error');
    err.textContent = '';

    ttState.examPolicy   = document.getElementById('ttExamPolicy').value;
    ttState.instructions = document.getElementById('ttInstructions').value;

    var btn = document.getElementById('btnTtStep3Preview');
    var spinner = document.getElementById('ttStep3Spinner');
    btn.disabled = true; spinner.style.display = '';

    try {
        var payload = {
            academic_year_id        : ttState.academicYearId,
            year_level_id           : ttState.yearLevelId,
            semester                : ttState.semester,
            schedule_ids            : ttState.selectedIds,
            exam_policy             : ttState.examPolicy,
            additional_instructions : ttState.instructions,
        };
        if (ttState.majorId) payload.major_id = ttState.majorId;

        var resp = await fetch(PREVIEW_URL, {
            method  : 'POST',
            headers : { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body    : JSON.stringify(payload),
        });

        if (!resp.ok) {
            var e = await resp.json().catch(function(){return{};});
            err.textContent = e.message || 'Preview failed. Please try again.';
            return;
        }

        var data = await resp.json();

        // Populate preview panel
        var ay  = document.getElementById('ttAcademicYear');
        var yl  = document.getElementById('ttYearLevel');
        var maj = document.getElementById('ttMajor');
        var sem = document.getElementById('ttSemester');

        document.getElementById('ttPreviewGroup').textContent = ttGroupLabel();
        document.getElementById('ttPreviewExamCount').textContent = ttState.selectedIds.length + ' exam schedule(s)';
        document.getElementById('ttPreviewSubject').textContent =
            '[' + (window._APP_NAME || 'Believe Exam') + '] Examination Time Table — Semester ' + ttState.semester;
        document.getElementById('ttPreviewBadge').textContent = 'Group Send';
        document.getElementById('ttPreviewFrame').srcdoc = data.html;

        // Populate hidden form fields
        document.getElementById('ttHiddenAcademicYear').value = ttState.academicYearId;
        document.getElementById('ttHiddenYearLevel').value    = ttState.yearLevelId;
        document.getElementById('ttHiddenMajor').value        = ttState.majorId || '';
        document.getElementById('ttHiddenSemester').value     = ttState.semester;
        document.getElementById('ttHiddenPolicy').value       = ttState.examPolicy;
        document.getElementById('ttHiddenInstructions').value = ttState.instructions;

        // Inject schedule_ids[] hidden inputs
        var container = document.getElementById('ttHiddenScheduleIds');
        container.innerHTML = '';
        ttState.selectedIds.forEach(function (id) {
            var inp = document.createElement('input');
            inp.type  = 'hidden';
            inp.name  = 'schedule_ids[]';
            inp.value = id;
            container.appendChild(inp);
        });

        ttShowStep(4);

    } catch (ex) {
        err.textContent = 'Network error. Please try again.';
    } finally {
        btn.disabled = false; spinner.style.display = 'none';
    }
});

document.getElementById('btnTtStep3Back').addEventListener('click', function () { ttShowStep(2); });
document.getElementById('btnTtStep4Back').addEventListener('click', function () { ttShowStep(3); });

// ── Send form spinner ─────────────────────────────────────────────
document.getElementById('ttSendForm').addEventListener('submit', function () {
    var btn     = document.getElementById('ttBtnSend');
    var spinner = document.getElementById('ttSendSpinner');
    btn.disabled = true; btn.style.display = 'none'; spinner.style.display = '';
});

// Init at step 1
ttShowStep(1);

})();
</script>
@endpush
