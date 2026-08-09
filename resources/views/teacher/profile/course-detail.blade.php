@extends('layouts.app')
@section('title', $course->title)
@section('page-title', 'Course Detail')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Teacher', 'url' => route('teacher.dashboard')],
        ['label' => 'My Profile', 'url' => route('teacher.profile.show')],
        ['label' => $course->title],
    ]])
@endsection
@section('sidebar')
@include('partials.teacher-sidebar')
@endsection

@push('styles')
<style>
/* ── Section card ── */
.section-card {
    background: #fff; border-radius: 14px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 10px rgba(11,42,91,0.05);
    margin-bottom: 1.5rem; overflow: hidden;
}
.section-card-header {
    background: linear-gradient(135deg, #071d40, #0b2a5b);
    color: #fff; padding: 0.85rem 1.25rem;
    font-size: 0.88rem; font-weight: 700;
    display: flex; align-items: center; gap: 0.5rem;
}
/* ── Info rows ── */
.info-row {
    display: flex; align-items: flex-start;
    padding: 0.7rem 0;
    border-bottom: 1px solid #f1f5f9;
}
.info-row:last-child { border-bottom: none; }
.info-label {
    width: 150px; flex-shrink: 0;
    font-size: 0.75rem; font-weight: 700;
    color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em;
    padding-top: 2px;
}
.info-value {
    font-size: 0.92rem; font-weight: 600;
    color: var(--blc-navy, #0b2a5b);
}

/* ── Exam item ── */
.exam-item {
    display: flex; align-items: center;
    justify-content: space-between;
    padding: 0.85rem 1.1rem;
    border-radius: 10px;
    border: 1px solid #e8edf8;
    background: #fafbff;
    margin-bottom: 0.6rem;
    transition: box-shadow 0.15s;
}
.exam-item:hover {
    box-shadow: 0 3px 12px rgba(11,42,91,0.08);
}
.exam-item:last-child { margin-bottom: 0; }
.exam-item-title {
    font-size: 0.9rem; font-weight: 700;
    color: var(--blc-navy, #0b2a5b);
}
.exam-item-meta {
    font-size: 0.75rem; color: #6b7280; margin-top: 2px;
}
</style>
@endpush

@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="{{ route('teacher.profile.show') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to My Profile
    </a>
    <a href="{{ route('teacher.exams.create', ['course_id' => $course->id]) }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i> Create Exam
    </a>
</div>

<div class="row g-3">

    {{-- ── Course Information ── --}}
    <div class="col-md-5 col-lg-4">
        <div class="section-card">
            <div class="section-card-header">
                <i class="bi bi-book-half"></i> Course Information
            </div>
            <div class="card-body px-3 py-2">

                <div class="info-row">
                    <span class="info-label">Course</span>
                    <span class="info-value">{{ $course->title }}</span>
                </div>

                <div class="info-row">
                    <span class="info-label">Code</span>
                    <span class="info-value">
                        @if($course->code)
                        <span class="badge" style="background:#eef2ff;color:var(--blc-royal,#2d27a0);font-size:0.82rem;font-weight:700">
                            {{ $course->code }}
                        </span>
                        @else
                        <span class="text-muted">—</span>
                        @endif
                    </span>
                </div>

                <div class="info-row">
                    <span class="info-label">Academic Year</span>
                    <span class="info-value">
                        {{ $course->academicYear->name ?? '—' }}
                    </span>
                </div>

                <div class="info-row">
                    <span class="info-label">Year Level</span>
                    <span class="info-value">{{ $course->yearLevelLabel }}</span>
                </div>

                <div class="info-row">
                    <span class="info-label">Semester</span>
                    <span class="info-value">{{ $course->semesterLabel }}</span>
                </div>

                @if($course->description)
                <div class="info-row">
                    <span class="info-label">Description</span>
                    <span class="info-value" style="font-weight:400;font-size:0.85rem;color:#374151">
                        {{ $course->description }}
                    </span>
                </div>
                @endif

            </div>
        </div>
    </div>

    {{-- ── Course Exams ── --}}
    <div class="col-md-7 col-lg-8">
        <div class="section-card">
            <div class="section-card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-file-earmark-text me-1"></i> Course Exams</span>
                <span class="badge" style="background:rgba(255,255,255,0.2);color:#fff;font-size:0.78rem">
                    {{ $course->exams->count() }} exam{{ $course->exams->count() !== 1 ? 's' : '' }}
                </span>
            </div>
            <div class="card-body p-3">

                @if($course->exams->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-file-earmark-x d-block mb-2" style="font-size:2.5rem;opacity:0.35"></i>
                    <p class="mb-1">No exams created for this course yet.</p>
                    <a href="{{ route('teacher.exams.create', ['course_id' => $course->id]) }}"
                       class="btn btn-primary btn-sm mt-2">
                        <i class="bi bi-plus-circle me-1"></i> Create First Exam
                    </a>
                </div>
                @else

                @foreach($course->exams as $exam)
                <div class="exam-item">
                    <div>
                        <div class="exam-item-title">
                            <a href="{{ route('teacher.exams.show', $exam) }}"
                               style="color:inherit;text-decoration:none">
                                {{ $exam->title }}
                            </a>
                        </div>
                        <div class="exam-item-meta">
                            <i class="bi bi-check-square me-1"></i>{{ $exam->questions()->count() }} question{{ $exam->questions()->count() !== 1 ? 's' : '' }}
                            &nbsp;·&nbsp;
                            <i class="bi bi-award me-1"></i>{{ $exam->total_marks }} marks
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        @php
                            $statusMap = [
                                'draft'            => ['bg' => '#f1f5f9', 'color' => '#475569', 'label' => 'Draft'],
                                'pending_approval' => ['bg' => '#fef9c3', 'color' => '#854d0e', 'label' => 'Pending Approval'],
                                'approved'         => ['bg' => '#dcfce7', 'color' => '#166534', 'label' => 'Approved'],
                                'published'        => ['bg' => '#dbeafe', 'color' => '#1e40af', 'label' => 'Published'],
                                'closed'           => ['bg' => '#fee2e2', 'color' => '#991b1b', 'label' => 'Closed'],
                            ];
                            $s = $statusMap[$exam->status] ?? ['bg' => '#f1f5f9', 'color' => '#374151', 'label' => ucfirst($exam->status)];
                        @endphp
                        <span class="badge" style="background:{{ $s['bg'] }};color:{{ $s['color'] }};font-size:0.75rem;padding:0.3rem 0.65rem;font-weight:600">
                            {{ $s['label'] }}
                        </span>
                        <a href="{{ route('teacher.exams.show', $exam) }}" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
                @endforeach

                <div class="mt-3 pt-2 border-top">
                    <a href="{{ route('teacher.exams.create', ['course_id' => $course->id]) }}" class="btn btn-primary w-100">
                        <i class="bi bi-plus-circle me-1"></i> Create Exam
                    </a>
                </div>

                @endif

            </div>
        </div>
    </div>

</div>
@endsection
