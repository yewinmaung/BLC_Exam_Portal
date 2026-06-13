@extends('layouts.app')
@section('title', 'Students')
@section('page-title', 'Students')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Students'],
    ]])
@endsection
@section('sidebar')@endsection

@section('content')
<div class="page-header">
    <div></div>
    @if(Route::has('admin.students.create'))
    <a href="{{ route('admin.students.create') }}" class="btn btn-primary">
        <i class="bi bi-person-plus me-1"></i> Add Student
    </a>
    @endif
</div>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-mortarboard me-2"></i>All Students</span>
        <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">
            {{ $students->total() }} total
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table datatable mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Enrollments</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($students as $s)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#0e6b6b,#0d9488);color:#fff;display:flex;align-items:center;justify-content:center;font-size:0.72rem;font-weight:700;flex-shrink:0">
                                    {{ strtoupper(substr($s->name,0,1)) }}
                                </div>
                                <span style="font-weight:600">{{ $s->name }}</span>
                            </div>
                        </td>
                        <td class="text-muted">{{ $s->email }}</td>
                        <td>
                            <span class="badge" style="background:#ede9fe;color:#3730a3">
                                {{ $s->enrollments_count }}
                            </span>
                        </td>
                        <td>
                            @if($s->is_active)
                                <span class="status-pill status-approved">Active</span>
                            @else
                                <span class="status-pill status-closed">Inactive</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="{{ route('admin.students.show', $s) }}" class="btn btn-sm btn-outline-primary" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('admin.students.edit', $s) }}" class="btn btn-sm btn-outline-secondary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('admin.students.destroy', $s) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Permanently delete {{ addslashes($s->name) }}?')">
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
                            <i class="bi bi-mortarboard d-block mb-2" style="font-size:2rem;opacity:0.3"></i>
                            No students found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($students->hasPages())
        <div class="p-3 border-top">{{ $students->links() }}</div>
        @endif
    </div>
</div>
@endsection
