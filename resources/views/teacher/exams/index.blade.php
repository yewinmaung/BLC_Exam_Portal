@extends('layouts.app')
@section('title', 'My Exams')
@section('page-title', 'My Exams')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Teacher', 'url' => route('teacher.dashboard')],
        ['label' => 'My Exams'],
    ]])
@endsection
@section('sidebar')
@include('partials.teacher-sidebar')
@endsection

@section('content')

<div class="page-header">
    <div></div>
    <a href="{{ route('teacher.exams.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i> New Exam
    </a>
</div>

@if($exams->isEmpty())

<div class="card">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-file-earmark-x d-block mb-2" style="font-size:3rem;opacity:0.3"></i>
        <h6>No exams yet</h6>
        <p class="small mb-0"><a href="{{ route('teacher.exams.create') }}">Create your first exam</a> to get started.</p>
    </div>
</div>

@else

@php
    $statusColors = [
        'draft'            => ['bg' => '#f0f4ff', 'color' => '#3730a3',  'label' => 'Draft'],
        'pending_approval' => ['bg' => '#fef3c7', 'color' => '#92400e',  'label' => 'Pending Approval'],
        'approved'         => ['bg' => '#dcfce7', 'color' => '#166534',  'label' => 'Approved'],
        'published'        => ['bg' => '#dbeafe', 'color' => '#1e40af',  'label' => 'Published'],
        'closed'           => ['bg' => '#f3f4f6', 'color' => '#374151',  'label' => 'Closed'],
    ];
@endphp

{{-- ══ ACCORDION HIERARCHY ════════════════════════════════════════════════ --}}
@foreach($grouped as $ayId => $ylGroups)
@php
    $ay      = $ayMap[$ayId] ?? null;
    $ayLabel = $ay ? $ay->name : 'Unassigned';
    $ayKey   = 'ay_' . $ayId;

    // Count all exams under this academic year
    $ayExamCount = 0;
    foreach ($ylGroups as $semGroups) {
        foreach ($semGroups as $courseGroups) {
            foreach ($courseGroups as $cg) {
                $ayExamCount += count($cg['exams']);
            }
        }
    }
@endphp

