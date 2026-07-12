@extends('layouts.app')
@section('title', 'Edit Student')
@section('page-title', 'Edit Student')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Students', 'url' => route('admin.students.index')],
        ['label' => $student->name],
        ['label' => 'Edit'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')
<div class="row justify-content-center">
<div class="col-lg-8">
<div class="card">
    <div class="card-header"><i class="bi bi-pencil me-2"></i>Edit — {{ $student->name }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.students.update', $student) }}">
            @csrf
             @method('PUT')

            <div class="card mb-3" style="border:1px solid var(--border-2,#e4e5f0)!important;box-shadow:none!important">
                <div class="card-header" style="font-size:0.82rem;font-weight:700">
                    <i class="bi bi-person me-1"></i> Account
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control"
                                   value="{{ old('name', $student->name) }}" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control"
                                   value="{{ old('email', $student->email) }}" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">New Password <span class="text-muted fw-normal">(leave blank to keep)</span></label>
                            <input type="password" name="password" class="form-control" minlength="8">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control"
                                   value="{{ old('phone', $student->phone) }}">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" value="1" class="form-check-input"
                                       id="isActive" {{ old('is_active', $student->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label" for="isActive">Account Active</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3" style="border:1px solid var(--border-2,#e4e5f0)!important;box-shadow:none!important">
                <div class="card-header" style="font-size:0.82rem;font-weight:700">
                    <i class="bi bi-mortarboard me-1"></i> Academic
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label">Academic Year</label>
                            <select name="academic_year_id" class="form-select">
                                <option value="">—</option>
                                @foreach($academicYears as $ay)
                                <option value="{{ $ay->id }}"
                                    {{ old('academic_year_id', $currentRecord?->academic_year_id) == $ay->id ? 'selected' : '' }}>
                                    {{ $ay->name }} {{ $ay->is_current ? '(Current)' : '' }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Year Level</label>
                            <select name="year_level_id" id="sel_year_level" class="form-select">
                                <option value="">—</option>
                                @foreach($yearLevels as $yl)
                                <option value="{{ $yl->id }}"
                                    data-level="{{ $yl->level }}"
                                    {{ old('year_level_id', $currentRecord?->year_level_id) == $yl->id ? 'selected' : '' }}>
                                    {{ $yl->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label">Semester</label>
                            <select name="semester" class="form-select">
                                <option value="1" {{ old('semester', $currentRecord?->semester ?? '1') == '1' ? 'selected' : '' }}>Semester 1</option>
                                <option value="2" {{ old('semester', $currentRecord?->semester) == '2' ? 'selected' : '' }}>Semester 2</option>
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label">Department</label>
                            <input type="text" name="department" class="form-control"
                                   value="{{ old('department', $currentRecord?->department) }}">
                        </div>
                        <div class="col-sm-4" id="majorWrapper">
                            <label class="form-label">
                                Major
                                <span class="text-danger" id="majorRequired" style="display:none">*</span>
                                <span class="text-muted fw-normal" id="majorOptional" style="font-size:0.8rem">(Year 1 — not required)</span>
                            </label>
                            <select name="major_id" id="sel_major" class="form-select @error('major_id') is-invalid @enderror">
                                <option value="">— No Major (Year 1) —</option>
                                @foreach($majors as $m)
                                <option value="{{ $m->id }}"
                                    data-code="{{ $m->code }}"
                                    {{ old('major_id', $currentMajorId) == $m->id ? 'selected' : '' }}>
                                    {{ $m->code }}
                                </option>
                                @endforeach
                            </select>
                            @error('major_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4" style="border:1px solid var(--border-2,#e4e5f0)!important;box-shadow:none!important">
                <div class="card-header" style="font-size:0.82rem;font-weight:700">
                    <i class="bi bi-book me-1"></i> Course Enrollments
                </div>
                <div class="card-body">
                    <div style="max-height:200px;overflow-y:auto;border:1.5px solid var(--border-2,#e4e5f0);border-radius:10px">
                        @foreach($courses as $c)
                        <label style="display:flex;align-items:center;gap:0.6rem;padding:0.5rem 0.85rem;cursor:pointer;border-bottom:1px solid var(--border-2,#e4e5f0);">
                            <input type="checkbox" name="course_ids[]" value="{{ $c->id }}"
                                   style="accent-color:var(--royal,#3730a3);width:15px;height:15px"
                                   {{ in_array($c->id, old('course_ids', $enrolledCourseIds)) ? 'checked' : '' }}>
                            <div>
                                <div style="font-size:0.84rem;font-weight:600">{{ $c->title }}</div>
                                <div style="font-size:0.7rem;color:#9ca3af">{{ $c->code }}</div>
                            </div>
                        </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-check-circle me-1"></i> Save Changes
                </button>
                <a href="{{ route('admin.students.show', $student) }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
</div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const selYearLevel = document.getElementById('sel_year_level');
    const majorSel     = document.getElementById('sel_major');
    const reqBadge     = document.getElementById('majorRequired');
    const optNote      = document.getElementById('majorOptional');

    if (!selYearLevel || !majorSel) return;

    // Store all major options for show/hide
    const allMajorOptions = Array.from(majorSel.options);

    function toggleMajor() {
        const opt   = selYearLevel.options[selYearLevel.selectedIndex];
        const level = opt ? parseInt(opt.dataset.level || '0', 10) : 0;

        // Store currently selected value
        const currentValue = majorSel.value;

        // Clear current options except the first "No Major" option
        while (majorSel.options.length > 1) {
            majorSel.remove(1);
        }

        // Filter and add back appropriate major options
        allMajorOptions.forEach((option, index) => {
            if (index === 0) return; // Skip "No Major" option

            const code = option.dataset.code || '';
            
            // For Year 1: Show ONLY CST
            if (level === 1) {
                if (code === 'CST') {
                    majorSel.add(option.cloneNode(true));
                }
            } 
            // For Year 2+: Show CS, CT, and other majors (hide CST)
            else if (level >= 2) {
                if (code !== 'CST') {
                    majorSel.add(option.cloneNode(true));
                }
            }
        });

        // Restore selection if still available
        majorSel.value = currentValue;

        // Update required field and display
        if (level >= 2) {
            majorSel.required = true;
            reqBadge.style.display = 'inline';
            optNote.style.display  = 'none';
        } else {
            majorSel.required = false;
            if (level < 2) majorSel.value = '';
            reqBadge.style.display = 'none';
            optNote.style.display  = 'inline';
        }
    }

    selYearLevel.addEventListener('change', toggleMajor);
    toggleMajor();
})();
</script>
@endpush
