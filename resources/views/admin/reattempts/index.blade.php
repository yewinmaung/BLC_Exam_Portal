@extends('layouts.app')
@section('title', 'Re-Attempt Requests')
@section('page-title', 'Re-Attempt Requests')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Re-Attempt Requests'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')

{{-- Filter bar --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
            <div style="min-width:150px;flex:1">
                <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="pending"  {{ request('status') === 'pending'  ? 'selected' : '' }}>Pending</option>
                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>
            <div style="min-width:160px;flex:1">
                <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600">Student</label>
                <select name="student_id" class="form-select form-select-sm">
                    <option value="">All Students</option>
                    @foreach($students as $s)
                    <option value="{{ $s->id }}" {{ request('student_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="min-width:160px;flex:1">
                <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600">Teacher</label>
                <select name="teacher_id" class="form-select form-select-sm">
                    <option value="">All Teachers</option>
                    @foreach($teachers as $t)
                    <option value="{{ $t->id }}" {{ request('teacher_id') == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="min-width:160px;flex:1">
                <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600">Course</label>
                <select name="course_id" class="form-select form-select-sm">
                    <option value="">All Courses</option>
                    @foreach($courses as $c)
                    <option value="{{ $c->id }}" {{ request('course_id') == $c->id ? 'selected' : '' }}>{{ $c->title }}</option>
                    @endforeach
                </select>
            </div>
            <div style="min-width:160px;flex:1">
                <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600">Exam</label>
                <select name="exam_id" class="form-select form-select-sm">
                    <option value="">All Exams</option>
                    @foreach($exams as $e)
                    <option value="{{ $e->id }}" {{ request('exam_id') == $e->id ? 'selected' : '' }}>{{ $e->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="d-flex gap-1">
                <button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('admin.reattempts.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-arrow-repeat me-2"></i>Re-Attempt Requests</span>
        <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">
            {{ $requests->total() }} total
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Exam</th>
                        <th>Teacher</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $r)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#1e1b6e,#3730a3);color:#fff;display:flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:700;flex-shrink:0">
                                    {{ strtoupper(substr($r->student->name,0,1)) }}
                                </div>
                                <span style="font-weight:600;font-size:0.855rem">{{ $r->student->name }}</span>
                            </div>
                        </td>
                        <td>
                            <div style="font-size:0.855rem;font-weight:500">{{ $r->exam->title }}</div>
                            <div style="font-size:0.72rem;color:#9ca3af">{{ $r->exam->course->title ?? '' }}</div>
                            @if($r->re_attempt_start_at && $r->re_attempt_end_at)
                            <div style="font-size:0.72rem;color:#0f766e">Window: {{ $r->re_attempt_start_at->format('M d, H:i') }} → {{ $r->re_attempt_end_at->format('M d, H:i') }}</div>
                            @endif
                        </td>
                        <td class="text-muted" style="font-size:0.855rem">{{ $r->teacher->name }}</td>
                        <td style="max-width:180px">
                            <span style="font-size:0.8rem;color:#374151">{{ Str::limit($r->reason, 60) }}</span>
                        </td>
                        <td>
                            @if($r->status === 'pending')
                                <span class="status-pill status-pending">Pending Admin Review</span>
                            @elseif($r->status === 'approved')
                                <span class="status-pill status-approved">Approved</span>
                            @else
                                <span class="status-pill status-closed">Rejected</span>
                            @endif
                        </td>
                        <td style="font-size:0.78rem;color:#9ca3af">{{ $r->created_at->format('M d, Y') }}</td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="{{ route('admin.reattempts.show', $r) }}"
                                   class="btn btn-sm btn-outline-primary" title="View details">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @if($r->status === 'pending')
                                <button type="button" class="btn btn-sm btn-success"
                                        data-bs-toggle="modal"
                                        data-bs-target="#approveModal"
                                        data-id="{{ $r->id }}"
                                        data-name="{{ $r->student->name }}"
                                        data-exam="{{ $r->exam->title }}"
                                        title="Approve">
                                    <i class="bi bi-check-circle"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger"
                                        data-bs-toggle="modal"
                                        data-bs-target="#rejectModal"
                                        data-id="{{ $r->id }}"
                                        data-name="{{ $r->student->name }}"
                                        title="Reject">
                                    <i class="bi bi-x-circle"></i>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-arrow-repeat d-block mb-2" style="font-size:2rem;opacity:0.3"></i>
                            No re-attempt requests found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($requests->hasPages())
        <div class="p-3 border-top d-flex align-items-center justify-content-between flex-wrap gap-2">
            <span class="text-muted" style="font-size:0.8rem">
                Showing {{ $requests->firstItem() }} to {{ $requests->lastItem() }} of {{ $requests->total() }} entries
            </span>
            {{ $requests->links() }}
        </div>
        @endif
    </div>
</div>

{{-- Approve Modal --}}
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px;border:none">
            <div class="modal-header" style="background:linear-gradient(135deg,#166534,#15803d);border-radius:14px 14px 0 0;border:none">
                <h5 class="modal-title text-white"><i class="bi bi-check-circle me-2"></i>Approve Re-Attempt</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="approveForm">
                @csrf
                <div class="modal-body p-4">
                    <p class="mb-3">Approve re-attempt for <strong id="approveStudentName"></strong> on <strong id="approveExamName"></strong>?</p>
                    <div>
                        <label class="form-label">Admin Remark <span class="text-muted fw-normal">(optional)</span></label>
                        <textarea name="admin_remark" class="form-control" rows="2"
                                  placeholder="Add any remarks for the student..."></textarea>
                    </div>
                    <div class="mt-2">
                        <label class="form-label">Re-attempt Start <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="re_attempt_start_at" class="form-control" required>
                    </div>
                    <div class="mt-2">
                        <label class="form-label">Re-attempt End <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="re_attempt_end_at" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-circle me-1"></i>Approve</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Reject Modal --}}
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px;border:none">
            <div class="modal-header" style="background:linear-gradient(135deg,#b91c1c,#dc2626);border-radius:14px 14px 0 0;border:none">
                <h5 class="modal-title text-white"><i class="bi bi-x-circle me-2"></i>Reject Request</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="rejectForm">
                @csrf
                <div class="modal-body p-4">
                    <p class="mb-3">Reject re-attempt request for <strong id="rejectStudentName"></strong>?</p>
                    <div>
                        <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea name="admin_remark" class="form-control" rows="2"
                                  placeholder="Explain why the request is rejected..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-x-circle me-1"></i>Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('approveModal')?.addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('approveStudentName').textContent = btn.dataset.name;
    document.getElementById('approveExamName').textContent    = btn.dataset.exam;
    document.getElementById('approveForm').action = `/admin/reattempts/${btn.dataset.id}/approve`;
});
document.getElementById('rejectModal')?.addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('rejectStudentName').textContent = btn.dataset.name;
    document.getElementById('rejectForm').action = `/admin/reattempts/${btn.dataset.id}/reject`;
});
</script>
@endpush
