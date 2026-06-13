@extends('layouts.app')
@section('title', 'Teacher Dashboard')
@section('page-title', 'Dashboard')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Teacher', 'url' => route('teacher.dashboard')],
        ['label' => 'Dashboard'],
    ]])
@endsection
@section('sidebar')
<nav class="nav flex-column gap-1">
    <a class="nav-link {{ request()->routeIs('teacher.dashboard') ? 'active' : '' }}" href="{{ route('teacher.dashboard') }}"><i class="bi bi-speedometer2"></i> Dashboard</a>
    <a class="nav-link {{ request()->routeIs('teacher.profile.*') ? 'active' : '' }}" href="{{ route('teacher.profile.show') }}"><i class="bi bi-person-badge"></i> My Profile</a>
    <a class="nav-link {{ request()->routeIs('teacher.exams.index') || request()->routeIs('teacher.exams.show') ? 'active' : '' }}" href="{{ route('teacher.exams.index') }}"><i class="bi bi-file-earmark-text"></i> My Exams</a>
    <a class="nav-link {{ request()->routeIs('teacher.exams.create') ? 'active' : '' }}" href="{{ route('teacher.exams.create') }}"><i class="bi bi-plus-circle"></i> Create Exam</a>
    <a class="nav-link {{ request()->routeIs('teacher.reattempts.*') ? 'active' : '' }}" href="{{ route('teacher.reattempts.index') }}"><i class="bi bi-arrow-repeat"></i> Re-attempts</a>
    <a class="nav-link {{ request()->routeIs('chat.*') ? 'active' : '' }}" href="{{ route('chat.index') }}"><i class="bi bi-chat-dots"></i> Chat</a>
    <a class="nav-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}" href="{{ route('notifications.index') }}"><i class="bi bi-bell"></i> Notifications</a>
</nav>
@endsection
@section('content')
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="card stat-card stat-navy">
            <div class="stat-icon"><i class="bi bi-book-fill"></i></div>
            <div class="stat-value">{{ $stats['courses'] }}</div>
            <div class="stat-label">My Courses</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card stat-card stat-teal">
            <div class="stat-icon"><i class="bi bi-file-earmark-text-fill"></i></div>
            <div class="stat-value">{{ $stats['exams'] }}</div>
            <div class="stat-label">Total Exams</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card stat-card stat-gold">
            <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-value">{{ $stats['pending_approval'] }}</div>
            <div class="stat-label">Pending Approval</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-lightning-charge-fill text-warning"></i> Quick Actions
    </div>
    <div class="card-body d-flex flex-wrap gap-2">
        <a href="{{ route('teacher.exams.create') }}" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Create New Exam</a>
        <a href="{{ route('teacher.exams.index') }}" class="btn btn-outline-primary"><i class="bi bi-list-ul"></i> View All Exams</a>
    </div>
</div>
@endsection
