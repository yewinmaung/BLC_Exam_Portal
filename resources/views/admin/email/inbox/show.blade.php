@extends('layouts.app')
@section('title', 'Inbox — ' . $inboxEmail->subject)
@section('page-title', 'Conversation')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin',  'url' => route('admin.dashboard')],
        ['label' => 'Inbox',  'url' => route('admin.email.inbox')],
        ['label' => Str::limit($inboxEmail->subject, 40)],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@push('styles')
<style>
/* ── Thread timeline ─────────────────────────────────────────── */
.thread-wrap   { max-width:820px; }
.msg-bubble    { border-radius:12px;padding:0;overflow:hidden;margin-bottom:16px;box-shadow:0 1px 4px rgba(0,0,0,0.06); }
.msg-bubble.inbound  { border:1.5px solid #e8eaf2; }
.msg-bubble.outbound { border:1.5px solid #d1fae5; }
.msg-header    { display:flex;align-items:center;justify-content:space-between;padding:10px 16px;font-size:0.82rem;gap:12px; }
.msg-header.inbound  { background:#f8f9fc; }
.msg-header.outbound { background:#f0fdf4; }
.msg-from      { font-weight:700;color:#111827; }
.msg-meta      { font-size:0.73rem;color:#9ca3af;white-space:nowrap; }
.msg-body      { padding:0; }
.msg-body iframe { width:100%;border:none;display:block;min-height:80px; }
.msg-body-text { padding:16px 20px;font-size:0.875rem;color:#374151;white-space:pre-wrap;line-height:1.65; }
.msg-status    { font-size:0.7rem;font-weight:700;padding:2px 8px;border-radius:4px; }
/* Reply box */
.reply-box     { background:#fff;border:1.5px solid #e8eaf2;border-radius:12px;padding:20px;margin-top:8px; }
</style>
@endpush

@section('content')
<div class="d-flex justify-content-center">
    <div class="thread-wrap">

    {{-- ── Thread header ── --}}
    <div class="d-flex align-items-start justify-content-between mb-3 gap-3">
        <div>
            <h5 style="font-weight:700;color:#111827;margin-bottom:4px">{{ $inboxEmail->subject }}</h5>
            <div style="font-size:0.8rem;color:#6b7280">
                @if($thread->count() > 1)
                <span><i class="bi bi-chat-dots me-1"></i>{{ $thread->count() }} messages in this conversation</span>
                @else
                <span><i class="bi bi-envelope me-1"></i>Single message</span>
                @endif
                &nbsp;·&nbsp;
                <span>Started {{ $thread->first()->received_at->format('d M Y') }}</span>
            </div>
        </div>
        <div class="d-flex gap-2 flex-shrink-0">
            <a href="{{ route('admin.email.inbox') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Inbox
            </a>
            <!-- @if($inboxEmail->status !== 'archived')
            <form action="{{ route('admin.email.inbox.archive', $inboxEmail) }}" method="POST" class="d-inline"
                  onsubmit="return confirm('Archive this thread?')">
                @csrf @method('DELETE')
                <button class="btn btn-outline-warning btn-sm">
                    <i class="bi bi-archive me-1"></i>Archive
                </button>
            </form>
            @endif -->
        </div>
    </div>

    {{-- ── Conversation timeline ── --}}
    @foreach($thread as $msg)
    @php
        $isOutbound = $msg->user_id && $msg->from_email === config('mail.from.address');
        $direction  = $isOutbound ? 'outbound' : 'inbound';
        $sc = [
            'unread'   => 'background:#dbeafe;color:#1d4ed8',
            'read'     => 'background:#f3f4f6;color:#6b7280',
            'replied'  => 'background:#d1fae5;color:#065f46',
            'archived' => 'background:#fef9c3;color:#854d0e',
        ];
    @endphp
    <div class="msg-bubble {{ $direction }}" id="msg-{{ $msg->id }}">

        <div class="msg-header {{ $direction }}">
            <div class="d-flex align-items-center gap-2 min-width-0">
                @if($isOutbound)
                <i class="bi bi-arrow-right-circle-fill" style="color:#059669;font-size:1rem;flex-shrink:0"></i>
                @else
                <i class="bi bi-arrow-left-circle-fill" style="color:#2d27a0;font-size:1rem;flex-shrink:0"></i>
                @endif
                <div>
                    <span class="msg-from">
                        @if($isOutbound)
                            You ({{ config('mail.from.name') }})
                        @else
                            {{ $msg->display_name }}
                            @if($msg->from_name)
                                <span class="fw-normal text-muted">&lt;{{ $msg->from_email }}&gt;</span>
                            @endif
                        @endif
                    </span>
                    @if($msg->sender_type === 'student' && !$isOutbound)
                    <span style="font-size:0.68rem;background:#eef2ff;color:#3730a3;padding:1px 5px;border-radius:3px;font-weight:600;margin-left:5px">Student</span>
                    @endif
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="msg-status" style="{{ $sc[$msg->status] ?? '' }}">{{ ucfirst($msg->status) }}</span>
                <span class="msg-meta">{{ $msg->received_at->format('d M Y H:i') }}</span>
                @if(!$isOutbound && $msg->id !== $inboxEmail->id)
                <a href="{{ route('admin.email.inbox.show', $msg) }}"
                   class="btn btn-xs btn-outline-secondary" style="padding:1px 6px;font-size:0.7rem" title="Open">
                    <i class="bi bi-box-arrow-up-right"></i>
                </a>
                @endif
            </div>
        </div>

        <div class="msg-body">
            @if($msg->body_html)
            <iframe class="msg-iframe"
                    style="width:100%;border:none;display:block;min-height:80px"
                    title="Message body"
                    data-html="{{ base64_encode($msg->body_html) }}">
            </iframe>
            @elseif($msg->body_text)
            <div class="msg-body-text">{{ $msg->body_text }}</div>
            @else
            <div class="msg-body-text text-muted" style="font-style:italic">No message body.</div>
            @endif
        </div>

    </div>
    @endforeach

    {{-- ── Reply form ── --}}
    @if($inboxEmail->status !== 'archived')
    <div class="reply-box">
        <div style="font-size:0.88rem;font-weight:700;color:#374151;margin-bottom:14px">
            <i class="bi bi-reply-fill me-2" style="color:var(--blc-royal,#2d27a0)"></i>
            Reply to {{ $inboxEmail->from_name ?: $inboxEmail->from_email }}
        </div>

        @if($errors->any())
        <div class="alert alert-danger d-flex align-items-start gap-2 mb-3" style="font-size:0.83rem">
            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
            <ul class="mb-0 ps-2">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
        @endif

        <form method="POST" action="{{ route('admin.email.inbox.reply', $inboxEmail) }}">
            @csrf

            <div class="mb-3">
                <label class="form-label" style="font-size:0.82rem;font-weight:600">Subject</label>
                <input type="text" name="subject" class="form-control form-control-sm"
                       value="{{ old('subject', (str_starts_with(strtolower($inboxEmail->subject), 're:') ? $inboxEmail->subject : 'Re: '.$inboxEmail->subject)) }}"
                       maxlength="255">
            </div>

            <div class="mb-3">
                <label class="form-label" style="font-size:0.82rem;font-weight:600">
                    Message <span class="text-danger">*</span>
                </label>
                <textarea name="reply_body" rows="7" class="form-control"
                          placeholder="Write your reply here…"
                          required>{{ old('reply_body') }}</textarea>
                <div class="form-text" style="font-size:0.75rem">
                    Plain text. Line breaks preserved. The original message will be quoted below your reply.
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm px-4">
                    <i class="bi bi-send me-1"></i>Send Reply
                </button>
                <a href="{{ route('admin.email.inbox') }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
            </div>
        </form>
    </div>
    @else
    <div class="alert alert-secondary d-flex gap-2 align-items-center mt-3" style="font-size:0.84rem">
        <i class="bi bi-archive-fill"></i>
        This thread is archived. Replies are disabled.
    </div>
    @endif

</div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    // Inject iframe bodies via JS — same pattern as log-show and template preview
    document.querySelectorAll('.msg-iframe').forEach(function (frame) {
        const encoded = frame.dataset.html;
        if (!encoded) return;

        try {
            const html = atob(encoded);
            const doc  = frame.contentDocument || frame.contentWindow.document;
            doc.open();
            doc.write(html);
            doc.close();

            function resize() {
                try {
                    const h = doc.documentElement.scrollHeight || doc.body.scrollHeight;
                    if (h > 40) frame.style.height = (h + 16) + 'px';
                } catch (e) {}
            }

            frame.addEventListener('load', resize);
            setTimeout(resize, 200);
            setTimeout(resize, 600);
        } catch (e) { /* non-fatal */ }
    });

    // Scroll to the latest message on load
    const msgs = document.querySelectorAll('.msg-bubble');
    if (msgs.length > 1) {
        msgs[msgs.length - 1].scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
})();
</script>
@endpush
