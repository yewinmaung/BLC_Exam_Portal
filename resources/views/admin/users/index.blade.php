@extends('layouts.app')
@section('title', 'Users')
@section('page-title', 'Users')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Users'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection
@section('content')
<div class="page-header">
    <div></div>
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
        <i class="bi bi-person-plus me-1"></i> Add User
    </a>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-people me-2"></i>All Users</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table datatable mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Year</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $u)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--blc-navy),var(--blc-navy-2));display:flex;align-items:center;justify-content:center;color:#fff;font-size:0.75rem;font-weight:700;flex-shrink:0">
                                    {{ strtoupper(substr($u->name,0,1)) }}
                                </div>
                                <span class="fw-500" style="font-weight:500">{{ $u->name }}</span>
                            </div>
                        </td>
                        <td class="text-muted">{{ $u->email }}</td>
                        <td>
                            <span class="badge" style="background:var(--blc-gold-light);color:var(--blc-navy);font-weight:600">
                                {{ $u->role->name ?? '—' }}
                            </span>
                        </td>
                        <td>
                            @if($u->isStudent() && $u->academic_year)
                            <span class="badge bg-primary-subtle text-primary">Year {{ $u->academic_year }}</span>
                            @else
                            <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($u->is_active)
                                <span class="status-pill status-approved">Active</span>
                            @else
                                <span class="status-pill status-closed">Inactive</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex gap-1 flex-wrap">
                                @if($u->isTeacher())
                                <a href="{{ route('admin.teachers.show', $u) }}" class="btn btn-sm btn-outline-info" title="Teacher profile">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @endif
                                @if($u->isStudent())
                                <a href="{{ route('admin.students.show', $u) }}" class="btn btn-sm btn-outline-info" title="Student courses">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @endif
                                <a href="{{ route('admin.users.edit', $u) }}" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>

                                @if($u->id !== auth()->id())
                                    {{-- Terminate / Reinstate --}}
                                    @if($u->is_active)
                                    <form action="{{ route('admin.users.terminate', $u) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Suspend {{ addslashes($u->name) }}? They will be logged out and notified by email.')">
                                        @csrf
                                        <button class="btn btn-sm btn-warning" title="Suspend account">
                                            <i class="bi bi-slash-circle"></i>
                                        </button>
                                    </form>
                                    @else
                                    <form action="{{ route('admin.users.update', $u) }}" method="POST" class="d-inline">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="name"     value="{{ $u->name }}">
                                        <input type="hidden" name="email"    value="{{ $u->email }}">
                                        <input type="hidden" name="role_id"  value="{{ $u->role_id }}">
                                        <input type="hidden" name="academic_year"  value="{{ $u->academic_year }}">
                                        <input type="hidden" name="is_active" value="1">
                                        <button class="btn btn-sm btn-success" title="Reinstate account">
                                            <i class="bi bi-check-circle"></i>
                                        </button>
                                    </form>
                                    @endif

                                    {{-- Permanent delete --}}
                                    <form action="{{ route('admin.users.destroy', $u) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('PERMANENTLY delete {{ addslashes($u->name) }}? This cannot be undone.')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Permanently delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($users->hasPages())
        <div class="p-3 border-top">{{ $users->links() }}</div>
        @endif
    </div>
</div>
@endsection
