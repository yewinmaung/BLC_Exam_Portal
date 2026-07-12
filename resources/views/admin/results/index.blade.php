@extends('layouts.app')
@section('title', 'Exam Results')
@section('page-title', 'Exam Results')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Results'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')

{{-- Stats row --}}
<div class="row g-3 mb-4">
    @foreach([
        ['label'=>'Total',      'value'=>$stats['total'],   'icon'=>'bi-list-check',         'color'=>'var(--royal,#3730a3)'],
        ['label'=>'Passed',     'value'=>$stats['passed'],  'icon'=>'bi-check-circle-fill',   'color'=>'#22c55e'],
        ['label'=>'Failed',     'value'=>$stats['failed'],  'icon'=>'bi-x-circle-fill',       'color'=>'#ef4444'],
        ['label'=>'Avg Score',  'value'=>$stats['avg_pct'].'%', 'icon'=>'bi-bar-chart-fill',  'color'=>'#f59e0b'],
    ] as $s)
    <div class="col-sm-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div style="width:42px;height:42px;border-radius:10px;background:{{ $s['color'] }}1a;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <i class="bi {{ $s['icon'] }}" style="font-size:1.2rem;color:{{ $s['color'] }}"></i>
                </div>
                <div>
                    <div style="font-size:1.4rem;font-weight:800;color:var(--text-1)">{{ $s['value'] }}</div>
                    <div style="font-size:0.75rem;color:#6b7280">{{ $s['label'] }}</div>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Filter bar --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-sm-6 col-md-3">
                <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600">Student</label>
                <select name="student_id" class="form-select form-select-sm">
                    <option value="">All Students</option>
                    @foreach($students as $s)
                    <option value="{{ $s->id }}" {{ request('student_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
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
                <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600">Course</label>
                <select name="course_id" class="form-select form-select-sm">
                    <option value="">All Courses</option>
                    @foreach($courses as $c)
                    <option value="{{ $c->id }}" {{ request('course_id') == $c->id ? 'selected' : '' }}>{{ $c->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-4 col-md-1">
                <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600">Status</label>
                <select name="is_passed" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="1" {{ request('is_passed') === '1' ? 'selected' : '' }}>Passed</option>
                    <option value="0" {{ request('is_passed') === '0' ? 'selected' : '' }}>Failed</option>
                </select>
            </div>
            <div class="col-auto d-flex gap-1">
                <button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('admin.results.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
            </div>
        </form>
    </div>
</div>

{{-- Results table --}}
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-list-check me-2"></i>Results</span>
        <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">{{ $results->total() }} total</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0" style="font-size:0.84rem">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Exam</th>
                        <th>Course</th>
                        <th>Score</th>
                        <th>%</th>
                        <th>Grade</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($results as $r)
                    <tr>
                        <td>
                            <div style="font-weight:600">{{ $r->student->name ?? '—' }}</div>
                            <div style="font-size:0.7rem;color:#9ca3af">{{ $r->student->email ?? '' }}</div>
                        </td>
                        <td>{{ $r->exam->title ?? '—' }}</td>
                        <td style="font-size:0.78rem;color:#6b7280">{{ $r->exam->course->title ?? '—' }}</td>
                        <td>
                            <span style="font-weight:700">{{ $r->obtained_marks }}</span>
                            <span class="text-muted">/{{ $r->total_marks }}</span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-1">
                                <div style="width:50px;height:5px;background:#e5e7eb;border-radius:3px;overflow:hidden">
                                    <div style="width:{{ min($r->percentage,100) }}%;height:100%;background:{{ $r->is_passed ? '#22c55e' : '#ef4444' }};border-radius:3px"></div>
                                </div>
                                <span style="font-size:0.78rem;font-weight:600">{{ $r->percentage }}%</span>
                            </div>
                        </td>
                        <td>
                            <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">{{ $r->grade ?? '—' }}</span>
                        </td>
                        <td>
                            @if($r->isDisqualified())
                                <div class="d-flex align-items-center gap-1">
                                    <span class="badge" style="background:#fef3c7;color:#92400e">
                                        Failed (Cheating)
                                    </span>
                                    @if($r->violation_reason)
                                    <button class="btn btn-xs btn-outline-warning" 
                                            data-bs-toggle="tooltip" 
                                            title="{{ $r->violation_reason }}">
                                        <i class="bi bi-info-circle"></i>
                                    </button>
                                    @endif
                                </div>
                            @elseif($r->is_passed)
                                <span class="badge bg-success">Passed</span>
                            @else
                                <span class="badge bg-danger">Failed</span>
                            @endif
                        </td>
                        <td style="font-size:0.75rem;color:#6b7280">{{ $r->created_at->format('M d, Y') }}</td>
                        <td>
                            <a href="{{ route('admin.results.student', $r->student) }}"
                               class="btn btn-xs btn-outline-secondary" title="Full history">
                                <i class="bi bi-person-lines-fill"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="bi bi-bar-chart d-block mb-2" style="font-size:2rem;opacity:0.3"></i>
                            No results found for the selected filters.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($results->hasPages())
        <div class="p-3 border-top d-flex align-items-center justify-content-between flex-wrap gap-2">
            <span class="text-muted" style="font-size:0.8rem">
                Showing {{ $results->firstItem() }} to {{ $results->lastItem() }} of {{ $results->total() }} entries
            </span>
            {{ $results->links() }}
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
// Initialize Bootstrap tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>
@endpush
