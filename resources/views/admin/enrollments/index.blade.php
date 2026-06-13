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
                                {{ old('academic_year_id') == $ay->id ? 'selected' : '' }}>
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

                    {{-- ③ Students (dynamically loaded) --}}
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
                        <div class="d-flex gap-2 mb-2" id="studentBulkBtns" style="display:none!important">
                            <button type="button" id="selectAll"
                                    style="font-size:0.75rem;padding:0.2rem 0.6rem"
                                    class="btn btn-outline-secondary btn-sm">Select all</button>
                            <button type="button" id="selectNone"
                                    style="font-size:0.75rem;padding:0.2rem 0.6rem"
                                    class="btn btn-outline-secondary btn-sm">Clear</button>
                        </div>
                        <div class="student-list" id="studentList" style="display:none"></div>
                    </div>

                    {{-- ④ Semester ── drives course list --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Semester <span class="text-danger">*</span>
                        </label>
                        <select name="semester" id="selSemester" class="form-select">
                            <option value="1" {{ old('semester','1') == '1' ? 'selected' : '' }}>Semester 1</option>
                            <option value="2" {{ old('semester') == '2' ? 'selected' : '' }}>Semester 2</option>
                        </select>
                    </div>

                    {{-- ⑤ Course (filtered by AY + Year Level + Semester via AJAX) --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Course <span class="text-danger">*</span>
                        </label>
                        <select name="course_id" class="form-select" id="courseSelect" required>
                            <option value="">— Select Academic Year &amp; Year Level first —</option>
                        </select>
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

        {{-- Filter bar --}}
        <div class="card mb-3">
            <div class="card-body py-2">
                <form method="GET" action="{{ route('admin.enrollments.index') }}"
                      class="d-flex flex-wrap gap-2 align-items-end">
                    <div style="min-width:180px;flex:1">
                        <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600">Course</label>
                        <select name="course_id" class="form-select form-select-sm">
                            <option value="">All courses</option>
                            @foreach($courses as $c)
                            <option value="{{ $c->id }}" {{ request('course_id') == $c->id ? 'selected' : '' }}>
                                {{ $c->title }}
                                @if($c->year_level > 0)
                                    ({{ \App\Models\Course::$yearLevelLabels[$c->year_level] ?? '' }})
                                @endif
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div style="min-width:120px">
                        <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600">Year Level</label>
                        <select name="year" class="form-select form-select-sm">
                            <option value="">All years</option>
                            @foreach($years as $val => $label)
                            <option value="{{ $val }}" {{ request('year') == $val ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div style="min-width:180px;flex:1">
                        <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600">Student</label>
                        <select name="student_id" class="form-select form-select-sm">
                            <option value="">All students</option>
                            @foreach($students as $s)
                            <option value="{{ $s->id }}" {{ request('student_id') == $s->id ? 'selected' : '' }}>
                                {{ $s->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="d-flex gap-1">
                        <button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
                        <a href="{{ route('admin.enrollments.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
                    </div>
                </form>
            </div>
        </div>

        {{-- Table --}}
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-list-check me-2"></i>Enrolled Students</span>
                <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">
                    {{ $enrollments->total() }} total
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table datatable mb-0">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Course</th>
                                <th>Year Level</th>
                                <th>Enrolled</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($enrollments as $e)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#1e1b6e,#3730a3);color:#fff;display:flex;align-items:center;justify-content:center;font-size:0.72rem;font-weight:700;flex-shrink:0">
                                            {{ strtoupper(substr($e->student->name ?? '?',0,1)) }}
                                        </div>
                                        <div>
                                            <div style="font-weight:600;font-size:0.875rem">{{ $e->student->name ?? '—' }}</div>
                                            <div style="font-size:0.72rem;color:#9ca3af">{{ $e->student->email ?? '' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-size:0.875rem">{{ $e->course->title ?? '—' }}</span>
                                    <div style="font-size:0.72rem;color:#9ca3af">{{ $e->course->code ?? '' }}</div>
                                </td>
                                <td>
                                    @php $yl = $e->course->year_level ?? 0; @endphp
                                    <span class="year-badge year-badge-{{ $e->year }}">
                                        @if($yl > 0)
                                            {{ \App\Models\Course::$yearLevelLabels[$yl] ?? 'Year '.$yl }}
                                        @else
                                            All Years
                                        @endif
                                    </span>
                                </td>
                                <td style="font-size:0.78rem;color:#6b7280">
                                    {{ $e->enrolled_at?->format('M d, Y') ?? $e->created_at->format('M d, Y') }}
                                </td>
                                <td>
                                    <form method="POST"
                                          action="{{ route('admin.enrollments.destroy', $e) }}"
                                          onsubmit="return confirm('Remove this enrollment?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Remove">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-person-x d-block mb-2" style="font-size:2rem;opacity:0.35"></i>
                                    No enrollments found.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($enrollments->hasPages())
                <div class="p-3 border-top">{{ $enrollments->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
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
    const hiddenYear     = document.getElementById('hiddenYear');
    const courseSelect   = document.getElementById('courseSelect');
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

    function esc(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function updateCount() {
        const n = studentList.querySelectorAll('.student-cb:checked').length;
        countEl.textContent = n + ' selected';
        enrollBtn.disabled = (n === 0);
    }

    // ── Load Students (by Academic Year + Year Level) ─────────
    function loadStudents() {
        const acYearId    = selAcYear.value;
        const yearLevelId = selYearLevel.value;

        // Sync hidden year field from selected option's data-level
        const opt = selYearLevel.options[selYearLevel.selectedIndex];
        hiddenYear.value = opt ? (opt.dataset.level || 1) : 1;

        // Reset student UI
        studentList.style.display   = 'none';
        studentList.innerHTML       = '';
        studentEmpty.style.display  = 'none';
        studentSearch.style.display = 'none';
        studentBulkBtns.style.display = 'none';
        enrollBtn.disabled          = true;
        countEl.textContent         = '0 selected';

        if (!acYearId || !yearLevelId) {
            studentEmpty.style.display = 'block';
            return;
        }

        studentLoading.style.display = 'block';

        fetch(studentsApiUrl + '?academic_year_id=' + acYearId + '&year_level_id=' + yearLevelId)
            .then(r => r.json())
            .then(students => {
                studentLoading.style.display = 'none';
                studentList.innerHTML = '';

                if (!students.length) {
                    studentEmpty.innerHTML = `
                        <i class="bi bi-people d-block mb-1" style="font-size:1.4rem;opacity:0.35"></i>
                        No students found for this Academic Year &amp; Year Level combination.`;
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
                    label.querySelector('.student-cb').addEventListener('change', updateCount);
                    studentList.appendChild(label);
                });

                studentList.style.display   = 'block';
                studentSearch.style.display = 'block';
                studentBulkBtns.style.display = '';
                updateCount();
            })
            .catch(() => {
                studentLoading.style.display = 'none';
                studentEmpty.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i> Failed to load students.';
                studentEmpty.style.display = 'block';
            });
    }

    // ── Load Courses (by AY + Year Level + Semester) ──────────
    function loadCourses() {
        const acYearId    = selAcYear.value;
        const yearLevelId = selYearLevel.value;
        const semester    = selSemester.value;
        const opt         = selYearLevel.options[selYearLevel.selectedIndex];
        const level       = opt ? (opt.dataset.level || 0) : 0;

        courseSelect.innerHTML = '<option value="">Loading...</option>';
        courseSelect.disabled  = true;
        courseHint.textContent = '';

        if (!yearLevelId) {
            courseSelect.innerHTML = '<option value="">— Select Year Level first —</option>';
            courseSelect.disabled  = false;
            return;
        }

        const params = new URLSearchParams({
            year_level:       level,
            academic_year_id: acYearId || 0,
            semester:         semester,
        });

        fetch(coursesApiUrl + '?' + params.toString())
            .then(r => r.json())
            .then(courses => {
                courseSelect.innerHTML = '<option value="">— Select course —</option>';
                if (!courses.length) {
                    courseSelect.innerHTML += '<option disabled>No courses for this combination</option>';
                    courseHint.textContent = 'No courses match this academic year, year level & semester.';
                } else {
                    courses.forEach(c => {
                        const semLabel = c.semester == 1 ? 'Sem 1' : c.semester == 2 ? 'Sem 2' : 'Both';
                        const opt = document.createElement('option');
                        opt.value = c.id;
                        opt.textContent = c.title + ' (' + c.code + ') · ' + semLabel;
                        courseSelect.appendChild(opt);
                    });
                    courseHint.textContent = courses.length + ' course(s) available.';
                }
                courseSelect.disabled = false;
            })
            .catch(() => {
                courseSelect.innerHTML = '<option value="">Error loading courses</option>';
                courseSelect.disabled  = false;
            });
    }

    // ── Student search ─────────────────────────────────────────
    studentSearch.addEventListener('input', function () {
        const q = this.value.toLowerCase().trim();
        studentList.querySelectorAll('.student-item').forEach(item => {
            item.classList.toggle('hidden', q !== '' && !item.dataset.name.includes(q));
        });
    });

    // ── Select all / none ──────────────────────────────────────
    document.getElementById('selectAll').addEventListener('click', () => {
        studentList.querySelectorAll('.student-item:not(.hidden) .student-cb')
            .forEach(cb => cb.checked = true);
        updateCount();
    });
    document.getElementById('selectNone').addEventListener('click', () => {
        studentList.querySelectorAll('.student-cb').forEach(cb => cb.checked = false);
        updateCount();
    });

    // ── Cascade change listeners ───────────────────────────────
    selAcYear.addEventListener('change', () => { loadStudents(); loadCourses(); });
    selYearLevel.addEventListener('change', () => { loadStudents(); loadCourses(); });
    selSemester.addEventListener('change', loadCourses);

    // ── On page load: restore old() state if validation failed ─
    if (selYearLevel.value) {
        loadStudents();
        loadCourses();
    }
})();
</script>
@endpush
