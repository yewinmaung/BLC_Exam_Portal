@extends('layouts.app')
@section('title', 'My Results')
@section('page-title', 'My Results')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Student', 'url' => route('student.dashboard')],
        ['label' => 'My Results'],
    ]])
@endsection
@section('sidebar')@include('partials.student-sidebar')@endsection

@section('content')

{{-- ══ Summary stats ════════════════════════════════════════════════════ --}}
<div class="row g-3 mb-4">
    <div class="col-4">
        <div class="card text-center py-3">
            <div class="mb-1">
                <i class="bi bi-journal-check" style="font-size:1.5rem;color:var(--blc-navy,#0b2a5b)"></i>
            </div>
            <div style="font-size:1.6rem;font-weight:800;color:var(--blc-navy,#0b2a5b)">{{ $totalExams }}</div>
            <div class="text-muted small">Total Exams</div>
        </div>
    </div>
    <div class="col-4">
        <div class="card text-center py-3">
            <div class="mb-1">
                <i class="bi bi-patch-check-fill" style="font-size:1.5rem;color:#166534"></i>
            </div>
            <div style="font-size:1.6rem;font-weight:800;color:#166534">{{ $passedCount }}</div>
            <div class="text-muted small">Passed</div>
        </div>
    </div>
    <div class="col-4">
        <div class="card text-center py-3">
            <div class="mb-1">
                <i class="bi bi-bar-chart-fill" style="font-size:1.5rem;color:var(--blc-navy,#0b2a5b)"></i>
            </div>
            <div style="font-size:1.6rem;font-weight:800;color:var(--blc-navy,#0b2a5b)">{{ $avgPct }}%</div>
            <div class="text-muted small">Avg Score</div>
        </div>
    </div>
</div>

{{-- ══ Empty state ══════════════════════════════════════════════════════ --}}
@if(empty($grouped))
<div class="card">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-bar-chart-line d-block mb-3" style="font-size:3rem;opacity:0.3"></i>
        <h6>No academic records found</h6>
        <p class="small mb-0">Your published exam results will appear here once they are released.</p>
    </div>
</div>
@else

{{-- ══ Per-academic-year groups ════════════════════════════════════════ --}}
@foreach($grouped as $group)
@php
    $record  = $group['record'];
    $sem1    = $group['sem1'];   // Collection<Result>
    $sem2    = $group['sem2'];   // Collection<Result>
    $hasSem1 = $sem1->isNotEmpty();
    $hasSem2 = $sem2->isNotEmpty();
    $ayName  = $record->academicYear->name ?? '—';
    $ylName  = $record->yearLevel->name    ?? '—';
    $ayKey   = 'ay-'.$record->id;

    // Sub-group each semester's results by course
    $groupByCourse = function (\Illuminate\Support\Collection $results) {
        return $results
            ->groupBy(fn ($r) => $r->exam?->course_id ?? 0)
            ->map(fn ($courseResults) => [
                'course'  => $courseResults->first()?->exam?->course,
                'results' => $courseResults->values(),
            ])
            ->values();
    };

    $sem1Courses = $groupByCourse($sem1);
    $sem2Courses = $groupByCourse($sem2);
@endphp