{{-- ── Level 1: Academic Year ─────────────────────────────────────── --}}
<div class="card mb-3" style="border:1.5px solid #e2e8f0;border-radius:12px;overflow:hidden">

    {{-- Academic Year header --}}
    <div class="d-flex align-items-center justify-content-between px-4 py-3"
         style="background:var(--blc-navy,#0b2a5b);cursor:pointer;user-select:none"
         onclick="toggleSection('{{ $ayKey }}')">
        <div class="d-flex align-items-center gap-3">
            <i class="bi bi-calendar3" style="color:#93c5fd;font-size:1.1rem"></i>
            <span style="font-weight:700;font-size:1rem;color:#fff">{{ $ayLabel }}</span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="badge" style="background:rgba(255,255,255,0.15);color:#fff;font-size:0.73rem">
                {{ $ayExamCount }} exam{{ $ayExamCount !== 1 ? 's' : '' }}
            </span>
            <i class="bi bi-chevron-down ay-chevron-{{ $ayKey }}"
               style="color:#93c5fd;font-size:0.85rem;transition:transform 0.2s"></i>
        </div>
    </div>

    <div id="{{ $ayKey }}" style="display:block">
    @foreach($ylGroups as $yl => $semGroups)
    @php
        $ylLabel = \App\Models\Course::$yearLevelLabels[$yl] ?? 'Year ' . $yl;
    @endphp

    @foreach($semGroups as $sem => $courseGroups)
    @php
        $semLabel   = \App\Models\Course::$semesterLabels[$sem] ?? 'Semester ' . $sem;
        $semKey     = $ayKey . '_yl' . $yl . '_sem' . $sem;

        $semExamCount = 0;
        foreach ($courseGroups as $cg) {
            $semExamCount += count($cg['exams']);
        }
    @endphp

    {{-- ── Level 2: Year Level + Semester ─────────────────────────── --}}
    <div style="border-top:1px solid #e2e8f0">

        {{-- Year Level / Semester header --}}
        <div class="d-flex align-items-center justify-content-between px-4 py-2"
             style="background:#f8faff;cursor:pointer;user-select:none"
             onclick="toggleSection('{{ $semKey }}')">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-mortarboard" style="color:var(--blc-navy,#0b2a5b);font-size:0.95rem"></i>
                <span style="font-weight:700;color:var(--blc-navy,#0b2a5b);font-size:0.9rem">{{ $ylLabel }}</span>
                <span class="badge" style="background:#ede9fe;color:#3730a3;font-size:0.7rem;font-weight:600">{{ $semLabel }}</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="text-muted" style="font-size:0.78rem">{{ $semExamCount }} exam{{ $semExamCount !== 1 ? 's' : '' }}</span>
                <i class="bi bi-chevron-down sem-chevron-{{ $semKey }}"
                   style="font-size:0.78rem;color:#9ca3af;transition:transform 0.2s"></i>
            </div>
        </div>

        <div id="{{ $semKey }}" style="display:block;padding:0.75rem 1.25rem 1rem">

            @foreach($courseGroups as $courseId => $cg)
            @php
                $course     = $cg['course'];
                $courseKey  = $semKey . '_course_' . $courseId;
                $courseExams = $cg['exams'];
            @endphp

            {{-- ── Level 3: Course ─────────────────────────────────── --}}
            <div class="mb-2" style="border:1.5px solid #e2e8f0;border-radius:10px;overflow:hidden">

                {{-- Course header --}}
                <div class="d-flex align-items-center justify-content-between px-3 py-2"
                     style="background:#fff;cursor:pointer"
                     onclick="toggleSection('{{ $courseKey }}')">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-book-fill" style="color:var(--blc-navy,#0b2a5b)"></i>
                        <div>
                            <span style="font-weight:700;color:var(--blc-navy,#0b2a5b)">{{ $course->title }}</span>
                            <span class="text-muted ms-2" style="font-size:0.78rem">{{ $course->code }}</span>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span class="small text-muted">{{ count($courseExams) }} exam{{ count($courseExams) !== 1 ? 's' : '' }}</span>
                        <i class="bi bi-chevron-down course-chevron-{{ $courseKey }}"
                           style="font-size:0.8rem;color:#6b7280;transition:transform 0.2s"></i>
                    </div>
                </div>

                {{-- ── Level 4: Exams list ──────────────────────────── --}}
                <div id="{{ $courseKey }}"
                     style="display:block;border-top:1px solid #e2e8f0;background:#f8faff">

                    @foreach($courseExams as $exam)
                    @php
                        $sc    = $statusColors[$exam->status] ?? ['bg'=>'#f3f4f6','color'=>'#374151','label'=>ucfirst($exam->status)];
                        $qCount = $exam->questions_count ?? 0;
                    @endphp

                    <div class="d-flex align-items-center justify-content-between px-3 py-2"
                         style="border-bottom:1px solid #f0f4f8;background:#fff;margin:0 0.75rem 0;
                                margin:0.5rem 0.75rem;border-radius:8px;border:1px solid #e8edf4">

                        {{-- Exam info --}}
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-file-earmark-text" style="color:#6b7280;font-size:0.95rem"></i>
                            <div>
                                <div style="font-weight:600;font-size:0.875rem;color:#111827">{{ $exam->title }}</div>
                                <div class="d-flex align-items-center gap-2 mt-1">
                                    <span style="background:{{ $sc['bg'] }};color:{{ $sc['color'] }};
                                                 font-size:0.68rem;font-weight:700;padding:2px 7px;
                                                 border-radius:4px;text-transform:uppercase;letter-spacing:0.04em">
                                        {{ $sc['label'] }}
                                    </span>
                                    <span class="text-muted" style="font-size:0.72rem">
                                        <i class="bi bi-question-circle me-1"></i>{{ $qCount }} question{{ $qCount !== 1 ? 's' : '' }}
                                    </span>
                                    <span class="text-muted" style="font-size:0.72rem">
                                        <i class="bi bi-award me-1"></i>{{ $exam->total_marks }} marks
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="d-flex align-items-center gap-1 flex-shrink-0">
                            <a href="{{ route('teacher.exams.show', $exam) }}"
                               class="btn btn-sm btn-primary"
                               style="font-size:0.75rem;padding:4px 12px">
                                <i class="bi bi-arrow-right me-1"></i>Open
                            </a>
                            @if($exam->status === 'draft')
                            <form action="{{ route('teacher.exams.destroy', $exam) }}" method="POST"
                                  onsubmit="return confirm('Delete exam &quot;{{ addslashes($exam->title) }}&quot;?\nThis will permanently remove all questions and cannot be undone.')">
                                @csrf @method('DELETE')
                                <button type="submit"
                                        class="btn btn-sm btn-outline-danger"
                                        style="font-size:0.75rem;padding:4px 8px"
                                        title="Delete draft exam">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            @endif
                        </div>

                    </div>
                    @endforeach

                    {{-- Bottom spacing inside course card --}}
                    <div style="height:0.5rem"></div>

                </div>{{-- /exams list --}}

            </div>{{-- /course card --}}
            @endforeach

        </div>{{-- /semester body --}}
    </div>{{-- /semester block --}}

    @endforeach {{-- semesters --}}
    @endforeach {{-- year levels --}}
    </div>{{-- /ay body --}}

</div>{{-- /ay card --}}
@endforeach {{-- academic years --}}

@endif
@endsection

@push('scripts')
<script>
(function () {
    window.toggleSection = function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        var isOpen = el.style.display !== 'none';
        el.style.display = isOpen ? 'none' : 'block';

        // Rotate matching chevron
        var prefixes = ['ay-chevron-', 'sem-chevron-', 'course-chevron-'];
        for (var i = 0; i < prefixes.length; i++) {
            var ch = document.querySelector('.' + prefixes[i] + id);
            if (ch) {
                ch.style.transform = isOpen ? '' : 'rotate(180deg)';
                break;
            }
        }
    };
})();
</script>
@endpush
