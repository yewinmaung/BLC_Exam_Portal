@extends('layouts.app')
@section('title', 'Enrollment Management')
@section('page-title', 'Enrollment Management')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Enrollments'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')
<div class="row g-3">

    {{-- ── Enroll Form ── --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-person-plus-fill" style="color:var(--blc-gold,#d4a51c)"></i>
                Enroll Students
            </div>
            <div class="card-body">

                @if($errors->any())
                <div class="alert alert-danger py-2 mb-3">
                    <ul class="mb-0 ps-3" style="font-size:0.82rem">
                        @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
                    </ul>
                </div>
                @endif

                @if(session('skip_details') && config('app.debug'))
                <div class="alert alert-warning py-2 mb-3" style="max-height:200px;overflow-y:auto">
                    <strong style="font-size:0.85rem">Debug: Skip Reasons</strong>
                    <ul class="mb-0 ps-3 mt-1" style="font-size:0.75rem">
                        @foreach(session('skip_details') as $reason)
                        <li>{{ $reason }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <form method="POST" action="{{ route('admin.enrollments.store') }}" id="enrollForm">
                    @csrf

                    {{-- ① Academic Year ── drives student + course filtering --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Academic Year <span class="text-danger">*</span>
                        </label>
                        <select name="academic_year_id" id="selAcYear" class="form-select" required>
                            <option value="">— Select Academic Year —</option>
                            @foreach($academicYears as $ay)
                            <option value="{{ $ay->id }}"
                                {{ old('academic_year_id', $currentYearId) == $ay->id ? 'selected' : '' }}>
                                {{ $ay->name }}{{ $ay->is_current ? ' (Current)' : '' }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- ② Year Level ── drives student list --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Year Level <span class="text-danger">*</span>
                        </label>
                        <select name="year_level_id" id="selYearLevel" class="form-select" required>
                            <option value="">— Select Year Level —</option>
                            @foreach($yearLevels as $yl)
                            <option value="{{ $yl->id }}"
                                data-level="{{ $yl->level }}"
                                {{ old('year_level_id') == $yl->id ? 'selected' : '' }}>
                                {{ $yl->name }}
                            </option>
                            @endforeach
                        </select>
                        <input type="hidden" name="year" id="hiddenYear" value="{{ old('year', 1) }}">
                        <div class="form-text">Filters both the student list and available courses.</div>
                    </div>
  {{--  Semester ── drives course list --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Semester <span class="text-danger">*</span>
                        </label>
                        <select name="semester" id="selSemester" class="form-select">
                            <option value="1" {{ old('semester','1') == '1' ? 'selected' : '' }}>Semester 1</option>
                            <option value="2" {{ old('semester') == '2' ? 'selected' : '' }}>Semester 2</option>
                        </select>
                    </div>

                    {{-- ⑤ Major — required for Year 2+, hidden for Year 1 --}}
                    <div class="mb-3" id="majorWrapper" style="display:none">
                        <label class="form-label fw-semibold">
                            Major
                            <span class="text-danger" id="majorRequired" style="display:none">*</span>
                            <span class="text-muted fw-normal" id="majorOptional" style="font-size:0.8rem">(optional for Year 1 — CST only)</span>
                        </label>
                        <select name="major_id" id="selMajor" class="form-select @error('major_id') is-invalid @enderror">
                            <option value="">— No Major (Year 1) —</option>
                            @foreach($majors as $m)
                            <option value="{{ $m->id }}"
                                    data-code="{{ $m->code }}"
                                    {{ old('major_id') == $m->id ? 'selected' : '' }}>
                                {{ $m->code }}
                            </option>
                            @endforeach
                        </select>
                        @error('major_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    {{-- Students (dynamically loaded) --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold d-flex justify-content-between align-items-center">
                            <span>Students <span class="text-danger">*</span></span>
                            <span class="text-muted small" id="selectedCount">0 selected</span>
                        </label>

                        {{-- Loading / empty state --}}
                        <div id="studentLoading" class="text-center py-3 text-muted small" style="display:none">
                            <i class="bi bi-arrow-repeat" style="animation:spin 1s linear infinite"></i> Loading students...
                        </div>
                        <div id="studentEmpty" class="text-center py-3 text-muted small">
                            <i class="bi bi-people d-block mb-1" style="font-size:1.4rem;opacity:0.35"></i>
                            Select Academic Year &amp; Year Level to load students.
                        </div>

                        <input type="text" id="studentSearch" class="form-control mb-2"
                               placeholder="Search students..." autocomplete="off" style="display:none">
                        <div class="d-flex gap-2 mb-2" id="studentBulkBtns" style="display:none">
                            <button type="button" id="selectAll"
                                    style="font-size:0.75rem;padding:0.2rem 0.6rem"
                                    class="btn btn-outline-secondary btn-sm">Select all</button>
                            <button type="button" id="selectNone"
                                    style="font-size:0.75rem;padding:0.2rem 0.6rem"
                                    class="btn btn-outline-secondary btn-sm">Clear</button>
                        </div>
                        <div class="student-list" id="studentList" style="display:none"></div>
                    </div>

                  

                    {{-- ⑥ Courses (filtered by AY + Year Level + Semester + Major via AJAX, multi-select checkboxes) --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold d-flex justify-content-between align-items-center">
                            <span>Course <span class="text-danger">*</span></span>
                            <span class="text-muted small" id="courseCount">0 selected</span>
                        </label>

                        <div id="courseLoading" class="text-center py-3 text-muted small" style="display:none">
                            <i class="bi bi-arrow-repeat" style="animation:spin 1s linear infinite"></i> Loading courses...
                        </div>
                        <div id="courseEmpty" class="text-center py-3 text-muted small">
                            <i class="bi bi-book d-block mb-1" style="font-size:1.4rem;opacity:0.35"></i>
                            Select Academic Year &amp; Year Level first.
                        </div>

                        <input type="text" id="courseSearch" class="form-control form-control-sm mb-2"
                               placeholder="Search courses..." autocomplete="off" style="display:none">
                        <div class="d-flex gap-2 mb-2" id="courseBulkBtns" style="display:none">
                            <button type="button" id="courseSelectAll"
                                    class="btn btn-outline-secondary btn-sm"
                                    style="font-size:0.75rem;padding:0.2rem 0.6rem">Select all</button>
                            <button type="button" id="courseSelectNone"
                                    class="btn btn-outline-secondary btn-sm"
                                    style="font-size:0.75rem;padding:0.2rem 0.6rem">Clear</button>
                        </div>
                        <div class="course-list" id="courseList" style="display:none"></div>
                        <div class="form-text" id="courseHint"></div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100" id="enrollBtn" disabled>
                        <i class="bi bi-person-check me-1"></i> Enroll Selected Students
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- ── Enrollment List ── --}}
    <div class="col-lg-8">

        {{-- ── Search + Filter bar ── --}}
        <div class="card mb-3">
            <div class="card-body py-3">
                <form method="GET" action="{{ route('admin.enrollments.index') }}">

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
                                   placeholder="Search student name, email, or course…"
                                   maxlength="100"
                                   autocomplete="off">
                            @if(request('search'))
                            <a href="{{ route('admin.enrollments.index', request()->except('search', 'page')) }}"
                               class="input-group-text text-muted" style="background:#f8f9fc;border-color:#e2e8f0;text-decoration:none" title="Clear search">
                                <i class="bi bi-x" style="font-size:1rem"></i>
                            </a>
                            @endif
                        </div>
                    </div>

                    {{-- Filter dropdowns row --}}
                    <div class="d-flex flex-wrap gap-2 align-items-end">
                        <div style="min-width:150px;flex:1">
                            <select name="course_id" class="form-select form-select-sm" style="font-size:0.8rem">
                                <option value="">All courses</option>
                                @foreach($courses as $c)
                                <option value="{{ $c->id }}" {{ request('course_id') == $c->id ? 'selected' : '' }}>
                                    {{ $c->title }}{{ $c->year_level > 0 ? ' ('.\App\Models\Course::$yearLevelLabels[$c->year_level].')' : '' }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div style="min-width:110px">
                            <select name="year_level_id" class="form-select form-select-sm" style="font-size:0.8rem">
                                <option value="">All levels</option>
                                @foreach($yearLevels as $yl)
                                <option value="{{ $yl->id }}" {{ request('year_level_id') == $yl->id ? 'selected' : '' }}>
                                    {{ $yl->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
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
                        <div style="min-width:140px;flex:1">
                            <select name="student_id" class="form-select form-select-sm" style="font-size:0.8rem">
                                <option value="">All students</option>
                                @foreach($students as $s)
                                <option value="{{ $s->id }}" {{ request('student_id') == $s->id ? 'selected' : '' }}>
                                    {{ $s->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="d-flex gap-1">
                            <button type="submit" class="btn btn-primary btn-sm px-3">
                                <i class="bi bi-funnel-fill me-1"></i>Filter
                            </button>
                            <a href="{{ route('admin.enrollments.index') }}"
                               class="btn btn-outline-secondary btn-sm" title="Reset all filters">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                        </div>
                    </div>

                    {{-- Active filter tags --}}
                    @php
                        $activeFilters = array_filter([
                            'search'         => request('search'),
                            'course_id'      => request('course_id'),
                            'year_level_id'  => request('year_level_id'),
                            'major_id'       => request('major_id'),
                            'student_id'     => request('student_id'),
                        ]);
                    @endphp
                    @if(count($activeFilters) > 0)
                    <div class="d-flex flex-wrap gap-1 mt-2">
                        <span class="text-muted" style="font-size:0.72rem;line-height:1.8">Active filters:</span>
                        @if(request('search'))
                        <span class="badge d-flex align-items-center gap-1" style="background:#eef2ff;color:#3730a3;font-weight:500;font-size:0.72rem">
                            <i class="bi bi-search" style="font-size:0.6rem"></i> "{{ request('search') }}"
                            <a href="{{ route('admin.enrollments.index', request()->except('search','page')) }}" class="text-decoration-none ms-1" style="color:#3730a3">×</a>
                        </span>
                        @endif
                        @if(request('year_level_id'))
                        @php $activeYl = $yearLevels->firstWhere('id', request('year_level_id')); @endphp
                        <span class="badge d-flex align-items-center gap-1" style="background:#f0fdf4;color:#166534;font-weight:500;font-size:0.72rem">
                            {{ $activeYl->name ?? '' }}
                            <a href="{{ route('admin.enrollments.index', request()->except('year_level_id','page')) }}" class="text-decoration-none ms-1" style="color:#166534">×</a>
                        </span>
                        @endif
                        @if(request('major_id'))
                        @php $activeMajor = $majors->firstWhere('id', request('major_id')); @endphp
                        <span class="badge d-flex align-items-center gap-1" style="background:#eff6ff;color:#1d4ed8;font-weight:500;font-size:0.72rem">
                            {{ $activeMajor->code ?? '' }}
                            <a href="{{ route('admin.enrollments.index', request()->except('major_id','page')) }}" class="text-decoration-none ms-1" style="color:#1d4ed8">×</a>
                        </span>
                        @endif
                    </div>
                    @endif
                </form>
            </div>
        </div>

        {{-- ── Table Card ── --}}
        <div class="card">
            {{-- Card header --}}
            <div class="card-header d-flex align-items-center justify-content-between py-3"
                 style="background:#fafbff;border-bottom:1.5px solid #e8eaf2">
                <div class="d-flex align-items-center gap-2">
                    <div style="width:32px;height:32px;border-radius:9px;background:linear-gradient(135deg,#1e1b6e,#3730a3);display:flex;align-items:center;justify-content:center">
                        <i class="bi bi-person-lines-fill" style="color:#fff;font-size:0.85rem"></i>
                    </div>
                    <div>
                        <div style="font-weight:700;font-size:0.9rem;color:var(--text-1,#111827)">Enrolled Students</div>
                        <div style="font-size:0.7rem;color:#9ca3af">
                            {{ $enrollments->total() }} total enrollment{{ $enrollments->total() !== 1 ? 's' : '' }}
                        </div>
                    </div>
                </div>
                @if(count($activeFilters) > 0)
                <span class="badge" style="background:#fef9c3;color:#854d0e;font-size:0.72rem">
                    <i class="bi bi-funnel-fill me-1"></i>Filtered
                </span>
                @endif
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0" style="font-size:0.855rem">
                        <thead style="background:#f8f9fc">
                            <tr>
                                <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 1rem;border-bottom:1.5px solid #e8eaf2">Student</th>
                                <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Course</th>
                                <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Year Level</th>
                                <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Enrolled</th>
                                <th style="padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2;width:48px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($enrollments as $e)
                            <tr class="enroll-row">
                                {{-- Student --}}
                                <td style="padding:0.75rem 1rem;vertical-align:middle">
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#1e1b6e,#3730a3);color:#fff;display:flex;align-items:center;justify-content:center;font-size:0.78rem;font-weight:700;flex-shrink:0;box-shadow:0 2px 6px rgba(55,48,163,0.25)">
                                            {{ strtoupper(substr($e->student->name ?? '?', 0, 1)) }}
                                        </div>
                                        <div style="min-width:0">
                                            <div style="font-weight:600;color:var(--text-1,#111827);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:130px">
                                                {{ $e->student->name ?? '—' }}
                                            </div>
                                            <div style="font-size:0.7rem;color:#9ca3af;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:130px">
                                                {{ $e->student->email ?? '' }}
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                {{-- Course --}}
                                <td style="padding:0.75rem 0.75rem;vertical-align:middle">
                                    <div style="font-weight:500;color:var(--text-1,#111827)">{{ $e->course->title ?? '—' }}</div>
                                    <div style="font-size:0.7rem;margin-top:1px">
                                        <span style="background:#eef2ff;color:#3730a3;padding:0.1rem 0.4rem;border-radius:4px;font-weight:600;font-size:0.68rem">
                                            {{ $e->course->code ?? '' }}
                                        </span>
                                    </div>
                                </td>

                                {{-- Year Level --}}
                                <td style="padding:0.75rem 0.75rem;vertical-align:middle">
                                    @php $yl = $e->course->year_level ?? 0; @endphp
                                    <span class="year-badge year-badge-{{ $e->year }}">
                                        @if($yl > 0)
                                            {{ \App\Models\Course::$yearLevelLabels[$yl] ?? 'Year '.$yl }}
                                        @else
                                            All Years
                                        @endif
                                    </span>
                                </td>

                                {{-- Enrolled date --}}
                                <td style="padding:0.75rem 0.75rem;vertical-align:middle">
                                    <div style="font-size:0.8rem;color:#374151;font-weight:500">
                                        {{ ($e->enrolled_at ?? $e->created_at)->format('M d, Y') }}
                                    </div>
                                    <div style="font-size:0.68rem;color:#9ca3af">
                                        {{ ($e->enrolled_at ?? $e->created_at)->diffForHumans() }}
                                    </div>
                                </td>

                                {{-- Remove --}}
                                <td style="padding:0.75rem 0.5rem;vertical-align:middle;text-align:center">
                                    <form method="POST"
                                          action="{{ route('admin.enrollments.destroy', $e) }}"
                                          onsubmit="return confirm('Remove {{ addslashes($e->student->name ?? '') }} from {{ addslashes($e->course->title ?? '') }}?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm enroll-del-btn" title="Remove enrollment"
                                                style="width:30px;height:30px;padding:0;display:flex;align-items:center;justify-content:center;border-radius:8px;border:1.5px solid #fecaca;color:#ef4444;background:#fff5f5;transition:all 0.15s">
                                            <i class="bi bi-trash" style="font-size:0.75rem"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <div style="opacity:0.4">
                                        <i class="bi bi-person-x d-block mb-2" style="font-size:2.2rem"></i>
                                    </div>
                                    <div style="font-weight:600;color:#374151;font-size:0.9rem">No enrollments found</div>
                                    @if(count($activeFilters) > 0)
                                    <div class="mt-1" style="font-size:0.8rem;color:#9ca3af">
                                        Try adjusting your search or filters.
                                    </div>
                                    <a href="{{ route('admin.enrollments.index') }}"
                                       class="btn btn-sm btn-outline-secondary mt-2">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i>Clear filters
                                    </a>
                                    @endif
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- ── Pagination footer ── --}}
                @if($enrollments->hasPages())
                <div class="px-3 py-2 border-top" style="background:#fafbff">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <span style="font-size:0.78rem;color:#6b7280">
                            Showing
                            <strong style="color:#374151">{{ $enrollments->firstItem() }}</strong>
                            –
                            <strong style="color:#374151">{{ $enrollments->lastItem() }}</strong>
                            of
                            <strong style="color:#374151">{{ $enrollments->total() }}</strong>
                            enrollments
                        </span>
                        <div>
                            {{ $enrollments->withQueryString()->links() }}
                        </div>
                    </div>
                </div>
                @elseif($enrollments->count() > 0)
                <div class="px-3 py-2 border-top" style="background:#fafbff">
                    <span style="font-size:0.78rem;color:#6b7280">
                        Showing all <strong style="color:#374151">{{ $enrollments->total() }}</strong> enrollment{{ $enrollments->total() !== 1 ? 's' : '' }}
                    </span>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
/* ── Enrollment table row hover ── */
.enroll-row { transition: background 0.12s; }
.enroll-row:hover { background: #f8f9ff; }
.enroll-del-btn:hover {
    background: #fef2f2 !important;
    border-color: #ef4444 !important;
    color: #dc2626 !important;
    transform: scale(1.08);
}

.student-list {
    max-height:260px;overflow-y:auto;
    border:1.5px solid #e2e8f0;border-radius:10px;background:#fafbff;
}
.student-item {
    display:flex;align-items:center;gap:0.6rem;
    padding:0.55rem 0.85rem;cursor:pointer;
    border-bottom:1px solid #f0f3fa;transition:background 0.12s;user-select:none;
}
.student-item:last-child{border-bottom:none;}
.student-item:hover{background:#f0f4ff;}
.student-item.hidden{display:none;}
.student-item input[type="checkbox"]{accent-color:var(--royal,#3730a3);width:15px;height:15px;flex-shrink:0;}
.student-avatar{width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#1e1b6e,#3730a3);color:#fff;display:flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:700;flex-shrink:0;}
.student-info{flex:1;min-width:0;}
.student-name{font-size:0.82rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.student-email{font-size:0.7rem;color:#9ca3af;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
/* Course list — same look as student list */
.course-list {
    max-height:220px;overflow-y:auto;
    border:1.5px solid #e2e8f0;border-radius:10px;background:#fafbff;
}
.course-item {
    display:flex;align-items:flex-start;gap:0.6rem;
    padding:0.55rem 0.85rem;cursor:pointer;
    border-bottom:1px solid #f0f3fa;transition:background 0.12s;user-select:none;
}
.course-item:last-child{border-bottom:none;}
.course-item:hover{background:#f0f4ff;}
.course-item.hidden{display:none;}
.course-item input[type="checkbox"]{accent-color:var(--royal,#3730a3);width:15px;height:15px;flex-shrink:0;margin-top:2px;}
.course-item-info{flex:1;min-width:0;}
.course-item-title{font-size:0.82rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.course-item-meta{font-size:0.7rem;color:#9ca3af;}
.year-badge{display:inline-block;padding:0.22rem 0.6rem;border-radius:20px;font-size:0.73rem;font-weight:700;}
.year-badge-1{background:#f0fdf4;color:#166534;}
.year-badge-2{background:#eff6ff;color:#1d4ed8;}
.year-badge-3{background:#fef9c3;color:#854d0e;}
.year-badge-4{background:#fdf4ff;color:#7e22ce;}
.year-badge-5{background:#fff1f2;color:#be123c;}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
@endpush

@push('scripts')
<script>
(function () {
    // ── Elements ────────────────────────────────────────────────
    const selAcYear      = document.getElementById('selAcYear');
    const selYearLevel   = document.getElementById('selYearLevel');
    const selSemester    = document.getElementById('selSemester');
    const selMajor       = document.getElementById('selMajor');
    const majorRequired  = document.getElementById('majorRequired');
    const majorOptional  = document.getElementById('majorOptional');
    const hiddenYear     = document.getElementById('hiddenYear');
    const courseList     = document.getElementById('courseList');
    const courseLoading  = document.getElementById('courseLoading');
    const courseEmpty    = document.getElementById('courseEmpty');
    const courseSearch   = document.getElementById('courseSearch');
    const courseBulkBtns = document.getElementById('courseBulkBtns');
    const courseCount    = document.getElementById('courseCount');
    const courseHint     = document.getElementById('courseHint');
    const studentList    = document.getElementById('studentList');
    const studentLoading = document.getElementById('studentLoading');
    const studentEmpty   = document.getElementById('studentEmpty');
    const studentSearch  = document.getElementById('studentSearch');
    const studentBulkBtns= document.getElementById('studentBulkBtns');
    const countEl        = document.getElementById('selectedCount');
    const enrollBtn      = document.getElementById('enrollBtn');

    const studentsApiUrl = "{{ route('admin.enrollments.students-by-year-level') }}";
    const coursesApiUrl  = "{{ route('admin.courses.by-year-level') }}";

    // CS and CT students take CST courses.
    // When CS or CT is selected, pass CST's major_id to the courses API.
    const CST_MAJOR_ID = {{ (int) ($cstMajorId ?? 0) }};
    const CS_MAJOR_ID  = {{ (int) ($csMajorId  ?? 0) }};
    const CT_MAJOR_ID  = {{ (int) ($ctMajorId  ?? 0) }};

    function esc(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function updateStudentCount() {
        const n = studentList.querySelectorAll('.student-cb:checked').length;
        countEl.textContent = n + ' selected';
        updateEnrollBtn();
    }

    function updateCourseCount() {
        const n = courseList.querySelectorAll('.course-cb:checked').length;
        courseCount.textContent = n + ' selected';
        updateEnrollBtn();
    }

    function updateEnrollBtn() {
        const hasStudents = studentList.querySelectorAll('.student-cb:checked').length > 0;
        const hasCourses  = courseList.querySelectorAll('.course-cb:checked').length > 0;
        enrollBtn.disabled = !(hasStudents && hasCourses);
    }

    // Store all major options once on page load
    const allMajorOptions = Array.from(selMajor.options);

    function toggleMajor() {
        const opt   = selYearLevel.options[selYearLevel.selectedIndex];
        const level = opt ? parseInt(opt.dataset.level || '0', 10) : 0;
        const majorWrapper = document.getElementById('majorWrapper');

        // ── Rebuild major options based on year level ─────────────────────
        // Clear all options except the first placeholder
        while (selMajor.options.length > 1) selMajor.remove(1);

        allMajorOptions.forEach((option, index) => {
            if (index === 0) return; // skip placeholder
            const code = option.dataset.code || '';
            if (level === 1) {
                // Year 1 → no major shown (CST only, auto-applied on backend)
                // do not add any options
            } else if (level >= 2) {
                // Year 2+ → show CS and CT, hide CST
                if (code !== 'CST') selMajor.add(option.cloneNode(true));
            }
        });

        // ── Show / hide wrapper & required state ──────────────────────────
        if (level === 1) {
            // Year 1: hide major entirely — CST is implicit
            if (majorWrapper) majorWrapper.style.display = 'none';
            selMajor.required = false;
            selMajor.value = '';
        } else if (level >= 2) {
            // Year 2+: show major, required
            if (majorWrapper) majorWrapper.style.display = '';
            selMajor.required = true;
            majorRequired.style.display = 'inline';
            majorOptional.style.display = 'none';
        } else {
            // No year selected — hide
            if (majorWrapper) majorWrapper.style.display = 'none';
            selMajor.required = false;
            selMajor.value = '';
        }
    }

    // ── Load Students (by Academic Year + Year Level + Major) ─────────
    function loadStudents() {
        const acYearId    = selAcYear.value;
        const yearLevelId = selYearLevel.value;
        const opt         = selYearLevel.options[selYearLevel.selectedIndex];
        const level       = opt ? parseInt(opt.dataset.level || '0', 10) : 0;

        // Sync hidden year field
        hiddenYear.value = opt ? (opt.dataset.level || 1) : 1;

        // Reset student UI
        studentList.style.display    = 'none';
        studentList.innerHTML        = '';
        studentEmpty.style.display   = 'none';
        studentSearch.style.display  = 'none';
        studentBulkBtns.style.display = 'none';
        countEl.textContent          = '0 selected';
        updateEnrollBtn();

        if (!acYearId || !yearLevelId) {
            studentEmpty.innerHTML = `<i class="bi bi-people d-block mb-1" style="font-size:1.4rem;opacity:0.35"></i>
                Select Academic Year &amp; Year Level to load students.`;
            studentEmpty.style.display = 'block';
            return;
        }

        // Year 2+: wait for major to be selected before loading students
        if (level >= 2 && !selMajor.value) {
            studentEmpty.innerHTML = `<i class="bi bi-people d-block mb-1" style="font-size:1.4rem;opacity:0.35"></i>
                Select a Major to load students.`;
            studentEmpty.style.display = 'block';
            return;
        }

        studentLoading.style.display = 'block';

        // Build query params — include major_id for Year 2+
        const params = new URLSearchParams({
            academic_year_id: acYearId,
            year_level_id:    yearLevelId,
        });
        if (level >= 2 && selMajor.value) {
            params.set('major_id', selMajor.value);
        }

        fetch(studentsApiUrl + '?' + params.toString())
            .then(r => r.json())
            .then(students => {
                studentLoading.style.display = 'none';
                studentList.innerHTML = '';

                if (!students.length) {
                    const majorNote = level >= 2 ? ' and Major' : '';
                    studentEmpty.innerHTML = `
                        <i class="bi bi-people d-block mb-1" style="font-size:1.4rem;opacity:0.35"></i>
                        No students found for this Year Level${majorNote} combination.`;
                    studentEmpty.style.display = 'block';
                    return;
                }

                students.forEach(s => {
                    const label = document.createElement('label');
                    label.className = 'student-item';
                    label.dataset.name = (s.name + ' ' + s.email).toLowerCase();
                    label.innerHTML = `
                        <input type="checkbox" name="student_ids[]" value="${s.id}" class="student-cb">
                        <div class="student-avatar">${esc(s.name.charAt(0).toUpperCase())}</div>
                        <div class="student-info">
                            <div class="student-name">${esc(s.name)}</div>
                            <div class="student-email">${esc(s.email)}</div>
                        </div>`;
                    label.querySelector('.student-cb').addEventListener('change', updateStudentCount);
                    studentList.appendChild(label);
                });

                studentList.style.display    = 'block';
                studentSearch.style.display  = 'block';
                studentBulkBtns.style.display = '';
                updateStudentCount();
            })
            .catch(() => {
                studentLoading.style.display = 'none';
                studentEmpty.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i> Failed to load students.';
                studentEmpty.style.display = 'block';
            });
    }

    // ── Load Courses as checkbox list ─────────────────────────
    function loadCourses() {
        const acYearId    = selAcYear.value;
        const yearLevelId = selYearLevel.value;
        const semester    = selSemester.value;
        const opt         = selYearLevel.options[selYearLevel.selectedIndex];
        const level       = opt ? parseInt(opt.dataset.level || '0', 10) : 0;

        // Reset course UI
        courseList.style.display    = 'none';
        courseList.innerHTML        = '';
        courseEmpty.style.display   = 'none';
        courseSearch.style.display  = 'none';
        courseBulkBtns.style.display = 'none';
        courseCount.textContent     = '0 selected';
        courseHint.textContent      = '';
        updateEnrollBtn();

        if (!yearLevelId) {
            courseEmpty.innerHTML = '<i class="bi bi-book d-block mb-1" style="font-size:1.4rem;opacity:0.35"></i>Select Year Level first.';
            courseEmpty.style.display = 'block';
            return;
        }

        // Year 2+: do NOT load courses until a major is selected
        if (level >= 2 && !selMajor.value) {
            courseEmpty.innerHTML = '<i class="bi bi-book d-block mb-1" style="font-size:1.4rem;opacity:0.35"></i>Select a Major to load courses.';
            courseEmpty.style.display = 'block';
            return;
        }

        courseLoading.style.display = 'block';

        const params = new URLSearchParams({
            year_level:       level,
            academic_year_id: acYearId || 0,
            semester:         semester,
        });

        // Major substitution rules:
        //   Year 1         → always fetch CST courses (no major needed)
        //   Year 2+ CS/CT  → fetch CST courses + own major courses (merged)
        //   Year 2+ other  → fetch by selected major only
        const rawMajorId = selMajor.value ? parseInt(selMajor.value, 10) : 0;
        const selectedMajorId = (level === 1)
            ? (CST_MAJOR_ID > 0 ? CST_MAJOR_ID : 0)
            : rawMajorId;

        const isCS = (CS_MAJOR_ID > 0 && selectedMajorId === CS_MAJOR_ID);
        const isCT = (CT_MAJOR_ID > 0 && selectedMajorId === CT_MAJOR_ID);

        let fetchPromises = [];

        if ((isCS || isCT) && CST_MAJOR_ID > 0) {
            // CS or CT: fetch CST courses + own major courses, merge
            const p1 = new URLSearchParams(params);
            p1.set('major_id', CST_MAJOR_ID);
            const p2 = new URLSearchParams(params);
            p2.set('major_id', selectedMajorId);
            fetchPromises = [
                fetch(coursesApiUrl + '?' + p1.toString()).then(r => r.json()),
                fetch(coursesApiUrl + '?' + p2.toString()).then(r => r.json()),
            ];
        } else {
            if (selectedMajorId > 0) {
                params.set('major_id', selectedMajorId);
            }
            fetchPromises = [fetch(coursesApiUrl + '?' + params.toString()).then(r => r.json())];
        }

        Promise.all(fetchPromises)
            .then(results => {
                courseLoading.style.display = 'none';
                courseList.innerHTML = '';

                // Merge and deduplicate by course ID
                const seen = new Set();
                const courses = [];
                results.forEach(list => {
                    list.forEach(c => {
                        if (!seen.has(c.id)) {
                            seen.add(c.id);
                            courses.push(c);
                        }
                    });
                });
                courses.sort((a, b) => a.title.localeCompare(b.title));

                if (!courses.length) {
                    const majorNote = level >= 2 ? ' and Major' : '';
                    courseEmpty.innerHTML = `<i class="bi bi-book d-block mb-1" style="font-size:1.4rem;opacity:0.35"></i>No courses match this Year Level${majorNote} &amp; Semester.`;
                    courseEmpty.style.display = 'block';
                    courseHint.textContent = '';
                    return;
                }

                courses.forEach(c => {
                    const semLabel = c.semester == 1 ? 'Sem 1' : c.semester == 2 ? 'Sem 2' : 'Both';
                    const label = document.createElement('label');
                    label.className = 'course-item';
                    label.dataset.name = (c.title + ' ' + c.code).toLowerCase();
                    label.innerHTML = `
                        <input type="checkbox" name="course_ids[]" value="${c.id}" class="course-cb">
                        <div class="course-item-info">
                            <div class="course-item-title">${esc(c.title)}</div>
                            <div class="course-item-meta">${esc(c.code)} · ${semLabel}</div>
                        </div>`;
                    label.querySelector('.course-cb').addEventListener('change', updateCourseCount);
                    courseList.appendChild(label);
                });

                courseList.style.display    = 'block';
                courseSearch.style.display  = 'block';
                courseBulkBtns.style.display = 'flex';
                courseHint.textContent = courses.length + ' course(s) available.';
                updateCourseCount();
            })
            .catch(() => {
                courseLoading.style.display = 'none';
                courseEmpty.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i> Failed to load courses.';
                courseEmpty.style.display = 'block';
            });
    }

    // ── Course search ──────────────────────────────────────────
    courseSearch.addEventListener('input', function () {
        const q = this.value.toLowerCase().trim();
        courseList.querySelectorAll('.course-item').forEach(item => {
            item.classList.toggle('hidden', q !== '' && !item.dataset.name.includes(q));
        });
    });

    // ── Course select all / none ───────────────────────────────
    document.getElementById('courseSelectAll').addEventListener('click', () => {
        courseList.querySelectorAll('.course-item:not(.hidden) .course-cb')
            .forEach(cb => cb.checked = true);
        updateCourseCount();
    });
    document.getElementById('courseSelectNone').addEventListener('click', () => {
        courseList.querySelectorAll('.course-cb').forEach(cb => cb.checked = false);
        updateCourseCount();
    });

    // ── Student search ─────────────────────────────────────────
    studentSearch.addEventListener('input', function () {
        const q = this.value.toLowerCase().trim();
        studentList.querySelectorAll('.student-item').forEach(item => {
            item.classList.toggle('hidden', q !== '' && !item.dataset.name.includes(q));
        });
    });

    // ── Student select all / none ──────────────────────────────
    document.getElementById('selectAll').addEventListener('click', () => {
        studentList.querySelectorAll('.student-item:not(.hidden) .student-cb')
            .forEach(cb => cb.checked = true);
        updateStudentCount();
    });
    document.getElementById('selectNone').addEventListener('click', () => {
        studentList.querySelectorAll('.student-cb').forEach(cb => cb.checked = false);
        updateStudentCount();
    });

    // ── Cascade change listeners ───────────────────────────────
    // Academic Year change: reload everything
    selAcYear.addEventListener('change', () => {
        toggleMajor();
        loadStudents();
        loadCourses();
    });

    // Year Level change: reset major, reload everything
    selYearLevel.addEventListener('change', () => {
        selMajor.value = '';   // clear stale major selection
        toggleMajor();
        loadStudents();
        loadCourses();
    });

    // Semester change: only reload courses
    selSemester.addEventListener('change', loadCourses);

    // Major change: reload BOTH students AND courses
    selMajor.addEventListener('change', () => {
        loadStudents();
        loadCourses();
    });

    // ── On page load: restore old() state if validation failed ─
    toggleMajor();
    if (selYearLevel.value) {
        loadStudents();
        loadCourses();
    } else if (selAcYear.value) {
        loadCourses();
    }
})();
</script>
@endpush
