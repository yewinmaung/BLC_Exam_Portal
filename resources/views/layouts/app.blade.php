<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'Believe Exam'))</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/believe-theme.css') }}" rel="stylesheet">
    <style>
    /* ── Notification Bell — uses CSS vars, auto light/dark ── */
    .notif-bell-wrap { position: relative; }

    .notif-bell-btn {
        position: relative; width: 34px; height: 34px;
        border-radius: var(--radius-sm, 8px);
        border: 1.5px solid var(--border-2, #e4e5f0);
        background: var(--surface, #fff);
        color: var(--text-secondary, #5a5a7a);
        display: flex; align-items: center; justify-content: center;
        font-size: 0.98rem; cursor: pointer; transition: all 0.16s;
    }
    .notif-bell-btn:hover {
        background: var(--blc-royal-light, #eef2ff);
        border-color: var(--blc-royal, #2d27a0);
        color: var(--blc-royal, #2d27a0);
    }
    .notif-bell-btn.has-unread {
        color: var(--blc-royal, #2d27a0);
        border-color: var(--blc-royal, #2d27a0);
    }

    .notif-badge {
        position: absolute; top: -5px; right: -5px;
        min-width: 17px; height: 17px;
        background: #ef4444; color: #fff;
        border-radius: 20px; font-size: 0.62rem; font-weight: 800;
        display: flex; align-items: center; justify-content: center;
        padding: 0 3px; border: 2px solid var(--surface, #fff); line-height: 1;
        animation: badge-pop 0.28s ease;
    }
    @keyframes badge-pop { 0%{transform:scale(0)} 70%{transform:scale(1.2)} 100%{transform:scale(1)} }

    .notif-dropdown {
        position: absolute; top: calc(100% + 9px); right: 0;
        width: 336px;
        background: var(--surface, #fff);
        border-radius: 15px;
        box-shadow: 0 16px 48px rgba(0,0,0,0.14), 0 2px 8px rgba(0,0,0,0.06);
        border: 1px solid var(--border-2, #e4e5f0);
        z-index: 9999; display: none; overflow: hidden;
        animation: dropdown-in 0.16s ease;
    }
    @keyframes dropdown-in { from{opacity:0;transform:translateY(-7px)} to{opacity:1;transform:translateY(0)} }
    .notif-dropdown.open { display: block; }

    .notif-dropdown-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 13px 15px 9px;
        border-bottom: 1px solid var(--border-2, #e4e5f0);
        font-size: 0.875rem; font-weight: 700; color: var(--text-primary, #1a1a3e);
    }
    .notif-mark-all {
        background: none; border: none; cursor: pointer;
        font-size: 0.73rem; font-weight: 600;
        color: var(--blc-royal, #2d27a0);
        display: flex; align-items: center; gap: 0.28rem; padding: 0;
        transition: opacity 0.14s;
    }
    .notif-mark-all:hover { opacity: 0.7; }

    .notif-list { max-height: 330px; overflow-y: auto; }

    .notif-item {
        display: flex; align-items: flex-start; gap: 10px;
        padding: 11px 15px;
        border-bottom: 1px solid var(--border-2, #e4e5f0);
        cursor: pointer; transition: background 0.13s;
        text-decoration: none; color: inherit;
    }
    .notif-item:last-child { border-bottom: none; }
    .notif-item:hover { background: var(--surface-2, #f0f1f8); }
    .notif-item.unread { background: var(--blc-royal-light, #eef2ff); }
    .notif-item.unread:hover { background: #e4e8ff; }

    .notif-item-icon {
        width: 34px; height: 34px; border-radius: 9px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.95rem; flex-shrink: 0;
    }
    .notif-icon-exam_submitted  { background:#fef9c3; color:#854d0e; }
    .notif-icon-exam_published  { background:#eef2ff; color:#3730a3; }
    .notif-icon-exam_approved   { background:#f0fdf4; color:#166534; }
    .notif-icon-question_added  { background:#f5f3ff; color:#6d28d9; }
    .notif-icon-default         { background:var(--surface-2,#f0f1f8); color:var(--text-muted,#8888aa); }

    .notif-item-body { flex: 1; min-width: 0; }
    .notif-item-title {
        font-size: 0.81rem; font-weight: 700;
        color: var(--text-primary, #1a1a3e); margin-bottom: 2px;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .notif-item-msg {
        font-size: 0.75rem; color: var(--text-secondary, #5a5a7a); line-height: 1.4;
        display: -webkit-box; -webkit-line-clamp: 2;
        -webkit-box-orient: vertical; overflow: hidden;
    }
    .notif-item-time { font-size: 0.67rem; color: var(--text-muted, #8888aa); margin-top: 3px; }

    .notif-unread-dot {
        width: 7px; height: 7px; border-radius: 50%;
        background: var(--blc-royal, #2d27a0); flex-shrink: 0; margin-top: 5px;
    }

    .notif-empty {
        display: flex; flex-direction: column; align-items: center;
        justify-content: center; gap: 0.5rem;
        padding: 2.5rem 1rem; color: var(--text-muted, #8888aa); font-size: 0.84rem;
    }
    .notif-empty i { font-size: 2rem; opacity: 0.35; }

    .notif-dropdown-footer {
        padding: 9px 15px; border-top: 1px solid var(--border-2, #e4e5f0); text-align: center;
    }
    .notif-dropdown-footer a {
        font-size: 0.79rem; font-weight: 600;
        color: var(--blc-royal, #2d27a0); text-decoration: none;
    }
    .notif-dropdown-footer a:hover { text-decoration: underline; }
    </style>
    @stack('styles')
</head>
<body>
@auth
{{-- ══ SIDEBAR ══ --}}
<aside class="sidebar" id="sidebar">
    <div class="sidebar-inner">

        {{-- Brand --}}
        <div class="blc-brand">
            <img src="{{ asset('images/logo.png') }}" alt="Believe Learning Center">
            <div class="title">
                <strong>Believe Exam</strong>
                <small>Learning Center</small>
            </div>
        </div>

        {{-- User badge --}}
        <div class="sidebar-user">
            <div class="sidebar-user-avatar">
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
            </div>
            <div class="sidebar-user-info">
                <div class="name">{{ auth()->user()->name }}</div>
                <div class="role">{{ ucfirst(auth()->user()->role->slug ?? 'user') }}</div>
            </div>
        </div>

        {{-- Nav --}}
        <div class="sidebar-section-label">Menu</div>
        @yield('sidebar')

    </div>

    {{-- ══ SIGN OUT — always pinned at sidebar bottom ══ --}}
    <div class="sidebar-signout-fixed">
        <form action="{{ route('logout') }}" method="POST">@csrf
            <button type="submit" class="sidebar-signout-btn">
                <i class="bi bi-box-arrow-right"></i> Sign Out
            </button>
        </form>
    </div>

</aside>
@endauth

{{-- ══ MAIN ══ --}}
<main class="@auth main-content @else @endauth">

    @auth
    {{-- Top bar --}}
    <div class="main-topbar">
        <div class="page-title-wrap">
            @hasSection('breadcrumbs')
                @yield('breadcrumbs')
            @endif
            <h4>@yield('page-title')</h4>
        </div>
        <div class="topbar-actions">

            {{-- ── Notification Bell ── --}}
            <div class="notif-bell-wrap" id="notifWrap">
                <button class="notif-bell-btn" id="notifBtn" title="Notifications">
                    <i class="bi bi-bell"></i>
                    <span class="notif-badge" id="notifBadge" style="display:none">0</span>
                </button>

                {{-- Dropdown --}}
                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-dropdown-header">
                        <span class="fw-700" style="font-weight:700;font-size:0.9rem">Notifications</span>
                        <button class="notif-mark-all" id="notifMarkAll" title="Mark all read">
                            <i class="bi bi-check2-all"></i> Mark all read
                        </button>
                    </div>
                    <div class="notif-list" id="notifList">
                        <div class="notif-empty">
                            <i class="bi bi-bell-slash"></i>
                            <span>No notifications</span>
                        </div>
                    </div>
                    <div class="notif-dropdown-footer">
                        <a href="{{ route('notifications.index') }}">View all notifications</a>
                    </div>
                </div>
            </div>

            <button class="btn btn-sm btn-outline-secondary" id="themeToggle" title="Toggle theme">
                <i class="bi bi-moon-stars" id="themeIcon"></i>
            </button>
            <button class="btn btn-sm btn-outline-primary d-md-none" id="sidebarToggle">
                <i class="bi bi-list"></i>
            </button>
        </div>
    </div>
    @endauth

    <div class="@auth main-body @else container py-5 @endauth">

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 mb-3">
                <i class="bi bi-check-circle-fill"></i>
                <span>{{ session('success') }}</span>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-start gap-2 mb-3">
                <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                <ul class="mb-0 ps-2">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @yield('content')
    </div>
</main>

{{-- Mobile overlay --}}
@auth
<div class="sidebar-overlay d-md-none" id="sidebarOverlay"
     style="display:none!important;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:1039;"></div>
@endauth

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script>
(function(){
    // Theme
    const html = document.documentElement;
    const saved = localStorage.getItem('blc-theme') || 'light';
    html.setAttribute('data-theme', saved);
    const icon = document.getElementById('themeIcon');
    if(icon) icon.className = saved === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';

    document.getElementById('themeToggle')?.addEventListener('click', () => {
        const cur = html.getAttribute('data-theme');
        const next = cur === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', next);
        localStorage.setItem('blc-theme', next);
        const ic = document.getElementById('themeIcon');
        if(ic) ic.className = next === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
    });

    // Sidebar mobile
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    document.getElementById('sidebarToggle')?.addEventListener('click', () => {
        sidebar?.classList.toggle('show');
        if(overlay) overlay.style.display = sidebar?.classList.contains('show') ? 'block' : 'none';
    });
    overlay?.addEventListener('click', () => {
        sidebar?.classList.remove('show');
        overlay.style.display = 'none';
    });

    // DataTables
    $(document).ready(function(){
        $('.datatable').DataTable({ responsive:true, pageLength:15,
            language:{ search:'<i class="bi bi-search"></i>', searchPlaceholder:'Search...' }
        });
    });
})();
</script>
@auth
<script>
(function(){
    const btn      = document.getElementById('notifBtn');
    const dropdown = document.getElementById('notifDropdown');
    const badge    = document.getElementById('notifBadge');
    const list     = document.getElementById('notifList');
    const markAll  = document.getElementById('notifMarkAll');

    if (!btn) return;

    const POLL_INTERVAL = 30000; // 30 seconds
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const typeIcons = {
        exam_submitted : { icon: 'bi-file-earmark-arrow-up', cls: 'notif-icon-exam_submitted' },
        exam_published : { icon: 'bi-broadcast',              cls: 'notif-icon-exam_published' },
        exam_approved  : { icon: 'bi-check-circle-fill',      cls: 'notif-icon-exam_approved'  },
        question_added : { icon: 'bi-patch-plus-fill',        cls: 'notif-icon-question_added' },
    };

    function getIcon(type) {
        return typeIcons[type] || { icon: 'bi-bell-fill', cls: 'notif-icon-default' };
    }

    function renderNotifications(data) {
        const count = data.count;
        const items = data.notifications;

        // Badge
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'flex';
            btn.classList.add('has-unread');
        } else {
            badge.style.display = 'none';
            btn.classList.remove('has-unread');
        }

        // List
        if (!items || items.length === 0) {
            list.innerHTML = `<div class="notif-empty"><i class="bi bi-bell-slash"></i><span>No notifications</span></div>`;
            return;
        }

        list.innerHTML = items.map(n => {
            const ic = getIcon(n.type);
            const unreadClass = n.is_read ? '' : 'unread';
            const dot = n.is_read ? '' : '<div class="notif-unread-dot"></div>';
            const href = n.link || '#';
            return `
            <a class="notif-item ${unreadClass}" href="${href}"
               data-id="${n.id}" data-read="${n.is_read ? '1' : '0'}">
                <div class="notif-item-icon ${ic.cls}">
                    <i class="bi ${ic.icon}"></i>
                </div>
                <div class="notif-item-body">
                    <div class="notif-item-title">${escHtml(n.title)}</div>
                    <div class="notif-item-msg">${escHtml(n.message)}</div>
                    <div class="notif-item-time">${n.time}</div>
                </div>
                ${dot}
            </a>`;
        }).join('');

        // Click to mark read
        list.querySelectorAll('.notif-item[data-read="0"]').forEach(el => {
            el.addEventListener('click', function(e) {
                const id = this.dataset.id;
                fetch(`/notifications/${id}/read`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
                }).then(() => fetchNotifications());
            });
        });
    }

    function fetchNotifications() {
        fetch('/notifications/unread-count', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => renderNotifications(data))
        .catch(() => {});
    }

    function escHtml(str) {
        return String(str || '')
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // Toggle dropdown
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdown.classList.toggle('open');
        if (dropdown.classList.contains('open')) fetchNotifications();
    });

    // Close on outside click
    document.addEventListener('click', function(e) {
        if (!document.getElementById('notifWrap')?.contains(e.target)) {
            dropdown.classList.remove('open');
        }
    });

    // Mark all read
    markAll?.addEventListener('click', function(e) {
        e.stopPropagation();
        fetch('/notifications/read-all', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
        }).then(() => fetchNotifications());
    });

    // Initial fetch + polling
    fetchNotifications();
    setInterval(fetchNotifications, POLL_INTERVAL);
})();
</script>
@endauth
@stack('scripts')
</body>
</html>
