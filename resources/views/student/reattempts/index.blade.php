@extends('layouts.app')
@section('title', 'My Re-Attempt Requests')
@section('page-title', 'Re-Attempt Requests')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Student', 'url' => route('student.dashboard')],
        ['label' => 'Re-Attempt Requests'],
    ]])
@endsection
@section('sidebar')
@include('partials.student-sidebar')
@endsection

@section('content')
<div class="row g-3">
    @forelse($requests as $r)
    <div class="col-md-6">
        <div class="card h-100" style="border-left: 4px solid {{ $r->status === 'approved' ? '#22c55e' : ($r->status === 'rejected' ? '#ef4444' : '#f59e0b') }} !important">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-2">
                    <div>
                        <div style="font-weight:700;color:var(--text-1)">{{ $r->exam->title }}</div>
                        <div style="font-size:0.78rem;color:#9ca3af"><i class="bi bi-book me-1"></i>{{ $r->exam->course->title ?? '' }}</div>
                    </div>
                    @if($r->status === 'pending')
                        <span class="status-pill status-pending">Pending</span>
                    @elseif($r->status === 'approved')
                        <span class="status-pill status-approved">Approved</span>
                    @else
                        <span class="status-pill status-closed">Rejected</span>
                    @endif
                </div>

                <div class="mb-2 p-2 rounded" style="background:var(--surface-2,#f1f3f9);font-size:0.82rem">
                    <strong>Reason:</strong> {{ $r->reason }}
                </div>

                @if($r->admin_remark)
                <div class="mb-2 p-2 rounded" style="background:{{ $r->status === 'approved' ? '#f0fdf4' : '#fef2f2' }};font-size:0.82rem;color:{{ $r->status === 'approved' ? '#166534' : '#b91c1c' }}">
                    <strong>Admin:</strong> {{ $r->admin_remark }}
                </div>
                @endif

                @if($r->status === 'approved')
                <a href="{{ route('student.exams.show', $r->exam_id) }}" class="btn btn-sm btn-success w-100 mt-2">
                    <i class="bi bi-play-fill me-1"></i> Re-Attempt Available — Go to Exam
                </a>
                @if($r->re_attempt_start_at && $r->re_attempt_end_at)
                <div class="mt-2 p-2 rounded text-center" style="background:#ecfeff;border:1px solid #99f6e4;font-size:0.78rem;color:#0f766e">
                    <i class="bi bi-clock-history me-1"></i>
                    Window: {{ $r->re_attempt_start_at->format('M d, Y H:i') }} to {{ $r->re_attempt_end_at->format('M d, Y H:i') }}
                </div>
                @endif
                @elseif($r->status === 'pending')
                <div class="mt-2 p-2 rounded text-center" style="background:#fefce8;border:1px solid #fde68a;font-size:0.78rem;color:#854d0e">
                    <i class="bi bi-hourglass-split me-1"></i> Awaiting admin approval
                </div>
                @else
                <div class="mt-2 p-2 rounded text-center" style="background:#fef2f2;border:1px solid #fecaca;font-size:0.78rem;color:#b91c1c">
                    <i class="bi bi-x-circle me-1"></i> Request rejected — contact your teacher
                </div>
                @endif

                <div class="mt-2" style="font-size:0.72rem;color:#9ca3af">
                    <i class="bi bi-clock me-1"></i>{{ $r->created_at->format('M d, Y H:i') }}
                    @if($r->teacher)· Requested by {{ $r->teacher->name }}@endif
                    @if($r->approved_at)· {{ ucfirst($r->status) }} {{ $r->approved_at->format('M d, Y') }}@endif
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center py-5 text-muted">
                <i class="bi bi-arrow-repeat d-block mb-3" style="font-size:3rem;opacity:0.3"></i>
                <h6>No Re-Attempt Requests</h6>
                <p class="small mb-0">If you need to retake an exam, ask your teacher to submit a request.</p>
            </div>
        </div>
    </div>
    @endforelse
</div>
@if($requests->hasPages())
<div class="mt-3">{{ $requests->links() }}</div>
@endif
@endsection
