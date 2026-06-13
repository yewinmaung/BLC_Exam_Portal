@extends('layouts.app')
@section('title', 'Teachers')
@section('page-title', 'Teachers')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Teachers'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')
<div class="page-header">
    <div></div>
    <a href="{{ route('admin.teachers.create') }}" class="btn btn-primary">
        <i class="bi bi-person-plus me-1"></i> Add Teacher
    </a>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-person-workspace me-2"></i>All Teachers</span>
        <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">
            {{ $teachers->total() }} total
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table datatable mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Courses</th>
                        <th>Exams</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($teachers as $t)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#1e1b6e,#3730a3);color:#fff;display:flex;align-items:center;justify-content:center;font-size:0.72rem;font-weight:700;flex-shrink:0">
                                    {{ strtoupper(substr($t->name,0,1)) }}
                                </div>
                                <span style="font-weight:600">{{ $t->name }}</span>
                            </div>
                        </td>
                        <td class="text-muted">{{ $t->email }}</td>
                        <td class="text-muted">{{ $t->phone ?? '—' }}</td>
                        <td>
                            <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">
                                {{ $t->taught_courses_count }}
                            </span>
                        </td>
                        <td>
                            <span class="badge" style="background:#f0fdf4;color:#166534">
                                {{ $t->exams_as_teacher_count }}
                            </span>
                        </td>
                        <td>
                            @if($t->is_active)
                                <span class="status-pill status-approved">Active</span>
                            @else
                                <span class="status-pill status-closed">Inactive</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="{{ route('admin.teachers.show', $t) }}" class="btn btn-sm btn-outline-primary" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('admin.teachers.edit', $t) }}" class="btn btn-sm btn-outline-secondary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-person-workspace d-block mb-2" style="font-size:2rem;opacity:0.3"></i>
                            No teachers found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($teachers->hasPages())
        <div class="p-3 border-top">{{ $teachers->links() }}</div>
        @endif
    </div>
</div>
@endsection
