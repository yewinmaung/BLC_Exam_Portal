@extends('layouts.app')
@section('title', 'Email Log #'.$log->id)
@section('page-title', 'Email Log Detail')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Email', 'url' => route('admin.email.index')],
        ['label' => 'Logs', 'url' => route('admin.email.logs')],
        ['label' => '#'.$log->id],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')
<div class="d-flex gap-2 mb-3">
    @if($log->status === 'failed')
    <form method="POST" action="{{ route('admin.email.logs.retry', $log) }}">
        @csrf
        <button class="btn btn-sm btn-warning"><i class="bi bi-arrow-repeat me-1"></i> Retry</button>
    </form>
    @endif
    <a href="{{ route('admin.email.logs') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to Logs
    </a>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header" style="font-size:0.82rem"><i class="bi bi-info-circle me-2"></i>Delivery Info</div>
            <div class="card-body" style="font-size:0.82rem">
                @php $color = ['sent'=>'success','queued'=>'warning','failed'=>'danger'][$log->status] ?? 'secondary' @endphp
                <table class="table table-sm mb-0">
                    <tr><th class="text-muted fw-normal" style="width:110px">Status</th>
                        <td><span class="badge bg-{{ $color }}">{{ ucfirst($log->status) }}</span></td></tr>
                    <tr><th class="text-muted fw-normal">To</th><td>{{ $log->to_name }}<br><small class="text-muted">{{ $log->to_email }}</small></td></tr>
                    <tr><th class="text-muted fw-normal">From</th><td>{{ $log->from_name }}<br><small class="text-muted">{{ $log->from_email }}</small></td></tr>
                    <tr><th class="text-muted fw-normal">Event</th><td>{{ $log->event ?? '—' }}</td></tr>
                    <tr><th class="text-muted fw-normal">Template</th><td><code style="font-size:0.75rem">{{ $log->template_slug ?? '—' }}</code></td></tr>
                    <tr><th class="text-muted fw-normal">Provider</th><td>{{ $log->provider }}</td></tr>
                    <tr><th class="text-muted fw-normal">Queued</th><td>{{ $log->queued_at?->format('M d Y, H:i:s') ?? '—' }}</td></tr>
                    <tr><th class="text-muted fw-normal">Sent</th><td>{{ $log->sent_at?->format('M d Y, H:i:s') ?? '—' }}</td></tr>
                    <tr><th class="text-muted fw-normal">Message-ID</th><td style="font-size:0.72rem;word-break:break-all">{{ $log->message_id ?? '—' }}</td></tr>
                </table>

                @if($log->error)
                <div class="alert alert-danger mt-3 mb-0 py-2" style="font-size:0.78rem">
                    <strong>Error:</strong><br>{{ $log->error }}
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header" style="font-size:0.82rem">
                <i class="bi bi-envelope me-2"></i>Subject: {{ $log->subject }}
            </div>
            <div class="card-body p-0">
                <div style="background:#f3f4f6;padding:1rem">
                    <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;border:1px solid #e5e7eb;overflow:hidden">
                        <div style="background:linear-gradient(135deg,#1e1b6e,#3730a3);padding:1.2rem;text-align:center">
                            <div style="color:#fff;font-weight:700">{{ config('app.name') }}</div>
                        </div>
                        <div style="padding:1.5rem;font-family:Arial,sans-serif;font-size:14px;color:#374151;line-height:1.6">
                            {!! $log->body_html ?? '<em class="text-muted">No body content.</em>' !!}
                        </div>
                        <div style="background:#f9fafb;padding:0.8rem;text-align:center;font-size:11px;color:#9ca3af;border-top:1px solid #e5e7eb">
                            © {{ date('Y') }} {{ config('app.name') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
