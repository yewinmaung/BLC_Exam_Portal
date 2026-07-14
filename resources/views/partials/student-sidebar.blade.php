@php
    /*
     * Per-category unread badge counts — computed once per page load.
     * Single GROUP BY query on user_notifications (no new tables/columns).
     * The JS in app.blade.php refreshes these every 30 s via the
     * /notifications/unread-by-category endpoint.
     */
    $navBadges = auth()->check()
        ? \App\Models\UserNotification::unreadCountsByCategory(auth()->id())
        : ['exam' => 0, 'result' => 0, 'course' => 0, 'general' => 0];

    // Exams nav total = exam + result
    $examNavTotal = ($navBadges['exam'] ?? 0)
                  + ($navBadges['result'] ?? 0);

    $inExamsSection = request()->routeIs('student.exams.*')
                   || request()->routeIs('student.exam.*')
                   || request()->routeIs('student.results.*');
@endphp

<nav class="nav flex-column gap-1">

    {{-- Dashboard --}}
    <a class="nav-link {{ request()->routeIs('student.dashboard') ? 'active' : '' }}"
       href="{{ route('student.dashboard') }}">
        <i class="bi bi-speedometer2"></i> Dashboard
    </a>

    {{-- My Courses — badge: 'course' category --}}
    <a class="nav-link {{ request()->routeIs('student.courses.*') ? 'active' : '' }}"
       href="{{ route('student.courses.index') }}">
        <i class="bi bi-book"></i> My Courses
        <span id="nav-badge-course"
              class="nav-badge ms-auto"
              style="display:{{ ($navBadges['course'] ?? 0) > 0 ? 'inline-flex' : 'none' }}">
            {{ $navBadges['course'] > 99 ? '99+' : $navBadges['course'] }}
        </span>
    </a>

    {{-- ── Exams group ──────────────────────────────────────────────── --}}
    {{-- The group header shows the sum of exam + result unread. --}}
    {{-- Sub-items are always visible when the user is in any exams section. --}}
    <div class="nav-group {{ $inExamsSection ? 'open' : '' }}">

        <a class="nav-link nav-group-toggle {{ $inExamsSection ? 'active' : '' }}"
           href="{{ route('student.exams.index') }}">
            <i class="bi bi-pencil-square"></i> Exams
            <span id="nav-badge-exam"
                  class="nav-badge ms-auto"
                  style="display:{{ $examNavTotal > 0 ? 'inline-flex' : 'none' }}">
                {{ $examNavTotal > 99 ? '99+' : $examNavTotal }}
            </span>
        </a>

        <div class="nav-sub">

            <a class="nav-link nav-sub-link {{ request()->routeIs('student.exams.index') ? 'active' : '' }}"
               href="{{ route('student.exams.index') }}">
                <i class="bi bi-list-check"></i> My Exams
            </a>

            <a class="nav-link nav-sub-link {{ request()->routeIs('student.results.*') ? 'active' : '' }}"
               href="{{ route('student.results.index') }}">
                <i class="bi bi-bar-chart-line"></i> My Results
                <span id="nav-badge-result"
                      class="nav-badge ms-auto"
                      style="display:{{ ($navBadges['result'] ?? 0) > 0 ? 'inline-flex' : 'none' }}">
                    {{ ($navBadges['result'] ?? 0) > 99 ? '99+' : ($navBadges['result'] ?? 0) }}
                </span>
            </a>

        </div>
    </div>
    {{-- ─────────────────────────────────────────────────────────────── --}}

    {{-- Notifications — global bell (shows only 'general' unread) --}}
    <a class="nav-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}"
       href="{{ route('notifications.index') }}">
        <i class="bi bi-bell"></i> Notifications
        <span id="nav-badge-general"
              class="nav-badge ms-auto"
              style="display:{{ ($navBadges['general'] ?? 0) > 0 ? 'inline-flex' : 'none' }}">
            {{ ($navBadges['general'] ?? 0) > 99 ? '99+' : ($navBadges['general'] ?? 0) }}
        </span>
    </a>

</nav>

@once
@push('styles')
<style>
/* ── Per-nav badge (red pill) ───────────────────────────────────────── */
.nav-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    border-radius: 9px;
    background: #dc2626;
    color: #fff;
    font-size: 0.63rem;
    font-weight: 800;
    line-height: 1;
    flex-shrink: 0;
    transition: transform 0.15s;
}

/* Align icon + label + badge on same row */
.nav-link {
    display: flex !important;
    align-items: center;
    gap: 0.5rem;
}

/* ── Exams sub-group ─────────────────────────────────────────────────── */
.nav-sub {
    display: none;
    flex-direction: column;
    gap: 1px;
    padding-left: 1.5rem;
    margin-top: 2px;
}

.nav-group.open .nav-sub {
    display: flex;
}

.nav-sub-link {
    font-size: 0.83rem;
    padding-top: 0.28rem !important;
    padding-bottom: 0.28rem !important;
}

.nav-sub-link i { font-size: 0.8rem; opacity: 0.75; }
</style>
@endpush
@endonce
