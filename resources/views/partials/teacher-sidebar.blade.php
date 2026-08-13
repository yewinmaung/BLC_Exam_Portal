@php
    /*
     * Per-category unread badge counts for the teacher nav.
     * Reuses UserNotification::unreadCountsByCategory() — single GROUP BY query.
     * No new tables, no new columns, no changes to notification logic.
     *
     * Teacher badge mapping:
     *   My Exams nav     → 'exam' category (exam_approved, exam_published, security_warning …)
     *   Results nav      → 'result' category (future use; wired now, always 0 currently)
     *   Notifications nav → 'general' category
     */
    $teacherBadges = auth()->check()
        ? \App\Models\UserNotification::unreadCountsByCategory(auth()->id())
        : ['exam' => 0, 'result' => 0, 'course' => 0, 'general' => 0];

    $examBadge    = $teacherBadges['exam']    ?? 0;
    $resultBadge  = $teacherBadges['result']  ?? 0;
    $generalBadge = $teacherBadges['general'] ?? 0;
@endphp

<nav class="nav flex-column gap-1">

    {{-- Dashboard --}}
    <a class="nav-link {{ request()->routeIs('teacher.dashboard') ? 'active' : '' }}"
       href="{{ route('teacher.dashboard') }}">
        <i class="bi bi-speedometer2"></i> Dashboard
    </a>

    {{-- My Profile --}}
    <a class="nav-link {{ request()->routeIs('teacher.profile.*') ? 'active' : '' }}"
       href="{{ route('teacher.profile.show') }}">
        <i class="bi bi-person-badge"></i> My Profile
    </a>

    {{-- My Exams — badge: 'exam' category (exam_approved, exam_published, security_warning …) --}}
    <a class="nav-link {{ request()->routeIs('teacher.exams.index') || request()->routeIs('teacher.exams.show') ? 'active' : '' }}"
       href="{{ route('teacher.exams.index') }}">
        <i class="bi bi-file-earmark-text"></i> My Exams
        <span id="nav-badge-exam"
              class="nav-badge ms-auto"
              style="display:{{ $examBadge > 0 ? 'inline-flex' : 'none' }}">
            {{ $examBadge > 99 ? '99+' : $examBadge }}
        </span>
    </a>

    {{-- Create Exam --}}
    <a class="nav-link {{ request()->routeIs('teacher.exams.create') ? 'active' : '' }}"
       href="{{ route('teacher.exams.create') }}">
        <i class="bi bi-plus-circle"></i> Create Exam
    </a>

    {{-- Results — badge: 'result' category (wired for future use; currently 0 for teachers) --}}
    <a class="nav-link {{ request()->routeIs('teacher.results.*') ? 'active' : '' }}"
       href="{{ route('teacher.results.index') }}">
        <i class="bi bi-bar-chart-line"></i> Results
        <span id="nav-badge-result"
              class="nav-badge ms-auto"
              style="display:{{ $resultBadge > 0 ? 'inline-flex' : 'none' }}">
            {{ $resultBadge > 99 ? '99+' : $resultBadge }}
        </span>
    </a>

    {{-- Notifications — global bell ('general' unread only) --}}
    <a class="nav-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}"
       href="{{ route('notifications.index') }}">
        <i class="bi bi-bell"></i> Notifications
        <span id="nav-badge-general"
              class="nav-badge ms-auto"
              style="display:{{ $generalBadge > 0 ? 'inline-flex' : 'none' }}">
            {{ $generalBadge > 99 ? '99+' : $generalBadge }}
        </span>
    </a>

</nav>

@once
@push('styles')
<style>
/* ── Per-nav badge (red pill) — shared with admin and student sidebars ── */
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
}

.nav-link {
    display: flex !important;
    align-items: center;
    gap: 0.5rem;
}
</style>
@endpush
@endonce
