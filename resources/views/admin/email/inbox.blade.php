@extends('layouts.app')
@section('title', 'Inbox')
@section('page-title', 'Inbox')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Email'],
        ['label' => 'Inbox'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')

{{-- Filter bar --}}
<div class="card mb-3">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('admin.email.inbox') }}"
              class="d-flex flex-wrap gap-2 align-items-end">

            <div style="flex:2;min-width:200px">
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Search sender or subject…"
                       value="{{ request('search') }}">
            </div>

            <div style="min-width:130px">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All statuses</option>
                    @foreach(['unread'=>'Unread','read'=>'Read','replied'=>'Replied','archived'=>'Archived'] as $val => $lbl)
                    <option value="{{ $val }}" {{ request('status') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>

            <div class="d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm px-3">
                    <i class="bi bi-funnel-fill me-1"></i>Filter
                </button>
                <a href="{{ route('admin.email.inbox') }}" class="btn btn-outline-secondary btn-sm" title="Reset">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>

        </form>
    </div>
</div>

{{-- Inbox table --}}
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span>
            <i class="bi bi-inbox-fill me-2" style="color:var(--blc-royal,#2d27a0)"></i>
            Inbox
        </span>
        <div class="d-flex align-items-center gap-2">
            @if($unreadCount > 0)
            <span class="badge bg-danger">{{ $unreadCount }} unread</span>
            @endif
            <span class="badge" style="background:#eef2ff;color:#3730a3">
                {{ $emails->total() }} total
            </span>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0" style="font-size:0.845rem">
                <thead style="background:#f8f9fc">
                    <tr>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 1rem;border-bottom:1.5px solid #e8eaf2">From</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Subject</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Status</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Received</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($emails as $email)
                    @php
                        $isUnread = $email->status === 'unread';
                        $rowBg    = $isUnread ? 'background:#f0f4ff' : '';
                    @endphp
                    <tr style="{{ $rowBg }}">
                        <td style="padding:0.7rem 1rem">
                            <div style="font-weight:{{ $isUnread ? '700' : '500' }};color:#111827">
                                {{ $email->display_name }}
                            </div>
                            <div style="font-size:0.72rem;color:#9ca3af">{{ $email->from_email }}</div>
                            @if($email->sender_type === 'student')
                            <span style="font-size:0.68rem;background:#eef2ff;color:#3730a3;padding:1px 5px;border-radius:3px;font-weight:600">Student</span>
                            @endif
                        </td>
                        <td style="padding:0.7rem 0.75rem;max-width:260px">
                            <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:{{ $isUnread ? '700' : '400' }};color:#374151"
                                 title="{{ $email->subject }}">
                                @if($isUnread)
                                <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:#2563eb;margin-right:5px;vertical-align:middle"></span>
                                @endif
                                {{ $email->subject }}
                            </div>
                        </td>
                        <td style="padding:0.7rem 0.75rem">
                            @php
                                $statusColors = [
                                    'unread'   => 'background:#dbeafe;color:#1d4ed8',
                                    'read'     => 'background:#f3f4f6;color:#6b7280',
                                    'replied'  => 'background:#d1fae5;color:#065f46',
                                    'archived' => 'background:#fef9c3;color:#854d0e',
                                ];
                            @endphp
                            <span style="font-size:0.7rem;font-weight:700;padding:3px 8px;border-radius:5px;{{ $statusColors[$email->status] ?? '' }}">
                                {{ ucfirst($email->status) }}
                            </span>
                        </td>
                        <td style="padding:0.7rem 0.75rem;color:#9ca3af;font-size:0.78rem;white-space:nowrap">
                            {{ $email->received_at->format('d M Y H:i') }}
                        </td>
                        <td style="padding:0.7rem 0.75rem">
                            <div class="d-flex gap-1">
                                <a href="{{ route('admin.email.inbox.show', $email) }}"
                                   class="btn btn-sm btn-outline-primary" title="Open">
                                    <i class="bi bi-envelope-open"></i>
                                </a>
                                @if($email->status !== 'archived')
                                <form action="{{ route('admin.email.inbox.archive', $email) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Archive this email?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-secondary" title="Archive">
                                        <i class="bi bi-archive"></i>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox d-block mb-2" style="font-size:2rem;opacity:0.3"></i>
                            @if(request()->hasAny(['search','status']))
                                No emails match your filters.
                            @else
                                Inbox is empty.
                                <div class="mt-2 small text-muted">
                                    IMAP/Webhook receiving is a planned future feature.
                                    Emails received externally will appear here once configured.
                                </div>
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($emails->hasPages())
        <div class="px-3 py-2 border-top d-flex align-items-center justify-content-between flex-wrap gap-2"
             style="background:#fafbff">
            <span style="font-size:0.78rem;color:#6b7280">
                Showing <strong>{{ $emails->firstItem() }}</strong>–<strong>{{ $emails->lastItem() }}</strong>
                of <strong>{{ $emails->total() }}</strong>
            </span>
            {{ $emails->withQueryString()->links() }}
        </div>
        @endif
    </div>
</div>

@endsection
