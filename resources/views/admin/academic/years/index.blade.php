@extends('layouts.app')
@section('title', 'Academic Years')
@section('page-title', 'Academic Years')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Academic Years'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')
<div class="page-header">
    <div></div>
    <a href="{{ route('admin.academic.years.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i> New Academic Year
    </a>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-calendar3 me-2"></i>All Academic Years</span>
        <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">
            {{ $years->total() }} total
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table datatable mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Period</th>
                        <th>Enrolled Students</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($years as $y)
                    <tr>
                        <td style="font-weight:700;color:var(--text-1,#111827)">{{ $y->name }}</td>
                        <td class="text-muted">{{ $y->start_year }} – {{ $y->end_year }}</td>
                        <td>
                            <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">
                                {{ $y->student_year_records_count }} enrolled
                            </span>
                        </td>
                        <td>
                            @if($y->is_current)
                                <span class="status-pill status-approved">Current</span>
                            @else
                                <span class="status-pill status-draft">Past</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="{{ route('admin.academic.years.students', $y) }}"
                                   class="btn btn-sm btn-primary" title="Manage Students">
                                    <i class="bi bi-people"></i>
                                </a>
                                <a href="{{ route('admin.academic.years.show', $y) }}"
                                   class="btn btn-sm btn-outline-secondary" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('admin.academic.years.edit', $y) }}"
                                   class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('admin.academic.years.destroy', $y) }}"
                                      method="POST"
                                      onsubmit="return confirm('Delete {{ addslashes($y->name) }}? This cannot be undone.')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="bi bi-calendar-x d-block mb-2" style="font-size:2rem;opacity:0.35"></i>
                            No academic years yet.
                            <a href="{{ route('admin.academic.years.create') }}">Create one</a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
    </div>
</div>
@endsection
