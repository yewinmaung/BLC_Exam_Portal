@extends('layouts.app')
@section('title', 'Create Exam')
@section('page-title', 'Create New Exam')
@section('breadcrumbs')
    @if(isset($selectedCourse) && $selectedCourse)
        @include('partials.breadcrumbs', ['items' => [
            ['label' => 'Teacher', 'url' => route('teacher.dashboard')],
            ['label' => 'My Profile', 'url' => route('teacher.profile.show')],
            ['label' => $selectedCourse->title, 'url' => route('teacher.profile.course-detail', $selectedCourse)],
            ['label' => 'Create'],
        ]])
    @else
        @include('partials.breadcrumbs', ['items' => [
            ['label' => 'Teacher', 'url' => route('teacher.dashboard')],
            ['label' => 'My Exams', 'url' => route('teacher.exams.index')],
            ['label' => 'Create'],
        ]])
    @endif
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

                    {{-- ── Course: auto-selected or manual dropdown ── --}}
                    @if(isset($selectedCourse) && $selectedCourse)

                        {{-- Pre-selected course: hidden input + display-only block --}}
                        <input type="hidden" name="course_id" value="{{ $selectedCourse->id }}">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Course</label>
                            <div class="d-flex align-items-center gap-2 p-2 rounded"
                                 style="background:#f0f4ff;border:1.5px solid #c7d2fe;">
                                <i class="bi bi-book-half" style="color:var(--blc-royal,#2d27a0);font-size:1.1rem"></i>
                                <div>
                                    <div style="font-weight:700;color:var(--blc-navy,#0b2a5b);font-size:0.92rem">
                                        {{ $selectedCourse->title }}
                                        @if($selectedCourse->code)
                                        <span style="color:var(--blc-royal,#2d27a0)">({{ $selectedCourse->code }})</span>
                                        @endif
                                    </div>
                                    <div style="font-size:0.75rem;color:#6b7280">
                                        {{ $selectedCourse->yearLevelLabel }}
                                        &nbsp;·&nbsp;{{ $selectedCourse->semesterLabel }}
                                        @if($selectedCourse->academicYear)
                                        &nbsp;·&nbsp;{{ $selectedCourse->academicYear->name }}
                                        @endif
                                    </div>
                                </div>
                                <span class="ms-auto badge"
                                      style="background:#dbeafe;color:#1e40af;font-size:0.72rem;font-weight:600">
                                    Auto Selected
                                </span>
                            </div>
                        </div>

                    @else

                        {{-- Manual course selection --}}
                        <div class="mb-3">
                            <label class="form-label">Course</label>
                            <select name="course_id" class="form-select" required>
                                <option value="">— Select a course —</option>
                                @foreach($courses as $c)
                                @php
                                    $ylLabel  = \App\Models\Course::$yearLevelLabels[$c->year_level] ?? ('Year '.$c->year_level);
                                    $semLabel = $c->semester > 0 ? 'Sem '.$c->semester : 'Both Sems';
                                @endphp
                                <option value="{{ $c->id }}" {{ old('course_id') == $c->id ? 'selected' : '' }}>
                                    {{ $c->title }}
                                    @if($c->code) ({{ $c->code }}) @endif
                                    — {{ $ylLabel }}, {{ $semLabel }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                    @endif

                    <div class="mb-3">
                        <label class="form-label">Academic Year</label>
                        <select name="academic_year_id" class="form-select" required>
                            <option value="">— Select academic year —</option>
                            @foreach($academicYears as $ay)
                            <option value="{{ $ay->id }}"
                                {{ old('academic_year_id', optional(\App\Models\AcademicYear::current())->id) == $ay->id ? 'selected' : '' }}>
                                {{ $ay->name }}{{ $ay->is_current ? ' (Current)' : '' }}
                            </option>
                            @endforeach
                        </select>
                        <div class="form-text">The academic year this exam belongs to.</div>
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

                    {{-- Question Order Randomization --}}
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Question Order Randomization</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="shuffle_questions"
                                       id="shuffleDisabled" value="0"
                                       {{ old('shuffle_questions', '0') == '0' ? 'checked' : '' }}>
                                <label class="form-check-label" for="shuffleDisabled">
                                    <strong>Disabled</strong>
                                    <div class="text-muted" style="font-size:0.78rem">Every student sees questions in teacher-defined order</div>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="shuffle_questions"
                                       id="shuffleEnabled" value="1"
                                       {{ old('shuffle_questions') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label" for="shuffleEnabled">
                                    <strong>Enabled</strong>
                                    <div class="text-muted" style="font-size:0.78rem">Each student receives questions in a unique random order</div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="bi bi-arrow-right-circle me-1"></i> Create & Add Questions
                        </button>
                        @if(isset($selectedCourse) && $selectedCourse)
                        <a href="{{ route('teacher.profile.course-detail', $selectedCourse) }}" class="btn btn-outline-secondary">Cancel</a>
                        @else
                        <a href="{{ route('teacher.exams.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
