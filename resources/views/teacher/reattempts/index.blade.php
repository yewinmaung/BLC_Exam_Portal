@extends('layouts.app')
@section('title', 'Re-Attempt Requests')
@section('page-title', 'Re-Attempt Requests')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Teacher', 'url' => route('teacher.dashboard')],
        ['label' => 'Re-Attempt Requests'],
    ]])
@endsection
@section('sidebar')
@include('partials.teacher-sidebar')
@endsection

@section('content')
<div class="page-header">
    <div></div>
    <a href="{{ route('teacher.reattempts.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i> New Request
    </a>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-arrow-repeat me-2"></i>My Re-Attempt Requests</span>
        <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">
            {{ $requests->total() }}
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table datatable mb-0">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Exam</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Admin Remark</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $r)
                    <tr>
                        <td>
                            <div style="font-weight:600;font-size:0.855rem">{{ $r->student->name }}</div>
                            <div style="font-size:0.72rem;color:#9ca3af">{{ $r->student->email }}</div>
                        </td>
                        <td>
                            <div style="font-size:0.855rem;font-weight:500">{{ $r->exam->title }}</div>
                            <div style="font-size:0.72rem;color:#9ca3af">{{ $r->exam->course->title ?? '' }}</div>
                        </td>
                        <td style="max-width:160px;font-size:0.82rem;color:#374151">
                            {{ Str::limit($r->reason, 55) }}
                        </td>
                        <td>
                            @if($r->status === 'pending')
                                <span class="status-pill status-pending">Pending</span>
                            @elseif($r->status === 'approved')
                                <span class="status-pill status-approved">Approved</span>
                            @else
                                <span class="status-pill status-closed">Rejected</span>
                            @endif
                        </td>
                        <td style="font-size:0.82rem;color:#6b7280;max-width:160px">
                            {{ $r->admin_remark ? Str::limit($r->admin_remark, 55) : '—' }}
                        </td>
                        <td style="font-size:0.78rem;color:#9ca3af;white-space:nowrap">
                            {{ $r->created_at->format('M d, Y') }}
                        </td>
                        <td>
                            @if($r->status === 'pending')
                            <form method="POST"
                                  action="{{ route('teacher.reattempts.cancel', $r) }}"
                                  onsubmit="return confirm('Cancel this re-attempt request for {{ addslashes($r->student->name) }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancel request">
                                    <i class="bi bi-x-circle me-1"></i>Cancel
                                </button>
                            </form>
                            @else
                            <span style="font-size:0.78rem;color:#9ca3af">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-arrow-repeat d-block mb-2" style="font-size:2rem;opacity:0.3"></i>
                            No requests yet.
                            <a href="{{ route('teacher.reattempts.create') }}">Create one</a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
    </div>
</div>
@endsection
