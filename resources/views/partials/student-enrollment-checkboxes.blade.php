@php
    $selectedIds = array_map('intval', old('student_ids', $enrolledIds ?? []));
@endphp
<div class="mb-3">
    <label class="form-label fw-semibold">Enrolled Students</label>
    <p class="text-muted small">Select students enrolled in this course. Each student&apos;s academic year is used for the enrollment record.</p>
    @if($students->isEmpty())
    <p class="text-muted">No students found.</p>
    @else
    <div class="d-flex gap-2 mb-2">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-select-all-students>Select all</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-select-none-students>Clear all</button>
    </div>
    <div class="row g-2" style="max-height:280px;overflow-y:auto">
        @foreach($students as $s)
        <div class="col-md-6">
            <label class="d-flex align-items-start gap-2 border rounded p-2 mb-0 w-100 {{ in_array($s->id, $selectedIds) ? 'border-primary bg-light' : '' }}"
                   style="cursor:pointer">
                <input type="checkbox" class="form-check-input mt-1 student-checkbox" name="student_ids[]"
                       value="{{ $s->id }}" @checked(in_array($s->id, $selectedIds))>
                <span class="small">
                    <strong>{{ $s->name }}</strong>
                    @if($s->academic_year)<span class="badge bg-primary-subtle text-primary ms-1">Y{{ $s->academic_year }}</span>@endif
                    <br><span class="text-muted">{{ $s->email }}</span>
                </span>
            </label>
        </div>
        @endforeach
    </div>
    @endif
</div>

@once
@push('scripts')
<script>
document.querySelectorAll('[data-select-all-students]').forEach(btn => {
    btn.addEventListener('click', () => {
        btn.closest('.mb-3')?.querySelectorAll('.student-checkbox').forEach(cb => { cb.checked = true; });
    });
});
document.querySelectorAll('[data-select-none-students]').forEach(btn => {
    btn.addEventListener('click', () => {
        btn.closest('.mb-3')?.querySelectorAll('.student-checkbox').forEach(cb => { cb.checked = false; });
    });
});
</script>
@endpush
@endonce
