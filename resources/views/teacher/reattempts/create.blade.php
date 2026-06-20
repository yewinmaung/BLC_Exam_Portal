@extends('layouts.app')
@section('title', 'New Re-Attempt Request')
@section('page-title', 'New Re-Attempt Request')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Teacher', 'url' => route('teacher.dashboard')],
        ['label' => 'Re-Attempts', 'url' => route('teacher.reattempts.index')],
        ['label' => 'New Request'],
    ]])
@endsection
@section('sidebar')
@include('partials.teacher-sidebar')
@endsection

@section('content')
<div class="row justify-content-center">
<div class="col-lg-7">
<div class="card">
    <div class="card-header"><i class="bi bi-arrow-repeat me-2"></i>Submit Re-Attempt Request</div>
    <div class="card-body">
        <div class="alert-modern alert-info mb-4" style="background:#ede9fe;border-radius:10px;padding:0.85rem 1rem;display:flex;gap:0.5rem;font-size:0.855rem;color:#3730a3;border:1px solid rgba(55,48,163,0.2)">
            <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
            <span>After submitting, the admin will review and approve/reject the request. The student <strong>cannot</strong> retake the exam until admin approves.</span>
        </div>

        <form method="POST" action="{{ route('teacher.reattempts.store') }}">@csrf

            @if($exams->count() === 0)
            <div class="alert alert-warning">
                No exams found for your account. Create an exam first (or ask admin to assign you a course).
            </div>
            @endif

            <div class="mb-3">
                <label class="form-label">Student <span class="text-danger">*</span></label>
                <select name="student_id" class="form-select" required id="studentSelect">
                    <option value="">— Select Student —</option>
                    @foreach($students as $s)
                    <option value="{{ $s->id }}" {{ old('student_id') == $s->id ? 'selected' : '' }}>
                        {{ $s->name }} ({{ $s->email }})
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Exam <span class="text-danger">*</span></label>
                <select name="exam_id" class="form-select" required @disabled($exams->count() === 0)>
                    <option value="">— Select Exam —</option>
                    @foreach($exams as $e)
                    <option value="{{ $e->id }}" {{ old('exam_id') == $e->id ? 'selected' : '' }}>
                        {{ $e->title }} — {{ $e->course->title ?? '—' }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Reason <span class="text-danger">*</span></label>
                <select name="reason_preset" class="form-select mb-2" id="reasonPreset">
                    <option value="">— Select preset or write below —</option>
                    @foreach(\App\Models\ReAttemptRequest::$reasons as $key => $label)
                    <option value="{{ $label }}">{{ $label }}</option>
                    @endforeach
                </select>
                <textarea name="reason" class="form-control" rows="3" id="reasonText"
                          placeholder="Provide detailed reason for re-attempt..."
                          required>{{ old('reason') }}</textarea>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-send me-1"></i> Submit Request to Admin
                </button>
                <a href="{{ route('teacher.reattempts.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
</div>
</div>
@endsection

@push('scripts')
<script>
// Preset reason → textarea
document.getElementById('reasonPreset')?.addEventListener('change', function() {
    if (this.value) document.getElementById('reasonText').value = this.value;
});
</script>
@endpush
