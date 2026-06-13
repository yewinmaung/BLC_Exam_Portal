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
<div class="card col-lg-9">
    <div class="card-header">Edit courses for {{ $teacher->name }}</div>
    <div class="card-body">
        @include('partials.assign-courses-form', [
            'formAction' => route('admin.teachers.update', $teacher),
            'courses' => $courses,
            'assignedCourseIds' => $assignedCourseIds,
            'label' => 'Courses taught by this teacher',
            'hint' => 'Check each course this teacher should teach.',
            'submitLabel' => 'Save Teacher Courses',
            'cancelUrl' => route('admin.teachers.show', $teacher),
        ])
    </div>
</div>
@endsection
