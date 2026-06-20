@extends('layouts.app')
@section('title', $student->name)
@section('page-title', $student->name)
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Students', 'url' => route('admin.students.index')],
        ['label' => $student->name],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')
<div class="row g-3">

    {{-- Student info --}}
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-body text-center py-4">
                <div style="width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,#0e6b6b,#0d9488);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:800;margin:0 auto 1rem">
                    {{ strtoupper(substr($student->name,0,1)) }}
                </div>
                <h6 style="font-weight:800;margin-bottom:0.2rem">{{ $student->name }}</h6>
                <div class="text-muted small mb-2">{{ $student->email }}</div>
                @if($student->phone)
                <div class="text-muted small mb-2"><i class="bi bi-telephone me-1"></i>{{ $student->phone }}</div>
                @endif
                @if($student->is_active)
                    <span class="status-pill status-approved">Active</span>
                @else
                    <span class="status-pill status-closed">Suspended</span>
                @endif
            </div>
            <div class="card-footer d-flex gap-2">
                <a href="{{ route('admin.students.edit', $student) }}" class="btn btn-sm btn-primary flex-grow-1">
                    <i class="bi bi-pencil me-1"></i>Edit
                </a>
                <a href="{{ route('admin.results.student', $student) }}" class="btn btn-sm btn-outline-primary" title="View Results">
                    <i class="bi bi-bar-chart-line"></i>
                </a>
                <a href="{{ route('admin.academic.transcripts.show', $student) }}" class="btn btn-sm btn-outline-secondary" title="Transcript">
                    <i class="bi bi-file-earmark-text"></i>
                </a>
                <form action="{{ route('admin.students.destroy', $student) }}" method="POST"
                      onsubmit="return confirm('Permanently delete {{ addslashes($student->name) }}?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
            </div>
        </div>

        {{-- Current academic record --}}
        @if($yearRecords->count())
        <div class="card">
            <div class="card-header"><i class="bi bi-mortarboard me-2"></i>Academic Records</div>
            <div class="card-body p-0">
                @foreach($yearRecords as $yr)
                <div class="p-3 border-bottom">
                    <div style="font-weight:600;font-size:0.875rem">{{ $yr->academicYear->name }}</div>
                    <div class="text-muted small">{{ $yr->yearLevel->name }} · Sem {{ $yr->semester }}</div>
                    @if($yr->department)<div class="text-muted small">{{ $yr->department }}</div>@endif
                    @if($yr->gpa)<div><span class="badge" style="background:#f0fdf4;color:#166534;font-weight:700">GPA: {{ $yr->gpa }}</span></div>@endif
                    <span class="status-pill status-{{ $yr->status === 'active' ? 'approved' : ($yr->status === 'promoted' ? 'published' : 'closed') }}" style="font-size:0.68rem;margin-top:4px">
                        {{ ucfirst($yr->status) }}
                    </span>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    {{-- Enrolled courses --}}
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-book me-2"></i>Enrolled Courses</span>
                <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">
                    {{ $student->enrollments->count() }}
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr><th>Course</th><th>Code</th><th>Year Level</th><th>Teacher</th></tr>
                        </thead>
                        <tbody>
                            @forelse($student->enrollments as $e)
                            <tr>
                                <td style="font-weight:600">{{ $e->course->title ?? '—' }}</td>
                                <td class="text-muted">{{ $e->course->code ?? '—' }}</td>
                                <td>
                                    @php $yl = $e->course->year_level ?? 0; @endphp
                                    <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">
                                        {{ $yl > 0 ? (\App\Models\Course::$yearLevelLabels[$yl] ?? 'Year '.$yl) : 'All Years' }}
                                    </span>
                                </td>
                                <td class="text-muted">{{ $e->course->teacher->name ?? '—' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center py-4 text-muted">No courses enrolled.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
