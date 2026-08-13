{{--
    Partial: inbox table rows only.
    Used by EmailController::inboxRows() for AJAX-based table refresh.
    Variables: $emails (LengthAwarePaginator), $threadCounts (array)
--}}
@forelse($emails as $email)
@php
    $isUnread    = $email->status === 'unread';
    $threadCount = $threadCounts[$email->thread_id] ?? 1;
    $hasThread   = $threadCount > 1;
@endphp
<tr style="{{ $isUnread ? 'background:#f0f4ff' : '' }}">
    <td style="padding:0.7rem 1rem">
        <div style="font-weight:{{ $isUnread ? '700' : '500' }};color:#111827">
            {{ $email->display_name }}
        </div>
        <div style="font-size:0.72rem;color:#9ca3af">{{ $email->from_email }}</div>
        @if($email->sender_type === 'student')
        <span style="font-size:0.68rem;background:#eef2ff;color:#3730a3;padding:1px 5px;border-radius:3px;font-weight:600">Student</span>
        @endif
    </td>
    <td style="padding:0.7rem 0.75rem;max-width:300px">
        <div class="d-flex align-items-center gap-2" style="min-width:0">
            @if($isUnread)
            <span style="flex-shrink:0;width:7px;height:7px;border-radius:50%;background:#2563eb;display:inline-block"></span>
            @endif
            <a href="{{ route('admin.email.inbox.show', $email) }}"
               class="text-decoration-none"
               style="font-weight:{{ $isUnread ? '700' : '400' }};color:#374151;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block;min-width:0"
               title="{{ $email->subject }}">
                {{ $email->subject }}
            </a>
            @if($hasThread)
            <span style="flex-shrink:0;font-size:0.68rem;font-weight:700;background:#f3f4f6;color:#6b7280;padding:1px 6px;border-radius:10px;white-space:nowrap">
                <i class="bi bi-chat-dots me-1"></i>{{ $threadCount }}
            </span>
            @endif
        </div>
    </td>
    <td style="padding:0.7rem 0.75rem">
        @php
            $sc = [
                'unread'   => 'background:#dbeafe;color:#1d4ed8',
                'read'     => 'background:#f3f4f6;color:#6b7280',
                'replied'  => 'background:#d1fae5;color:#065f46',
                'archived' => 'background:#fef9c3;color:#854d0e',
            ];
            $displayStatus = $email->status;
        @endphp
        <span style="font-size:0.7rem;font-weight:700;padding:3px 8px;border-radius:5px;{{ $sc[$displayStatus] ?? '' }}">
            {{ ucfirst($displayStatus) }}
        </span>
    </td>
    <td style="padding:0.7rem 0.75rem;color:#9ca3af;font-size:0.78rem;white-space:nowrap">
        {{ $email->received_at->format('d M Y H:i') }}
    </td>
    <td style="padding:0.7rem 0.75rem">
        <div class="d-flex gap-1">
            <a href="{{ route('admin.email.inbox.show', $email) }}"
               class="btn btn-sm btn-outline-primary" title="Open thread">
                <i class="bi bi-envelope-open"></i>
            </a>
            <!-- @if($email->status !== 'archived')
            <form action="{{ route('admin.email.inbox.archive', $email) }}" method="POST" class="d-inline"
                  onsubmit="return confirm('Archive this email?')">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-secondary" title="Archive">
                    <i class="bi bi-archive"></i>
                </button>
            </form>
            @endif -->
        </div>
    </td>
</tr>
@empty
<tr id="emptyRow">
    <td colspan="5" class="text-center py-5 text-muted">
        <i class="bi bi-inbox d-block mb-2" style="font-size:2rem;opacity:0.3"></i>
        @if(request()->hasAny(['search','status']))
            No emails match your filters.
        @else
            Inbox is empty. Click <strong>Sync Inbox</strong> to fetch new messages.
        @endif
    </td>
</tr>
@endforelse
