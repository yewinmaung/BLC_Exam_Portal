<form method="POST" action="{{ $formAction }}" class="assign-courses-form">
    @csrf
    @method('PUT')
    @include('partials.course-assignment-checkboxes', [
        'courses' => $courses,
        'assignedCourseIds' => $assignedCourseIds,
        'label' => $label ?? 'Assigned Courses',
        'hint' => $hint ?? null,
        'showTeacher' => $showTeacher ?? false,
    ])
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary" @if(!empty($submitDisabled)) disabled @endif>
            <i class="bi bi-check-lg"></i> {{ $submitLabel ?? 'Save Course Assignments' }}
        </button>
        @if(!empty($cancelUrl))
        <a href="{{ $cancelUrl }}" class="btn btn-outline-secondary">Cancel</a>
        @endif
    </div>
</form>
