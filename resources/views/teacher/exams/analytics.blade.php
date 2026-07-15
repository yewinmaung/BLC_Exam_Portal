@extends('layouts.app')
@section('title', 'Analytics — ' . $exam->title)
@section('page-title', 'Exam Analytics')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Teacher',      'url' => route('teacher.dashboard')],
        ['label' => 'My Exams',     'url' => route('teacher.exams.index')],
        ['label' => $exam->title,   'url' => route('teacher.exams.show', $exam)],
        ['label' => 'Analytics'],
    ]])
@endsection
@section('sidebar')@include('partials.teacher-sidebar')@endsection

@section('content')

{{-- Header strip --}}
<div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
    <div>
        <h6 class="mb-0" style="font-weight:700;color:var(--text-1,#111827)">{{ $exam->title }}</h6>
        <small class="text-muted">
            <i class="bi bi-book me-1"></i>{{ $exam->course->title }}
        </small>
    </div>
    <div class="ms-auto d-flex gap-2">
        <a href="{{ route('teacher.exams.show', $exam) }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Exam
        </a>
        <a href="{{ route('teacher.exams.results', $exam) }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-bar-chart me-1"></i> Results
        </a>
    </div>
</div>

{{-- Stat cards --}}
<div class="row g-3 mb-4">

    {{-- Total Students --}}
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100 text-center" style="border-top:3px solid #0f3a7a">
            <div class="card-body py-3 px-2">
                <div style="font-size:2rem;font-weight:900;color:#0f3a7a;line-height:1">
                    {{ $stats['totalStudents'] }}
                </div>
                <div class="text-muted small mt-1" style="font-size:0.78rem">Total Students</div>
            </div>
        </div>
    </div>

    {{-- Completed --}}
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100 text-center" style="border-top:3px solid #16a34a">
            <div class="card-body py-3 px-2">
                <div style="font-size:2rem;font-weight:900;color:#16a34a;line-height:1">
                    {{ $stats['completed'] }}
                </div>
                <div class="text-muted small mt-1" style="font-size:0.78rem">Completed</div>
            </div>
        </div>
    </div>

    {{-- Not Attempted --}}
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100 text-center" style="border-top:3px solid #9ca3af">
            <div class="card-body py-3 px-2">
                <div style="font-size:2rem;font-weight:900;color:#6b7280;line-height:1">
                    {{ $stats['notAttempted'] }}
                </div>
                <div class="text-muted small mt-1" style="font-size:0.78rem">Not Attempted</div>
                @if($stats['notAttempted'] > 0)
                <a href="{{ route('teacher.exams.analytics.not-attempted', $exam) }}"
                   class="btn btn-sm btn-outline-secondary mt-2 py-0 px-2"
                   style="font-size:0.72rem">
                    View <i class="bi bi-arrow-right"></i>
                </a>
                @endif
            </div>
        </div>
    </div>

    {{-- In Progress --}}
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100 text-center" style="border-top:3px solid #d97706">
            <div class="card-body py-3 px-2">
                <div style="font-size:2rem;font-weight:900;color:#d97706;line-height:1">
                    {{ $stats['inProgress'] }}
                </div>
                <div class="text-muted small mt-1" style="font-size:0.78rem">In Progress</div>
            </div>
        </div>
    </div>

    {{-- Passed --}}
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100 text-center" style="border-top:3px solid #22c55e">
            <div class="card-body py-3 px-2">
                <div style="font-size:2rem;font-weight:900;color:#16a34a;line-height:1">
                    {{ $stats['passed'] }}
                </div>
                <div class="text-muted small mt-1" style="font-size:0.78rem">Passed</div>
            </div>
        </div>
    </div>

    {{-- Failed --}}
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100 text-center" style="border-top:3px solid #ef4444">
            <div class="card-body py-3 px-2">
                <div style="font-size:2rem;font-weight:900;color:#dc2626;line-height:1">
                    {{ $stats['failed'] }}
                </div>
                <div class="text-muted small mt-1" style="font-size:0.78rem">Failed</div>
            </div>
        </div>
    </div>

</div>

{{-- Terminated alert --}}
@if($stats['terminated'] > 0)
<div class="alert d-flex align-items-center gap-3 mb-4"
     style="background:#fef3c7;border:1px solid #fde68a;border-radius:12px;padding:1rem 1.25rem">
    <i class="bi bi-shield-exclamation text-warning" style="font-size:1.5rem;flex-shrink:0"></i>
    <div>
        <strong style="color:#92400e">{{ $stats['terminated'] }} student(s) terminated</strong>
        <div class="text-muted small">
            Includes: terminated, suspicious, terminated_pending_review, rejected statuses.
        </div>
    </div>
</div>
@endif

{{-- Summary table --}}
<div class="card">
    <div class="card-header">
        <i class="bi bi-table me-2"></i>Analytics Summary
        <span class="text-muted small ms-2">{{ $exam->title }}</span>
    </div>
    <div class="card-body p-0">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th style="width:55%">Metric</th>
                    <th>Count</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><i class="bi bi-people me-2 text-primary"></i>Total Assigned Students</td>
                    <td><strong>{{ $stats['totalStudents'] }}</strong></td>
                </tr>
                <tr>
                    <td><i class="bi bi-check2-circle me-2 text-success"></i>Completed (Submitted)</td>
                    <td><strong>{{ $stats['completed'] }}</strong></td>
                </tr>
                <tr>
                    <td><i class="bi bi-dash-circle me-2 text-secondary"></i>Not Attempted</td>
                    <td>
                        <strong>{{ $stats['notAttempted'] }}</strong>
                        @if($stats['notAttempted'] > 0)
                        &nbsp;
                        <a href="{{ route('teacher.exams.analytics.not-attempted', $exam) }}"
                           class="text-decoration-none small" style="font-size:0.78rem">
                            view list →
                        </a>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td><i class="bi bi-hourglass-split me-2 text-warning"></i>In Progress</td>
                    <td><strong>{{ $stats['inProgress'] }}</strong></td>
                </tr>
                <tr>
                    <td><i class="bi bi-trophy me-2 text-success"></i>Passed</td>
                    <td><strong>{{ $stats['passed'] }}</strong></td>
                </tr>
                <tr>
                    <td><i class="bi bi-x-circle me-2 text-danger"></i>Failed</td>
                    <td><strong>{{ $stats['failed'] }}</strong></td>
                </tr>
                <tr>
                    <td><i class="bi bi-shield-x me-2 text-warning"></i>Terminated</td>
                    <td><strong>{{ $stats['terminated'] }}</strong></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

@endsection
