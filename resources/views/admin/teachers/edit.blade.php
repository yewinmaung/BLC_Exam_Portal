@extends('layouts.app')
@section('title', 'Edit Teacher Courses')
@section('page-title', 'Edit Assigned Courses')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Teachers', 'url' => route('admin.teachers.index')],
        ['label' => $teacher->name, 'url' => route('admin.teachers.show', $teacher)],
        ['label' => 'Edit Courses'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection
@section('content')
<div class="row">
   <div class="col-6">
     <div class="card">
    <div class="card-header">Edit Teacher</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.teachers.update',$teacher)}}">
            @csrf @method('PUT')
            @include('partials.teacher-form')
            <hr>
            <!-- @include('partials.course-assignment-checkboxes', [
                'courses' => $courses,
                'assignedCourseIds' => $assignedCourseIds,
                'label' => 'Assign courses (optional)',
                'hint' => 'You can also assign courses after creating the teacher.',
            ]) -->
            <button class="btn btn-primary">Update Teacher</button>
        </form>
    </div>
</div>
   </div>
<div class="col-6">
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <span><i class="bi bi-book me-2"></i>Courses for {{ $teacher->name }}</span>
            <span class="badge" style="background:var(--blc-gold-light,#fef9ec);color:var(--blc-navy,#0b2a5b);font-weight:600">
                {{ count($assignedCourseIds) }} course{{ count($assignedCourseIds) !== 1 ? 's' : '' }}
            </span>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">Courses currently assigned to this teacher.</p>

            @if(empty($assignedCourseIds))
                <p class="text-muted small">No courses assigned.</p>
            @else
                @php
                    $assignedCourses = $courses->whereIn('id', $assignedCourseIds)->values();
                @endphp
                <div class="row g-2" style="max-height:400px;overflow-y:auto">
                    @foreach($assignedCourses as $course)
                    <div class="col-md-6">
                        <div class="border rounded p-2 bg-light d-flex align-items-start gap-2">
                            <i class="bi bi-book-half text-primary mt-1 flex-shrink-0"></i>
                            <span class="small">
                                <strong>{{ $course->title }}</strong>
                                <span class="text-muted">({{ $course->code }})</span>
                            </span>
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
</div>

@endsection
