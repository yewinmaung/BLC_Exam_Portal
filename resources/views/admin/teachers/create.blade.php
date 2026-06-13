@extends('layouts.app')
@section('title', 'Add Teacher')
@section('page-title', 'Add Teacher')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Teachers', 'url' => route('admin.teachers.index')],
        ['label' => 'Create'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection
@section('content')
<div class="card col-lg-7">
    <div class="card-header">New Teacher</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.teachers.store') }}">@csrf
            @include('partials.teacher-form', ['teacher' => new \App\Models\User(), 'isAdminEdit' => false])
            <hr>
            @include('partials.course-assignment-checkboxes', [
                'courses' => $courses,
                'assignedCourseIds' => $assignedCourseIds,
                'label' => 'Assign courses (optional)',
                'hint' => 'You can also assign courses after creating the teacher.',
            ])
            <button class="btn btn-primary">Create Teacher</button>
        </form>
    </div>
</div>
@endsection
