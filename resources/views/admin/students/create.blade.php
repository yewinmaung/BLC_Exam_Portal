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
                                {{ old('academic_year_id', $currentYearId) == $ay->id ? 'selected' : '' }}>
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
                    <div class="col-sm-4" id="majorWrapper">
                        <label class="form-label" id="majorLabel">
                            Major
                            <span class="text-danger" id="majorRequired" style="display:none">*</span>
                            <span class="text-muted fw-normal" id="majorOptional" style="font-size:0.8rem">(Year 1 — not required)</span>
                        </label>
                        <select name="major_id" id="sel_major" class="form-select @error('major_id') is-invalid @enderror">
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
</style>
@endpush

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
