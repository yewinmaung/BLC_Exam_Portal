@extends('layouts.app')
@section('title', 'Edit Academic Year')
@section('page-title', 'Edit Academic Year')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Academic Years', 'url' => route('admin.academic.years.index')],
        ['label' => 'Edit'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')
<div class="row justify-content-center">
<div class="col-lg-6">
<div class="card">
    <div class="card-header"><i class="bi bi-pencil me-2"></i>Edit — {{ $year->name }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.academic.years.update', $year) }}">
            @csrf @method('PUT')

            <div class="mb-3">
                <label class="form-label">Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control"
                       value="{{ old('name', $year->name) }}"
                       required maxlength="50">
            </div>

            <div class="row g-3 mb-3">
                <div class="col-6">
                    <label class="form-label">Start Year <span class="text-danger">*</span></label>
                    <input type="number" name="start_year" class="form-control"
                           value="{{ old('start_year', $year->start_year) }}"
                           min="2000" max="2099" required>
                </div>
                <div class="col-6">
                    <label class="form-label">End Year <span class="text-danger">*</span></label>
                    <input type="number" name="end_year" class="form-control"
                           value="{{ old('end_year', $year->end_year) }}"
                           min="2000" max="2099" required>
                </div>
            </div>

            <div class="mb-4">
                <div class="form-check">
                    <input type="checkbox" name="is_current" value="1"
                           class="form-check-input" id="isCurrent"
                           {{ old('is_current', $year->is_current) ? 'checked' : '' }}>
                    <label class="form-check-label" for="isCurrent">
                        Set as current academic year
                    </label>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-check-circle me-1"></i> Save Changes
                </button>
                <a href="{{ route('admin.academic.years.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
</div>
</div>
@endsection
