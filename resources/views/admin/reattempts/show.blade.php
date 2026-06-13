@extends('layouts.app')
@section('title', 'Re-Attempt Request')
@section('page-title', 'Re-Attempt Request Detail')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Re-Attempts', 'url' => route('admin.reattempts.index')],
        ['label' => 'Request #'.$reattempt->id],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-8">

        {{-- Request Info --}}
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-info-circle me-2"></i>Request Details</span>
                @if($reattempt->status === 'pending')
                    <span class="status-pill status-pending">Pending</span>
                @elseif($reattempt->status === 'approved')
                    <span class="status-pill status-approved">Approved</span>
                @else
                    <span class="status-pill status-closed">Rejected</span>
                @endif
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="text-muted small mb-1">Student</div>
                        <div style="font-weight:600">{{ $reattempt->student->name }}</div>
                        <div style="font-size:0.78rem;color:#9ca3af">{{ $reattempt->student->email }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small mb-1">Teacher</div>
                        <div style="font-weight:600">{{ $reattempt->teacher->name }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small mb-1">Exam</div>
                        <div style="font-weight:600">{{ $reattempt->exam->title }}</div>
                        <div style="font-size:0.78rem;color:#9ca3af">{{ $reattempt->exam->course->title ?? '' }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small mb-1">Submitted</div>
                        <div style="font-weight:600">{{ $reattempt->created_at->format('M d, Y H:i') }}</div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted small mb-1">Reason</div>
                        <div class="p-3 rounded" style="background:var(--surface-2,#f1f3f9);font-size:0.875rem">
                            {{ $reattempt->reason }}
                        </div>
                    </div>
                    @if($reattempt->admin_remark)
                    <div class="col-12">
                        <div class="text-muted small mb-1">Admin Remark</div>
                        <div class="p-3 rounded" style="background:{{ $reattempt->status === 'approved' ? '#f0fdf4' : '#fef2f2' }};font-size:0.875rem;color:{{ $reattempt->status === 'approved' ? '#166534' : '#b91c1c' }}">
                            {{ $reattempt->admin_remark }}
                        </div>
                    </div>
                    @endif
                    @if($reattempt->approver)
                    <div class="col-sm-6">
                        <div class="text-muted small mb-1">{{ ucfirst($reattempt->status) }} By</div>
                        <div style="font-weight:600">{{ $reattempt->approver->name }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small mb-1">{{ ucfirst($reattempt->status) }} At</div>
                        <div style="font-weight:600">{{ $reattempt->approved_at?->format('M d, Y H:i') }}</div>
                    </div>
                    @endif
                    @if($reattempt->re_attempt_start_at && $reattempt->re_attempt_end_at)
                    <div class="col-12">
                        <div class="text-muted small mb-1">Re-Attempt Window</div>
                        <div class="p-3 rounded" style="background:#ecfeff;font-size:0.875rem;color:#0f766e">
                            {{ $reattempt->re_attempt_start_at->format('M d, Y H:i') }}
                            <span class="text-muted">to</span>
                            {{ $reattempt->re_attempt_end_at->format('M d, Y H:i') }}
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Audit Log --}}
        <div class="card">
            <div class="card-header"><i class="bi bi-journal-text me-2"></i>Audit Log</div>
            <div class="card-body p-0">
                @forelse($reattempt->logs as $log)
                <div class="p-3 border-bottom d-flex align-items-start gap-3">
                    <div style="width:32px;height:32px;border-radius:50%;background:{{ $log->action === 'approved' ? '#dcfce7' : ($log->action === 'rejected' ? '#fee2e2' : '#ede9fe') }};color:{{ $log->action === 'approved' ? '#166534' : ($log->action === 'rejected' ? '#b91c1c' : '#3730a3') }};display:flex;align-items:center;justify-content:center;font-size:0.8rem;font-weight:700;flex-shrink:0">
                        <i class="bi {{ $log->action === 'approved' ? 'bi-check' : ($log->action === 'rejected' ? 'bi-x' : 'bi-plus') }}"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div style="font-weight:600;font-size:0.855rem">
                            {{ ucfirst($log->action) }}
                            <span class="text-muted fw-normal">by {{ $log->actor->name }} ({{ $log->actor_role }})</span>
                        </div>
                        @if($log->remarks)
                        <div style="font-size:0.8rem;color:#6b7280;margin-top:2px">{{ $log->remarks }}</div>
                        @endif
                        <div style="font-size:0.72rem;color:#9ca3af;margin-top:2px">{{ $log->created_at->format('M d, Y H:i') }}</div>
                    </div>
                </div>
                @empty
                <div class="p-4 text-center text-muted small">No audit logs.</div>
                @endforelse
            </div>
        </div>

    </div>

    {{-- Actions --}}
    <div class="col-lg-4">
        @if($reattempt->status === 'pending')
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-shield-check me-2"></i>Admin Action</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.reattempts.approve', $reattempt) }}" class="mb-3">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label">Approval Remark <span class="text-muted fw-normal">(optional)</span></label>
                        <textarea name="admin_remark" class="form-control" rows="2" placeholder="Optional note..."></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Re-attempt Start <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="re_attempt_start_at" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Re-attempt End <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="re_attempt_end_at" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-check-circle me-1"></i> Approve Re-Attempt
                    </button>
                </form>
                <hr>
                <form method="POST" action="{{ route('admin.reattempts.reject', $reattempt) }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea name="admin_remark" class="form-control" rows="2"
                                  placeholder="Explain why..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-outline-danger w-100">
                        <i class="bi bi-x-circle me-1"></i> Reject Request
                    </button>
                </form>
            </div>
        </div>
        @endif
        @if($reattempt->status === 'approved')
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-clock-history me-2"></i>Update Re-Attempt Window</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.reattempts.window.update', $reattempt) }}">
                    @csrf
                    @method('PUT')
                    <div class="mb-2">
                        <label class="form-label">Re-attempt Start <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="re_attempt_start_at" class="form-control"
                               value="{{ $reattempt->re_attempt_start_at?->format('Y-m-d\\TH:i') }}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Re-attempt End <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="re_attempt_end_at" class="form-control"
                               value="{{ $reattempt->re_attempt_end_at?->format('Y-m-d\\TH:i') }}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Admin Remark <span class="text-muted fw-normal">(optional)</span></label>
                        <textarea name="admin_remark" class="form-control" rows="2">{{ $reattempt->admin_remark }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-outline-primary w-100">
                        <i class="bi bi-save me-1"></i> Save Window
                    </button>
                </form>
            </div>
        </div>
        @endif
        <a href="{{ route('admin.reattempts.index') }}" class="btn btn-outline-secondary w-100">
            <i class="bi bi-arrow-left me-1"></i> Back to List
        </a>
    </div>
</div>
@endsection
