@extends('layouts.app')
@section('title', 'Edit Course')
@section('page-title', 'Edit Course')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Courses', 'url' => route('admin.courses.index')],
        ['label' => $course->title],
        ['label' => 'Edit'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')
<div class="row justify-content-center">
<div class="col-lg-8">
<div class="card">
    <div class="card-header"><i class="bi bi-pencil me-2"></i>Edit — {{ $course->title }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.courses.update', $course) }}">
            @csrf @method('PUT')

            {{-- ── Basic Info ── --}}
            <div class="card mb-3" style="border:1px solid var(--border-2,#e4e5f0)!important;box-shadow:none!important">
                <div class="card-header" style="font-size:0.82rem;font-weight:700">
                    <i class="bi bi-info-circle me-1"></i> Basic Information
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-8">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                                   value="{{ old('title', $course->title) }}" required>
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label">Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
                                   value="{{ old('code', $course->code) }}" required>
                            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2">{{ old('description', $course->description) }}</textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActive"
                                       {{ old('is_active', $course->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label" for="isActive">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Academic Structure ── --}}
            <div class="card mb-3" style="border:1px solid var(--border-2,#e4e5f0)!important;box-shadow:none!important">
                <div class="card-header" style="font-size:0.82rem;font-weight:700">
                    <i class="bi bi-mortarboard me-1"></i> Academic Structure
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label">Academic Year <span class="text-danger">*</span></label>
                            <select name="academic_year_id" class="form-select @error('academic_year_id') is-invalid @enderror" required>
                                <option value="">— Select Academic Year —</option>
                                @foreach($academicYears as $ay)
                                <option value="{{ $ay->id }}" {{ old('academic_year_id', $course->academic_year_id) == $ay->id ? 'selected' : '' }}>
                                    {{ $ay->name }} {{ $ay->is_current ? '(Current)' : '' }}
                                </option>
                                @endforeach
                            </select>
                            @error('academic_year_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Year Level <span class="text-danger">*</span></label>
                            <select name="year_level" id="yearLevelSelect" class="form-select @error('year_level') is-invalid @enderror" required>
                                @foreach($yearLevels as $val => $label)
                                <option value="{{ $val }}" {{ old('year_level', $course->year_level) == $val ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                                @endforeach
                            </select>
                            @error('year_level')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Semester <span class="text-danger">*</span></label>
                            <select name="semester" class="form-select @error('semester') is-invalid @enderror" required>
                                <option value="1" {{ old('semester', $course->semester) == '1' ? 'selected' : '' }}>Semester 1</option>
                                <option value="2" {{ old('semester', $course->semester) == '2' ? 'selected' : '' }}>Semester 2</option>
                                <option value="0" {{ old('semester', $course->semester) == '0' ? 'selected' : '' }}>Both</option>
                            </select>
                            @error('semester')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-sm-6" id="majorWrapper">
                            <label class="form-label">
                                Major <span class="text-danger">*</span>
                            </label>
                            <select name="major_id" id="majorSelect" class="form-select @error('major_id') is-invalid @enderror" required>
                                <option value="">— Select Major —</option>
                                @foreach($majors as $m)
                                <option value="{{ $m->id }}" {{ old('major_id', $course->major_id) == $m->id ? 'selected' : '' }}>
                                    {{ $m->code }}
                                </option>
                                @endforeach
                            </select>
                            @error('major_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Teacher Assignment ── --}}
            <div class="card mb-3" style="border:1px solid var(--border-2,#e4e5f0)!important;box-shadow:none!important">
                <div class="card-header" style="font-size:0.82rem;font-weight:700">
                    <i class="bi bi-person-badge me-1"></i> Teacher Assignment
                </div>
                <div class="card-body">
                    <label class="form-label">Assigned Teacher <span class="text-danger">*</span></label>
                    <select name="teacher_id" class="form-select @error('teacher_id') is-invalid @enderror" required>
                        <option value="">— Select Teacher —</option>
                        @foreach($teachers as $t)
                        <option value="{{ $t->id }}" {{ old('teacher_id', $course->teacher_id) == $t->id ? 'selected' : '' }}>
                            {{ $t->name }}
                        </option>
                        @endforeach
                    </select>
                    @error('teacher_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
