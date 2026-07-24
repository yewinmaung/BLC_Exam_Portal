@extends('layouts.app')
@section('title', 'Email Log #' . $log->id)
@section('page-title', 'Email Log Detail')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Logs', 'url' => route('admin.email.logs')],
        ['label' => 'Log #' . $log->id],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')
<div style="max-width:780px">

    {{-- ── Back + Actions ──────────────────────────────────────────────── --}}
    <div class="d-flex gap-2 mb-3">
        <a href="{{ route('admin.email.logs') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Logs
        </a>
        @if($log->status === 'failed')
        <form action="{{ route('admin.email.logs.retry', $log) }}" method="POST" class="d-inline">
            @csrf
            <button class="btn btn-warning btn-sm">
                <i class="bi bi-arrow-clockwise me-1"></i>Retry
            </button>
        </form>
        @endif
    </div>

    {{-- ── Meta card ───────────────────────────────────────────────────── --}}
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
                    <tr>
                        <td style="width:150px;font-weight:600;color:#6b7280;background:#f8f9fc;padding:0.6rem 1rem">To</td>
                        <td style="padding:0.6rem 1rem">
                            @if($log->to_name)
                                <strong>{{ $log->to_name }}</strong>
                                <span class="text-muted ms-1">&lt;{{ $log->to_email }}&gt;</span>
                            @else
                                {{ $log->to_email }}
                            @endif
                        </td>
                    </tr>
                    @if($log->cc_email)
                    <tr>
                        <td style="font-weight:600;color:#6b7280;background:#f8f9fc;padding:0.6rem 1rem">CC</td>
                        <td style="padding:0.6rem 1rem">
                            @if($log->cc_name)
                                <strong>{{ $log->cc_name }}</strong>
                                <span class="text-muted ms-1">&lt;{{ $log->cc_email }}&gt;</span>
                            @else
                                {{ $log->cc_email }}
                            @endif
                        </td>
                    </tr>
                    @endif
                    <tr>
                        <td style="font-weight:600;color:#6b7280;background:#f8f9fc;padding:0.6rem 1rem">From</td>
                        <td style="padding:0.6rem 1rem">
                            @if($log->from_name)
                                <strong>{{ $log->from_name }}</strong>
                                <span class="text-muted ms-1">&lt;{{ $log->from_email }}&gt;</span>
                            @else
                                {{ $log->from_email }}
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight:600;color:#6b7280;background:#f8f9fc;padding:0.6rem 1rem">Subject</td>
                        <td style="padding:0.6rem 1rem;font-weight:600">{{ $log->subject }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight:600;color:#6b7280;background:#f8f9fc;padding:0.6rem 1rem">Type / Event</td>
                        <td style="padding:0.6rem 1rem">
                            @if($log->email_type)
                                <span style="font-size:0.72rem;background:#eef2ff;color:#3730a3;padding:2px 8px;border-radius:4px;font-weight:700">
                                    {{ $log->email_type }}
                                </span>
                            @endif
                            @if($log->event)
                                <span style="font-size:0.72rem;background:#f3f4f6;color:#6b7280;padding:2px 8px;border-radius:4px;margin-left:4px">
                                    {{ $log->event }}
                                </span>
                            @endif
                            @if(!$log->email_type && !$log->event)
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                    @if($log->template_slug)
                    <tr>
                        <td style="font-weight:600;color:#6b7280;background:#f8f9fc;padding:0.6rem 1rem">Template</td>
                        <td style="padding:0.6rem 1rem;font-family:monospace;font-size:0.82rem">{{ $log->template_slug }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td style="font-weight:600;color:#6b7280;background:#f8f9fc;padding:0.6rem 1rem">Provider</td>
                        <td style="padding:0.6rem 1rem">{{ $log->provider }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight:600;color:#6b7280;background:#f8f9fc;padding:0.6rem 1rem">Queued At</td>
                        <td style="padding:0.6rem 1rem">{{ $log->queued_at?->format('d M Y, H:i:s') ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight:600;color:#6b7280;background:#f8f9fc;padding:0.6rem 1rem">Sent At</td>
                        <td style="padding:0.6rem 1rem">{{ $log->sent_at?->format('d M Y, H:i:s') ?? '—' }}</td>
                    </tr>
                    @if($log->message_id)
                    <tr>
                        <td style="font-weight:600;color:#6b7280;background:#f8f9fc;padding:0.6rem 1rem">Message ID</td>
                        <td style="padding:0.6rem 1rem;font-family:monospace;font-size:0.78rem;color:#6b7280">{{ $log->message_id }}</td>
                    </tr>
                    @endif
                    @if($log->error)
                    <tr>
                        <td style="font-weight:600;color:#dc2626;background:#fef2f2;padding:0.6rem 1rem">Error</td>
                        <td style="padding:0.6rem 1rem;color:#dc2626;font-size:0.82rem">{{ $log->error }}</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Email Body Preview ──────────────────────────────────────────── --}}
    @if($log->body_html)
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <span><i class="bi bi-eye me-2"></i>Email Body Preview</span>
            <button type="button"
                    class="btn btn-outline-secondary btn-sm"
                    id="toggleSource"
                    title="Toggle HTML source">
                <i class="bi bi-code-slash me-1"></i>View Source
            </button>
        </div>

        {{-- Rendered visual preview --}}
        <div class="card-body p-0" id="previewPane" style="background:#f4f6fb;border-radius:0 0 8px 8px;overflow:hidden">
            <iframe id="emailFrame"
                    style="width:100%;border:none;display:block;min-height:200px"
                    title="Email Preview"
                    sandbox="allow-same-origin">
            </iframe>
        </div>

        {{-- Raw HTML source (hidden by default) --}}
        <div class="card-body p-0" id="sourcePane" style="display:none">
            <pre style="margin:0;padding:16px 20px;font-size:0.76rem;line-height:1.6;background:#1e1e2e;color:#cdd6f4;border-radius:0 0 8px 8px;overflow-x:auto;white-space:pre-wrap;word-break:break-all">{{ $log->body_html }}</pre>
        </div>
    </div>
    @else
    <div class="card">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-envelope-x d-block mb-2" style="font-size:2rem;opacity:0.3"></i>
            No email body stored for this log.
        </div>
    </div>
    @endif

</div>

@push('scripts')
<script>
(function () {
    'use strict';

    // ── Inject HTML into iframe via JS (avoids srcdoc encoding issues) ───
    const frame = document.getElementById('emailFrame');
    if (frame) {
        // Use a data URI approach to write the full HTML into the iframe
        const html  = @json($log->body_html ?? '');
        const doc   = frame.contentDocument || frame.contentWindow.document;
        doc.open();
        doc.write(html);
        doc.close();

        // Auto-resize iframe to content height after render
        function resizeFrame() {
            try {
                const h = doc.documentElement.scrollHeight || doc.body.scrollHeight;
                if (h > 100) {
                    frame.style.height = (h + 20) + 'px';
                }
            } catch (e) {}
        }

        frame.addEventListener('load', resizeFrame);
        // Fallback resize after a short delay for content that loads async
        setTimeout(resizeFrame, 300);
        setTimeout(resizeFrame, 800);
    }

    // ── Toggle between preview and source ───────────────────────────────
    const toggleBtn  = document.getElementById('toggleSource');
    const previewPane = document.getElementById('previewPane');
    const sourcePane  = document.getElementById('sourcePane');

    toggleBtn?.addEventListener('click', function () {
        const showingPreview = previewPane.style.display !== 'none';
        if (showingPreview) {
            previewPane.style.display = 'none';
            sourcePane.style.display  = '';
            this.innerHTML = '<i class="bi bi-eye me-1"></i>View Preview';
        } else {
            previewPane.style.display = '';
            sourcePane.style.display  = 'none';
            this.innerHTML = '<i class="bi bi-code-slash me-1"></i>View Source';
        }
    });
})();
</script>
@endpush

@endsection
