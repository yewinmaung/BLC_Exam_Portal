@extends('layouts.app')
@section('title', $teacher->name)
@section('page-title', 'Teacher Details')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Teachers', 'url' => route('admin.teachers.index')],
        ['label' => $teacher->name],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection
@section('content')
<div class="d-flex justify-content-end gap-2 mb-3">
    <a href="{{ route('admin.teachers.index') }}" class="btn btn-outline-secondary">Back</a>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center"
                     style="width:72px;height:72px;background:linear-gradient(135deg,var(--blc-navy),var(--blc-navy-2));color:#fff;font-size:1.5rem;font-weight:700">
                    {{ strtoupper(substr($teacher->name, 0, 1)) }}
                </div>
                <h5 class="mb-1">{{ $teacher->name }}</h5>
                <p class="text-muted small mb-2">{{ $teacher->email }}</p>
                @if($teacher->phone)<p class="small mb-2"><i class="bi bi-telephone"></i> {{ $teacher->phone }}</p>@endif
                <span class="badge bg-{{ $teacher->is_active ? 'success' : 'secondary' }}">
                    {{ $teacher->is_active ? 'Active' : 'Inactive' }}
                </span>
                <hr>
                <div class="text-start small">
                    <div class="d-flex justify-content-between mb-1"><span>Courses</span><strong>{{ $stats['courses'] }}</strong></div>
                    <div class="d-flex justify-content-between mb-1"><span>Exams</span><strong>{{ $stats['exams'] }}</strong></div>
                    <div class="d-flex justify-content-between"><span>Pending approval</span><strong>{{ $stats['pending'] }}</strong></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-book me-1"></i> Assigned Courses</span>
                <span class="badge" style="background:var(--blc-gold-light,#fef9ec);color:var(--blc-navy,#0b2a5b);font-weight:600">
                    {{ count($assignedCourseIds) }} course{{ count($assignedCourseIds) !== 1 ? 's' : '' }}
                </span>
            </div>
            <div class="card-body">
                @if(empty($assignedCourseIds))
                    <p class="text-muted small mb-0">No courses assigned.</p>
                @else
                    @php
                        $assignedCourses = $courses->whereIn('id', $assignedCourseIds)->values();
                    @endphp
                    <div class="row g-2" style="max-height:320px;overflow-y:auto">
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

        <div class="card">
            <div class="card-header">Recent Exams</div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead><tr><th>Title</th><th>Course</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse($teacher->examsAsTeacher as $exam)
                        <tr>
                            <td><a href="{{ route('admin.exams.show', $exam) }}">{{ $exam->title }}</a></td>
                            <td>{{ $exam->course->title ?? '—' }}</td>
                            <td><span class="badge bg-secondary">{{ $exam->status }}</span></td>
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
