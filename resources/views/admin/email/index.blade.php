@extends('layouts.app')
@section('title', 'Email Dashboard')
@section('page-title', 'Email Dashboard')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Email Dashboard'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')

{{-- ── Stat Cards ── --}}
<div class="row g-3 mb-4">
    @php
        $cards = [
            ['label' => 'Total Sent',   'value' => $stats['sent'],   'icon' => 'bi-send-check-fill',       'color' => '#059669', 'bg' => '#f0fdf4', 'border' => '#bbf7d0'],
            ['label' => 'Queued',       'value' => $stats['queued'], 'icon' => 'bi-hourglass-split',       'color' => '#d97706', 'bg' => '#fffbeb', 'border' => '#fde68a'],
            ['label' => 'Failed',       'value' => $stats['failed'], 'icon' => 'bi-exclamation-triangle-fill','color' => '#dc2626','bg' => '#fef2f2','border' => '#fecaca'],
            ['label' => 'Total Emails', 'value' => $stats['total'],  'icon' => 'bi-envelope-fill',         'color' => '#2d27a0', 'bg' => '#eef2ff', 'border' => '#c7d2fe'],
        ];
    @endphp
    @foreach($cards as $card)
    <div class="col-6 col-md-3">
        <div class="card h-100" style="border-color:{{ $card['border'] }};background:{{ $card['bg'] }}">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div style="width:42px;height:42px;border-radius:10px;background:{{ $card['color'] }}20;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <i class="bi {{ $card['icon'] }}" style="font-size:1.1rem;color:{{ $card['color'] }}"></i>
                </div>
                <div>
                    <div style="font-size:1.5rem;font-weight:800;color:{{ $card['color'] }};line-height:1">{{ number_format($card['value']) }}</div>
                    <div style="font-size:0.72rem;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;margin-top:2px">{{ $card['label'] }}</div>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- ── Quick Actions ── --}}
<div class="row g-3 mb-4">
    @php
        $actions = [
            ['label'=>'Send Test Email',   'icon'=>'bi-send',              'route'=>route('admin.email.test'),      'style'=>'primary'],
            ['label'=>'Bulk Email',        'icon'=>'bi-send-check',        'route'=>route('admin.email.bulk'),      'style'=>'outline-primary'],
            ['label'=>'Scheduled Emails',  'icon'=>'bi-calendar-check',    'route'=>route('admin.email.scheduled'), 'style'=>'outline-secondary'],
            ['label'=>'Email Templates',   'icon'=>'bi-file-earmark-code', 'route'=>route('admin.email.templates'),'style'=>'outline-secondary'],
            ['label'=>'Email Logs',        'icon'=>'bi-journal-text',      'route'=>route('admin.email.logs'),      'style'=>'outline-secondary'],
            ['label'=>'SMTP Settings',     'icon'=>'bi-gear',              'route'=>route('admin.email.smtp'),      'style'=>'outline-secondary'],
        ];
    @endphp
    @foreach($actions as $action)
    <div class="col-6 col-md-4 col-lg-2">
        <a href="{{ $action['route'] }}" class="btn btn-{{ $action['style'] }} w-100 d-flex flex-column align-items-center gap-1 py-3" style="font-size:0.8rem;min-height:72px;justify-content:center">
            <i class="bi {{ $action['icon'] }}" style="font-size:1.25rem"></i>
            {{ $action['label'] }}
        </a>
    </div>
    @endforeach
</div>

{{-- ── Recent Email Logs ── --}}
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-clock-history me-2"></i>Recent Emails</span>
        <a href="{{ route('admin.email.logs') }}" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0" style="font-size:0.845rem">
                <thead style="background:#f8f9fc">
                    <tr>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 1rem;border-bottom:1.5px solid #e8eaf2">Recipient</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Subject</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Type</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Status</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentLogs as $log)
                    <tr>
                        <td style="padding:0.7rem 1rem">
                            <div style="font-weight:600;color:#111827">{{ $log->to_name ?: 'Unknown' }}</div>
                            <div style="font-size:0.72rem;color:#9ca3af">{{ $log->to_email }}</div>
                        </td>
                        <td style="padding:0.7rem 0.75rem;color:#374151;max-width:220px">
                            <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $log->subject }}</div>
                        </td>
                        <td style="padding:0.7rem 0.75rem">
                            @if($log->email_type)
                            <span style="font-size:0.7rem;background:#f0f4ff;color:#3730a3;padding:2px 7px;border-radius:4px;font-weight:600">{{ $log->email_type }}</span>
                            @elseif($log->event)
                            <span style="font-size:0.7rem;background:#f3f4f6;color:#6b7280;padding:2px 7px;border-radius:4px">{{ $log->event }}</span>
                            @else
                            <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td style="padding:0.7rem 0.75rem">
                            @php
                                $statusStyle = match($log->status) {
                                    'sent'   => 'background:#f0fdf4;color:#059669',
                                    'failed' => 'background:#fef2f2;color:#dc2626',
                                    default  => 'background:#fffbeb;color:#d97706',
                                };
                            @endphp
                            <span style="font-size:0.72rem;font-weight:700;padding:2px 8px;border-radius:4px;{{ $statusStyle }}">
                                {{ ucfirst($log->status) }}
                            </span>
                        </td>
                        <td style="padding:0.7rem 0.75rem;color:#9ca3af;font-size:0.78rem;white-space:nowrap">
                            {{ $log->created_at->format('d M H:i') }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">
                            <i class="bi bi-envelope-x d-block mb-1" style="font-size:1.5rem;opacity:0.3"></i>
                            No emails sent yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
