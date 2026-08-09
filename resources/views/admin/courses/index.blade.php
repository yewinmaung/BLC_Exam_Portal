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

{{-- ══ FILTER BAR ══════════════════════════════════════════════════════════ --}}
<div class="card mb-3">
    <div class="card-body py-3 px-3">
        <form method="GET" action="{{ route('admin.courses.index') }}" id="filterForm">
            <div class="row g-2 align-items-end">

                <div class="col-12 col-md-4">
                    <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600;color:var(--text-3)">Search</label>
                    <div class="input-group">
                        <span class="input-group-text" style="background:#f8f9fc;border-right:0;border-color:#e2e8f0">
                            <i class="bi bi-search" style="color:#9ca3af;font-size:0.8rem"></i>
                        </span>
                        <input type="text" name="search" value="{{ $search }}"
                               class="form-control" style="border-left:0;border-color:#e2e8f0;font-size:0.855rem"
                               placeholder="Course name or code…" maxlength="100" autocomplete="off">
                        @if($search)
                        <a href="{{ route('admin.courses.index', request()->except('search','page')) }}"
                           class="input-group-text text-muted" style="background:#f8f9fc;border-color:#e2e8f0;text-decoration:none" title="Clear">
                            <i class="bi bi-x"></i>
                        </a>
                        @endif
                    </div>
                </div>

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

                <div class="col-6 col-md-2">
                    <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600;color:var(--text-3)">Major</label>
                    <select name="major_id" class="form-select form-select-sm" style="border-color:#e2e8f0;font-size:0.855rem">
                        <option value="">All Majors</option>
                        @foreach($majors as $m)
                        <option value="{{ $m->id }}" {{ $majorId == $m->id ? 'selected' : '' }}>{{ $m->code }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-1">
                    <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600;color:var(--text-3)">Status</label>
                    <select name="status" class="form-select form-select-sm" style="border-color:#e2e8f0;font-size:0.855rem">
                        <option value="">All</option>
                        <option value="active"   {{ $status === 'active'   ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>

                <div class="col-12 col-md-1 d-flex gap-1 align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm px-3 w-100">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                    @if($search || $yearLevel !== null || $academicYearId || $majorId || $status)
                    <a href="{{ route('admin.courses.index') }}" class="btn btn-outline-secondary btn-sm" title="Clear all">
                        <i class="bi bi-x-lg"></i>
                    </a>
                    @endif
                </div>

            </div>

            {{-- Active filter chips --}}
            @php
                $activeFilters = array_filter([
                    $search         ? "Search: \"{$search}\""                                                          : null,
                    $yearLevel      ? 'Year: '          . ($yearLevels[$yearLevel] ?? $yearLevel)                      : null,
                    $academicYearId ? 'Academic Year: ' . ($academicYears->firstWhere('id',$academicYearId)?->name ?? $academicYearId) : null,
                    $majorId        ? 'Major: '         . ($majors->firstWhere('id',$majorId)?->code ?? $majorId)      : null,
                    $status         ? 'Status: '        . ucfirst($status)                                             : null,
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

{{-- ══ RESULTS SUMMARY ═════════════════════════════════════════════════════ --}}
<div class="d-flex align-items-center justify-content-between mb-3 px-1">
    <span class="text-muted" style="font-size:0.82rem">
        <i class="bi bi-book me-1"></i>
        {{ $courses->count() }} course{{ $courses->count() !== 1 ? 's' : '' }} found
    </span>
</div>

@if($courses->isEmpty())

<div class="card">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-book d-block mb-2" style="font-size:3rem;opacity:0.3"></i>
        <h6>No courses found</h6>
        @if($search || $yearLevel !== null || $academicYearId || $majorId || $status)
            <p class="small mb-2">No courses match the selected filters.</p>
            <a href="{{ route('admin.courses.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-counterclockwise me-1"></i>Clear filters
            </a>
        @else
            <p class="small mb-0"><a href="{{ route('admin.courses.create') }}">Add the first course</a> to get started.</p>
        @endif
    </div>
</div>

@else

{{-- ══ ACCORDION: Year Level → Semester → Academic Year → Courses ═════════ --}}
@foreach($grouped as $yl => $semGroups)
@php
    $ylLabel  = \App\Models\Course::$yearLevelLabels[$yl] ?? 'Year ' . $yl;
    $ylKey    = 'yl_' . $yl;
    $ylCourseCount = 0;
    foreach ($semGroups as $ayGroups) {
        foreach ($ayGroups as $cList) {
            $ylCourseCount += count($cList);
        }
    }
@endphp

{{-- ── Level 1: Year Level ────────────────────────────────────────── --}}
<div class="card mb-3" style="border:1.5px solid #e2e8f0;border-radius:12px;overflow:hidden">

    <div class="d-flex align-items-center justify-content-between px-4 py-3"
         style="background:var(--blc-navy,#0b2a5b);cursor:pointer;user-select:none"
         onclick="toggleSection('{{ $ylKey }}')">
        <div class="d-flex align-items-center gap-3">
            <i class="bi bi-mortarboard" style="color:#93c5fd;font-size:1.1rem"></i>
            <span style="font-weight:700;font-size:1rem;color:#fff">{{ $ylLabel }}</span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="badge" style="background:rgba(255,255,255,0.15);color:#fff;font-size:0.73rem">
                {{ $ylCourseCount }} course{{ $ylCourseCount !== 1 ? 's' : '' }}
            </span>
            <i class="bi bi-chevron-down yl-chevron-{{ $ylKey }}"
               style="color:#93c5fd;font-size:0.85rem;transition:transform 0.2s"></i>
        </div>
    </div>

    <div id="{{ $ylKey }}" style="display:block">
    @foreach($semGroups as $sem => $ayGroups)
    @php
        $semLabel = \App\Models\Course::$semesterLabels[$sem] ?? 'Semester ' . $sem;
        $semKey   = $ylKey . '_sem' . $sem;
        $semCount = 0;
        foreach ($ayGroups as $cList) { $semCount += count($cList); }
    @endphp

    {{-- ── Level 2: Semester ──────────────────────────────────────── --}}
    <div style="border-top:1px solid #e2e8f0">

        <div class="d-flex align-items-center justify-content-between px-4 py-2"
             style="background:#f8faff;cursor:pointer;user-select:none"
             onclick="toggleSection('{{ $semKey }}')">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-calendar2-week" style="color:var(--blc-navy,#0b2a5b);font-size:0.95rem"></i>
                <span style="font-weight:700;color:var(--blc-navy,#0b2a5b);font-size:0.9rem">{{ $semLabel }}</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="text-muted" style="font-size:0.78rem">{{ $semCount }} course{{ $semCount !== 1 ? 's' : '' }}</span>
                <i class="bi bi-chevron-down sem-chevron-{{ $semKey }}"
                   style="font-size:0.78rem;color:#9ca3af;transition:transform 0.2s"></i>
            </div>
        </div>

        <div id="{{ $semKey }}" style="display:block;padding:0.75rem 1.25rem 1rem">

            @foreach($ayGroups as $ayId => $courseList)
            @php
                $ay    = $ayMap[$ayId] ?? null;
                $ayLabel = $ay ? $ay->name : 'Unassigned Academic Year';
                $ayKey  = $semKey . '_ay' . $ayId;
            @endphp

            {{-- ── Level 3: Academic Year ──────────────────────────── --}}
            <div class="mb-3" style="border:1.5px solid #e2e8f0;border-radius:10px;overflow:hidden">

                <div class="d-flex align-items-center justify-content-between px-3 py-2"
                     style="background:#fff;cursor:pointer"
                     onclick="toggleSection('{{ $ayKey }}')">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-calendar3" style="color:var(--blc-navy,#0b2a5b)"></i>
                        <span style="font-weight:700;color:var(--blc-navy,#0b2a5b);font-size:0.88rem">
                            Academic Year: {{ $ayLabel }}
                        </span>
                        @if($ay?->is_current)
                        <span class="badge" style="background:#fef9c3;color:#854d0e;font-size:0.65rem;font-weight:700">Current</span>
                        @endif
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="small text-muted">{{ count($courseList) }} course{{ count($courseList) !== 1 ? 's' : '' }}</span>
                        <i class="bi bi-chevron-down ay-chevron-{{ $ayKey }}"
                           style="font-size:0.8rem;color:#6b7280;transition:transform 0.2s"></i>
                    </div>
                </div>

                {{-- ── Level 4: Course Cards ────────────────────────── --}}
                <div id="{{ $ayKey }}"
                     style="display:block;border-top:1px solid #e2e8f0;background:#f8faff;padding:0.75rem 0.875rem">

                    <div class="row g-2">
                    @foreach($courseList as $c)
                    <div class="col-12 col-lg-6">
                        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:9px;padding:0.875rem 1rem;height:100%">

                            {{-- Top row: code badge + status + actions --}}
                            <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="badge" style="background:#ede9fe;color:#3730a3;font-weight:700;font-size:0.72rem">
                                        {{ $c->code }}
                                    </span>
                                    @if($c->major)
                                    <span class="badge" style="background:#eff6ff;color:#1d4ed8;font-size:0.68rem;font-weight:700"
                                          title="{{ $c->major->name }}">
                                        {{ $c->major->code }}
                                    </span>
                                    @endif
                                    @if($c->is_active)
                                        <span class="badge" style="background:#f0fdf4;color:#166534;font-size:0.68rem;font-weight:600">Active</span>
                                    @else
                                        <span class="badge" style="background:#f3f4f6;color:#6b7280;font-size:0.68rem;font-weight:600">Inactive</span>
                                    @endif
                                </div>
                                <div class="d-flex gap-1 flex-shrink-0">
                                    <a href="{{ route('admin.courses.edit', $c) }}"
                                       class="btn btn-sm btn-outline-primary"
                                       style="font-size:0.72rem;padding:3px 8px" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form action="{{ route('admin.courses.destroy', $c) }}" method="POST"
                                          onsubmit="return confirm('Delete course &quot;{{ addslashes($c->title) }}&quot;?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"
                                                style="font-size:0.72rem;padding:3px 8px" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            {{-- Course title --}}
                            <div style="font-weight:700;color:var(--blc-navy,#0b2a5b);font-size:0.9rem;margin-bottom:0.5rem;line-height:1.3">
                                {{ $c->title }}
                            </div>

                            {{-- Meta row --}}
                            <div class="d-flex flex-wrap gap-3" style="font-size:0.78rem;color:#6b7280">

                                <div class="d-flex align-items-center gap-1">
                                    <i class="bi bi-person-badge" style="color:#9ca3af"></i>
                                    @if($c->teacher)
                                        <span>{{ $c->teacher->name }}</span>
                                    @else
                                        <span class="text-danger" style="font-size:0.73rem">
                                            <i class="bi bi-exclamation-triangle me-1"></i>Unassigned
                                        </span>
                                    @endif
                                </div>

                                <div class="d-flex align-items-center gap-1">
                                    <i class="bi bi-people" style="color:#9ca3af"></i>
                                    <span>{{ $c->enrollments->count() }} student{{ $c->enrollments->count() !== 1 ? 's' : '' }}</span>
                                </div>

                            </div>

                        </div>
                    </div>
                    @endforeach
                    </div>{{-- /row --}}

                </div>{{-- /ay body --}}
            </div>{{-- /ay card --}}

            @endforeach {{-- academic years --}}

        </div>{{-- /semester body --}}
    </div>{{-- /semester block --}}

    @endforeach {{-- semesters --}}
    </div>{{-- /yl body --}}

</div>{{-- /yl card --}}
@endforeach {{-- year levels --}}

@endif
@endsection

@push('scripts')
<script>
(function () {
    window.toggleSection = function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        var isOpen = el.style.display !== 'none';
        el.style.display = isOpen ? 'none' : 'block';

        var prefixes = ['yl-chevron-', 'sem-chevron-', 'ay-chevron-'];
        for (var i = 0; i < prefixes.length; i++) {
            var ch = document.querySelector('.' + prefixes[i] + id);
            if (ch) {
                ch.style.transform = isOpen ? '' : 'rotate(180deg)';
                break;
            }
        }
    };
})();
</script>
@endpush
