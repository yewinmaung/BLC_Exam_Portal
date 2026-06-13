@extends('layouts.app')
@section('title', 'Admin Dashboard')
@section('page-title', 'Dashboard')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Dashboard'],
    ]])
@endsection
@section('sidebar')
@include('partials.admin-sidebar')
@endsection
@section('content')
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card stat-navy">
            <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
            <div class="stat-value">{{ $stats['users'] }}</div>
            <div class="stat-label">Total Users</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card stat-gold">
            <div class="stat-icon"><i class="bi bi-book-fill"></i></div>
            <div class="stat-value">{{ $stats['courses'] }}</div>
            <div class="stat-label">Courses</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card stat-teal">
            <div class="stat-icon"><i class="bi bi-file-earmark-text-fill"></i></div>
            <div class="stat-value">{{ $stats['exams'] }}</div>
            <div class="stat-label">Exams</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card stat-rose">
            <div class="stat-icon"><i class="bi bi-shield-exclamation"></i></div>
            <div class="stat-value">{{ $stats['cheating_logs'] }}</div>
            <div class="stat-label">Violations</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-lightning-charge-fill text-warning"></i> Quick Actions
            </div>
            <div class="card-body d-flex flex-wrap gap-2">
                <a href="{{ route('admin.users.create') }}" class="btn btn-primary"><i class="bi bi-person-plus"></i> Add User</a>
                <a href="{{ route('admin.courses.create') }}" class="btn btn-outline-primary"><i class="bi bi-plus-circle"></i> New Course</a>
                <a href="{{ route('admin.exams.index') }}" class="btn btn-outline-primary"><i class="bi bi-file-earmark-text"></i> Manage Exams</a>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-info-circle-fill" style="color:var(--blc-navy-2)"></i> System Overview
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">Platform</span><strong>Believe Exam Portal</strong></div>
                <div class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">Role</span><strong>Administrator</strong></div>
                <div class="d-flex justify-content-between py-2"><span class="text-muted">Today</span><strong>{{ now()->format('M d, Y') }}</strong></div>
            </div>
        </div>
    </div>
</div>
@endsection
