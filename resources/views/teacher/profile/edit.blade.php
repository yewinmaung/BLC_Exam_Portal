@extends('layouts.app')
@section('title', 'Edit Profile')
@section('page-title', 'Edit Profile')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Teacher', 'url' => route('teacher.dashboard')],
        ['label' => 'My Profile', 'url' => route('teacher.profile.show')],
        ['label' => 'Edit'],
    ]])
@endsection
@section('sidebar')
<nav class="nav flex-column gap-1">
    <a class="nav-link" href="{{ route('teacher.dashboard') }}"><i class="bi bi-speedometer2"></i> Dashboard</a>
    <a class="nav-link active" href="{{ route('teacher.profile.show') }}"><i class="bi bi-person-badge"></i> My Profile</a>
</nav>
@include('partials.sidebar-signout')
@endsection
@section('content')
<div class="card col-lg-7">
    <div class="card-header">Edit My Profile</div>
    <div class="card-body">
        <form method="POST" action="{{ route('teacher.profile.update') }}">@csrf @method('PUT')
            @include('partials.teacher-form', ['teacher' => $teacher, 'isAdminEdit' => false])
            <div class="d-flex gap-2">
                <button class="btn btn-primary">Save</button>
                <a href="{{ route('teacher.profile.show') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
