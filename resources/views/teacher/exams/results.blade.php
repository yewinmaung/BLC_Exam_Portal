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

{{-- Exam info strip --}}
<div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
    <div>
        <h6 class="mb-0" style="font-weight:700;color:var(--text-1)">{{ $exam->title }}</h6>
        <small class="text-muted"><i class="bi bi-book me-1"></i>{{ $exam->course->title }}</small>
    </div>
    <div class="ms-auto d-flex gap-2">
        <a href="{{ route('teacher.exams.show', $exam) }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Exam
        </a>
    </div>
</div>

{{-- Filters and Search --}}
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('teacher.exams.results', $exam) }}" class="row g-3">
            <div class="col-md-4">
                <label class="form-label small text-muted">Filter Students</label>
                <select name="filter" class="form-select" onchange="this.form.submit()">
                    <option value="all"        {{ ($filter ?? 'all') === 'all'        ? 'selected' : '' }}>All Students</option>
                    <option value="failed"     {{ ($filter ?? '') === 'failed'        ? 'selected' : '' }}>Failed Students</option>
                    <option value="incomplete" {{ ($filter ?? '') === 'incomplete'    ? 'selected' : '' }}>Incomplete / Not Attempted</option>
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
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-bar-chart me-2"></i>Student Results</span>
        <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">
            {{ $results->count() }} students
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Score</th>
                        <th>Percentage</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($results as $r)
                    @php
                        $isIncomplete = isset($r->is_incomplete) && $r->is_incomplete;
                        $hasCheating  = false;
                        if ($r->attempt) {
                            $hasCheating = in_array($r->attempt->status, ['terminated', 'suspicious', 'terminated_pending_review']);
                        }
                    @endphp
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,var(--royal-deeper,#1e1b6e),var(--royal,#3730a3));color:#fff;display:flex;align-items:center;justify-content:center;font-size:0.72rem;font-weight:700;flex-shrink:0">
                                    {{ strtoupper(substr($r->student->name, 0, 1)) }}
                                </div>
                                <div>
                                    <div style="font-weight:600">{{ $r->student->name }}</div>
                                    @if($isIncomplete)
                                    <div class="text-muted small">No attempt recorded</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>
                            @if($isIncomplete)
                                <span class="text-muted">—</span>
                            @else
                                <span style="font-weight:700;color:var(--text-1)">{{ $r->obtained_marks }}</span>
                                <span class="text-muted">/{{ $r->total_marks }}</span>
                            @endif
                        </td>
                        <td>
                            @if($isIncomplete)
                                <span class="text-muted">—</span>
                            @else
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:60px;height:6px;background:#e5e7eb;border-radius:3px;overflow:hidden">
                                    <div style="width:{{ $r->percentage }}%;height:100%;background:{{ $r->is_passed ? '#22c55e' : '#ef4444' }};border-radius:3px"></div>
                                </div>
                                <span style="font-size:0.82rem;font-weight:600">{{ $r->percentage }}%</span>
                            </div>
                            @endif
                        </td>
                        <td>
                            @if($isIncomplete)
                                <span class="status-pill" style="background:#f3f4f6;color:#6b7280">Not Attempted</span>
                            @elseif($hasCheating)
                                <span class="status-pill status-closed" style="background:#fef3c7;color:#92400e">Security Violation</span>
                            @elseif($r->is_passed)
                                <span class="status-pill status-approved">Passed</span>
                            @else
                                <span class="status-pill status-closed">Failed</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center py-5 text-muted">
                            <i class="bi bi-bar-chart d-block mb-2" style="font-size:2rem;opacity:0.3"></i>
                            No results match your filter.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
