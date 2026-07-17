@extends('layouts.app')
@section('title', 'Email Log #' . $log->id)
@section('page-title', 'Email Log Detail')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Email', 'url' => route('admin.email.index')],
        ['label' => 'Logs', 'url' => route('admin.email.logs')],
        ['label' => 'Log #' . $log->id],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')
<div style="max-width:760px">

    {{-- Meta card --}}
    <div class="card mb-3">
        <div class="card-header d-flex align-items-center justify-content-between">
            <span><i class="bi bi-envelope me-2"></i>Email #{{ $log->id }}</span>
            @php
                $statusStyle = match($log->status) {
                    'sent'   => 'background:#f0fdf4;color:#059669',
                    'failed' => 'background:#fef2f2;color:#dc2626',
                    default  => 'background:#fffbeb;color:#d97706',
                };
            @endphp
            <span style="font-size:0.8rem;font-weight:700;padding:4px 12px;border-radius:6px;{{ $statusStyle }}">
                {{ ucfirst($log->status) }}
            </span>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0" style="font-size:0.845rem">
                <tbody>
                    <tr><td style="width:160px;font-weight:600;color:#6b7280;background:#f8f9fc;padding:0.6rem 1rem">To</td>
                        <td style="padding:0.6rem 1rem">{{ $log->to_name ? "{$log->to_name} &lt;{$log->to_email}&gt;" : $log->to_email }}</td></tr>
                    @if($log->cc_email)
                    <tr><td style="width:160px;font-weight:600;color:#6b7280;background:#f8f9fc;padding:0.6rem 1rem">CC</td>
                        <td style="padding:0.6rem 1rem">{{ $log->cc_name ? "{$log->cc_name} &lt;{$log->cc_email}&gt;" : $log->cc_email }}</td></tr>
                    @endif
                    <tr><td style="font-weight:600;color:#6b7280;background:#f8f9fc;padding:0.6rem 1rem">From</td>
                        <td style="padding:0.6rem 1rem">{{ $log->from_name ? "{$log->from_name} &lt;{$log->from_email}&gt;" : $log->from_email }}</td></tr>
                    <tr><td style="font-weight:600;color:#6b7280;background:#f8f9fc;padding:0.6rem 1rem">Subject</td>
                        <td style="padding:0.6rem 1rem;font-weight:600">{{ $log->subject }}</td></tr>
                    <tr><td style="font-weight:600;color:#6b7280;background:#f8f9fc;padding:0.6rem 1rem">Event</td>
                        <td style="padding:0.6rem 1rem">{{ $log->event ?: '—' }}</td></tr>
                    <tr><td style="font-weight:600;color:#6b7280;background:#f8f9fc;padding:0.6rem 1rem">Type</td>
                        <td style="padding:0.6rem 1rem">{{ $log->email_type ?: '—' }}</td></tr>
                    <tr><td style="font-weight:600;color:#6b7280;background:#f8f9fc;padding:0.6rem 1rem">Provider</td>
                        <td style="padding:0.6rem 1rem">{{ $log->provider }}</td></tr>
                    <tr><td style="font-weight:600;color:#6b7280;background:#f8f9fc;padding:0.6rem 1rem">Queued At</td>
                        <td style="padding:0.6rem 1rem">{{ $log->queued_at?->format('d M Y H:i:s') ?? '—' }}</td></tr>
                    <tr><td style="font-weight:600;color:#6b7280;background:#f8f9fc;padding:0.6rem 1rem">Sent At</td>
                        <td style="padding:0.6rem 1rem">{{ $log->sent_at?->format('d M Y H:i:s') ?? '—' }}</td></tr>
                    @if($log->error)
                    <tr><td style="font-weight:600;color:#dc2626;background:#fef2f2;padding:0.6rem 1rem">Error</td>
                        <td style="padding:0.6rem 1rem;color:#dc2626;font-size:0.82rem">{{ $log->error }}</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    {{-- Actions --}}
    <div class="d-flex gap-2 mb-3">
        <a href="{{ route('admin.email.logs') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Logs
        </a>
        @if($log->status === 'failed')
        <form action="{{ route('admin.email.logs.retry', $log) }}" method="POST" class="d-inline">
            @csrf
            <button class="btn btn-warning btn-sm">
                <i class="bi bi-arrow-clockwise me-1"></i> Retry
            </button>
        </form>
        @endif
    </div>

    {{-- Email body preview --}}
    @if($log->body_html)
    <div class="card">
        <div class="card-header"><i class="bi bi-eye me-2"></i>Email Body Preview</div>
        <div class="card-body p-0" style="background:#f4f6fb">
            <iframe srcdoc="{{ e($log->body_html) }}"
                    style="width:100%;border:none;min-height:520px;display:block"
                    sandbox="allow-same-origin"
                    title="Email Preview">
            </iframe>
        </div>
    </div>
    @endif

</div>
@endsection
