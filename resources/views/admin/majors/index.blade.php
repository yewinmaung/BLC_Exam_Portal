@extends('layouts.app')
@section('title', 'Majors')
@section('page-title', 'Majors')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Majors'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')
<div class="page-header">
    <div></div>
    <a href="{{ route('admin.majors.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i> Add Major
    </a>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-collection me-2"></i>All Majors</span>
        <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">
            {{ $majors->total() }} total
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Courses</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($majors as $m)
                    <tr>
                        <td>
                            <span class="badge" style="background:#eff6ff;color:#1d4ed8;font-weight:700">{{ $m->code }}</span>
                        </td>
                        <td style="font-weight:600">{{ $m->name }}</td>
                        <td>
                            <a href="{{ route('admin.majors.show', $m) }}"
                               class="badge text-decoration-none"
                               style="background:#f0fdf4;color:#166534"
                               title="View courses">
                                {{ $m->courses_count }}
                            </a>
                        </td>
                        <td>
                            @if($m->is_active)
                                <span class="status-pill status-approved">Active</span>
                            @else
                                <span class="status-pill status-closed">Inactive</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="{{ route('admin.majors.show', $m) }}" class="btn btn-sm btn-outline-info" title="View courses">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('admin.majors.edit', $m) }}" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('admin.majors.destroy', $m) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Delete major {{ addslashes($m->name) }}?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="bi bi-collection d-block mb-2" style="font-size:2rem;opacity:0.3"></i>
                            No majors found. Create one to start assigning courses.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($majors->hasPages())
        <div class="p-3 border-top d-flex align-items-center justify-content-between flex-wrap gap-2">
            <span class="text-muted" style="font-size:0.8rem">
                Showing {{ $majors->firstItem() }} to {{ $majors->lastItem() }} of {{ $majors->total() }} entries
            </span>
            {{ $majors->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
