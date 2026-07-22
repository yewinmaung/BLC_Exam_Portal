@extends('layouts.app')
@section('title', 'Inbox — ' . $inboxEmail->subject)
@section('page-title', 'Inbox Email')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin',  'url' => route('admin.dashboard')],
        ['label' => 'Email'],
        ['label' => 'Inbox',  'url' => route('admin.email.inbox')],
        ['label' => 'Email #' . $inboxEmail->id],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')
<div style="max-width:800px">

    {{-- ── Email meta card ── --}}
    <div class="card mb-3">
        <div class="card-header d-flex align-items-center justify-content-between">
            <span>
                <i class="bi bi-envelope-open-fill me-2" style="color:var(--blc-royal,#2d27a0)"></i>
                {{ $inboxEmail->subject }}
            </span>
            @php
                $sc = [
                    'unread'   => 'background:#dbeafe;color:#1d4ed8',
                    'read'     => 'background:#f3f4f6;color:#6b7280',
                    'replied'  => 'background:#d1fae5;color:#065f46',
                    'archived' => 'background:#fef9c3;color:#854d0e',
                ];
            @endphp
            <span style="font-size:0.8rem;font-weight:700;padding:4px 12px;border-radius:6px;{{ $sc[$inboxEmail->status] ?? '' }}">
                {{ ucfirst($inboxEmail->status) }}
            </span>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0" style="font-size:0.845rem">
                <tbody>
                    <tr>
                        <td style="width:140px;font-weight:600;color:#6b7280;background:#f8f9fc;padding:0.6rem 1rem">From</td>
                        <td style="padding:0.6rem 1rem">
                            {{ $inboxEmail->display_name }}
                            @if($inboxEmail->from_name)
                                <span class="text-muted">&lt;{{ $inboxEmail->from_email }}&gt;</span>
                            @endif
                            @if($inboxEmail->sender_type === 'student')
                                <span style="font-size:0.7rem;background:#eef2ff;color:#3730a3;padding:1px 6px;border-radius:3px;font-weight:600;margin-left:6px">Student</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight:600;color:#6b7280;background:#f8f9fc;padding:0.6rem 1rem">Subject</td>
                        <td style="padding:0.6rem 1rem;font-weight:600">{{ $inboxEmail->subject }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight:600;color:#6b7280;background:#f8f9fc;padding:0.6rem 1rem">Received</td>
                        <td style="padding:0.6rem 1rem;color:#6b7280">
                            {{ $inboxEmail->received_at->format('d M Y H:i:s') }}
                            <span class="text-muted">({{ $inboxEmail->received_at->diffForHumans() }})</span>
                        </td>
                    </tr>
                    @if($inboxEmail->isReplied())
                    <tr>
                        <td style="font-weight:600;color:#065f46;background:#f0fdf4;padding:0.6rem 1rem">Replied By</td>
                        <td style="padding:0.6rem 1rem;color:#065f46">
                            {{ $inboxEmail->replier?->name ?? '—' }}
                            @if($inboxEmail->replied_at)
                                <span class="text-muted">&mdash; {{ $inboxEmail->replied_at->format('d M Y H:i') }}</span>
                            @endif
                        </td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Action buttons ── --}}
    <div class="d-flex gap-2 mb-3">
        <a href="{{ route('admin.email.inbox') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Inbox
        </a>
        @if($inboxEmail->status !== 'read' && $inboxEmail->status !== 'replied')
        <form action="{{ route('admin.email.inbox.read', $inboxEmail) }}" method="POST" class="d-inline">
            @csrf
            <button class="btn btn-outline-primary btn-sm">
                <i class="bi bi-envelope-check me-1"></i> Mark as Read
            </button>
        </form>
        @endif
        @if($inboxEmail->status !== 'archived')
        <form action="{{ route('admin.email.inbox.archive', $inboxEmail) }}" method="POST" class="d-inline"
              onsubmit="return confirm('Archive this email?')">
            @csrf @method('DELETE')
            <button class="btn btn-outline-warning btn-sm">
                <i class="bi bi-archive me-1"></i> Archive
            </button>
        </form>
        @endif
    </div>

    {{-- ── Email body ── --}}
    @if($inboxEmail->body_html)
    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-chat-text me-2"></i>Message</div>
        <div class="card-body p-0" style="background:#f4f6fb">
            <iframe srcdoc="{{ $inboxEmail->body_html }}"
                    style="width:100%;border:none;min-height:360px;display:block"
                    sandbox="allow-same-origin"
                    title="Email Body"></iframe>
        </div>
    </div>
    @elseif($inboxEmail->body_text)
    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-chat-text me-2"></i>Message</div>
        <div class="card-body" style="white-space:pre-wrap;font-size:0.875rem;color:#374151">{{ $inboxEmail->body_text }}</div>
    </div>
    @endif

    {{-- ── Reply form ── --}}
    @if($inboxEmail->status !== 'archived')
    <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
            <i class="bi bi-reply-fill" style="color:var(--blc-royal,#2d27a0)"></i>
            Reply to {{ $inboxEmail->from_email }}
        </div>
        <div class="card-body">

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
                    <input type="text" name="subject" class="form-control"
                           value="{{ old('subject', 'Re: ' . $inboxEmail->subject) }}"
                           maxlength="255">
                </div>

                <div class="mb-3">
                    <label class="form-label" style="font-size:0.82rem;font-weight:600">
                        Message <span class="text-danger">*</span>
                    </label>
                    <textarea name="reply_body" rows="8" class="form-control"
                              placeholder="Write your reply here…"
                              required>{{ old('reply_body') }}</textarea>
                    <div class="form-text" style="font-size:0.75rem">
                        Plain text only. Line breaks are preserved. Reply will be queued via the email queue.
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send me-1"></i> Send Reply
                    </button>
                    <a href="{{ route('admin.email.inbox') }}" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
    @else
    <div class="alert alert-secondary d-flex gap-2 align-items-center" style="font-size:0.84rem">
        <i class="bi bi-archive-fill"></i>
        <span>This email is archived. Unarchive it to reply.</span>
    </div>
    @endif

</div>
@endsection
