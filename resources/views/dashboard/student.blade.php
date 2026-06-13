@extends('layouts.app')
@section('title', 'Student Dashboard')
@section('page-title', 'Dashboard')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Student', 'url' => route('student.dashboard')],
        ['label' => 'Dashboard'],
    ]])
@endsection
@section('sidebar')
<nav class="nav flex-column gap-1">
    <a class="nav-link {{ request()->routeIs('student.dashboard') ? 'active' : '' }}" href="{{ route('student.dashboard') }}"><i class="bi bi-speedometer2"></i> Dashboard</a>
    <a class="nav-link {{ request()->routeIs('student.courses.*') ? 'active' : '' }}" href="{{ route('student.courses.index') }}"><i class="bi bi-book"></i> My Courses</a>
    <a class="nav-link {{ request()->routeIs('student.exams.*') || request()->routeIs('student.exam.*') ? 'active' : '' }}" href="{{ route('student.exams.index') }}"><i class="bi bi-pencil-square"></i> Exams</a>
    <a class="nav-link {{ request()->routeIs('student.reattempts.*') ? 'active' : '' }}" href="{{ route('student.reattempts.index') }}"><i class="bi bi-arrow-repeat"></i> Re-attempts</a>
    <a class="nav-link {{ request()->routeIs('chat.*') ? 'active' : '' }}" href="{{ route('chat.index') }}"><i class="bi bi-chat-dots"></i> Chat</a>
    <a class="nav-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}" href="{{ route('notifications.index') }}"><i class="bi bi-bell"></i> Notifications</a>
</nav>

@endsection
@section('content')
<div class="row g-3 mb-4">
    <div class="col-sm-6">
        <div class="card stat-card stat-navy">
            <div class="stat-icon"><i class="bi bi-book-fill"></i></div>
            <div class="stat-value">{{ $stats['enrolled_courses'] }}</div>
            <div class="stat-label">Enrolled Courses</div>
        </div>
    </div>
    <div class="col-sm-6">
        <div class="card stat-card stat-teal">
            <div class="stat-icon"><i class="bi bi-patch-check-fill"></i></div>
            <div class="stat-value">{{ $stats['completed_exams'] }}</div>
            <div class="stat-label">Completed Exams</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-lightning-charge-fill text-warning"></i> Quick Actions
    </div>
    <div class="card-body d-flex flex-wrap gap-2">
        <a href="{{ route('student.exams.index') }}" class="btn btn-primary"><i class="bi bi-pencil-square"></i> View Exams</a>
        <a href="{{ route('student.courses.index') }}" class="btn btn-outline-primary"><i class="bi bi-book"></i> My Courses</a>
    </div>
</div>
@endsection
