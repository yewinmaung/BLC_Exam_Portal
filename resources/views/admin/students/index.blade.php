@extends('layouts.app')
@section('title', 'Students')
@section('page-title', 'Students')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Students'],
    ]])
@endsection
@section('sidebar')
@include('partials.admin-sidebar')
@endsection

@section('content')
<div class="page-header">
    <div></div>
    @if(Route::has('admin.students.create'))
    <a href="{{ route('admin.students.create') }}" class="btn btn-primary">
        <i class="bi bi-person-plus me-1"></i> Add Student
    </a>
    @endif
</div>

{{-- ── Search & Filter ── --}}
<div class="card mb-3">
    <div class="card-body py-2 px-3">
        <form method="GET" action="{{ route('admin.students.index') }}">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                {{-- Text search --}}
                <div class="input-group" style="max-width:320px">
                    <span class="input-group-text" style="background:#f8f9fc;border-right:0;border-color:#e2e8f0">
                        <i class="bi bi-search" style="color:#9ca3af;font-size:0.8rem"></i>
                    </span>
                    <input type="text" name="search" value="{{ request('search') }}"
                           class="form-control" style="border-left:0;border-color:#e2e8f0;font-size:0.855rem"
                           placeholder="Name or email…" maxlength="100" autocomplete="off">
                    @if(request('search'))
                    <a href="{{ route('admin.students.index', request()->except('search','page')) }}"
                       class="input-group-text text-muted" style="background:#f8f9fc;border-color:#e2e8f0;text-decoration:none">
                        <i class="bi bi-x"></i>
                    </a>
                    @endif
                </div>
                {{-- Year Level dropdown --}}
                <select name="year_level_id" class="form-select form-select-sm" style="max-width:150px;font-size:0.8rem">
                    <option value="">All Year Levels</option>
                    @foreach($yearLevels as $yl)
                    <option value="{{ $yl->id }}" {{ request('year_level_id') == $yl->id ? 'selected' : '' }}>
                        {{ $yl->name }}
                    </option>
                    @endforeach
                </select>
                {{-- Status dropdown --}}
                <select name="status" class="form-select form-select-sm" style="max-width:130px;font-size:0.8rem">
                    <option value="">All Status</option>
                    <option value="active"   {{ request('status') === 'active'   ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm px-3">
                    <i class="bi bi-funnel-fill me-1"></i>Filter
                </button>
                @if(request()->hasAny(['search','year_level_id','status']))
                <a href="{{ route('admin.students.index') }}" class="btn btn-outline-secondary btn-sm" title="Reset">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
                @endif
            </div>
        </form>
    </div>
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
            <table class="table mb-0">
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
        <div class="p-3 border-top d-flex align-items-center justify-content-between flex-wrap gap-2">
            <span class="text-muted" style="font-size:0.8rem">
                Showing {{ $students->firstItem() }} to {{ $students->lastItem() }} of {{ $students->total() }} entries
            </span>
            {{ $students->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
