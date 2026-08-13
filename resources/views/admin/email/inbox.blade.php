@extends('layouts.app')
@section('title', 'Inbox')
@section('page-title', 'Inbox')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Inbox'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')

{{-- Error flash --}}
@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ $errors->first() }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- New email live banner (hidden until poll finds new messages) --}}
<div id="newEmailBanner" class="alert alert-info alert-dismissible fade mb-3" role="alert"
     style="display:none!important;background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af">
    <i class="bi bi-envelope-fill me-2"></i>
    <span id="newEmailText">New email received.</span>
    <a href="#" class="alert-link ms-2" onclick="event.preventDefault()">Inbox updated.</a>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>

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
            <span id="liveUnreadBadge" class="{{ $unreadCount > 0 ? '' : 'd-none' }} badge bg-danger ms-1">
                {{ $unreadCount }} unread
            </span>
        </span>
        <div class="d-flex align-items-center gap-2">
            <span id="inboxTotalBadge" class="badge" style="background:#eef2ff;color:#3730a3">{{ $emails->total() }} threads</span>
            <form method="POST" action="{{ route('admin.email.inbox.sync') }}" class="mb-0" id="syncForm">
                @csrf
                <button type="submit" class="btn btn-sm btn-primary" id="syncBtn">
                    <i class="bi bi-arrow-repeat me-1" id="syncIcon"></i>
                    <span id="syncLabel">Sync Inbox</span>
                </button>
            </form>
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
                <tbody id="inboxTableBody">
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
                                // Show thread-level status: unread if ANY message in thread is unread
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
                </tbody>
            </table>
        </div>

        @if($emails->hasPages())
        <div id="inboxPaginationWrap">
        <div class="px-3 py-2 border-top d-flex align-items-center justify-content-between flex-wrap gap-2"
             style="background:#fafbff">
            <span style="font-size:0.78rem;color:#6b7280">
                Showing <strong>{{ $emails->firstItem() }}</strong>–<strong>{{ $emails->lastItem() }}</strong>
                of <strong>{{ $emails->total() }}</strong>
            </span>
            {{ $emails->withQueryString()->links() }}
        </div>
        </div>
        @else
        <div id="inboxPaginationWrap"></div>
        @endif
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    // ── URLs (server-rendered, safe) ─────────────────────────────────────
    const SYNC_URL  = "{{ route('admin.email.inbox.sync') }}";
    const POLL_URL  = "{{ route('admin.email.inbox.poll') }}";
    const ROWS_URL  = "{{ route('admin.email.inbox.rows') }}";
    const CSRF      = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    // Polling interval: 12 seconds
    const POLL_MS   = 12000;

    // Track the timestamp of the newest email we know about.
    // Initialised to "now" so only emails arriving after page load trigger a refresh.
    let lastSince   = new Date().toISOString();

    // ── Helper: read active filter values from the filter form ───────────
    function currentParams() {
        const form   = document.querySelector('form[action*="inbox"]');
        const params = new URLSearchParams(window.location.search);
        // Keep page param if present so a mid-page poll doesn't jump to page 1
        return params.toString();
    }

    // ── Core: reload the inbox table body in-place ───────────────────────
    // Fetches rendered HTML from /inbox/rows and swaps tbody + pagination.
    // No full page reload. No prepend. Row count always matches DB.
    function refreshTable() {
        const qs = currentParams();
        fetch(ROWS_URL + (qs ? '?' + qs : ''), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
        .then(r => r.ok ? r.json() : null)
        .then(data => {
            if (!data) return;

            // Replace table rows
            const tbody = document.getElementById('inboxTableBody');
            if (tbody) tbody.innerHTML = data.rows_html;

            // Replace pagination (or hide if none)
            const paginationWrap = document.getElementById('inboxPaginationWrap');
            if (paginationWrap) {
                paginationWrap.innerHTML = data.pagination_html ?? '';
            }

            // Update unread badge
            updateUnreadBadge(data.unread_count ?? 0);

            // Update thread count label
            const countBadge = document.getElementById('inboxTotalBadge');
            if (countBadge && data.total !== undefined) {
                countBadge.textContent = data.total + ' threads';
            }

            // Advance lastSince to now so the next poll only checks truly new mail
            lastSince = new Date().toISOString();
        })
        .catch(() => { /* network error — silent, will retry on next tick */ });
    }

    // ── Unread badge helper ───────────────────────────────────────────────
    function updateUnreadBadge(count) {
        const badge = document.getElementById('liveUnreadBadge');
        if (!badge) return;
        badge.textContent = count + ' unread';
        badge.classList.toggle('d-none', count === 0);
    }

    // ── Sync button — AJAX (no full page reload) ─────────────────────────
    const syncForm  = document.getElementById('syncForm');
    const syncBtn   = document.getElementById('syncBtn');
    const syncIcon  = document.getElementById('syncIcon');
    const syncLabel = document.getElementById('syncLabel');

    if (syncForm) {
        syncForm.addEventListener('submit', function (e) {
            e.preventDefault(); // prevent traditional form POST redirect

            // Disable button and show spinner
            if (syncBtn)   syncBtn.disabled   = true;
            if (syncIcon)  syncIcon.className  = 'spinner-border spinner-border-sm me-1';
            if (syncLabel) syncLabel.textContent = 'Syncing…';

            fetch(SYNC_URL, {
                method:  'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN':     CSRF,
                    'Accept':           'application/json',
                },
                credentials: 'same-origin',
            })
            .then(r => r.json().then(d => ({ ok: r.ok, data: d })))
            .then(({ ok, data }) => {
                // Re-enable button
                if (syncBtn)   syncBtn.disabled   = false;
                if (syncIcon)  syncIcon.className  = 'bi bi-arrow-repeat me-1';
                if (syncLabel) syncLabel.textContent = 'Sync Inbox';

                // Show result banner
                showSyncBanner(ok, data.message ?? (ok ? 'Sync complete.' : 'Sync failed.'));

                // Always refresh the table after sync — new emails or not
                refreshTable();
            })
            .catch(() => {
                if (syncBtn)   syncBtn.disabled   = false;
                if (syncIcon)  syncIcon.className  = 'bi bi-arrow-repeat me-1';
                if (syncLabel) syncLabel.textContent = 'Sync Inbox';
                showSyncBanner(false, 'Network error. Check your connection and try again.');
            });
        });
    }

    // ── Sync result banner ────────────────────────────────────────────────
    function showSyncBanner(success, msg) {
        // Reuse newEmailBanner or create a transient alert
        const banner = document.getElementById('newEmailBanner');
        const text   = document.getElementById('newEmailText');
        if (!banner || !text) return;

        banner.className = 'alert alert-dismissible fade show mb-3';
        banner.style.removeProperty('display');

        if (success) {
            banner.style.background  = '#f0fdf4';
            banner.style.border      = '1px solid #bbf7d0';
            banner.style.color       = '#166534';
            text.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i>' + escHtml(msg);
        } else {
            banner.style.background  = '#fef2f2';
            banner.style.border      = '1px solid #fecaca';
            banner.style.color       = '#991b1b';
            text.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i>' + escHtml(msg);
        }

        // Auto-dismiss after 6 s
        setTimeout(() => {
            banner.classList.remove('show');
            setTimeout(() => banner.style.display = 'none', 200);
        }, 6000);
    }

    // ── Polling loop: lightweight check for new mail ─────────────────────
    // Only refreshes the table when new emails exist since lastSince.
    // This keeps poll requests cheap (no HTML rendering) and reserves the
    // heavier refreshTable() call for when it's actually needed.
    function pollInbox() {
        fetch(POLL_URL + '?since=' + encodeURIComponent(lastSince), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
        .then(r => r.ok ? r.json() : null)
        .then(data => {
            if (!data) return;

            // Always keep unread badge current
            updateUnreadBadge(data.unread_count ?? 0);

            if (data.emails && data.emails.length > 0) {
                // New emails arrived since last check — reload the full table
                // so row count matches DB exactly. No prepend = no duplicates.
                refreshTable();

                // Show "new email" banner
                const banner = document.getElementById('newEmailBanner');
                const text   = document.getElementById('newEmailText');
                if (banner && text) {
                    const count = data.emails.length;
                    banner.className = 'alert alert-dismissible fade show mb-3';
                    banner.style.removeProperty('display');
                    banner.style.background = '#eff6ff';
                    banner.style.border     = '1px solid #bfdbfe';
                    banner.style.color      = '#1e40af';
                    text.innerHTML = '<i class="bi bi-envelope-fill me-1"></i>' + escHtml(
                        count === 1
                            ? 'New email from ' + data.emails[0].display_name + ': ' + data.emails[0].subject
                            : count + ' new emails received.'
                    );
                }
            }
        })
        .catch(() => { /* network error — silent */ });
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // Start polling loop — first tick after POLL_MS, then repeating.
    setTimeout(pollInbox, POLL_MS);
    setInterval(pollInbox, POLL_MS);

})();
</script>
@endpush
