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

    {{-- Assign form --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-person-plus-fill" style="color:var(--blc-gold,#d4a51c)"></i>
                Assign Students to <strong>{{ $year->name }}</strong>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.academic.years.students.assign', $year) }}">@csrf

                    <div class="mb-3">
                        <label class="form-label">Year Level <span class="text-danger">*</span></label>
                        <select name="year_level_id" class="form-select" required>
                            <option value="">— Select Year Level —</option>
                            @foreach($yearLevels as $yl)
                            <option value="{{ $yl->id }}" {{ old('year_level_id') == $yl->id ? 'selected' : '' }}>
                                {{ $yl->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Semester <span class="text-danger">*</span></label>
                        <select name="semester" class="form-select" required>
                            <option value="1" {{ old('semester') == '1' ? 'selected' : '' }}>Semester 1</option>
                            <option value="2" {{ old('semester') == '2' ? 'selected' : '' }}>Semester 2</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Department <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="text" name="department" class="form-control"
                               value="{{ old('department') }}" placeholder="e.g. Computer Science">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Major <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="text" name="major" class="form-control"
                               value="{{ old('major') }}" placeholder="e.g. Software Engineering">
                    </div>

                    <div class="mb-3">
                        <label class="form-label d-flex justify-content-between">
                            <span>Students <span class="text-danger">*</span></span>
                            <span class="text-muted small" id="selCount">0 selected</span>
                        </label>
                        <input type="text" id="stuSearch" class="form-control form-control-sm mb-2"
                               placeholder="Search students..." autocomplete="off">
                        <div id="stuList" style="max-height:240px;overflow-y:auto;border:1.5px solid var(--border-2,#e4e5f0);border-radius:10px;background:var(--surface,#fff)">
                            @forelse($availableStudents as $s)
                            <label style="display:flex;align-items:center;gap:0.6rem;padding:0.6rem 0.85rem;border-bottom:1px solid var(--border-2,#e4e5f0);cursor:pointer;"
                                   class="stu-item" data-name="{{ strtolower($s->name) }} {{ strtolower($s->email) }}">
                                <input type="checkbox" name="student_ids[]" value="{{ $s->id }}"
                                       class="stu-cb" style="accent-color:var(--royal,#3730a3);width:15px;height:15px">
                                <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#1e1b6e,#3730a3);color:#fff;display:flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:700;flex-shrink:0">
                                    {{ strtoupper(substr($s->name,0,1)) }}
                                </div>
                                <div style="min-width:0">
                                    <div style="font-size:0.82rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $s->name }}</div>
                                    <div style="font-size:0.7rem;color:#9ca3af;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $s->email }}</div>
                                </div>
                            </label>
                            @empty
                            <div class="p-3 text-center text-muted small">All students already enrolled.</div>
                            @endforelse
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-person-check me-1"></i> Assign Students
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Enrolled list --}}
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-list-check me-2"></i>Currently Enrolled</span>
                <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">
                    {{ $records->total() }} total
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table datatable mb-0">
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
(function(){
    const searchInput = document.getElementById('stuSearch');
    const countEl     = document.getElementById('selCount');

    document.querySelectorAll('.stu-cb').forEach(cb => {
        cb.addEventListener('change', updateCount);
    });

    searchInput?.addEventListener('input', function(){
        const q = this.value.toLowerCase();
        document.querySelectorAll('.stu-item').forEach(item => {
            item.style.display = item.dataset.name.includes(q) ? '' : 'none';
        });
    });

    function updateCount(){
        const n = document.querySelectorAll('.stu-cb:checked').length;
        countEl.textContent = n + ' selected';
    }
    updateCount();
})();
</script>
@endpush
