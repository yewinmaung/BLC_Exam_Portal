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

{{-- ── Search & Filter bar ── --}}
<div class="card mb-3">
    <div class="card-body py-2 px-3">
        <form method="GET" action="{{ route('admin.academic.years.index') }}" id="filterForm">
            <div class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"
                              style="background:#f8f9fc;border-right:0;border-color:#e2e8f0">
                            <i class="bi bi-search" style="color:#9ca3af;font-size:0.8rem"></i>
                        </span>
                        <input type="text"
                               name="search"
                               value="{{ request('search') }}"
                               class="form-control"
                               style="border-left:0;border-color:#e2e8f0;font-size:0.855rem"
                               placeholder="Search by year name…"
                               maxlength="100"
                               autocomplete="off">
                        @if(request('search'))
                        <a href="{{ route('admin.academic.years.index') }}"
                           class="input-group-text text-muted"
                           style="background:#f8f9fc;border-color:#e2e8f0;text-decoration:none"
                           title="Clear search">
                            <i class="bi bi-x" style="font-size:1rem"></i>
                        </a>
                        @endif
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="year" class="form-select" style="font-size:0.855rem;border-color:#e2e8f0">
                        <option value="">All Years</option>
                        @foreach($availableYears as $year)
                            <option value="{{ $year }}" {{ request('year') == $year ? 'selected' : '' }}>
                                {{ $year }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select" style="font-size:0.855rem;border-color:#e2e8f0">
                        <option value="">All Status</option>
                        <option value="current" {{ request('status') == 'current' ? 'selected' : '' }}>Current</option>
                        <option value="past" {{ request('status') == 'past' ? 'selected' : '' }}>Past</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <div class="d-flex gap-1">
                        <button type="submit" class="btn btn-primary btn-sm px-3 w-100">
                            <i class="bi bi-funnel me-1"></i>Filter
                        </button>
                        @if(request()->hasAny(['search', 'year', 'status']))
                        <a href="{{ route('admin.academic.years.index') }}"
                           class="btn btn-outline-secondary btn-sm"
                           title="Clear all filters">
                            <i class="bi bi-x-lg"></i>
                        </a>
                        @endif
                    </div>
                </div>
            </div>
            @if(request()->hasAny(['search', 'year', 'status']))
            <div class="mt-2">
                <span class="badge" style="background:#eef2ff;color:#3730a3;font-size:0.75rem;font-weight:500">
                    @if(request('search'))
                        Search: "{{ request('search') }}"
                    @endif
                    @if(request('year'))
                        | Year: {{ request('year') }}
                    @endif
                    @if(request('status'))
                        | Status: {{ ucfirst(request('status')) }}
                    @endif
                </span>
            </div>
            @endif
        </form>
    </div>
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
            <table class="table mb-0">
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
                            @if(request()->hasAny(['search', 'year', 'status']))
                                No academic years found matching your filters.
                                <div class="mt-2">
                                    <a href="{{ route('admin.academic.years.index') }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i>Clear filters
                                    </a>
                                </div>
                            @else
                                No academic years yet.
                                <a href="{{ route('admin.academic.years.create') }}">Create one</a>
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($years->hasPages())
        <div class="p-3 border-top d-flex align-items-center justify-content-between flex-wrap gap-2">
            <span class="text-muted" style="font-size:0.8rem">
                Showing {{ $years->firstItem() }} to {{ $years->lastItem() }} of {{ $years->total() }} entries
            </span>
            {{ $years->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
