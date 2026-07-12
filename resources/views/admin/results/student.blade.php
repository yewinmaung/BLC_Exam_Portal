@extends('layouts.app')
@section('title', 'Results — '.$student->name)
@section('page-title', $student->name.' — Full Result History')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Results', 'url' => route('admin.results.index')],
        ['label' => $student->name],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')

<div class="d-flex gap-2 mb-4">
    <a href="{{ route('admin.results.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
    <a href="{{ route('admin.students.show', $student) }}" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-person me-1"></i> Student Profile
    </a>
</div>

{{-- Student info card --}}
<div class="card mb-4">
    <div class="card-body d-flex align-items-center gap-3">
        <div style="width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,#1e1b6e,#3730a3);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.2rem;font-weight:700;flex-shrink:0">
            {{ strtoupper(substr($student->name,0,1)) }}
        </div>
        <div>
            <div style="font-size:1.05rem;font-weight:700">{{ $student->name }}</div>
            <div style="font-size:0.8rem;color:#6b7280">{{ $student->email }}</div>
        </div>
        <div class="ms-auto text-end">
            @php
                $passed = $results->where('is_passed', true)->count();
                $total  = $results->count();
            @endphp
            <div style="font-size:1.3rem;font-weight:800;color:var(--royal,#3730a3)">{{ $passed }}/{{ $total }}</div>
            <div style="font-size:0.75rem;color:#6b7280">Exams Passed</div>
        </div>
    </div>
</div>

{{-- Exam results table --}}
<div class="card mb-4">
    <div class="card-header"><i class="bi bi-list-check me-2"></i>All Exam Results</div>
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
                        <td>{{ $r->obtained_marks }}/{{ $r->total_marks }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-1">
                                <div style="width:50px;height:5px;background:#e5e7eb;border-radius:3px;overflow:hidden">
                                    <div style="width:{{ min($r->percentage,100) }}%;height:100%;background:{{ $r->is_passed ? '#22c55e' : '#ef4444' }};border-radius:3px"></div>
                                </div>
                                <span>{{ $r->percentage }}%</span>
                            </div>
                        </td>
                        <td><span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">{{ $r->grade }}</span></td>
                        <td>
                            @if($r->isDisqualified())
                                <div class="d-flex align-items-center gap-1">
                                    <span class="badge" style="background:#fef3c7;color:#92400e">
                                        Failed (Cheating)
                                    </span>
                                    @if($r->violation_reason)
                                    <i class="bi bi-info-circle text-warning" 
                                       data-bs-toggle="tooltip" 
                                       title="{{ $r->violation_reason }}"></i>
                                    @endif
                                </div>
                            @elseif($r->is_passed)
                                <span class="badge bg-success">Passed</span>
                            @else
                                <span class="badge bg-danger">Failed</span>
                            @endif
                        </td>
                        <td style="color:#6b7280;font-size:0.75rem">{{ $r->created_at->format('M d, Y') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center py-4 text-muted">No exam results yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Academic year history --}}
@if(count($history) > 0)
<div class="card">
    <div class="card-header"><i class="bi bi-calendar3 me-2"></i>Academic Year History</div>
    <div class="card-body">
        @foreach($history as $h)
        <div class="mb-4 pb-3 {{ !$loop->last ? 'border-bottom' : '' }}">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge" style="background:var(--royal,#3730a3);color:#fff">
                    {{ $h['record']->academicYear->name ?? '—' }}
                </span>
                <span class="badge bg-secondary">{{ $h['record']->yearLevel->name ?? '—' }}</span>
                <span class="badge bg-light text-dark">Sem {{ $h['record']->semester }}</span>
                <span class="badge {{ $h['record']->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                    {{ ucfirst($h['record']->status) }}
                </span>
            </div>
            @if($h['results']->count())
            <div class="table-responsive">
                <table class="table table-sm mb-0" style="font-size:0.8rem">
                    <thead><tr><th>Exam</th><th>Score</th><th>%</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach($h['results'] as $er)
                        <tr>
                            <td>{{ $er->exam->title ?? '—' }}</td>
                            <td>{{ $er->obtained_marks }}/{{ $er->total_marks }}</td>
                            <td>{{ $er->percentage }}%</td>
                            <td>
                                @if($er->isDisqualified())
                                    <span class="badge bg-warning text-dark" style="font-size:0.65rem">Failed (Cheating)</span>
                                @elseif($er->is_passed)
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
                <div class="text-muted small">No results for this period.</div>
            @endif
        </div>
        @endforeach
    </div>
</div>
@endif
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
