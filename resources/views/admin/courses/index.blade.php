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

{{-- ── Search & Filter bar ── --}}
<div class="card mb-3">
    <div class="card-body py-3 px-3">
        <form method="GET" action="{{ route('admin.courses.index') }}" id="filterForm">
            <div class="row g-2 align-items-end">

                {{-- Search by name or code --}}
                <div class="col-12 col-md-4">
                    <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600;color:var(--text-3)">Search</label>
                    <div class="input-group">
                        <span class="input-group-text" style="background:#f8f9fc;border-right:0;border-color:#e2e8f0">
                            <i class="bi bi-search" style="color:#9ca3af;font-size:0.8rem"></i>
                        </span>
                        <input type="text"
                               name="search"
                               value="{{ $search }}"
                               class="form-control"
                               style="border-left:0;border-color:#e2e8f0;font-size:0.855rem"
                               placeholder="Course name or code…"
                               maxlength="100"
                               autocomplete="off">
                        @if($search)
                        <a href="{{ route('admin.courses.index', request()->except('search','page')) }}"
                           class="input-group-text text-muted"
                           style="background:#f8f9fc;border-color:#e2e8f0;text-decoration:none" title="Clear">
                            <i class="bi bi-x"></i>
                        </a>
                        @endif
                    </div>
                </div>

                {{-- Year Level --}}
                <div class="col-6 col-md-2">
                    <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600;color:var(--text-3)">Year</label>
                    <select name="year_level" class="form-select form-select-sm" style="border-color:#e2e8f0;font-size:0.855rem">
                        <option value="">All Years</option>
                        @foreach($yearLevels as $val => $label)
                            @if($val > 0)
                            <option value="{{ $val }}" {{ (string)$yearLevel === (string)$val ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                            @endif
                        @endforeach
                    </select>
                </div>

                {{-- Academic Year --}}
                <div class="col-6 col-md-2">
                    <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600;color:var(--text-3)">Academic Year</label>
                    <select name="academic_year_id" class="form-select form-select-sm" style="border-color:#e2e8f0;font-size:0.855rem">
                        <option value="">All Academic Years</option>
                        @foreach($academicYears as $ay)
                        <option value="{{ $ay->id }}" {{ $academicYearId == $ay->id ? 'selected' : '' }}>
                            {{ $ay->name }}{{ $ay->is_current ? ' (Current)' : '' }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Major --}}
                <div class="col-6 col-md-2">
                    <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600;color:var(--text-3)">Major</label>
                    <select name="major_id" class="form-select form-select-sm" style="border-color:#e2e8f0;font-size:0.855rem">
                        <option value="">All Majors</option>
                        @foreach($majors as $m)
                        <option value="{{ $m->id }}" {{ $majorId == $m->id ? 'selected' : '' }}>
                            {{ $m->code }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Status --}}
                <div class="col-6 col-md-1">
                    <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600;color:var(--text-3)">Status</label>
                    <select name="status" class="form-select form-select-sm" style="border-color:#e2e8f0;font-size:0.855rem">
                        <option value="">All</option>
                        <option value="active"   {{ $status === 'active'   ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>

                {{-- Buttons --}}
                <div class="col-12 col-md-1 d-flex gap-1 align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm px-3 w-100">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                    @if($search || $yearLevel !== null || $academicYearId || $majorId || $status)
                    <a href="{{ route('admin.courses.index') }}"
                       class="btn btn-outline-secondary btn-sm" title="Clear all">
                        <i class="bi bi-x-lg"></i>
                    </a>
                    @endif
                </div>

            </div>

            {{-- Active filter chips --}}
            @php
                $activeFilters = array_filter([
                    $search         ? "Search: \"{$search}\""                                                    : null,
                    $yearLevel      ? 'Year: ' . ($yearLevels[$yearLevel] ?? $yearLevel)                         : null,
                    $academicYearId ? 'Academic Year: ' . ($academicYears->firstWhere('id',$academicYearId)?->name ?? $academicYearId) : null,
                    $majorId        ? 'Major: '  . ($majors->firstWhere('id',$majorId)?->code ?? $majorId)       : null,
                    $status         ? 'Status: ' . ucfirst($status)                                              : null,
                ]);
            @endphp
            @if($activeFilters)
            <div class="mt-2 d-flex flex-wrap gap-1">
                @foreach($activeFilters as $chip)
                <span class="badge" style="background:#eef2ff;color:#3730a3;font-size:0.75rem;font-weight:500;padding:0.3rem 0.6rem">
                    <i class="bi bi-funnel-fill me-1" style="font-size:0.65rem"></i>{{ $chip }}
                </span>
                @endforeach
            </div>
            @endif
        </form>
    </div>
</div>

{{-- ── Table ── --}}
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-book me-2"></i>All Courses</span>
        <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">
            {{ $courses->total() }} total
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Title</th>
                        <th>Year</th>
                        <th>Sem</th>
                        <th>Academic Year</th>
                        <th>Major</th>
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
                        <td class="text-muted small">
                            {{ \App\Models\Course::$yearLevelLabels[$c->year_level] ?? '—' }}
                        </td>
                        <td class="text-muted small">
                            @if($c->semester == 1) Sem 1
                            @elseif($c->semester == 2) Sem 2
                            @else Both
                            @endif
                        </td>
                        <td class="text-muted small">
                            @if($c->academicYear)
                                {{ $c->academicYear->name }}
                                @if($c->academicYear->is_current)
                                    <span class="badge ms-1" style="background:#fef9c3;color:#854d0e;font-size:0.65rem">Current</span>
                                @endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($c->major)
                                <span class="badge" style="background:#eff6ff;color:#1d4ed8;font-size:0.7rem;font-weight:700" title="{{ $c->major->name }}">
                                    {{ $c->major->code }}
                                </span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td class="text-muted">
                            @if($c->teacher)
                                {{ $c->teacher->name }}
                            @else
                                <span class="text-danger small"><i class="bi bi-exclamation-triangle me-1"></i>Unassigned</span>
                            @endif
                        </td>
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
                        <td colspan="10" class="text-center py-5 text-muted">
                            <i class="bi bi-book d-block mb-2" style="font-size:2rem;opacity:0.3"></i>
                            @if($search || $yearLevel !== null || $academicYearId || $majorId || $status)
                                No courses found matching the selected filters.
                                <div class="mt-2">
                                    <a href="{{ route('admin.courses.index') }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i>Clear filters
                                    </a>
                                </div>
                            @else
                                No courses found.
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($courses->hasPages())
        <div class="p-3 border-top d-flex align-items-center justify-content-between flex-wrap gap-2">
            <span class="text-muted" style="font-size:0.8rem">
                Showing {{ $courses->firstItem() }} to {{ $courses->lastItem() }} of {{ $courses->total() }} entries
            </span>
            {{ $courses->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