{{-- Always render the academic year card, even if empty --}}
<div class="card mb-3" style="border:none;box-shadow:0 2px 12px rgba(11,42,91,0.10);border-radius:12px;overflow:hidden">

    {{-- ── Academic Year + Year Level header ───────────────────────── --}}
    <div class="d-flex align-items-center gap-2 px-4 py-3"
         style="background:linear-gradient(90deg,#0b2a5b 0%,#1e3a8a 100%);color:#fff;cursor:pointer;user-select:none"
         data-bs-toggle="collapse"
         data-bs-target="#{{ $ayKey }}"
         aria-expanded="true"
         aria-controls="{{ $ayKey }}">
        <i class="bi bi-calendar3-week" style="font-size:1rem;opacity:0.85"></i>
        <span style="font-weight:800;font-size:1rem;letter-spacing:0.01em">{{ $ayName }}</span>
        <span class="badge ms-1"
              style="background:rgba(255,255,255,0.18);color:#fff;font-size:0.75rem;font-weight:600;padding:3px 9px;border-radius:6px">
            {{ $ylName }}
        </span>
        <i class="bi bi-chevron-down ms-auto ay-chevron"
           style="font-size:0.9rem;transition:transform 0.25s"></i>
    </div>

    {{-- ── Year body — collapses ────────────────────────────────────── --}}
    <div id="{{ $ayKey }}" class="collapse show">
        <div class="px-3 pb-3 pt-2" style="background:#fafbff">

        {{-- ── Semester 1 ──────────────────────────────────────────── --}}
        @if($hasSem1)
        @php $s1Key = $ayKey.'-sem1'; @endphp
        <div class="mb-3">

            {{-- Semester 1 label (clickable) --}}
            <div class="d-flex align-items-center gap-2 px-2 py-2 mb-2 rounded"
                 style="background:#ede9fe;cursor:pointer;user-select:none"
                 data-bs-toggle="collapse"
                 data-bs-target="#{{ $s1Key }}"
                 aria-expanded="true">
                <i class="bi bi-bookmark-fill" style="color:#3730a3;font-size:0.85rem"></i>
                <span style="font-weight:700;color:#3730a3;font-size:0.9rem">Semester 1</span>
                <span class="ms-auto text-muted small">
                    {{ $sem1->count() }} exam{{ $sem1->count() !== 1 ? 's' : '' }}
                </span>
                <i class="bi bi-chevron-down sem-chevron"
                   style="font-size:0.8rem;color:#6b7280;transition:transform 0.2s"></i>
            </div>

            <div id="{{ $s1Key }}" class="collapse show">
            @foreach($sem1Courses as $cg)
            @php
                $course   = $cg['course'];
                $cResults = $cg['results'];
                $courseKey = $ayKey.'-s1-c'.($course?->id ?? 0);
            @endphp

                {{-- Course card --}}
                <div class="mb-2 ms-2"
                     style="border:1.5px solid #e2e8f0;border-radius:9px;overflow:hidden;background:#fff">

                    {{-- Course header (clickable) --}}
                    <div class="d-flex align-items-center gap-2 px-3 py-2"
                         style="cursor:pointer;user-select:none;background:#fff"
                         data-bs-toggle="collapse"
                         data-bs-target="#{{ $courseKey }}"
                         aria-expanded="true">
                        <i class="bi bi-book-fill" style="color:var(--blc-navy,#0b2a5b);font-size:0.85rem"></i>
                        <span style="font-weight:700;color:var(--blc-navy,#0b2a5b);font-size:0.88rem">
                            {{ $course?->title ?? '—' }}
                        </span>
                        @if($course?->code)
                        <span class="text-muted small" style="font-size:0.75rem">{{ $course->code }}</span>
                        @endif
                        <span class="ms-auto badge"
                              style="background:#f0f4ff;color:#3730a3;font-size:0.7rem">
                            {{ $cResults->count() }} exam{{ $cResults->count() !== 1 ? 's' : '' }}
                        </span>
                        <i class="bi bi-chevron-down course-chevron"
                           style="font-size:0.75rem;color:#9ca3af;transition:transform 0.2s;margin-left:4px"></i>
                    </div>

                    {{-- Exam list --}}
                    <div id="{{ $courseKey }}" class="collapse show"
                         style="border-top:1px solid #f0f4ff">
                        @foreach($cResults as $r)
                        @php
                            $collapseId = 'rv-'.$r->id;
                            $isPassed   = $r->is_passed;
                            $isDq       = $r->isDisqualified();
                            $statusColor = $isDq ? '#92400e' : ($isPassed ? '#166534' : '#991b1b');
                            $statusBg    = $isDq ? '#fef3c7' : ($isPassed ? '#f0fdf4' : '#fef2f2');
                            $statusText  = $isDq ? 'Disqualified' : ($isPassed ? 'Passed' : 'Failed');
                            $statusBadge = $isDq ? 'bg-warning text-dark' : ($isPassed ? 'bg-success' : 'bg-danger');
                        @endphp

                        {{-- Exam row (click to expand answer review) --}}
                        <div class="px-3 py-2 d-flex align-items-center gap-2 flex-wrap result-exam-row"
                             style="border-top:1px solid #f5f5f5;cursor:pointer;font-size:0.83rem;
                                    background:{{ $statusBg }}08"
                             data-bs-toggle="collapse"
                             data-bs-target="#{{ $collapseId }}"
                             aria-expanded="false">

                            <i class="bi bi-chevron-right exam-chevron"
                               style="font-size:0.75rem;color:#9ca3af;transition:transform 0.2s;flex-shrink:0"></i>

                            {{-- Exam title --}}
                            <span style="font-weight:600;color:#1e293b;flex:1;min-width:120px">
                                {{ $r->exam?->title ?? '—' }}
                            </span>

                            {{-- Score --}}
                            <span style="color:#374151;font-size:0.8rem;white-space:nowrap">
                                <span style="font-weight:700">{{ $r->obtained_marks }}</span>
                                <span class="text-muted">/{{ $r->total_marks }}</span>
                            </span>

                            {{-- Progress bar + % --}}
                            <div class="d-flex align-items-center gap-1" style="min-width:80px">
                                <div style="width:44px;height:4px;background:#e5e7eb;border-radius:2px;overflow:hidden;flex-shrink:0">
                                    <div style="width:{{ min($r->percentage,100) }}%;height:100%;border-radius:2px;
                                                background:{{ $isDq ? '#f59e0b' : ($isPassed ? '#22c55e' : '#ef4444') }}">
                                    </div>
                                </div>
                                <span style="font-size:0.78rem;font-weight:600;color:{{ $statusColor }}">
                                    {{ $r->percentage }}%
                                </span>
                            </div>

                            {{-- Status badge --}}
                            <span class="badge {{ $statusBadge }}" style="font-size:0.7rem">
                                {{ $statusText }}
                            </span>

                            {{-- Date --}}
                            <span class="text-muted" style="font-size:0.72rem;white-space:nowrap">
                                {{ $r->created_at->format('M d, Y') }}
                            </span>
                        </div>

                        {{-- Answer review (collapsed) --}}
                        <div id="{{ $collapseId }}" class="collapse">
                            <div class="px-4 py-3" style="background:#fafbff;border-top:1px solid #ede9fe">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <i class="bi bi-eye-fill" style="color:var(--blc-gold,#d4a51c)"></i>
                                    <span style="font-weight:700;font-size:0.85rem;color:var(--blc-navy,#0b2a5b)">
                                        Answer Review
                                    </span>
                                    <span class="badge ms-auto"
                                          style="background:#f0fdf4;color:#166534;font-size:0.7rem">
                                        Question · Your answer · Correct answer
                                    </span>
                                </div>
                                @include('student.results._answer_review', ['result' => $r])
                            </div>
                        </div>

                        @endforeach
                    </div>{{-- /exam list --}}

                </div>{{-- /course card --}}
            @endforeach
            </div>{{-- /s1Key collapse --}}

        </div>{{-- /semester 1 block --}}
        @endif

        {{-- ── Semester 2 ──────────────────────────────────────────── --}}
        @if($hasSem2)
        @php $s2Key = $ayKey.'-sem2'; @endphp
        <div class="mb-1">

            {{-- Semester 2 label (clickable) --}}
            <div class="d-flex align-items-center gap-2 px-2 py-2 mb-2 rounded"
                 style="background:#fef3c7;cursor:pointer;user-select:none"
                 data-bs-toggle="collapse"
                 data-bs-target="#{{ $s2Key }}"
                 aria-expanded="true">
                <i class="bi bi-bookmark-fill" style="color:#92400e;font-size:0.85rem"></i>
                <span style="font-weight:700;color:#92400e;font-size:0.9rem">Semester 2</span>
                <span class="ms-auto text-muted small">
                    {{ $sem2->count() }} exam{{ $sem2->count() !== 1 ? 's' : '' }}
                </span>
                <i class="bi bi-chevron-down sem-chevron"
                   style="font-size:0.8rem;color:#6b7280;transition:transform 0.2s"></i>
            </div>

            <div id="{{ $s2Key }}" class="collapse show">
            @foreach($sem2Courses as $cg)
            @php
                $course   = $cg['course'];
                $cResults = $cg['results'];
                $courseKey = $ayKey.'-s2-c'.($course?->id ?? 0);
            @endphp

                <div class="mb-2 ms-2"
                     style="border:1.5px solid #e2e8f0;border-radius:9px;overflow:hidden;background:#fff">

                    <div class="d-flex align-items-center gap-2 px-3 py-2"
                         style="cursor:pointer;user-select:none;background:#fff"
                         data-bs-toggle="collapse"
                         data-bs-target="#{{ $courseKey }}"
                         aria-expanded="true">
                        <i class="bi bi-book-fill" style="color:var(--blc-navy,#0b2a5b);font-size:0.85rem"></i>
                        <span style="font-weight:700;color:var(--blc-navy,#0b2a5b);font-size:0.88rem">
                            {{ $course?->title ?? '—' }}
                        </span>
                        @if($course?->code)
                        <span class="text-muted small" style="font-size:0.75rem">{{ $course->code }}</span>
                        @endif
                        <span class="ms-auto badge"
                              style="background:#f0f4ff;color:#3730a3;font-size:0.7rem">
                            {{ $cResults->count() }} exam{{ $cResults->count() !== 1 ? 's' : '' }}
                        </span>
                        <i class="bi bi-chevron-down course-chevron"
                           style="font-size:0.75rem;color:#9ca3af;transition:transform 0.2s;margin-left:4px"></i>
                    </div>

                    <div id="{{ $courseKey }}" class="collapse show"
                         style="border-top:1px solid #f0f4ff">
                        @foreach($cResults as $r)
                        @php
                            $collapseId  = 'rv-'.$r->id;
                            $isPassed    = $r->is_passed;
                            $isDq        = $r->isDisqualified();
                            $statusColor = $isDq ? '#92400e' : ($isPassed ? '#166534' : '#991b1b');
                            $statusBg    = $isDq ? '#fef3c7' : ($isPassed ? '#f0fdf4' : '#fef2f2');
                            $statusText  = $isDq ? 'Disqualified' : ($isPassed ? 'Passed' : 'Failed');
                            $statusBadge = $isDq ? 'bg-warning text-dark' : ($isPassed ? 'bg-success' : 'bg-danger');
                        @endphp

                        <div class="px-3 py-2 d-flex align-items-center gap-2 flex-wrap result-exam-row"
                             style="border-top:1px solid #f5f5f5;cursor:pointer;font-size:0.83rem;
                                    background:{{ $statusBg }}08"
                             data-bs-toggle="collapse"
                             data-bs-target="#{{ $collapseId }}"
                             aria-expanded="false">
                            <i class="bi bi-chevron-right exam-chevron"
                               style="font-size:0.75rem;color:#9ca3af;transition:transform 0.2s;flex-shrink:0"></i>
                            <span style="font-weight:600;color:#1e293b;flex:1;min-width:120px">
                                {{ $r->exam?->title ?? '—' }}
                            </span>
                            <span style="color:#374151;font-size:0.8rem;white-space:nowrap">
                                <span style="font-weight:700">{{ $r->obtained_marks }}</span>
                                <span class="text-muted">/{{ $r->total_marks }}</span>
                            </span>
                            <div class="d-flex align-items-center gap-1" style="min-width:80px">
                                <div style="width:44px;height:4px;background:#e5e7eb;border-radius:2px;overflow:hidden;flex-shrink:0">
                                    <div style="width:{{ min($r->percentage,100) }}%;height:100%;border-radius:2px;
                                                background:{{ $isDq ? '#f59e0b' : ($isPassed ? '#22c55e' : '#ef4444') }}">
                                    </div>
                                </div>
                                <span style="font-size:0.78rem;font-weight:600;color:{{ $statusColor }}">
                                    {{ $r->percentage }}%
                                </span>
                            </div>
                            <span class="badge {{ $statusBadge }}" style="font-size:0.7rem">
                                {{ $statusText }}
                            </span>
                            <span class="text-muted" style="font-size:0.72rem;white-space:nowrap">
                                {{ $r->created_at->format('M d, Y') }}
                            </span>
                        </div>

                        <div id="{{ $collapseId }}" class="collapse">
                            <div class="px-4 py-3" style="background:#fafbff;border-top:1px solid #fde68a">
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <i class="bi bi-eye-fill" style="color:var(--blc-gold,#d4a51c)"></i>
                                    <span style="font-weight:700;font-size:0.85rem;color:var(--blc-navy,#0b2a5b)">
                                        Answer Review
                                    </span>
                                    <span class="badge ms-auto"
                                          style="background:#f0fdf4;color:#166534;font-size:0.7rem">
                                        Question · Your answer · Correct answer
                                    </span>
                                </div>
                                @include('student.results._answer_review', ['result' => $r])
                            </div>
                        </div>

                        @endforeach
                    </div>

                </div>{{-- /course card --}}
            @endforeach
            </div>{{-- /s2Key collapse --}}

        </div>{{-- /semester 2 block --}}
        @endif

        {{-- Fallback when this academic year has no published results yet --}}
        @if(! $hasSem1 && ! $hasSem2)
        <div class="text-center py-3 text-muted small">
            <i class="bi bi-hourglass-split me-1"></i>No published results for this period yet.
        </div>
        @endif

        </div>{{-- /year body inner --}}
    </div>{{-- /ayKey collapse --}}

