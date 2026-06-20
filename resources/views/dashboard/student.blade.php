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
@include('partials.student-sidebar')

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
