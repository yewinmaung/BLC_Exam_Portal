@php
    $selectedIds = array_map('intval', old('course_ids', $assignedCourseIds ?? []));
@endphp
<div class="mb-3" id="assign-courses">
    <label class="form-label fw-semibold">{{ $label ?? 'Assigned Courses' }}</label>
    @if(!empty($hint))
    <p class="text-muted small mb-2">{{ $hint }}</p>
    @endif

    @if($courses->isEmpty())
    <p class="text-muted">No active courses available. <a href="{{ route('admin.courses.create') }}">Create a course</a> first.</p>
    @else
    <div class="d-flex gap-2 mb-2">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-select-all-courses>Select all</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-select-none-courses>Clear all</button>
    </div>
    <div class="row g-2 course-checklist" style="max-height:320px;overflow-y:auto">
        @foreach($courses as $course)
        <div class="col-md-6">
            <label class="d-flex align-items-start gap-2 border rounded p-2 mb-0 w-100 {{ in_array($course->id, $selectedIds) ? 'border-primary bg-light' : '' }}"
                   style="cursor:pointer">
                <input type="checkbox" class="form-check-input mt-1 course-checkbox" name="course_ids[]"
                       value="{{ $course->id }}" @checked(in_array($course->id, $selectedIds))>
                <span class="small">
                    <strong>{{ $course->title }}</strong>
                    <span class="text-muted">({{ $course->code }})</span>
                    @if(($showTeacher ?? false) && $course->teacher)
                    <br><span class="text-muted">Teacher: {{ $course->teacher->name }}</span>
                    @endif
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
document.querySelectorAll('[data-select-all-courses]').forEach(btn => {
    btn.addEventListener('click', () => {
        const root = btn.closest('#assign-courses') || btn.closest('.card-body');
        root?.querySelectorAll('.course-checkbox').forEach(cb => { cb.checked = true; });
    });
});
document.querySelectorAll('[data-select-none-courses]').forEach(btn => {
    btn.addEventListener('click', () => {
        const root = btn.closest('#assign-courses') || btn.closest('.card-body');
        root?.querySelectorAll('.course-checkbox').forEach(cb => { cb.checked = false; });
    });
});
</script>
@endpush
@endonce
