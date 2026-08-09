@extends('layouts.app')
@section('title', 'Exams')
@section('page-title', 'Exams')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Exams'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')

{{-- Filter Bar --}}
<div class="card mb-3">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('admin.exams.index') }}">

            {{-- Search row --}}
            <div class="mb-2">
                <div class="input-group">
                    <span class="input-group-text" style="background:#f8f9fc;border-right:0;border-color:#e2e8f0">
                        <i class="bi bi-search" style="color:#9ca3af;font-size:0.8rem"></i>
                    </span>
                    <input type="text" name="search"
                           value="{{ request('search') }}"
                           class="form-control"
                           style="border-left:0;border-color:#e2e8f0;font-size:0.855rem"
                           placeholder="Search by exam title…"
                           maxlength="100"
                           autocomplete="off">
                    @if(request('search'))
                    <a href="{{ route('admin.exams.index', request()->except('search','page')) }}"
                       class="input-group-text text-muted"
                       style="background:#f8f9fc;border-color:#e2e8f0;text-decoration:none" title="Clear search">
                        <i class="bi bi-x" style="font-size:1rem"></i>
                    </a>
                    @endif
                </div>
            </div>

            {{-- Filter dropdowns row --}}
            <div class="d-flex flex-wrap gap-2 align-items-end">

                {{-- Status --}}
                <div style="min-width:130px;flex:1">
                    <select name="status" class="form-select form-select-sm" style="font-size:0.8rem">
                        <option value="">All statuses</option>
                        @foreach(['draft','pending_approval','approved','published','closed'] as $s)
                        <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>
                            {{ ucfirst(str_replace('_',' ',$s)) }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Academic Year --}}
                <div style="min-width:140px;flex:1">
                    <select name="academic_year_id" class="form-select form-select-sm" style="font-size:0.8rem">
                        <option value="">All academic years</option>
                        @foreach($academicYears as $ay)
                        <option value="{{ $ay->id }}" {{ request('academic_year_id') == $ay->id ? 'selected' : '' }}>
                            {{ $ay->name }}{{ $ay->is_current ? ' (Current)' : '' }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Year Level --}}
                <div style="min-width:120px">
                    <select name="year_level" class="form-select form-select-sm" style="font-size:0.8rem">
                        <option value="">All levels</option>
                        @foreach($yearLevels as $yl)
                        <option value="{{ $yl->level }}" {{ request('year_level') == $yl->level ? 'selected' : '' }}>
                            {{ $yl->name }}
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- Semester --}}
                <div style="min-width:110px">
                    <select name="semester" class="form-select form-select-sm" style="font-size:0.8rem">
                        <option value="">All semesters</option>
                        <option value="1" {{ request('semester') == '1' ? 'selected' : '' }}>Semester 1</option>
                        <option value="2" {{ request('semester') == '2' ? 'selected' : '' }}>Semester 2</option>
                    </select>
                </div>

                {{-- Major --}}
                <div style="min-width:110px">
                    <select name="major_id" class="form-select form-select-sm" style="font-size:0.8rem">
                        <option value="">All majors</option>
                        @foreach($majors as $m)
                        <option value="{{ $m->id }}" {{ request('major_id') == $m->id ? 'selected' : '' }}>
                            {{ $m->code }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm px-3">
                        <i class="bi bi-funnel-fill me-1"></i>Filter
                    </button>
                    <a href="{{ route('admin.exams.index') }}"
                       class="btn btn-outline-secondary btn-sm" title="Reset all filters">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </div>

            {{-- Active filter tags --}}
            @php
                $activeFilters = array_filter([
                    'search'          => request('search'),
                    'status'          => request('status'),
                    'academic_year_id'=> request('academic_year_id'),
                    'year_level'      => request('year_level'),
                    'semester'        => request('semester'),
                    'major_id'        => request('major_id'),
                ]);
            @endphp
            @if(count($activeFilters))
            <div class="d-flex flex-wrap gap-1 mt-2">
                <span class="text-muted" style="font-size:0.72rem;line-height:1.8">Active filters:</span>

                @if(request('search'))
                <span class="badge d-flex align-items-center gap-1" style="background:#eef2ff;color:#3730a3;font-weight:500;font-size:0.72rem">
                    <i class="bi bi-search" style="font-size:0.6rem"></i> "{{ request('search') }}"
                    <a href="{{ route('admin.exams.index', request()->except('search','page')) }}" class="text-decoration-none ms-1" style="color:#3730a3">×</a>
                </span>
                @endif

                @if(request('status'))
                <span class="badge d-flex align-items-center gap-1" style="background:#f0fdf4;color:#166534;font-weight:500;font-size:0.72rem">
                    {{ ucfirst(str_replace('_',' ',request('status'))) }}
                    <a href="{{ route('admin.exams.index', request()->except('status','page')) }}" class="text-decoration-none ms-1" style="color:#166534">×</a>
                </span>
                @endif

                @if(request('academic_year_id'))
                @php $activeAy = $academicYears->firstWhere('id', request('academic_year_id')); @endphp
                <span class="badge d-flex align-items-center gap-1" style="background:#fef9c3;color:#854d0e;font-weight:500;font-size:0.72rem">
                    {{ $activeAy?->name }}
                    <a href="{{ route('admin.exams.index', request()->except('academic_year_id','page')) }}" class="text-decoration-none ms-1" style="color:#854d0e">×</a>
                </span>
                @endif

                @if(request('year_level'))
                @php $activeYl = $yearLevels->firstWhere('level', request('year_level')); @endphp
                <span class="badge d-flex align-items-center gap-1" style="background:#eff6ff;color:#1d4ed8;font-weight:500;font-size:0.72rem">
                    {{ $activeYl?->name ?? 'Year '.request('year_level') }}
                    <a href="{{ route('admin.exams.index', request()->except('year_level','page')) }}" class="text-decoration-none ms-1" style="color:#1d4ed8">×</a>
                </span>
                @endif

                @if(request('semester'))
                <span class="badge d-flex align-items-center gap-1" style="background:#fdf4ff;color:#7e22ce;font-weight:500;font-size:0.72rem">
                    Semester {{ request('semester') }}
                    <a href="{{ route('admin.exams.index', request()->except('semester','page')) }}" class="text-decoration-none ms-1" style="color:#7e22ce">×</a>
                </span>
                @endif

                @if(request('major_id'))
                @php $activeMajor = $majors->firstWhere('id', request('major_id')); @endphp
                <span class="badge d-flex align-items-center gap-1" style="background:#fff1f2;color:#be123c;font-weight:500;font-size:0.72rem">
                    {{ $activeMajor?->code }}
                    <a href="{{ route('admin.exams.index', request()->except('major_id','page')) }}" class="text-decoration-none ms-1" style="color:#be123c">×</a>
                </span>
                @endif
            </div>
            @endif

        </form>
    </div>
</div>

{{-- Exams Accordion grouped by Academic Year → Year Level → Semester --}}
@php
    $totalExams = $exams->count();
@endphp

<div class="d-flex align-items-center justify-content-between mb-2 px-1">
    <span style="font-size:0.82rem;color:#6b7280">
        @if($totalExams)
            Showing <strong style="color:#374151">{{ $totalExams }}</strong> exam{{ $totalExams !== 1 ? 's' : '' }}
        @endif
    </span>
    @if($totalExams)
    <div class="d-flex gap-1">
        <button type="button" class="btn btn-outline-secondary btn-sm" id="expandAllBtn" style="font-size:0.75rem">
            <i class="bi bi-chevron-expand me-1"></i>Expand All
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="collapseAllBtn" style="font-size:0.75rem">
            <i class="bi bi-chevron-contract me-1"></i>Collapse All
        </button>
    </div>
    @endif
</div>

@if($totalExams === 0)
<div class="card">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-file-earmark-x d-block mb-2" style="font-size:2rem;opacity:0.3"></i>
        No exams found.
        @if(count($activeFilters ?? []))
        <div class="mt-2">
            <a href="{{ route('admin.exams.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-counterclockwise me-1"></i>Clear filters
            </a>
        </div>
        @endif
    </div>
</div>
@else

{{-- ── Outer accordion: Academic Year ──────────────────────────────── --}}
<div class="accordion" id="accordionAY">
    @foreach($grouped as $ayId => $byYearLevel)
    @php
        $ayModel   = $ayMap[$ayId] ?? null;
        $ayLabel   = $ayModel ? $ayModel->name.($ayModel->is_current ? ' (Current)' : '') : 'No Academic Year';
        $ayExamCnt = array_sum(array_map(fn($bySem) => array_sum(array_map('count', $bySem)), $byYearLevel));
        $ayKey     = 'ay-'.$ayId;
    @endphp
    <div class="accordion-item border mb-2" style="border-radius:8px;overflow:hidden;border-color:#e2e8f0">

        {{-- Academic Year header --}}
        <h2 class="accordion-header" id="hd-{{ $ayKey }}">
            <button class="accordion-button"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#cl-{{ $ayKey }}"
                    aria-expanded="true"
                    aria-controls="cl-{{ $ayKey }}"
                    style="background:var(--blc-navy,#1e3a5f);color:#fff;font-weight:700;font-size:0.9rem;padding:0.75rem 1rem">
                <i class="bi bi-calendar-range me-2"></i>
                {{ $ayLabel }}
                <span class="badge ms-2" style="background:rgba(255,255,255,0.2);color:#fff;font-size:0.72rem;font-weight:500">
                    {{ $ayExamCnt }} exam{{ $ayExamCnt !== 1 ? 's' : '' }}
                </span>
            </button>
        </h2>

        <div id="cl-{{ $ayKey }}"
             class="accordion-collapse collapse show"
             aria-labelledby="hd-{{ $ayKey }}"
             data-bs-parent="#accordionAY">
            <div class="accordion-body p-2">

                {{-- ── Middle accordion: Year Level ──────────────────────── --}}
                <div class="accordion" id="accordionYL-{{ $ayId }}">
                    @foreach($byYearLevel as $yl => $bySemester)
                    @php
                        $ylLabel   = \App\Models\Course::$yearLevelLabels[$yl] ?? 'Year '.$yl;
                        $ylExamCnt = array_sum(array_map('count', $bySemester));
                        $ylKey     = 'yl-'.$ayId.'-'.$yl;
                    @endphp
                    <div class="accordion-item border mb-1" style="border-radius:6px;overflow:hidden;border-color:#dde3ee">

                        {{-- Year Level header --}}
                        <h2 class="accordion-header" id="hd-{{ $ylKey }}">
                            <button class="accordion-button"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#cl-{{ $ylKey }}"
                                    aria-expanded="true"
                                    aria-controls="cl-{{ $ylKey }}"
                                    style="background:#eef2ff;color:#3730a3;font-weight:700;font-size:0.83rem;padding:0.6rem 0.9rem">
                                <i class="bi bi-person-workspace me-2"></i>
                                {{ $ylLabel }}
                                <span class="badge ms-2" style="background:#c7d2fe;color:#3730a3;font-size:0.68rem;font-weight:500">
                                    {{ $ylExamCnt }} exam{{ $ylExamCnt !== 1 ? 's' : '' }}
                                </span>
                            </button>
                        </h2>

                        <div id="cl-{{ $ylKey }}"
                             class="accordion-collapse collapse show"
                             aria-labelledby="hd-{{ $ylKey }}"
                             data-bs-parent="#accordionYL-{{ $ayId }}">
                            <div class="accordion-body p-2">

                                {{-- ── Inner accordion: Semester ─────────────────── --}}
                                <div class="accordion" id="accordionSem-{{ $ayId }}-{{ $yl }}">
                                    @foreach($bySemester as $sem => $semExams)
                                    @php
                                        $semLabel   = \App\Models\Course::$semesterLabels[$sem] ?? 'Semester '.$sem;
                                        $semExamCnt = count($semExams);
                                        $semKey     = 'sem-'.$ayId.'-'.$yl.'-'.$sem;
                                    @endphp
                                    <div class="accordion-item border mb-1" style="border-radius:5px;overflow:hidden;border-color:#e8eaf2">

                                        {{-- Semester header --}}
                                        <h2 class="accordion-header" id="hd-{{ $semKey }}">
                                            <button class="accordion-button"
                                                    type="button"
                                                    data-bs-toggle="collapse"
                                                    data-bs-target="#cl-{{ $semKey }}"
                                                    aria-expanded="true"
                                                    aria-controls="cl-{{ $semKey }}"
                                                    style="background:#f5f3ff;color:#7c3aed;font-weight:600;font-size:0.8rem;padding:0.55rem 0.85rem">
                                                <i class="bi bi-bookmark-fill me-2" style="font-size:0.75rem"></i>
                                                {{ $semLabel }}
                                                <span class="badge ms-2" style="background:#ede9fe;color:#7c3aed;font-size:0.65rem;font-weight:500">
                                                    {{ $semExamCnt }} exam{{ $semExamCnt !== 1 ? 's' : '' }}
                                                </span>
                                            </button>
                                        </h2>

                                        <div id="cl-{{ $semKey }}"
                                             class="accordion-collapse collapse show"
                                             aria-labelledby="hd-{{ $semKey }}"
                                             data-bs-parent="#accordionSem-{{ $ayId }}-{{ $yl }}">
                                            <div class="accordion-body p-0">

                                                {{-- Exams table --}}
                                                <div class="table-responsive">
                                                    <table class="table mb-0" style="font-size:0.845rem">
                                                        <thead style="background:#f8f9fc">
                                                            <tr>
                                                                <th style="font-size:0.7rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.55rem 1rem;border-bottom:1.5px solid #e8eaf2;border-top:0">Title</th>
                                                                <th style="font-size:0.7rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.55rem 0.75rem;border-bottom:1.5px solid #e8eaf2;border-top:0">Course</th>
                                                                <th style="font-size:0.7rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.55rem 0.75rem;border-bottom:1.5px solid #e8eaf2;border-top:0">Teacher</th>
                                                                <th style="font-size:0.7rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.55rem 0.75rem;border-bottom:1.5px solid #e8eaf2;border-top:0">Status</th>
                                                                <th style="font-size:0.7rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.55rem 0.75rem;border-bottom:1.5px solid #e8eaf2;border-top:0">Schedule</th>
                                                                <th style="font-size:0.7rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.55rem 0.75rem;border-bottom:1.5px solid #e8eaf2;border-top:0">Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($semExams as $e)
                                                            <tr>
                                                                <td style="padding:0.65rem 1rem;font-weight:600;color:var(--text-1,#111827)">
                                                                    {{ $e->title }}
                                                                </td>
                                                                <td style="padding:0.65rem 0.75rem;color:#374151">
                                                                    {{ $e->course->title }}
                                                                    @if($e->course->major)
                                                                    <div style="font-size:0.7rem;color:#9ca3af">{{ $e->course->major->code }}</div>
                                                                    @endif
                                                                </td>
                                                                <td style="padding:0.65rem 0.75rem;color:#374151">{{ $e->teacher->name }}</td>
                                                                <td style="padding:0.65rem 0.75rem">
                                                                    <span class="status-pill status-{{ $e->status === 'pending_approval' ? 'pending' : $e->status }}">
                                                                        {{ ucfirst(str_replace('_', ' ', $e->status)) }}
                                                                    </span>
                                                                </td>
                                                                <td style="padding:0.65rem 0.75rem">
                                                                    @if($e->activeSchedule)
                                                                        <span style="font-size:0.78rem;color:#6b7280">
                                                                            <i class="bi bi-calendar3 me-1"></i>{{ $e->activeSchedule->starts_at->format('M d, H:i') }}
                                                                        </span>
                                                                    @else
                                                                        <span class="text-muted small">—</span>
                                                                    @endif
                                                                </td>
                                                                <td style="padding:0.65rem 0.75rem">
                                                                    <div class="d-flex gap-1">
                                                                        <a href="{{ route('admin.exams.show', $e) }}" class="btn btn-sm btn-primary">
                                                                            <i class="bi bi-gear me-1"></i>Manage
                                                                        </a>
                                                                        @if(in_array($e->status, ['published', 'closed']))
                                                                        <a href="{{ route('admin.exams.results', $e) }}"
                                                                           class="btn btn-sm btn-outline-primary"
                                                                           title="View Results">
                                                                            <i class="bi bi-bar-chart-fill"></i>
                                                                        </a>
                                                                        @endif
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>

                                            </div>
                                        </div>
                                    </div>{{-- /semester accordion item --}}
                                    @endforeach
                                </div>{{-- /semester accordion --}}

                            </div>
                        </div>
                    </div>{{-- /year level accordion item --}}
                    @endforeach
                </div>{{-- /year level accordion --}}

            </div>
        </div>
    </div>{{-- /academic year accordion item --}}
    @endforeach
</div>{{-- /academic year accordion --}}

@endif

@endsection

@push('scripts')
<script>
(function () {
    // Expand / Collapse all accordion panels
    document.getElementById('expandAllBtn')?.addEventListener('click', function () {
        document.querySelectorAll('.accordion-collapse').forEach(function (el) {
            var bsCollapse = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
            bsCollapse.show();
        });
    });
    document.getElementById('collapseAllBtn')?.addEventListener('click', function () {
        document.querySelectorAll('.accordion-collapse').forEach(function (el) {
            var bsCollapse = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
            bsCollapse.hide();
        });
    });
})();
</script>
@endpush
