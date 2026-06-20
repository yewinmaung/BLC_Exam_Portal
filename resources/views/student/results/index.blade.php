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

{{-- Summary stats --}}
@php
    $totalExams  = $results->total();
    $passedCount = \App\Models\Result::where('student_id', auth()->id())
                    ->where('is_published', true)
                    ->where('is_passed', true)
                    ->whereHas('exam.schedules', fn($sq) => $sq->where('ends_at', '<=', now()))
                    ->count();
    $avgPct      = round(\App\Models\Result::where('student_id', auth()->id())
                    ->where('is_published', true)
                    ->whereHas('exam.schedules', fn($sq) => $sq->where('ends_at', '<=', now()))
                    ->avg('percentage') ?? 0, 1);
@endphp

<div class="row g-3 mb-4">
    @foreach([
        ['label'=>'Exams Taken', 'value'=>$totalExams,      'icon'=>'bi-pencil-square',   'color'=>'var(--royal,#3730a3)'],
        ['label'=>'Passed',      'value'=>$passedCount,      'icon'=>'bi-check-circle',    'color'=>'#22c55e'],
        ['label'=>'Average',     'value'=>$avgPct.'%',       'icon'=>'bi-bar-chart',       'color'=>'#f59e0b'],
    ] as $s)
    <div class="col-sm-4">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div style="width:40px;height:40px;border-radius:10px;background:{{ $s['color'] }}1a;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <i class="bi {{ $s['icon'] }}" style="font-size:1.1rem;color:{{ $s['color'] }}"></i>
                </div>
                <div>
                    <div style="font-size:1.3rem;font-weight:800;color:var(--text-1)">{{ $s['value'] }}</div>
                    <div style="font-size:0.72rem;color:#6b7280">{{ $s['label'] }}</div>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Filters --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
            <div style="min-width:180px;flex:1">
                <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600">Academic Year</label>
                <select name="academic_year_id" class="form-select form-select-sm">
                    <option value="">All Years</option>
                    @foreach($academicYears as $ay)
                    <option value="{{ $ay->id }}" {{ request('academic_year_id') == $ay->id ? 'selected' : '' }}>{{ $ay->name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="min-width:110px">
                <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600">Semester</label>
                <select name="semester" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="1" {{ request('semester') === '1' ? 'selected' : '' }}>Semester 1</option>
                    <option value="2" {{ request('semester') === '2' ? 'selected' : '' }}>Semester 2</option>
                </select>
            </div>
            <div class="d-flex gap-1">
                <button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('student.results.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
            </div>
        </form>
    </div>
</div>

{{-- Results table --}}
<div class="card">
    <div class="card-header"><i class="bi bi-list-check me-2"></i>My Exam Results</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0" style="font-size:0.84rem">
                <thead>
                    <tr><th>Exam</th><th>Course</th><th>Score</th><th>%</th><th>Grade</th><th>Status</th><th>Date</th></tr>
                </thead>
                <tbody>
                    @forelse($results as $r)
                    <tr>
                        <td style="font-weight:600">{{ $r->exam->title ?? '—' }}</td>
                        <td style="color:#6b7280">{{ $r->exam->course->title ?? '—' }}</td>
                        <td>
                            <span style="font-weight:700">{{ $r->obtained_marks }}</span>
                            <span class="text-muted">/{{ $r->total_marks }}</span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-1">
                                <div style="width:50px;height:5px;background:#e5e7eb;border-radius:3px;overflow:hidden">
                                    <div style="width:{{ min($r->percentage,100) }}%;height:100%;background:{{ $r->is_passed ? '#22c55e' : '#ef4444' }};border-radius:3px"></div>
                                </div>
                                <span>{{ $r->percentage }}%</span>
                            </div>
                        </td>
                        <td>
                            <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">
                                {{ $r->grade ?? '—' }}
                            </span>
                        </td>
                        <td>
                            @if($r->is_passed)
                                <span class="badge bg-success">Passed</span>
                            @else
                                <span class="badge bg-danger">Failed</span>
                            @endif
                        </td>
                        <td style="font-size:0.75rem;color:#6b7280">{{ $r->created_at->format('M d, Y') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-hourglass-split d-block mb-2" style="font-size:2rem;opacity:0.3"></i>
                            No published results yet. Results will appear here once released by your teacher.
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

{{-- Academic history accordion --}}
@if(count($history) > 0)
<div class="card mt-3">
    <div class="card-header"><i class="bi bi-calendar3 me-2"></i>Academic Year History</div>
    <div class="card-body p-0">
        @foreach($history as $h)
        <div class="p-3 {{ !$loop->last ? 'border-bottom' : '' }}">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge" style="background:var(--royal,#3730a3);color:#fff">{{ $h['record']->academicYear->name ?? '—' }}</span>
                <span class="badge bg-secondary">{{ $h['record']->yearLevel->name ?? '—' }}</span>
                <span class="badge bg-light text-dark">Sem {{ $h['record']->semester }}</span>
                @if(isset($h['transcript']) && $h['transcript'])
                <span class="badge bg-info text-dark">GPA {{ number_format($h['transcript']->gpa, 2) }}</span>
                <span class="badge {{ $h['transcript']->is_passed ? 'bg-success' : 'bg-danger' }}">
                    {{ $h['transcript']->is_passed ? 'Promoted' : 'Not Promoted' }}
                </span>
                @endif
            </div>
            @if($h['results']->count())
            <div class="table-responsive">
                <table class="table table-sm mb-0" style="font-size:0.79rem">
                    <thead><tr><th>Exam</th><th>Score</th><th>%</th><th>Grade</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach($h['results'] as $er)
                        <tr>
                            <td>{{ $er->exam->title ?? '—' }}</td>
                            <td>{{ $er->obtained_marks }}/{{ $er->total_marks }}</td>
                            <td>{{ $er->percentage }}%</td>
                            <td>{{ $er->grade }}</td>
                            <td>
                                @if($er->is_passed)
                                    <span class="badge bg-success" style="font-size:0.65rem">Passed</span>
                                @else
                                    <span class="badge bg-danger" style="font-size:0.65rem">Failed</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
                <div class="text-muted small py-1">No results archived for this period.</div>
            @endif
        </div>
        @endforeach
    </div>
</div>
@endif

@endsection
