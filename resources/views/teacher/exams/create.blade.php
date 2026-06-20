@extends('layouts.app')
@section('title', 'Create Exam')
@section('page-title', 'Create New Exam')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Teacher', 'url' => route('teacher.dashboard')],
        ['label' => 'My Exams', 'url' => route('teacher.exams.index')],
        ['label' => 'Create'],
    ]])
@endsection
@section('sidebar')
@include('partials.teacher-sidebar')

@endsection
@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><i class="bi bi-file-earmark-plus me-2"></i>Exam Details</div>
            <div class="card-body">
                <form method="POST" action="{{ route('teacher.exams.store') }}">@csrf

                    <div class="mb-3">
                        <label class="form-label">Course</label>
                        <select name="course_id" class="form-select" required>
                            <option value="">— Select a course —</option>
                            @foreach($courses as $c)
                            <option value="{{ $c->id }}" {{ old('course_id') == $c->id ? 'selected' : '' }}>
                                {{ $c->title }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Exam Title</label>
                        <input type="text" name="title" class="form-control"
                               value="{{ old('title') }}" placeholder="e.g. Midterm Exam — Chapter 1-5" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description <span class="text-muted fw-normal">(optional)</span></label>
                        <textarea name="description" class="form-control" rows="3"
                                  placeholder="Brief description of this exam...">{{ old('description') }}</textarea>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-6">
                            <label class="form-label">Total Marks</label>
                            <input type="number" name="total_marks" class="form-control"
                                   value="{{ old('total_marks', 100) }}" min="1" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Passing Marks</label>
                            <input type="number" name="passing_marks" class="form-control"
                                   value="{{ old('passing_marks', 40) }}" min="0" required>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="bi bi-arrow-right-circle me-1"></i> Create & Add Questions
                        </button>
                        <a href="{{ route('teacher.exams.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
