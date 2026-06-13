@extends('layouts.app')
@section('title', 'Edit Course')
@section('page-title', 'Edit Course')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Courses', 'url' => route('admin.courses.index')],
        ['label' => 'Edit'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')
<div class="row justify-content-center">
<div class="col-lg-7">
<div class="card">
    <div class="card-header"><i class="bi bi-pencil me-2"></i>Edit — {{ $course->title }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.courses.update', $course) }}">
            @csrf @method('PUT')

            <div class="mb-3">
                <label class="form-label">Title <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control"
                       value="{{ old('title', $course->title) }}" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Code <span class="text-danger">*</span></label>
                <input type="text" name="code" class="form-control"
                       value="{{ old('code', $course->code) }}" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3">{{ old('description', $course->description) }}</textarea>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label class="form-label">Teacher</label>
                    <select name="teacher_id" class="form-select">
                        <option value="">— No teacher —</option>
                        @foreach($teachers as $t)
                        <option value="{{ $t->id }}" {{ old('teacher_id', $course->teacher_id) == $t->id ? 'selected' : '' }}>
                            {{ $t->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6">
                    <label class="form-label">Year Level <span class="text-danger">*</span></label>
                    <select name="year_level" class="form-select" required>
                        @foreach($yearLevels as $val => $label)
                        <option value="{{ $val }}" {{ old('year_level', $course->year_level) == $val ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6">
                    <label class="form-label">Academic Year</label>
                    <select name="academic_year_id" class="form-select">
                        <option value="">— All Academic Years —</option>
                        @foreach($academicYears as $ay)
                        <option value="{{ $ay->id }}" {{ old('academic_year_id', $course->academic_year_id) == $ay->id ? 'selected' : '' }}>
                            {{ $ay->name }} {{ $ay->is_current ? '(Current)' : '' }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6">
                    <label class="form-label">Semester <span class="text-danger">*</span></label>
                    <select name="semester" class="form-select" required>
                        @foreach(\App\Models\Course::$semesterLabels as $val => $label)
                        <option value="{{ $val }}" {{ old('semester', $course->semester) == $val ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mb-4">
                <div class="form-check">
                    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActive"
                           {{ old('is_active', $course->is_active) ? 'checked' : '' }}>
                    <label class="form-check-label" for="isActive">Active</label>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-check-circle me-1"></i> Save Changes
                </button>
                <a href="{{ route('admin.courses.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
</div>
</div>
@endsection
