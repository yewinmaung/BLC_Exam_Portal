@extends('layouts.app')
@section('title', $student->name)
@section('page-title', $student->name)
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Students', 'url' => route('admin.students.index')],
        ['label' => $student->name],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')
<div class="row g-3">

    {{-- Student info --}}
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-body text-center py-4">
                <div style="width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,#0e6b6b,#0d9488);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:800;margin:0 auto 1rem">
                    {{ strtoupper(substr($student->name,0,1)) }}
                </div>
                <h6 style="font-weight:800;margin-bottom:0.2rem">{{ $student->name }}</h6>
                <div class="text-muted small mb-2">{{ $student->email }}</div>
                @if($student->phone)
                <div class="text-muted small mb-2"><i class="bi bi-telephone me-1"></i>{{ $student->phone }}</div>
                @endif
                @if($student->is_active)
                    <span class="status-pill status-approved">Active</span>
                @else
                    <span class="status-pill status-closed">Suspended</span>
                @endif
            </div>
            <div class="card-footer d-flex gap-2">
                <a href="{{ route('admin.students.edit', $student) }}" class="btn btn-sm btn-primary flex-grow-1">
                    <i class="bi bi-pencil me-1"></i>Edit
                </a>
                <a href="{{ route('admin.results.student', $student) }}" class="btn btn-sm btn-outline-primary" title="View Results">
                    <i class="bi bi-bar-chart-line"></i>
                </a>
                <form action="{{ route('admin.students.destroy', $student) }}" method="POST"
                      onsubmit="return confirm('Permanently delete {{ addslashes($student->name) }}?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
            </div>
        </div>

        {{-- Current academic record --}}
        @if($yearRecords->count())
        <div class="card">
            <div class="card-header"><i class="bi bi-mortarboard me-2"></i>Academic Records</div>
            <div class="card-body p-0">
                @foreach($yearRecords as $yr)
                <div class="p-3 border-bottom">
                    <div style="font-weight:600;font-size:0.875rem">{{ $yr->academicYear->name }}</div>
                    <div class="text-muted small">{{ $yr->yearLevel->name }} · Sem {{ $yr->semester }}</div>
                    @if($yr->department)<div class="text-muted small">{{ $yr->department }}</div>@endif
                    @if($yr->record_type && $yr->record_type !== \App\Enums\RecordType::NORMAL)
                    <div class="text-muted small mt-1">
                        <span class="badge" style="background:#fef9c3;color:#92400e;font-size:0.65rem;font-weight:700">
                            {{ \App\Enums\RecordType::LABELS[$yr->record_type] ?? $yr->record_type }}
                        </span>
                    </div>
                    @endif
                    @if($yr->remark)
                    <div class="text-muted small mt-1" style="font-style:italic;font-size:0.75rem">
                        <i class="bi bi-chat-left-text me-1"></i>{{ $yr->remark }}
                    </div>
                    @endif
                    @if($yr->major)
                    <div class="text-muted small">
                        <i class="bi bi-collection me-1"></i>
                        <span class="badge" style="background:#eff6ff;color:#1d4ed8;font-size:0.68rem;font-weight:700">
                            {{ \App\Models\Major::codeFromLabel($yr->major) }}
                        </span>
                    </div>
                    @endif
                    @if($yr->gpa)<div><span class="badge" style="background:#f0fdf4;color:#166534;font-weight:700">{{ number_format($yr->gpa, 2) }} GPA</span></div>@endif
                    <span class="status-pill status-{{ $yr->status === 'active' ? 'approved' : ($yr->status === 'promoted' ? 'published' : 'closed') }}" style="font-size:0.68rem;margin-top:4px">
                        {{ ucfirst($yr->status) }}
                    </span>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    {{-- Enrolled courses — grouped accordion --}}
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-book me-2"></i>Enrolled Courses</span>
                <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">
                    {{ $student->enrollments->count() }}
                </span>
            </div>

            @php
                /*
                 * Group by the student's OWN academic year (from StudentYearRecord),
                 * matched on year_level integer + semester — NOT course.academic_year_id.
                 *
                 * Build a lookup:  [year_level][semester] => AcademicYear name
                 * from the student's year records.
                 */
                $yrLookup = [];
                $yrSortMap = [];   // for sorting: [ay_name] => start_year
                foreach ($yearRecords as $yr) {
                    $lvl = $yr->yearLevel?->level ?? 0;
                    $sem = (int) ($yr->semester ?? 0);
                    $ayName = $yr->academicYear?->name ?? 'Unassigned';
                    $yrLookup[$lvl][$sem] = $ayName;
                    // also allow sem=0 (both) to match any semester
                    $yrLookup[$lvl][0] = $yrLookup[$lvl][0] ?? $ayName;
                    $yrSortMap[$ayName] = $yr->academicYear?->start_year ?? 9999;
                }

                $grouped = $student->enrollments
                    ->map(function ($e) use ($yrLookup) {
                        $lvl = $e->course?->year_level ?? 0;
                        $sem = $e->course?->semester ?? 0;
                        // Try exact match first, then any-semester (0), then fallback
                        $ayName = $yrLookup[$lvl][$sem]
                            ?? $yrLookup[$lvl][0]
                            ?? $yrLookup[0][$sem]
                            ?? $yrLookup[0][0]
                            ?? 'Unassigned Academic Year';
                        $e->_groupAy = $ayName;
                        return $e;
                    })
                    ->sortBy([
                        fn ($e) => $yrSortMap[$e->_groupAy] ?? 9999,
                        fn ($e) => $e->course?->year_level ?? 0,
                        fn ($e) => $e->course?->semester ?? 0,
                        fn ($e) => $e->course?->title ?? '',
                    ])
                    ->groupBy('_groupAy');
            @endphp

            @if($student->enrollments->isEmpty())
            <div class="card-body text-center py-5 text-muted">
                <i class="bi bi-book d-block mb-2" style="font-size:1.8rem;opacity:0.3"></i>
                No courses enrolled.
            </div>
            @else
            <div class="accordion accordion-flush" id="enrollAccordion">

                @foreach($grouped as $ayName => $ayEnrollments)
                @php
                    $ayId        = 'ay-' . \Illuminate\Support\Str::slug($ayName);
                    $ayTotal     = $ayEnrollments->count();
                    $byYearLevel = $ayEnrollments->groupBy(fn ($e) => $e->course?->year_level ?? 0);
                @endphp

                {{-- ── Academic Year panel ── --}}
                <div class="accordion-item" style="border-left:none;border-right:none">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-semibold"
                                style="font-size:0.88rem;background:#f8f9fc;color:#1a2540"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#{{ $ayId }}"
                                aria-expanded="false">
                            <i class="bi bi-calendar3 me-2" style="color:#6366f1"></i>
                            {{ $ayName }}
                            <span class="badge ms-2" style="background:#eef2ff;color:#3730a3;font-weight:700;font-size:0.7rem">
                                {{ $ayTotal }} course{{ $ayTotal !== 1 ? 's' : '' }}
                            </span>
                        </button>
                    </h2>
                    <div id="{{ $ayId }}" class="accordion-collapse collapse">
                        <div class="accordion-body p-0">

                            @foreach($byYearLevel->sortKeys() as $yl => $ylEnrollments)
                            @php
                                $ylLabel    = \App\Models\Course::$yearLevelLabels[$yl] ?? 'Year ' . $yl;
                                $bySemester = $ylEnrollments->groupBy(fn ($e) => $e->course?->semester ?? 0);
                            @endphp

                            {{-- ── Year Level sub-header ── --}}
                            <div style="background:#f0f4ff;border-top:1px solid #e8eaf2;padding:0.5rem 1.1rem;display:flex;align-items:center;gap:0.5rem">
                                <i class="bi bi-layers" style="color:#6366f1;font-size:0.8rem"></i>
                                <span style="font-size:0.78rem;font-weight:700;color:#3730a3;text-transform:uppercase;letter-spacing:0.05em">
                                    {{ $ylLabel }}
                                </span>
                            </div>

                            @foreach($bySemester->sortKeys() as $sem => $semEnrollments)
                            @php
                                $semLabel = match((int)$sem) {
                                    1 => 'Semester 1',
                                    2 => 'Semester 2',
                                    default => 'Both Semesters',
                                };
                                $semIcon = match((int)$sem) {
                                    1 => 'bi-1-circle',
                                    2 => 'bi-2-circle',
                                    default => 'bi-infinity',
                                };
                            @endphp

                            {{-- ── Semester chip ── --}}
                            <div style="padding:0.4rem 1.4rem 0.2rem;border-top:1px solid #f1f3f9">
                                <span style="font-size:0.72rem;font-weight:700;color:#7c3aed;display:inline-flex;align-items:center;gap:4px">
                                    <i class="bi {{ $semIcon }}" style="font-size:0.75rem"></i>
                                    {{ $semLabel }}
                                </span>
                            </div>

                            {{-- ── Course rows ── --}}
                            <table class="table table-sm mb-0" style="font-size:0.845rem">
                                <thead style="background:#fafbff">
                                    <tr>
                                        <th style="padding:0.45rem 1.4rem;font-size:0.7rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;border-bottom:1.5px solid #e8eaf2">Course</th>
                                        <th style="padding:0.45rem 0.75rem;font-size:0.7rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;border-bottom:1.5px solid #e8eaf2">Code</th>
                                        <th style="padding:0.45rem 0.75rem;font-size:0.7rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;border-bottom:1.5px solid #e8eaf2">Teacher</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($semEnrollments->sortBy(fn($e) => $e->course?->title) as $e)
                                    <tr>
                                        <td style="padding:0.55rem 1.4rem;font-weight:600;color:#111827">
                                            {{ $e->course?->title ?? '—' }}
                                        </td>
                                        <td style="padding:0.55rem 0.75rem">
                                            <code style="font-size:0.75rem;background:#f3f4f6;padding:1px 6px;border-radius:4px;color:#374151">
                                                {{ $e->course?->code ?? '—' }}
                                            </code>
                                        </td>
                                        <td style="padding:0.55rem 0.75rem;color:#6b7280;font-size:0.8rem">
                                            {{ $e->historicalTeacher?->name ?? '—' }}
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            @endforeach {{-- /semester --}}
                            @endforeach {{-- /year level --}}

                        </div>
                    </div>
                </div>

                @endforeach {{-- /academic year --}}
            </div>
            @endif

        </div>
    </div>
</div>
@endsection
