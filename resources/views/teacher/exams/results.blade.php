@extends('layouts.app')
@section('title', 'Results — '.$exam->title)
@section('page-title', 'Exam Results')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Teacher', 'url' => route('teacher.dashboard')],
        ['label' => 'My Exams', 'url' => route('teacher.exams.index')],
        ['label' => $exam->title, 'url' => route('teacher.exams.show', $exam)],
        ['label' => 'Results'],
    ]])
@endsection
@section('sidebar')
<nav class="nav flex-column gap-1">
    <a class="nav-link" href="{{ route('teacher.dashboard') }}"><i class="bi bi-speedometer2"></i> Dashboard</a>
    <a class="nav-link active" href="{{ route('teacher.exams.index') }}"><i class="bi bi-file-earmark-text"></i> My Exams</a>
    <a class="nav-link" href="{{ route('teacher.exams.create') }}"><i class="bi bi-plus-circle"></i> Create Exam</a>
    <a class="nav-link" href="{{ route('teacher.reattempts.index') }}"><i class="bi bi-arrow-repeat"></i> Re-attempt Requests</a>
    <a class="nav-link" href="{{ route('chat.index') }}"><i class="bi bi-chat-dots"></i> Chat</a>
    <a class="nav-link" href="{{ route('notifications.index') }}"><i class="bi bi-bell"></i> Notifications</a>
</nav>

@endsection

@section('content')

{{-- Exam info strip --}}
<div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
    <div>
        <h6 class="mb-0" style="font-weight:700;color:var(--text-1)">{{ $exam->title }}</h6>
        <small class="text-muted"><i class="bi bi-book me-1"></i>{{ $exam->course->title }}</small>
    </div>
    <div class="ms-auto d-flex gap-2">
        <a href="{{ route('teacher.exams.show', $exam) }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Exam
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-bar-chart me-2"></i>Student Results</span>
        <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">
            {{ $results->count() }} students
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table datatable mb-0">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Score</th>
                        <th>Percentage</th>
                        <th>Grade</th>
                        <th>Status</th>
                        <th>Re-attempt</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($results as $r)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,var(--royal-deeper,#1e1b6e),var(--royal,#3730a3));color:#fff;display:flex;align-items:center;justify-content:center;font-size:0.72rem;font-weight:700;flex-shrink:0">
                                    {{ strtoupper(substr($r->student->name,0,1)) }}
                                </div>
                                <span style="font-weight:600">{{ $r->student->name }}</span>
                            </div>
                        </td>
                        <td>
                            <span style="font-weight:700;color:var(--text-1)">{{ $r->obtained_marks }}</span>
                            <span class="text-muted">/{{ $r->total_marks }}</span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:60px;height:6px;background:#e5e7eb;border-radius:3px;overflow:hidden">
                                    <div style="width:{{ $r->percentage }}%;height:100%;background:{{ $r->is_passed ? '#22c55e' : '#ef4444' }};border-radius:3px"></div>
                                </div>
                                <span style="font-size:0.82rem;font-weight:600">{{ $r->percentage }}%</span>
                            </div>
                        </td>
                        <td>
                            <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3);font-size:0.8rem;font-weight:700">
                                {{ $r->grade }}
                            </span>
                        </td>
                        <td>
                            @if($r->is_passed)
                                <span class="status-pill status-approved">Passed</span>
                            @else
                                <span class="status-pill status-closed">Failed</span>
                            @endif
                        </td>
                        <td>
                            @php
                                // Only failed students can be requested for re-attempt
                                $canRequestReattempt = !$r->is_passed;
                                $pending = $r->student->id ? \App\Models\ReAttemptRequest::where('exam_id', $exam->id)
                                    ->where('student_id', $r->student->id)
                                    ->whereIn('status', ['pending', 'approved'])
                                    ->exists() : false;
                            @endphp
                            @if(!$canRequestReattempt)
                                <span class="text-muted" style="font-size:0.8rem">—</span>
                            @elseif($pending)
                                <span class="status-pill status-pending" style="font-size:0.72rem">Requested</span>
                            @else
                                <button type="button"
                                        class="btn btn-sm btn-outline-primary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#reattemptModal"
                                        data-student-id="{{ $r->student->id }}"
                                        data-student-name="{{ $r->student->name }}">
                                    <i class="bi bi-arrow-repeat me-1"></i>Request
                                </button>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-bar-chart d-block mb-2" style="font-size:2rem;opacity:0.3"></i>
                            No results yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
    </div>
</div>

{{-- Re-attempt Request Modal --}}
<div class="modal fade" id="reattemptModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;border:none;box-shadow:0 20px 60px rgba(0,0,0,0.2)">
            <div class="modal-header" style="background:linear-gradient(135deg,var(--royal-deeper,#1e1b6e),var(--royal,#3730a3));border-radius:16px 16px 0 0;border:none">
                <h5 class="modal-title text-white">
                    <i class="bi bi-arrow-repeat me-2"></i>Request Re-attempt
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('teacher.reattempts.store') }}">
                @csrf
                <input type="hidden" name="student_id" id="modalStudentId">
                <input type="hidden" name="exam_id" value="{{ $exam->id }}">
                <div class="modal-body p-4">
                    <div class="mb-3 p-3 rounded" style="background:var(--royal-light,#ede9fe);border:1px solid rgba(55,48,163,0.15)">
                        <div class="text-muted small mb-1">Student</div>
                        <div id="modalStudentName" style="font-weight:700;color:var(--royal,#3730a3)"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason for Re-attempt <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3"
                                  placeholder="Explain why this student needs a re-attempt..."
                                  required></textarea>
                    </div>
                    <div class="p-3 rounded" style="background:#fefce8;border:1px solid #fde68a;font-size:0.8rem;color:#854d0e">
                        <i class="bi bi-info-circle me-1"></i>
                        This request will be sent to admin for final approval. The student's previous attempt record will be kept.
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send me-1"></i> Send Request to Admin
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('reattemptModal')?.addEventListener('show.bs.modal', function (e) {
    const btn = e.relatedTarget;
    document.getElementById('modalStudentId').value   = btn.dataset.studentId;
    document.getElementById('modalStudentName').textContent = btn.dataset.studentName;
});
</script>
@endpush
