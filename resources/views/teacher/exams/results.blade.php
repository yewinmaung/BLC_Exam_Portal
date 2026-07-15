@extends('layouts.app')
@section('title', 'Results — '.$exam->title)
@section('page-title', 'Exam Results')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Teacher', 'url' => route('teacher.dashboard')],
        ['label' => 'My Exams', 'url' => route('teacher.exams.index')],
        ['label' => $exam->title, 'url' => route('teacher.exams.show', $exam)],
        ['label' => 'Results'],
    ]])
@endsection
@section('sidebar')
@include('partials.teacher-sidebar')
@endsection

@section('content')

{{-- Header --}}
<div class="page-header mb-3">
    <div>
        <h5 class="mb-1" style="font-weight:700;color:var(--text-1,#111827)">{{ $exam->title }}</h5>
        <p class="text-muted mb-0" style="font-size:0.85rem">
            <i class="bi bi-book me-1"></i>{{ $exam->course->title }}
        </p>
    </div>
    <a href="{{ route('teacher.exams.show', $exam) }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to Exam
    </a>
</div>

{{-- Statistics Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Total Enrolled</div>
                        <div class="h3 mb-0 fw-bold">{{ $stats['total_enrolled'] }}</div>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center"
                         style="width:48px;height:48px;background:#ede9fe">
                        <i class="bi bi-people-fill" style="font-size:1.5rem;color:#6d28d9"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Completed</div>
                        <div class="h3 mb-0 fw-bold text-primary">{{ $stats['total_taken'] }}</div>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center"
                         style="width:48px;height:48px;background:#dbeafe">
                        <i class="bi bi-check-circle-fill" style="font-size:1.5rem;color:#1d4ed8"></i>
                    </div>
                </div>
                <div class="mt-2 small text-muted">
                    {{ $stats['total_enrolled'] > 0 ? round(($stats['total_taken'] / $stats['total_enrolled']) * 100, 1) : 0 }}% completion rate
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Passed</div>
                        <div class="h3 mb-0 fw-bold text-success">{{ $stats['passed'] }}</div>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center"
                         style="width:48px;height:48px;background:#d1fae5">
                        <i class="bi bi-trophy-fill" style="font-size:1.5rem;color:#059669"></i>
                    </div>
                </div>
                <div class="mt-2 small">
                    <span class="text-danger">{{ $stats['failed'] }} failed</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div class="text-muted small">Exam Info</div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center"
                         style="width:48px;height:48px;background:#fef3c7">
                        <i class="bi bi-info-circle-fill" style="font-size:1.5rem;color:#f59e0b"></i>
                    </div>
                </div>
                @php $schedule = $exam->latestSchedule; @endphp
                <div class="d-flex flex-column gap-1 mt-1">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted" style="font-size:0.78rem"><i class="bi bi-arrow-repeat me-1"></i>Attempts</span>
                        <span class="fw-bold" style="font-size:0.88rem">{{ $schedule?->attempt_limit ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted" style="font-size:0.78rem"><i class="bi bi-stopwatch me-1"></i>Duration</span>
                        <span class="fw-bold" style="font-size:0.88rem">{{ $schedule ? $schedule->duration_minutes.' min' : '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted" style="font-size:0.78rem"><i class="bi bi-calendar-event me-1"></i>Opens</span>
                        <span class="fw-bold" style="font-size:0.78rem">{{ $schedule ? $schedule->starts_at->format('M d, H:i') : '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted" style="font-size:0.78rem"><i class="bi bi-calendar-x me-1"></i>Closes</span>
                        <span class="fw-bold" style="font-size:0.78rem">{{ $schedule ? $schedule->ends_at->format('M d, H:i') : '—' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Search & Filter --}}
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('teacher.exams.results', $exam) }}" class="row g-3">
            <div class="col-md-4">
                <label class="form-label small text-muted">Filter Students</label>
                <select name="filter" class="form-select" onchange="this.form.submit()">
                    <option value="all"        {{ ($filter ?? 'all') === 'all'     ? 'selected' : '' }}>All Students</option>
                    <option value="failed"     {{ ($filter ?? '') === 'failed'     ? 'selected' : '' }}>Failed Students (incl. Cheating)</option>
                    <option value="incomplete" {{ ($filter ?? '') === 'incomplete' ? 'selected' : '' }}>Incomplete / Not Attempted</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label small text-muted">Search Students</label>
                <input type="text" name="search" class="form-control"
                       placeholder="Search by name or email..."
                       value="{{ $search ?? '' }}">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search me-1"></i> Search
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Results Table --}}
<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-table me-2"></i>Student Results</span>
        <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">
            {{ $results->count() }} results
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Email</th>
                        <th>Obtained / Total</th>
                        <th>Percentage</th>
                        <th>Status</th>
                        <th>Attempt</th>
                        <th>Submitted At</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($results as $index => $r)
                    @php
                        $isIncomplete = isset($r->is_incomplete) && $r->is_incomplete;
                        $hasCheating  = $r->attempt && in_array(
                            $r->attempt->status,
                            ['terminated', 'suspicious', 'terminated_pending_review']
                        );
                    @endphp
                    <tr>
                        <td class="text-muted">{{ $index + 1 }}</td>
                        <td style="font-weight:600;color:var(--text-1,#111827)">
                            {{ $r->student->name }}
                        </td>
                        <td class="text-muted small">{{ $r->student->email }}</td>
                        <td>
                            @if($isIncomplete)
                                <span class="text-muted">—</span>
                            @else
                                <span class="badge" style="background:#f0f4ff;color:#1e40af;font-weight:700">
                                    {{ $r->obtained_marks }} / {{ $r->total_marks }}
                                </span>
                            @endif
                        </td>
                        <td>
                            @if($isIncomplete)
                                <span class="text-muted">—</span>
                            @else
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress" style="width:60px;height:8px">
                                    <div class="progress-bar {{ $r->is_passed ? 'bg-success' : 'bg-danger' }}"
                                         style="width:{{ $r->percentage }}%"></div>
                                </div>
                                <span class="fw-bold {{ $r->is_passed ? 'text-success' : 'text-danger' }}">
                                    {{ $r->percentage }}%
                                </span>
                            </div>
                            @endif
                        </td>
                        <td>
                            @if($isIncomplete)
                                <span class="status-pill" style="background:#f3f4f6;color:#6b7280">Not Attempted</span>
                            @elseif(method_exists($r, 'isDisqualified') && $r->isDisqualified())
                                <span class="status-pill" style="background:#fef3c7;color:#92400e">
                                    <i class="bi bi-exclamation-triangle me-1"></i>Failed (Cheating)
                                </span>
                            @elseif($hasCheating)
                                <span class="status-pill" style="background:#fef3c7;color:#92400e">
                                    <i class="bi bi-shield-exclamation me-1"></i>Security Violation
                                </span>
                            @elseif($r->is_passed)
                                <span class="status-pill status-approved">
                                    <i class="bi bi-check-circle me-1"></i>Passed
                                </span>
                            @else
                                <span class="status-pill status-rejected">
                                    <i class="bi bi-x-circle me-1"></i>Failed
                                </span>
                            @endif
                        </td>
                        <td class="text-muted small">
                            @if($isIncomplete)—@else Attempt #{{ $r->attempt->attempt_number ?? 1 }}@endif
                        </td>
                        <td class="text-muted small">
                            @if($isIncomplete)—@else{{ $r->attempt?->submitted_at?->format('M d, Y H:i') ?? '—' }}@endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox d-block mb-2" style="font-size:2rem;opacity:0.3"></i>
                            No students have completed this exam yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Absent Students --}}
@if(isset($absentStudents) && $absentStudents->isNotEmpty())
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-person-x me-2"></i>Absent Students</span>
        <span class="badge bg-warning text-dark">{{ $absentStudents->count() }} students</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Email</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($absentStudents as $index => $student)
                    <tr>
                        <td class="text-muted">{{ $index + 1 }}</td>
                        <td style="font-weight:600;color:var(--text-1,#111827)">{{ $student->name }}</td>
                        <td class="text-muted small">{{ $student->email }}</td>
                        <td>
                            <span class="badge bg-warning text-dark">
                                <i class="bi bi-exclamation-triangle me-1"></i>Not Attempted
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

@endsection

@push('styles')
<style>
.progress {
    background-color: #e5e7eb;
    border-radius: 4px;
}
.card {
    border-radius: 8px;
}
.card-header {
    background: #f8f9fa;
    border-bottom: 1px solid #e5e7eb;
    font-weight: 600;
}
</style>
@endpush
