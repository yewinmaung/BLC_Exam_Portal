@php
    /*
     * Per-category unread badge counts for the admin nav.
     * Reuses UserNotification::unreadCountsByCategory() — single GROUP BY query.
     * No new tables, no new columns, no changes to notification logic.
     *
     * Admin badge mapping:
     *   Exams nav     → 'exam' category  (exam_submitted, exam_approved, exam_published, …)
     *   Results       → 'result' category
     *   Cheating Logs → 'exam' category shares (security_warning, security_incident_high, cheating)
     */
    $adminBadges = auth()->check()
        ? \App\Models\UserNotification::unreadCountsByCategory(auth()->id())
        : ['exam' => 0, 'result' => 0, 'course' => 0, 'general' => 0];

    $examBadge    = $adminBadges['exam']    ?? 0;
    $resultBadge  = $adminBadges['result']  ?? 0;
    $courseBadge  = $adminBadges['course']  ?? 0;
    $generalBadge = $adminBadges['general'] ?? 0;
@endphp

<nav class="nav flex-column gap-1">

    {{-- Dashboard --}}
    <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
       href="{{ route('admin.dashboard') }}">
        <i class="bi bi-speedometer2"></i> Dashboard
    </a>

    {{-- My Profile --}}
    <a class="nav-link {{ request()->routeIs('profile.show') ? 'active' : '' }}"
       href="{{ route('profile.show') }}">
        <i class="bi bi-person-circle"></i> My Profile
    </a>

    {{-- Users --}}
    <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"
       href="{{ route('admin.users.index') }}">
        <i class="bi bi-people"></i> Users
    </a>

    {{-- Teachers --}}
    <a class="nav-link {{ request()->routeIs('admin.teachers.*') ? 'active' : '' }}"
       href="{{ route('admin.teachers.index') }}">
        <i class="bi bi-person-workspace"></i> Teachers
    </a>

    {{-- Academic Years --}}
    <a class="nav-link {{ request()->routeIs('admin.academic.years.*') ? 'active' : '' }}"
       href="{{ route('admin.academic.years.index') }}">
        <i class="bi bi-calendar3"></i> Academic Years
    </a>

    {{-- Students --}}
    <a class="nav-link {{ request()->routeIs('admin.students.*') ? 'active' : '' }}"
       href="{{ route('admin.students.index') }}">
        <i class="bi bi-mortarboard"></i> Students
    </a>

    {{-- Courses — badge: 'course' category (enrolled, course_updated …) --}}
    <a class="nav-link {{ request()->routeIs('admin.courses.*') ? 'active' : '' }}"
       href="{{ route('admin.courses.index') }}">
        <i class="bi bi-book"></i> Courses
        <span id="admin-badge-course"
              class="nav-badge ms-auto"
              style="display:{{ $courseBadge > 0 ? 'inline-flex' : 'none' }}">
            {{ $courseBadge > 99 ? '99+' : $courseBadge }}
        </span>
    </a>

    {{-- Majors --}}
    <a class="nav-link {{ request()->routeIs('admin.majors.*') ? 'active' : '' }}"
       href="{{ route('admin.majors.index') }}">
        <i class="bi bi-collection"></i> Majors
    </a>

    {{-- Enrollments --}}
    <a class="nav-link {{ request()->routeIs('admin.enrollments.*') ? 'active' : '' }}"
       href="{{ route('admin.enrollments.index') }}">
        <i class="bi bi-person-check"></i> Enrollments
    </a>

    {{-- ── EXAM SECTION HEADER ──────────────────────────────────────── --}}
    <div class="nav-section-label" style="font-size:0.68rem;font-weight:700;color:#9ca3af;padding:0.6rem 0.85rem 0.2rem;text-transform:uppercase;letter-spacing:0.07em">
        Exams
    </div>

    {{-- Exams — badge: 'exam' category (exam_submitted, approved, published, security …) --}}
    <a class="nav-link {{ request()->routeIs('admin.exams.*') ? 'active' : '' }}"
       href="{{ route('admin.exams.index') }}">
        <i class="bi bi-file-earmark-text"></i> Exams
        <span id="admin-badge-exam"
              class="nav-badge ms-auto"
              style="display:{{ $examBadge > 0 ? 'inline-flex' : 'none' }}">
            {{ $examBadge > 99 ? '99+' : $examBadge }}
        </span>
    </a>

    {{-- Cheating Logs — shares the 'exam' badge category (no separate counter needed) --}}
    <a class="nav-link {{ request()->routeIs('admin.cheating-logs') ? 'active' : '' }}"
       href="{{ route('admin.cheating-logs') }}">
        <i class="bi bi-shield-exclamation"></i> Cheating Logs
    </a>

    {{-- Results — badge: 'result' category --}}
    <a class="nav-link {{ request()->routeIs('admin.results.*') ? 'active' : '' }}"
       href="{{ route('admin.results.index') }}">
        <i class="bi bi-bar-chart-line"></i> Results
        <span id="admin-badge-result"
              class="nav-badge ms-auto"
              style="display:{{ $resultBadge > 0 ? 'inline-flex' : 'none' }}">
            {{ $resultBadge > 99 ? '99+' : $resultBadge }}
        </span>
    </a>

    {{-- Notifications — global bell ('general' unread only) --}}
    <a class="nav-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}"
       href="{{ route('notifications.index') }}">
        <i class="bi bi-bell"></i> Notifications
        <span id="admin-badge-general"
              class="nav-badge ms-auto"
              style="display:{{ $generalBadge > 0 ? 'inline-flex' : 'none' }}">
            {{ $generalBadge > 99 ? '99+' : $generalBadge }}
        </span>
    </a>

    {{-- ── EMAIL SECTION ────────────────────────────────────────────── --}}
    <div class="nav-section-label" style="font-size:0.68rem;font-weight:700;color:#9ca3af;padding:0.6rem 0.85rem 0.2rem;text-transform:uppercase;letter-spacing:0.07em">
        Email
    </div>

    <a class="nav-link {{ request()->routeIs('admin.email.index') ? 'active' : '' }}"
       href="{{ route('admin.email.index') }}">
        <i class="bi bi-envelope-paper"></i> Email Dashboard
    </a>
    <a class="nav-link {{ request()->routeIs('admin.email.templates*') ? 'active' : '' }}"
       href="{{ route('admin.email.templates') }}">
        <i class="bi bi-file-earmark-code"></i> Templates
    </a>
    <a class="nav-link {{ request()->routeIs('admin.email.logs*') ? 'active' : '' }}"
       href="{{ route('admin.email.logs') }}">
        <i class="bi bi-journal-text"></i> Email Logs
    </a>
    <a class="nav-link {{ request()->routeIs('admin.email.bulk*') ? 'active' : '' }}"
       href="{{ route('admin.email.bulk') }}">
        <i class="bi bi-send-check"></i> Bulk Email
    </a>
    <a class="nav-link {{ request()->routeIs('admin.email.scheduled*') ? 'active' : '' }}"
       href="{{ route('admin.email.scheduled') }}">
        <i class="bi bi-calendar-check"></i> Scheduled
    </a>
    <a class="nav-link {{ request()->routeIs('admin.email.test*') ? 'active' : '' }}"
       href="{{ route('admin.email.test') }}">
        <i class="bi bi-send"></i> Individual Email
    </a>
    <a class="nav-link {{ request()->routeIs('admin.email.smtp*') ? 'active' : '' }}"
       href="{{ route('admin.email.smtp') }}">
        <i class="bi bi-gear"></i> SMTP Settings
    </a>

</nav>

@once
@push('styles')
<style>
/* ── Per-nav badge (red pill) — shared with student sidebar ─────────── */
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
