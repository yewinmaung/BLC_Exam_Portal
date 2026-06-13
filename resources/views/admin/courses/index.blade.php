@extends('layouts.app')
@section('title', 'Courses')
@section('page-title', 'Courses')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Courses'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')
<div class="page-header">
    <div></div>
    <a href="{{ route('admin.courses.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i> Add Course
    </a>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-book me-2"></i>All Courses</span>
        <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">
            {{ $courses->total() }} total
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table datatable mb-0">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Title</th>
                        <th>Teacher</th>
                        <th>Students</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($courses as $c)
                    <tr>
                        <td>
                            <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3);font-weight:700">
                                {{ $c->code }}
                            </span>
                        </td>
                        <td style="font-weight:600;color:var(--text-1,#111827)">{{ $c->title }}</td>
                        <td class="text-muted">{{ $c->teacher->name ?? '—' }}</td>
                        <td>
                            <span class="badge" style="background:#f0fdf4;color:#166534">
                                {{ $c->enrollments->count() }}
                            </span>
                        </td>
                        <td>
                            @if($c->is_active)
                                <span class="status-pill status-approved">Active</span>
                            @else
                                <span class="status-pill status-closed">Inactive</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="{{ route('admin.courses.edit', $c) }}" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('admin.courses.destroy', $c) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Delete course {{ addslashes($c->title) }}?')">
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
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-book d-block mb-2" style="font-size:2rem;opacity:0.3"></i>
                            No courses found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($courses->hasPages())
        <div class="p-3 border-top">{{ $courses->links() }}</div>
        @endif
    </div>
</div>
@endsection
