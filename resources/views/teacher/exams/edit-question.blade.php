@extends('layouts.app')
@section('title', 'Edit Question')
@section('page-title', 'Edit Question')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Teacher', 'url' => route('teacher.dashboard')],
        ['label' => 'My Exams', 'url' => route('teacher.exams.index')],
        ['label' => $exam->title, 'url' => route('teacher.exams.show', $exam)],
        ['label' => 'Edit Question'],
    ]])
@endsection
@section('sidebar')
<nav class="nav flex-column gap-1">
    <a class="nav-link" href="{{ route('teacher.dashboard') }}"><i class="bi bi-speedometer2"></i> Dashboard</a>
    <a class="nav-link active" href="{{ route('teacher.exams.index') }}"><i class="bi bi-file-earmark-text"></i> My Exams</a>
    <a class="nav-link" href="{{ route('teacher.exams.create') }}"><i class="bi bi-plus-circle"></i> Create Exam</a>
    <a class="nav-link" href="{{ route('chat.index') }}"><i class="bi bi-chat-dots"></i> Chat</a>
    <a class="nav-link" href="{{ route('notifications.index') }}"><i class="bi bi-bell"></i> Notifications</a>
</nav>

@endsection

@section('content')
<div class="row justify-content-center">
<div class="col-lg-8">

{{-- Info bar --}}
<div class="d-flex align-items-center gap-3 mb-4 p-3 rounded-3"
     style="background:#fffbeb;border:1px solid #fde68a">
    <i class="bi bi-pencil-square text-warning fs-5"></i>
    <div>
        <div class="fw-600" style="font-weight:600;font-size:0.9rem;color:#92400e">
            Editing question in: <strong>{{ $exam->title }}</strong>
        </div>
        <div class="text-muted small">Changes are saved immediately. You can still submit for approval after editing.</div>
    </div>
    <a href="{{ route('teacher.exams.show', $exam) }}" class="btn btn-sm btn-outline-secondary ms-auto">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
</div>

{{-- Edit form card --}}
<div class="card">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-pencil-fill" style="color:var(--blc-gold)"></i>
        Edit Question
    </div>
    <div class="card-body">
        <form method="POST"
              action="{{ route('teacher.exams.questions.update', [$exam, $question]) }}"
              id="questionForm">
            @csrf
            @method('PUT')

            {{-- Type --}}
            <div class="mb-3">
                <label class="form-label">Question Type</label>
                <select name="type" class="form-select" id="qType" required>
                    <option value="mcq"        {{ $question->type === 'mcq'        ? 'selected' : '' }}>Multiple Choice (MCQ)</option>
                    <option value="true_false" {{ $question->type === 'true_false' ? 'selected' : '' }}>True / False</option>
                    <option value="essay"      {{ $question->type === 'essay'      ? 'selected' : '' }}>Essay (written answer)</option>
                    <option value="fill_blank" {{ $question->type === 'fill_blank' ? 'selected' : '' }}>Fill in the Blank</option>
                </select>
            </div>

            {{-- Question text --}}
            <div class="mb-3">
                <label class="form-label" id="contentLabel">Question Text</label>
                <textarea name="content" class="form-control" id="qContent" rows="4"
                          placeholder="Enter your question here..." required>{{ old('content', $question->decrypted_content) }}</textarea>
                <div id="fillBlankHint" class="form-text {{ $question->type === 'fill_blank' ? '' : 'd-none' }}">
                    <i class="bi bi-info-circle me-1"></i>
                    Use <code>___</code> (three underscores) to mark blank positions.
                    <em>Example: The capital of France is ___.</em>
                </div>
            </div>

            {{-- Marks + Difficulty --}}
            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label class="form-label">Marks</label>
                    <input name="marks" type="number" class="form-control"
                           value="{{ old('marks', $question->marks) }}" min="1" required>
                </div>
                <div class="col-sm-6">
                    <label class="form-label">Difficulty</label>
                    <select name="difficulty" class="form-select">
                        <option value="easy"   {{ $question->difficulty === 'easy'   ? 'selected' : '' }}>Easy</option>
                        <option value="medium" {{ $question->difficulty === 'medium' ? 'selected' : '' }}>Medium</option>
                        <option value="hard"   {{ $question->difficulty === 'hard'   ? 'selected' : '' }}>Hard</option>
                    </select>
                </div>
            </div>

            {{-- Category --}}
            <div class="mb-4">
                <label class="form-label">Category <span class="text-muted fw-normal">(optional)</span></label>
                <select name="category_id" class="form-select">
                    <option value="">— None —</option>
                    @foreach($categories as $cat)
                    <option value="{{ $cat->id }}"
                        {{ $question->category_id == $cat->id ? 'selected' : '' }}>
                        {{ $cat->name }}
                    </option>
                    @endforeach
                </select>
            </div>

            {{-- MCQ / True-False answers --}}
            <div id="answersBlock" class="{{ in_array($question->type, ['mcq','true_false']) ? '' : 'd-none' }}">
                <label class="form-label d-flex justify-content-between align-items-center">
                    <span>Answer Choices</span>
                    <small class="text-muted">Mark the correct one</small>
                </label>
                <div id="answersList" class="d-flex flex-column gap-2 mb-2">
                    {{-- Pre-populated by JS from existing answers --}}
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="addAnswerBtn">
                    <i class="bi bi-plus me-1"></i> Add Choice
                </button>
            </div>

            {{-- Fill in the blank accepted answers --}}
            <div id="blankAnswersBlock" class="{{ $question->type === 'fill_blank' ? '' : 'd-none' }}">
                <label class="form-label d-flex justify-content-between align-items-center">
                    <span>Accepted Answers</span>
                    <small class="text-muted">All are marked correct</small>
                </label>
                <div id="blankAnswersList" class="d-flex flex-column gap-2 mb-2">
                    {{-- Pre-populated by JS --}}
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="addBlankAnswerBtn">
                    <i class="bi bi-plus me-1"></i> Add Accepted Answer
                </button>
                <div class="form-text mt-1">
                    Add all acceptable answers (e.g. "Paris", "paris", "PARIS")
                </div>
            </div>

            {{-- Actions --}}
            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-check-circle me-1"></i> Save Changes
                </button>
                <a href="{{ route('teacher.exams.show', $exam) }}" class="btn btn-outline-secondary">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

</div>
</div>
@endsection

@push('scripts')
{{-- Pass existing answers to JS for pre-population --}}
@php
    $existingAnswersJson = $question->answers->map(function($a) {
        return [
            'content'    => $a->decrypted_content,
            'is_correct' => (bool) $a->is_correct,
            'is_blank'   => (bool) $a->is_blank_answer,
        ];
    })->values()->toArray();
@endphp
<script>
window.existingAnswers = @json($existingAnswersJson);
window.editMode = true;
</script>
<script src="{{ asset('js/question-builder.js') }}"></script>
@endpush