</div>{{-- /year card --}}
@endforeach

@endif
@endsection

@push('styles')
<style>
.result-exam-row:hover { background: #f8faff !important; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    // ── Chevron rotation for any collapse toggle ────────────────────────
    // Handles: academic year (.ay-chevron), semester (.sem-chevron),
    //          course (.course-chevron), exam (.exam-chevron)
    document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(function (trigger) {
        var targetSel = trigger.getAttribute('data-bs-target');
        if (!targetSel) return;
        var panel = document.querySelector(targetSel);
        if (!panel) return;

        // Find the chevron icon inside this trigger element
        var chevron = trigger.querySelector(
            '.ay-chevron, .sem-chevron, .course-chevron, .bi-chevron-down'
        );

        panel.addEventListener('hide.bs.collapse', function () {
            if (chevron) chevron.style.transform = 'rotate(-90deg)';
        });
        panel.addEventListener('show.bs.collapse', function () {
            if (chevron) chevron.style.transform = 'rotate(0deg)';
        });
    });

    // ── Exam row chevron (right→down when answer review opens) ─────────
    document.querySelectorAll('.result-exam-row').forEach(function (row) {
        var targetSel = row.getAttribute('data-bs-target');
        if (!targetSel) return;
        var panel   = document.querySelector(targetSel);
        var chevron = row.querySelector('.exam-chevron');
        if (!panel || !chevron) return;

        panel.addEventListener('show.bs.collapse', function () {
            chevron.style.transform = 'rotate(90deg)';
        });
        panel.addEventListener('hide.bs.collapse', function () {
            chevron.style.transform = 'rotate(0deg)';
        });
    });

    // ── Bootstrap tooltips ──────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            .forEach(function (el) { new bootstrap.Tooltip(el); });
    });
})();
</script>
@endpush
