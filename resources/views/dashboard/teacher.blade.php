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
@include('partials.teacher-sidebar')

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
