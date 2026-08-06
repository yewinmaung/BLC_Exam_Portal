@extends('layouts.app')
@section('title', 'Manage Students — '.$year->name)
@section('page-title', 'Manage Students')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Academic Years', 'url' => route('admin.academic.years.index')],
        ['label' => $year->name, 'url' => route('admin.academic.years.show', $year)],
        ['label' => 'Students'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')
<div class="row g-3">

    {{-- ── Assign Students Panel ── --}}
    @if($availableStudents->count())
    <div class="col-12">
        <div class="card">
            <div class="card-header"><i class="bi bi-person-plus me-2"></i>Assign Students to {{ $year->name }}</div>
            <div class="card-body">
                @if($errors->any())
                <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ route('admin.academic.years.students.assign', $year) }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label">Students <span class="text-danger">*</span></label>
                            <select name="student_ids[]" class="form-select" multiple size="5" required>
                                @foreach($availableStudents as $s)
                                <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->email }})</option>
                                @endforeach
                            </select>
                            <div class="form-text">Hold Ctrl / Cmd to select multiple.</div>
                        </div>
                        <div class="col-sm-6">
                            <div class="row g-2">
                                <div class="col-12">
                                    <label class="form-label">Year Level <span class="text-danger">*</span></label>
                                    <select name="year_level_id" class="form-select" required>
                                        @foreach($yearLevels as $yl)
                                        <option value="{{ $yl->id }}">{{ $yl->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Semester <span class="text-danger">*</span></label>
                                    <select name="semester" class="form-select" required>
                                        <option value="1">Semester 1</option>
                                        <option value="2">Semester 2</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Student Type</label>
                                    <select name="record_type" id="sel_record_type_assign" class="form-select">
                                        @foreach(\App\Enums\RecordType::LABELS as $value => $label)
                                        <option value="{{ $value }}" {{ old('record_type', \App\Enums\RecordType::NORMAL) === $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Department</label>
                                    <input type="text" name="department" class="form-control" placeholder="Optional">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Major</label>
                                    <input type="text" name="major" class="form-control" placeholder="Optional">
                                </div>
                                <div class="col-12" id="remarkWrapperAssign" style="display:none">
                                    <label class="form-label">
                                        Remark <span class="text-danger">*</span>
                                    </label>
                                    <textarea name="remark" id="inp_remark_assign"
                                              class="form-control @error('remark') is-invalid @enderror"
                                              rows="2" maxlength="1000"
                                              placeholder="e.g. Transferred from University of Computer Studies (Yangon).">{{ old('remark') }}</textarea>
                                    @error('remark')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-person-check me-1"></i>Assign Selected Students
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- Enrolled list (full width now) --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-list-check me-2"></i>Currently Enrolled — {{ $year->name }}</span>
                <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">
                    {{ $records->total() }} total
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Year Level</th>
                                <th>Semester</th>
                                <th>Dept / Major</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($records as $r)
                            <tr>
                                <td>
                                    <div style="font-weight:600;font-size:0.875rem">{{ $r->student->name }}</div>
                                    <div style="font-size:0.72rem;color:#9ca3af">{{ $r->student->email }}</div>
                                </td>
                                <td>{{ $r->yearLevel->name ?? '—' }}</td>
                                <td>Sem {{ $r->semester }}</td>
                                <td style="font-size:0.82rem;color:#6b7280">
                                    {{ $r->department ?? '' }}
                                    @if($r->major) <br><span style="font-size:0.72rem">{{ $r->major }}</span> @endif
                                </td>
                                <td>
                                    <span class="status-pill status-{{ $r->status === 'active' ? 'approved' : ($r->status === 'promoted' ? 'published' : 'closed') }}">
                                        {{ ucfirst($r->status) }}
                                    </span>
                                    @if($r->record_type && $r->record_type !== \App\Enums\RecordType::NORMAL)
                                    <span class="badge ms-1" style="background:#fef9c3;color:#92400e;font-size:0.62rem;font-weight:700">
                                        {{ \App\Enums\RecordType::LABELS[$r->record_type] ?? $r->record_type }}
                                    </span>
                                    @endif
                                </td>
                                <td>
                                    <form method="POST"
                                          action="{{ route('admin.academic.years.students.remove', [$year, $r->student]) }}"
                                          onsubmit="return confirm('Remove {{ addslashes($r->student->name) }} from this year?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Remove">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-people d-block mb-2" style="font-size:2rem;opacity:0.35"></i>
                                    No students assigned yet.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($records->hasPages())
                <div class="p-3 border-top">{{ $records->links() }}</div>
                @endif
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
(function () {
    const selType   = document.getElementById('sel_record_type_assign');
    const wrapper   = document.getElementById('remarkWrapperAssign');
    const inp       = document.getElementById('inp_remark_assign');

    function toggle() {
        if (!selType || !wrapper || !inp) return;
        const needs = (selType.value === 'TRANSFER' || selType.value === 'READMISSION');
        wrapper.style.display = needs ? 'block' : 'none';
        inp.required = needs;
    }

    if (selType) {
        selType.addEventListener('change', toggle);
        toggle();
    }
})();
</script>
@endpush