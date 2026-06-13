@extends('layouts.app')
@section('title', 'Email Management')
@section('page-title', 'Email Management')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Email Management'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')

{{-- Stats Row --}}
<div class="row g-3 mb-4">
    @foreach([
        ['label'=>'Total Sent',  'value'=>$stats['sent'],   'icon'=>'bi-envelope-check',  'color'=>'#22c55e'],
        ['label'=>'Queued',      'value'=>$stats['queued'],  'icon'=>'bi-hourglass-split',  'color'=>'#f59e0b'],
        ['label'=>'Failed',      'value'=>$stats['failed'],  'icon'=>'bi-envelope-x',       'color'=>'#ef4444'],
        ['label'=>'Total',       'value'=>$stats['total'],   'icon'=>'bi-envelope',         'color'=>'var(--royal,#3730a3)'],
    ] as $s)
    <div class="col-sm-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div style="width:44px;height:44px;border-radius:12px;background:{{ $s['color'] }}1a;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <i class="bi {{ $s['icon'] }}" style="font-size:1.3rem;color:{{ $s['color'] }}"></i>
                </div>
                <div>
                    <div style="font-size:1.5rem;font-weight:800;color:var(--text-1)">{{ number_format($s['value']) }}</div>
                    <div style="font-size:0.78rem;color:#6b7280">{{ $s['label'] }}</div>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="row g-3">
    {{-- Quick Actions --}}
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-lightning me-2"></i>Quick Actions</div>
            <div class="card-body d-grid gap-2">
                <a href="{{ route('admin.email.test') }}"       class="btn btn-outline-primary"><i class="bi bi-send me-1"></i> Send Test Email</a>
                <a href="{{ route('admin.email.bulk') }}"       class="btn btn-outline-primary"><i class="bi bi-send-check me-1"></i> Send Bulk Email</a>
                <a href="{{ route('admin.email.templates.create') }}" class="btn btn-outline-secondary"><i class="bi bi-plus-circle me-1"></i> New Template</a>
                <a href="{{ route('admin.email.smtp') }}"       class="btn btn-outline-secondary"><i class="bi bi-gear me-1"></i> SMTP Settings</a>
                <a href="{{ route('admin.email.logs') }}"       class="btn btn-outline-secondary"><i class="bi bi-journal-text me-1"></i> View All Logs</a>
            </div>
        </div>

        {{-- Templates summary --}}
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-file-earmark-code me-2"></i>Templates</span>
                <a href="{{ route('admin.email.templates') }}" class="btn btn-xs btn-outline-secondary" style="font-size:0.72rem;padding:0.15rem 0.55rem">View all</a>
            </div>
            <div class="list-group list-group-flush">
                @forelse($templates as $t)
                <div class="list-group-item d-flex align-items-center justify-content-between py-2 px-3" style="font-size:0.82rem">
                    <span>{{ $t->name }}</span>
                    <span class="badge {{ $t->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $t->is_active ? 'Active' : 'Off' }}</span>
                </div>
                @empty
                <div class="list-group-item text-muted text-center py-3" style="font-size:0.82rem">No templates yet.</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Recent Logs --}}
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-journal-text me-2"></i>Recent Emails</span>
                <a href="{{ route('admin.email.logs') }}" class="btn btn-xs btn-outline-secondary" style="font-size:0.72rem;padding:0.15rem 0.55rem">View all</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0" style="font-size:0.82rem">
                        <thead><tr><th>To</th><th>Subject</th><th>Event</th><th>Status</th><th>Time</th></tr></thead>
                        <tbody>
                            @forelse($recentLogs as $log)
                            <tr>
                                <td>{{ $log->to_email }}</td>
                                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $log->subject }}</td>
                                <td><span class="badge bg-secondary" style="font-size:0.68rem">{{ $log->event ?? '—' }}</span></td>
                                <td>
                                    @php $sc = ['sent'=>'success','queued'=>'warning','failed'=>'danger'][$log->status] ?? 'secondary' @endphp
                                    <span class="badge bg-{{ $sc }}">{{ $log->status }}</span>
                                </td>
                                <td class="text-muted">{{ $log->created_at->diffForHumans() }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No emails logged yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
