@extends('layouts.app')
@section('title', $major->name . ' — Courses')
@section('page-title', $major->name)
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin',  'url' => route('admin.dashboard')],
        ['label' => 'Majors', 'url' => route('admin.majors.index')],
        ['label' => $major->name],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')

{{-- ── Header ── --}}
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div class="d-flex align-items-center gap-3">
        <div>
            <span class="badge fs-6" style="background:#eff6ff;color:#1d4ed8;font-weight:700">
                {{ $major->code }}
            </span>
        </div>
        <div>
            <h5 class="mb-0" style="font-weight:800;color:var(--blc-navy,#0b2a5b)">{{ $major->name }}</h5>
            @if($major->description)
            <p class="text-muted small mb-0">{{ $major->description }}</p>
            @endif
        </div>
        <span class="status-pill status-{{ $major->is_active ? 'approved' : 'closed' }}">
            {{ $major->is_active ? 'Active' : 'Inactive' }}
        </span>
    </div>
    <a href="{{ route('admin.majors.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Majors
    </a>
</div>

{{-- ── Active academic year notice ── --}}
<div class="card mb-3">
    <div class="card-body py-2 d-flex align-items-center gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-calendar3" style="color:var(--blc-gold,#d4a51c)"></i>
            <span class="small fw-semibold">Academic Year:</span>
            @if($currentYear)
                <span class="badge" style="background:var(--blc-gold-light,#fef9ec);color:var(--blc-navy,#0b2a5b);font-weight:700">
                    {{ $currentYear->name }} (Current)
                </span>
            @else
                <span class="text-muted small">No active academic year set.</span>
            @endif
        </div>
        <div class="text-muted small ms-auto">
            Showing only active courses for the current academic year.
        </div>
    </div>
</div>

{{-- ── Courses table ── --}}
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-book me-2"></i>Courses — {{ $major->name }}</span>
        <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">
            {{ $courses->count() }} course{{ $courses->count() !== 1 ? 's' : '' }}
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table datatable mb-0">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Title</th>
                        <th>Year Level</th>
                        <th>Semester</th>
                        <th>Academic Year</th>
                        <th>Teacher</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($courses as $c)
                    <tr>
                        <td>
                            <span class="badge" style="background:#eff6ff;color:#1d4ed8;font-weight:700">
                                {{ $c->code }}
                            </span>
                        </td>
                        <td style="font-weight:600;color:var(--blc-navy,#0b2a5b)">{{ $c->title }}</td>
                        <td>
                            <span class="badge" style="background:#f0f4ff;color:var(--blc-navy-2,#0f3a7a)">
                                {{ \App\Models\Course::$yearLevelLabels[$c->year_level] ?? 'All Years' }}
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-secondary-subtle text-secondary">
                                {{ \App\Models\Course::$semesterLabels[$c->semester] ?? 'Sem '.$c->semester }}
                            </span>
                        </td>
                        <td class="text-muted small">
                            {{ $c->academicYear?->name ?? '—' }}
                        </td>
                        <td class="text-muted small">
                            {{ $c->teacher?->name ?? '—' }}
                        </td>
                        <td>
                            <span class="status-pill status-{{ $c->is_active ? 'approved' : 'closed' }}">
                                {{ $c->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-book d-block mb-2" style="font-size:2rem;opacity:0.3"></i>
                            @if($currentYear)
                                No active courses found for <strong>{{ $major->name }}</strong>
                                in <strong>{{ $currentYear->name }}</strong>.
                            @else
                                No active academic year is set. Please set one to filter courses.
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
