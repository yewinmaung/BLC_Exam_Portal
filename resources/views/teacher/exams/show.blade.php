@extends('layouts.app')
@section('title', $exam->title)
@section('page-title', $exam->title)
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Teacher', 'url' => route('teacher.dashboard')],
        ['label' => 'My Exams', 'url' => route('teacher.exams.index')],
        ['label' => $exam->title],
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

{{-- Header --}}
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div class="d-flex align-items-center gap-3">
        <span class="status-pill status-{{ $exam->status === 'pending_approval' ? 'pending' : $exam->status }}">
            {{ ucfirst(str_replace('_', ' ', $exam->status)) }}
        </span>
        <span class="text-muted small"><i class="bi bi-book me-1"></i>{{ $exam->course->title }}</span>
    </div>
    <div class="d-flex gap-2">
        @if($exam->status === 'draft')
        <form method="POST" action="{{ route('teacher.exams.submit', $exam) }}">@csrf
            <button class="btn btn-success">
                <i class="bi bi-send me-1"></i> Submit for Approval
            </button>
        </form>
        @endif
        <a href="{{ route('teacher.exams.results', $exam) }}" class="btn btn-outline-primary">
            <i class="bi bi-bar-chart me-1"></i> Results
        </a>
    </div>
</div>

<div class="row g-3">

    {{-- ── Question list ── --}}
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-list-ol me-2"></i>Questions</span>
                <span class="badge" style="background:var(--blc-gold-light);color:var(--blc-navy)">
                    {{ $exam->questions->count() }} question{{ $exam->questions->count() !== 1 ? 's' : '' }}
                </span>
            </div>
            <div class="card-body">
                @forelse($exam->questions as $i => $q)
                <div class="question-card">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="flex-grow-1">
                            {{-- Badges --}}
                            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                <span class="q-number">Q{{ $i + 1 }}</span>
                                <span class="badge" style="background:#f0f4ff;color:var(--blc-navy-2);font-size:0.7rem">
                                    {{ strtoupper(str_replace('_', ' ', $q->type)) }}
                                </span>
                                <span class="badge" style="background:#f0fdf4;color:#166534;font-size:0.7rem">
                                    {{ $q->marks }} mark{{ $q->marks !== 1 ? 's' : '' }}
                                </span>
                                <span class="badge" style="background:#fafafa;color:#6b7280;font-size:0.7rem;border:1px solid #e5e7eb">
                                    {{ ucfirst($q->difficulty) }}
                                </span>
                            </div>

                            {{-- Question text --}}
                            <div class="q-text">
                                @if($canDecrypt)
                                    @if($q->type === 'fill_blank')
                                        {!! nl2br(e($q->decrypted_content)) !!}
                                    @else
                                        {{ $q->decrypted_content }}
                                    @endif
                                @else
                                    🔒 Encrypted
                                @endif
                            </div>

                            {{-- Answers / blanks --}}
                            @if($canDecrypt && $q->answers->count())
                                @if($q->type === 'fill_blank')
                                <div class="mt-2">
                                    <small class="text-muted fw-600" style="font-weight:600;font-size:0.75rem">Accepted answers:</small>
                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                        @foreach($q->answers as $a)
                                        <span style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;border-radius:6px;padding:0.2rem 0.6rem;font-size:0.78rem;font-weight:600">
                                            {{ $a->decrypted_content }}
                                        </span>
                                        @endforeach
                                    </div>
                                </div>
                                @else
                                <div class="mt-2 d-flex flex-column gap-1">
                                    @foreach($q->answers as $a)
                                    <div class="answer-option {{ $a->is_correct ? 'correct' : '' }}">
                                        <i class="bi {{ $a->is_correct ? 'bi-check-circle-fill' : 'bi-circle' }}" style="font-size:0.8rem"></i>
                                        {{ $a->decrypted_content }}
                                    </div>
                                    @endforeach
                                </div>
                                @endif
                            @endif
                        </div>

                        {{-- Action buttons --}}
                        @if(in_array($exam->status, ['draft', 'pending_approval']))
                        <div class="d-flex flex-column gap-1 flex-shrink-0">
                            <a href="{{ route('teacher.exams.questions.edit', [$exam, $q]) }}"
                               class="btn btn-sm btn-outline-primary" title="Edit question">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route('teacher.exams.questions.destroy', [$exam, $q]) }}" method="POST"
                                  onsubmit="return confirm('Delete this question?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                        @endif
                    </div>
                </div>
                @empty
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-question-circle d-block mb-2" style="font-size:2.5rem;opacity:0.4"></i>
                    <p class="mb-0">No questions yet. Add your first question →</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ── Add question panel ── --}}
    <div class="col-lg-5">
        @if(in_array($exam->status, ['draft', 'pending_approval']))
        <div class="card">
            <div class="card-header"><i class="bi bi-plus-circle me-2"></i>Add Question</div>
            <div class="card-body">
                <form method="POST" action="{{ route('teacher.exams.questions.store', $exam) }}" id="questionForm">@csrf

                    <div class="mb-3">
                        <label class="form-label">Question Type</label>
                        <select name="type" class="form-select" id="qType" required>
                            <option value="mcq">Multiple Choice (MCQ)</option>
                            <option value="true_false">True / False</option>
                            <option value="essay">Essay (written answer)</option>
                            <option value="fill_blank">Fill in the Blank</option>
                        </select>
                    </div>

                    <div class="mb-3" id="contentBlock">
                        <label class="form-label" id="contentLabel">Question Text</label>
                        <textarea name="content" class="form-control" id="qContent" rows="3"
                                  placeholder="Enter your question here..." required></textarea>
                        <div id="fillBlankHint" class="form-text d-none">
                            <i class="bi bi-info-circle me-1"></i>
                            Use <code>___</code> (three underscores) to mark blank positions.<br>
                            <em>Example: The capital of France is ___.</em>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Marks</label>
                            <input name="marks" type="number" class="form-control" value="1" min="1">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Difficulty</label>
                            <select name="difficulty" class="form-select">
                                <option value="easy">Easy</option>
                                <option value="medium" selected>Medium</option>
                                <option value="hard">Hard</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Category <span class="text-muted fw-normal">(optional)</span></label>
                        <select name="category_id" class="form-select">
                            <option value="">— None —</option>
                            @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- MCQ / True-False answers --}}
                    <div id="answersBlock">
                        <label class="form-label d-flex justify-content-between align-items-center">
                            <span>Answer Choices</span>
                            <small class="text-muted">Mark the correct one</small>
                        </label>
                        <div id="answersList" class="d-flex flex-column gap-2 mb-2"></div>
                        <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="addAnswerBtn">
                            <i class="bi bi-plus me-1"></i> Add Choice
                        </button>
                    </div>

                    {{-- Fill in the blank accepted answers --}}
                    <div id="blankAnswersBlock" class="d-none">
                        <label class="form-label d-flex justify-content-between align-items-center">
                            <span>Accepted Answers</span>
                            <small class="text-muted">All are marked correct</small>
                        </label>
                        <div id="blankAnswersList" class="d-flex flex-column gap-2 mb-2"></div>
                        <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="addBlankAnswerBtn">
                            <i class="bi bi-plus me-1"></i> Add Accepted Answer
                        </button>
                        <div class="form-text mt-1">
                            Add all acceptable answers (e.g. "Paris", "paris", "PARIS")
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mt-3">
                        <i class="bi bi-check-circle me-1"></i> Save Question
                    </button>
                </form>
            </div>
        </div>
        @endif
    </div>

    {{-- ── Import Questions panel ── --}}
    @if(in_array($exam->status, ['draft', 'pending_approval']))
    <div class="col-lg-5 col-12">
        <div class="card mt-0">
            <div class="card-header"><i class="bi bi-upload me-2"></i>Import Questions</div>
            <div class="card-body">
                <form method="POST" action="{{ route('teacher.exams.import', $exam) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Question File <span class="text-danger">*</span></label>
                        <input type="file" name="import_file" class="form-control"
                               accept=".txt,.pdf,.doc,.docx" required>
                        <div class="form-text">Supports: .txt, .pdf, .doc, .docx — Max 5MB</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category <span class="text-muted fw-normal">(optional)</span></label>
                        <select name="category_id" class="form-select">
                            <option value="">— None —</option>
                            @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="alert alert-info py-2 mb-3" style="font-size:0.78rem">
                        <strong>Format:</strong><br>
                        <code>[MCQ] Question text (2 marks)</code><br>
                        <code>A. Option 1</code><br>
                        <code>B. Correct *</code><br><br>
                        <code>[TRUE_FALSE] Statement (1 mark)</code><br>
                        <code>True *</code><br>
                        <code>False</code>
                    </div>
                    <button type="submit" class="btn btn-outline-primary w-100">
                        <i class="bi bi-upload me-1"></i> Import Questions
                    </button>
                </form>
            </div>
        </div>
    </div>
    @endif

</div>
@endsection
@push('scripts')
<script src="{{ asset('js/question-builder.js') }}"></script>
@endpush
