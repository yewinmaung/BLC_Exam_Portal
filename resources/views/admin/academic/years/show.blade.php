@extends('layouts.app')
@section('title', $year->name)
@section('page-title', $year->name)
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Academic Years', 'url' => route('admin.academic.years.index')],
        ['label' => $year->name],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')
<div class="d-flex align-items-center gap-3 mb-4">
    <div>
        <h5 style="font-weight:800;color:var(--text-1,#111827);margin:0">{{ $year->name }}</h5>
        <div class="text-muted small">{{ $year->start_year }} – {{ $year->end_year }}</div>
    </div>
    @if($year->is_current)
        <span class="status-pill status-approved">Current Year</span>
    @endif
    <div class="ms-auto d-flex gap-2">
        <a href="{{ route('admin.academic.years.students', $year) }}" class="btn btn-primary">
            <i class="bi bi-people me-1"></i> Manage Students
        </a>
        <a href="{{ route('admin.academic.years.edit', $year) }}" class="btn btn-outline-primary">
            <i class="bi bi-pencil me-1"></i> Edit
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-people me-2"></i>Enrolled Students</span>
        <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">
            {{ $records->total() }} total
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table datatable mb-0">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Year Level</th>
                        <th>Semester</th>
                        <th>Department</th>
                        <th>GPA</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $r)
                    <tr>
                        <td>
                            <div style="font-weight:600;font-size:0.875rem">{{ $r->student->name }}</div>
                            <div style="font-size:0.72rem;color:#9ca3af">{{ $r->student->email }}</div>
                        </td>
                        <td>{{ $r->yearLevel->name ?? '—' }}</td>
                        <td>Semester {{ $r->semester }}</td>
                        <td class="text-muted">{{ $r->department ?? '—' }}</td>
                        <td>
                            @if($r->gpa)
                                <span class="badge" style="background:#f0fdf4;color:#166534;font-weight:700">
                                    {{ $r->gpa }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="status-pill status-{{ $r->status === 'active' ? 'approved' : ($r->status === 'promoted' ? 'published' : 'closed') }}">
                                {{ ucfirst($r->status) }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-people d-block mb-2" style="font-size:2rem;opacity:0.35"></i>
                            No students enrolled yet.
                            <a href="{{ route('admin.academic.years.students', $year) }}">Add students</a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
    </div>
</div>
@endsection
