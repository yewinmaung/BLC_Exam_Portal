@extends('layouts.app')
@section('title', 'My Profile')
@section('page-title', 'My Profile')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Teacher', 'url' => route('teacher.dashboard')],
        ['label' => 'My Profile'],
    ]])
@endsection
@section('sidebar')
@include('partials.teacher-sidebar')

@endsection
@section('content')
<div class="d-flex justify-content-end mb-3">
    <a href="{{ route('teacher.profile.edit') }}" class="btn btn-primary"><i class="bi bi-pencil"></i> Edit Profile</a>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h5>{{ $teacher->name }}</h5>
                <p class="text-muted mb-1">{{ $teacher->email }}</p>
                @if($teacher->phone)<p class="small"><i class="bi bi-telephone"></i> {{ $teacher->phone }}</p>@endif
                <span class="badge bg-primary">Teacher</span>
                <hr>
                <div class="small">
                    <div class="d-flex justify-content-between mb-1"><span>Courses</span><strong>{{ $stats['courses'] }}</strong></div>
                    <div class="d-flex justify-content-between mb-1"><span>Exams</span><strong>{{ $stats['exams'] }}</strong></div>
                    <div class="d-flex justify-content-between"><span>Pending</span><strong>{{ $stats['pending'] }}</strong></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card mb-3">
            <div class="card-header">My Courses</div>
            <ul class="list-group list-group-flush">
                @forelse($teacher->taughtCourses as $c)
                <li class="list-group-item">{{ $c->title }} ({{ $c->code }})</li>
                @empty
                <li class="list-group-item text-muted">No courses assigned yet.</li>
                @endforelse
            </ul>
        </div>
        <div class="card">
            <div class="card-header">Recent Exams</div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead><tr><th>Exam</th><th>Course</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse($teacher->examsAsTeacher as $exam)
                        <tr>
                            <td><a href="{{ route('teacher.exams.show', $exam) }}">{{ $exam->title }}</a></td>
                            <td>{{ $exam->course->title ?? '—' }}</td>
                            <td>{{ $exam->status }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-muted">No exams yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
