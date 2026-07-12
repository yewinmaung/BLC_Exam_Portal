@extends('layouts.app')
@section('title', 'Student Results')
@section('page-title', 'Student Results')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Teacher', 'url' => route('teacher.dashboard')],
        ['label' => 'Results'],
    ]])
@endsection
@section('sidebar')@include('partials.teacher-sidebar')@endsection

@section('content')

{{-- Filter bar --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-sm-6 col-md-3">
                <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600">Course</label>
                <select name="course_id" class="form-select form-select-sm">
                    <option value="">All My Courses</option>
                    @foreach($courses as $c)
                    <option value="{{ $c->id }}" {{ request('course_id') == $c->id ? 'selected' : '' }}>{{ $c->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-6 col-md-2">
                <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600">Academic Year</label>
                <select name="academic_year_id" class="form-select form-select-sm">
                    <option value="">All Years</option>
                    @foreach($academicYears as $ay)
                    <option value="{{ $ay->id }}" {{ request('academic_year_id') == $ay->id ? 'selected' : '' }}>{{ $ay->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-4 col-md-1">
                <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600">Semester</label>
                <select name="semester" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="1" {{ request('semester') === '1' ? 'selected' : '' }}>Sem 1</option>
                    <option value="2" {{ request('semester') === '2' ? 'selected' : '' }}>Sem 2</option>
                </select>
            </div>
            <div class="col-sm-4 col-md-2">
                <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600">Year Level</label>
                <select name="year_level_id" class="form-select form-select-sm">
                    <option value="">All Levels</option>
                    @foreach($yearLevels as $yl)
                    <option value="{{ $yl->id }}" {{ request('year_level_id') == $yl->id ? 'selected' : '' }}>{{ $yl->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-4 col-md-2">
                <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600">Status</label>
                <select name="is_passed" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="1" {{ request('is_passed') === '1' ? 'selected' : '' }}>Passed</option>
                    <option value="0" {{ request('is_passed') === '0' ? 'selected' : '' }}>Failed</option>
                </select>
            </div>
            <div class="col-auto d-flex gap-1">
                <button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('teacher.results.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-bar-chart me-2"></i>Student Results</span>
        <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">
            {{ $results->total() }} total
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0" style="font-size:0.84rem">
                <thead>
                    <tr><th>Student</th><th>Exam</th><th>Course</th><th>Score</th><th>%</th><th>Status</th><th>Date</th></tr>
                </thead>
                <tbody>
                    @forelse($results as $r)
                    <tr>
                        <td>
                            <div style="font-weight:600">{{ $r->student->name ?? '—' }}</div>
                            <div style="font-size:0.7rem;color:#9ca3af">{{ $r->student->email ?? '' }}</div>
                        </td>
                        <td>{{ $r->exam->title ?? '—' }}</td>
                        <td style="color:#6b7280;font-size:0.78rem">{{ $r->exam->course->title ?? '—' }}</td>
                        <td><span style="font-weight:700">{{ $r->obtained_marks }}</span><span class="text-muted">/{{ $r->total_marks }}</span></td>
                        <td>
                            <div class="d-flex align-items-center gap-1">
                                <div style="width:44px;height:5px;background:#e5e7eb;border-radius:3px;overflow:hidden">
                                    <div style="width:{{ min($r->percentage,100) }}%;height:100%;background:{{ $r->is_passed ? '#22c55e' : '#ef4444' }};border-radius:3px"></div>
                                </div>
                                <span style="font-size:0.78rem">{{ $r->percentage }}%</span>
                            </div>
                        </td>
                        <td>
                            @if($r->isDisqualified())
                                <span class="badge bg-warning text-dark">Failed (Cheating)</span>
                            @elseif($r->is_passed)
                                <span class="badge bg-success">Passed</span>
                            @else
                                <span class="badge bg-danger">Failed</span>
                            @endif
                        </td>
                        <td style="font-size:0.75rem;color:#6b7280">{{ $r->created_at->format('M d, Y') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-bar-chart d-block mb-2" style="font-size:2rem;opacity:0.3"></i>
                            No results found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($results->hasPages())
        <div class="p-3 border-top">{{ $results->links() }}</div>
        @endif
    </div>
</div>
@endsection
