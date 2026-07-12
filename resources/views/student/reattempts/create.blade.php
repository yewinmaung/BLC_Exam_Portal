@extends('layouts.app')
@section('title', 'Request Re-Attempt')
@section('page-title', 'Request Re-Attempt')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Student', 'url' => route('student.dashboard')],
        ['label' => 'Re-attempts', 'url' => route('student.reattempts.index')],
        ['label' => 'New Request'],
    ]])
@endsection
@section('sidebar')
@include('partials.student-sidebar')
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><i class="bi bi-arrow-repeat me-2"></i>Request Re-Attempt (Missed Exam)</div>
            <div class="card-body">
                <div class="mb-3 p-3 rounded" style="background:var(--surface-2,#f1f3f9)">
                    <div class="text-muted small">Exam</div>
                    <div style="font-weight:700">{{ $exam->title }}</div>
                    <div class="text-muted small">{{ $exam->course->title ?? '' }}</div>
                    <div class="text-muted small">Teacher: {{ $exam->teacher->name ?? '' }}</div>
                </div>

                <form method="POST" action="{{ route('student.reattempts.store') }}">@csrf
                    <input type="hidden" name="exam_id" value="{{ $exam->id }}">
                    <div class="mb-3">
                        <label class="form-label">Reason <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" required
                                  placeholder="Why did you miss the exam?">{{ old('reason') }}</textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary flex-grow-1"><i class="bi bi-send me-1"></i>Send to Teacher</button>
                        <a href="{{ route('student.reattempts.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

