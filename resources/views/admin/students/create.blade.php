@extends('layouts.app')
@section('title', 'Create Student')
@section('page-title', 'Create Student')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Students', 'url' => route('admin.students.index')],
        ['label' => 'Create'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')
<div class="row justify-content-center">
<div class="col-lg-8">
<div class="card">
    <div class="card-header"><i class="bi bi-person-plus me-2"></i>New Student</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.students.store') }}">@csrf

            {{-- ── Account ── --}}
            <div class="section-box mb-3">
                <div class="section-title"><i class="bi bi-person me-1"></i> Account Information</div>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required minlength="8" placeholder="Min. 8 characters">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                    </div>
                </div>
            </div>

            {{-- ── Academic ── --}}
            <div class="section-box mb-3">
                <div class="section-title"><i class="bi bi-mortarboard me-1"></i> Academic Information</div>
                <div class="row g-3">

                    {{-- 1. Academic Year --}}
                    <div class="col-sm-6">
                        <label class="form-label">Academic Year</label>
                        <select name="academic_year_id" id="sel_academic_year" class="form-select">
                            <option value="">— Not assigned —</option>
                            @foreach($academicYears as $ay)
                            <option value="{{ $ay->id }}"
                                {{ old('academic_year_id') == $ay->id ? 'selected' : '' }}
                                {{ $ay->is_current ? 'data-current=1' : '' }}>
                                {{ $ay->name }} {{ $ay->is_current ? '(Current)' : '' }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- 2. Year Level --}}
                    <div class="col-sm-6">
                        <label class="form-label">Year Level</label>
                        <select name="year_level_id" id="sel_year_level" class="form-select">
                            <option value="">— Not assigned —</option>
                            @foreach($yearLevels as $yl)
                            <option value="{{ $yl->id }}"
                                data-level="{{ $yl->level }}"
                                {{ old('year_level_id') == $yl->id ? 'selected' : '' }}>
                                {{ $yl->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- 3. Semester --}}
                    <div class="col-sm-4">
                        <label class="form-label">Semester</label>
                        <select name="semester" id="sel_semester" class="form-select">
                            <option value="1" {{ old('semester','1') == '1' ? 'selected' : '' }}>Semester 1</option>
                            <option value="2" {{ old('semester') == '2' ? 'selected' : '' }}>Semester 2</option>
                        </select>
                    </div>

                    <div class="col-sm-4">
                        <label class="form-label">Department</label>
                        <input type="text" name="department" class="form-control" value="{{ old('department') }}" placeholder="e.g. Computer Science">
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Major</label>
                        <input type="text" name="major" class="form-control" value="{{ old('major') }}" placeholder="e.g. Software Engineering">
                    </div>
                </div>
            </div>

            {{-- ── Course enrollment — dynamically filtered ── --}}
            

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-check-circle me-1"></i> Create Student
                </button>
                <a href="{{ route('admin.students.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
</div>
</div>
@endsection

@push('styles')
<style>
.section-box {
    border: 1px solid var(--border-2, #e4e5f0);
    border-radius: 10px;
    overflow: hidden;
}
.section-title {
    background: var(--surface-2, #f1f3f9);
    padding: 0.65rem 1rem;
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--text-1, #111827);
    border-bottom: 1px solid var(--border-2, #e4e5f0);
}
.section-box > .row { padding: 1rem; margin: 0; }
.section-box > .row.g-3 { padding: 0.85rem 0.85rem 0.85rem 0.85rem; }
.course-item {
    display: flex; align-items: center; gap: 0.6rem;
    padding: 0.55rem 0.85rem; cursor: pointer;
    border-bottom: 1px solid var(--border-2, #e4e5f0);
    transition: background 0.12s;
}
.course-item:last-child { border-bottom: none; }
.course-item:hover { background: var(--royal-light, #ede9fe); }
.course-item input[type="checkbox"] {
    accent-color: var(--royal, #3730a3);
    width: 15px; height: 15px; flex-shrink: 0;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const selAcYear    = document.getElementById('sel_academic_year');
    const selYearLevel = document.getElementById('sel_year_level');
    const selSemester  = document.getElementById('sel_semester');
    const courseList   = document.getElementById('courseList');
    const courseEmpty  = document.getElementById('courseEmpty');
    const courseLoading= document.getElementById('courseLoading');
    const filterLabel  = document.getElementById('courseFilterLabel');
    const apiUrl       = "{{ route('admin.courses.by-year-level') }}";

    let selectedCourseIds = @json(old('course_ids', []));

    function loadCourses() {
        const academicYearId = selAcYear.value;
        const yearLevelId    = selYearLevel.value;
        const semester       = selSemester.value;

        // Get the level number from the selected option's data attribute
        const selectedOption = selYearLevel.options[selYearLevel.selectedIndex];
        const level          = selectedOption ? (selectedOption.dataset.level || 0) : 0;

        if (!yearLevelId) {
            courseList.innerHTML = '';
            courseEmpty.style.display = 'block';
            courseList.appendChild(courseEmpty);
            filterLabel.textContent = 'Select year level & semester to filter courses';
            return;
        }

        // Show loading
        courseList.innerHTML = '';
        courseLoading.style.display = 'block';
        filterLabel.textContent = 'Loading...';

        const params = new URLSearchParams({
            year_level:       level,
            academic_year_id: academicYearId,
            semester:         semester,
        });

        fetch(apiUrl + '?' + params.toString())
            .then(r => r.json())
            .then(courses => {
                courseLoading.style.display = 'none';
                courseList.innerHTML = '';

                if (!courses.length) {
                    courseList.innerHTML = `
                        <div class="text-center py-4 text-muted small">
                            <i class="bi bi-exclamation-circle d-block mb-1" style="font-size:1.5rem;opacity:0.4"></i>
                            No courses found for this combination. Create courses first with matching settings.
                        </div>`;
                    filterLabel.textContent = '0 courses found';
                    return;
                }

                filterLabel.textContent = courses.length + ' course(s) available';

                courses.forEach(c => {
                    const label = document.createElement('label');
                    label.className = 'course-item';

                    const checked = selectedCourseIds.includes(String(c.id)) ? 'checked' : '';
                    const semLabel = c.semester == 1 ? 'Sem 1' : c.semester == 2 ? 'Sem 2' : 'Both';

                    label.innerHTML = `
                        <input type="checkbox" name="course_ids[]" value="${c.id}" ${checked}>
                        <div>
                            <div style="font-size:0.84rem;font-weight:600">${escHtml(c.title)}</div>
                            <div style="font-size:0.7rem;color:#9ca3af">${escHtml(c.code)} · ${semLabel}</div>
                        </div>`;
                    courseList.appendChild(label);
                });
            })
            .catch(() => {
                courseLoading.style.display = 'none';
                courseList.innerHTML = '<div class="text-center py-3 text-muted small">Failed to load courses. Please try again.</div>';
                filterLabel.textContent = 'Error loading';
            });
    }

    function escHtml(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    selAcYear.addEventListener('change',    loadCourses);
    selYearLevel.addEventListener('change', loadCourses);
    selSemester.addEventListener('change',  loadCourses);

    // Auto-load if values already selected (e.g. old() on validation error)
    if (selYearLevel.value) {
        loadCourses();
    }
})();
</script>
@endpush
