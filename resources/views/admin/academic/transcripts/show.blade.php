@extends('layouts.app')
@section('title', 'Transcript — '.$student->name)
@section('page-title', $student->name.' — Academic Transcript')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Results', 'url' => route('admin.results.index')],
        ['label' => $student->name.' — Transcript'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')

<div class="d-flex gap-2 mb-4 flex-wrap">
    <a href="{{ route('admin.results.student', $student) }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to Results
    </a>
</div>

{{-- Student card --}}
<div class="card mb-4">
    <div class="card-body d-flex align-items-center gap-3">
        <div style="width:50px;height:50px;border-radius:50%;background:linear-gradient(135deg,#1e1b6e,#3730a3);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.1rem;font-weight:700;flex-shrink:0">
            {{ strtoupper(substr($student->name,0,1)) }}
        </div>
        <div>
            <div style="font-size:1rem;font-weight:700">{{ $student->name }}</div>
            <div style="font-size:0.8rem;color:#6b7280">{{ $student->email }}</div>
        </div>
    </div>
</div>

{{-- Generate Transcript form --}}
<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><i class="bi bi-plus-circle me-2"></i>Generate / Refresh Transcript</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.academic.transcripts.generate', $student) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Academic Year <span class="text-danger">*</span></label>
                        <select name="academic_year_id" class="form-select" required>
                            <option value="">— Select —</option>
                            @foreach($academicYears as $ay)
                            <option value="{{ $ay->id }}">{{ $ay->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Year Level <span class="text-danger">*</span></label>
                        <select name="year_level_id" class="form-select" required>
                            <option value="">— Select —</option>
                            @foreach($yearLevels as $yl)
                            <option value="{{ $yl->id }}">{{ $yl->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Semester <span class="text-danger">*</span></label>
                        <select name="semester" class="form-select" required>
                            <option value="1">Semester 1</option>
                            <option value="2">Semester 2</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-arrow-clockwise me-1"></i> Generate Transcript
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- PDF export --}}
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><i class="bi bi-file-earmark-pdf me-2"></i>Export Transcript PDF</div>
            <div class="card-body">
                <form method="GET" action="{{ route('admin.academic.transcripts.pdf', $student) }}" target="_blank">
                    <div class="mb-3">
                        <label class="form-label">Academic Year</label>
                        <select name="academic_year_id" class="form-select" required>
                            <option value="">— Select Year —</option>
                            @foreach($academicYears as $ay)
                            <option value="{{ $ay->id }}">{{ $ay->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-danger w-100">
                        <i class="bi bi-file-earmark-pdf me-1"></i> Download PDF Transcript
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- History --}}
@forelse($history as $h)
<div class="card mb-3">
    <div class="card-header d-flex align-items-center gap-2">
        <span class="badge" style="background:var(--royal,#3730a3);color:#fff">{{ $h['record']->academicYear->name ?? '—' }}</span>
        <span class="badge bg-secondary">{{ $h['record']->yearLevel->name ?? '—' }}</span>
        <span class="badge bg-light text-dark border">Sem {{ $h['record']->semester }}</span>
        <span class="badge {{ $h['record']->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($h['record']->status) }}</span>
        @if(isset($h['transcript']) && $h['transcript'])
        <span class="ms-auto badge bg-info text-dark">GPA: {{ number_format($h['transcript']->gpa, 2) }}</span>
        <span class="badge {{ $h['transcript']->is_passed ? 'bg-success' : 'bg-danger' }}">
            {{ $h['transcript']->is_passed ? 'Passed' : 'Failed' }}
        </span>
        @endif
    </div>
    <div class="card-body p-0">
        @if($h['results']->count())
        <div class="table-responsive">
            <table class="table table-sm mb-0" style="font-size:0.82rem">
                <thead><tr><th>Exam</th><th>Score</th><th>%</th><th>Grade</th><th>Status</th></tr></thead>
                <tbody>
                    @foreach($h['results'] as $er)
                    <tr>
                        <td>{{ $er->exam->title ?? '—' }}</td>
                        <td>{{ $er->obtained_marks }}/{{ $er->total_marks }}</td>
                        <td>{{ $er->percentage }}%</td>
                        <td><span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">{{ $er->grade }}</span></td>
                        <td>
                            <span class="badge {{ $er->is_passed ? 'bg-success' : 'bg-danger' }}">
                                {{ $er->is_passed ? 'Passed' : 'Failed' }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="p-3 text-muted small">No exam results archived for this period.</div>
        @endif
    </div>
</div>
@empty
<div class="card"><div class="card-body text-center text-muted py-5">No academic records found for this student.</div></div>
@endforelse

@endsection
