@extends('layouts.app')
@section('title', 'Email Logs')
@section('page-title', 'Email Logs')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Email', 'url' => route('admin.email.index')],
        ['label' => 'Logs'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')

{{-- Filter bar --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
            <div style="min-width:160px;flex:1">
                <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="sent"   {{ request('status')=='sent'   ? 'selected':'' }}>Sent</option>
                    <option value="queued" {{ request('status')=='queued' ? 'selected':'' }}>Queued</option>
                    <option value="failed" {{ request('status')=='failed' ? 'selected':'' }}>Failed</option>
                </select>
            </div>
            <div style="min-width:200px;flex:2">
                <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600">Recipient Email</label>
                <input type="text" name="email" class="form-control form-control-sm"
                       value="{{ request('email') }}" placeholder="Filter by email...">
            </div>
            <div style="min-width:160px;flex:1">
                <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600">Event</label>
                <input type="text" name="event" class="form-control form-control-sm"
                       value="{{ request('event') }}" placeholder="e.g. exam_published">
            </div>
            <div class="d-flex gap-1">
                <button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('admin.email.logs') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-journal-text me-2"></i>Email Logs</span>
        <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">{{ $logs->total() }} total</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0" style="font-size:0.82rem">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>To</th>
                        <th>Subject</th>
                        <th>Event</th>
                        <th>Status</th>
                        <th>Provider</th>
                        <th>Time</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr>
                        <td class="text-muted">{{ $log->id }}</td>
                        <td>
                            <div style="font-weight:600">{{ $log->to_name ?: '—' }}</div>
                            <div style="font-size:0.72rem;color:#9ca3af">{{ $log->to_email }}</div>
                        </td>
                        <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                            {{ $log->subject }}
                        </td>
                        <td><span class="badge bg-secondary" style="font-size:0.68rem">{{ $log->event ?? '—' }}</span></td>
                        <td>
                            @php $color = ['sent'=>'success','queued'=>'warning','failed'=>'danger'][$log->status] ?? 'secondary' @endphp
                            <span class="badge bg-{{ $color }}">{{ ucfirst($log->status) }}</span>
                        </td>
                        <td class="text-muted" style="font-size:0.75rem">{{ $log->provider }}</td>
                        <td class="text-muted" style="font-size:0.75rem" title="{{ $log->created_at }}">
                            {{ $log->created_at->format('M d, H:i') }}
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="{{ route('admin.email.logs.show', $log) }}"
                                   class="btn btn-xs btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                                @if($log->status === 'failed')
                                <form method="POST" action="{{ route('admin.email.logs.retry', $log) }}">
                                    @csrf
                                    <button class="btn btn-xs btn-outline-warning" title="Retry">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-journal d-block mb-2" style="font-size:2rem;opacity:0.3"></i>
                            No email logs found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
        <div class="p-3 border-top">{{ $logs->links() }}</div>
        @endif
    </div>
</div>
@endsection
