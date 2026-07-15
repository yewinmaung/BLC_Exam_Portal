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

{{-- Exams Table --}}
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-file-earmark-text me-2"></i>All Exams</span>
        <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">
            {{ $exams->total() }} total
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0" style="font-size:0.855rem">
                <thead style="background:#f8f9fc">
                    <tr>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 1rem;border-bottom:1.5px solid #e8eaf2">Title</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Course</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Teacher</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Status</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Schedule</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($exams as $e)
                    <tr>
                        <td style="padding:0.75rem 1rem;font-weight:600;color:var(--text-1,#111827)">
                            {{ $e->title }}
                            @php
                                $yl = $e->course->year_level ?? 0;
                                $sem = $e->course->semester ?? 0;
                            @endphp
                            @if($yl > 0)
                            <div class="mt-1">
                                <span style="font-size:0.68rem;background:#eef2ff;color:#3730a3;padding:0.1rem 0.45rem;border-radius:4px;font-weight:600">
                                    {{ \App\Models\Course::$yearLevelLabels[$yl] ?? 'Year '.$yl }}
                                    @if($sem > 0) · Sem {{ $sem }}@endif
                                </span>
                            </div>
                            @endif
                        </td>
                        <td style="padding:0.75rem 0.75rem;color:#374151">
                            {{ $e->course->title }}
                            @if($e->course->major)
                            <div style="font-size:0.7rem;color:#9ca3af">{{ $e->course->major->code ?? '' }}</div>
                            @endif
                        </td>
                        <td style="padding:0.75rem 0.75rem;color:#374151">{{ $e->teacher->name }}</td>
                        <td style="padding:0.75rem 0.75rem">
                            <span class="status-pill status-{{ $e->status === 'pending_approval' ? 'pending' : $e->status }}">
                                {{ ucfirst(str_replace('_', ' ', $e->status)) }}
                            </span>
                        </td>
                        <td style="padding:0.75rem 0.75rem">
                            @if($e->activeSchedule)
                                <span style="font-size:0.78rem;color:#6b7280">
                                    <i class="bi bi-calendar3 me-1"></i>{{ $e->activeSchedule->starts_at->format('M d, H:i') }}
                                </span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td style="padding:0.75rem 0.75rem">
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
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-file-earmark-x d-block mb-2" style="font-size:2rem;opacity:0.3"></i>
                            No exams found.
                            @if(count($activeFilters ?? []))
                            <div class="mt-2">
                                <a href="{{ route('admin.exams.index') }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Clear filters
                                </a>
                            </div>
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($exams->hasPages())
        <div class="px-3 py-2 border-top" style="background:#fafbff">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <span style="font-size:0.78rem;color:#6b7280">
                    Showing <strong style="color:#374151">{{ $exams->firstItem() }}</strong>
                    – <strong style="color:#374151">{{ $exams->lastItem() }}</strong>
                    of <strong style="color:#374151">{{ $exams->total() }}</strong> exams
                </span>
                {{ $exams->withQueryString()->links() }}
            </div>
        </div>
        @elseif($exams->count())
        <div class="px-3 py-2 border-top" style="background:#fafbff">
            <span style="font-size:0.78rem;color:#6b7280">
                Showing all <strong style="color:#374151">{{ $exams->total() }}</strong> exam{{ $exams->total() !== 1 ? 's' : '' }}
            </span>
        </div>
        @endif
    </div>
</div>

@endsection
