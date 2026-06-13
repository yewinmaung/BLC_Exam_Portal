<nav class="nav flex-column gap-1">

    {{-- Dashboard --}}
    <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
       href="{{ route('admin.dashboard') }}">
        <i class="bi bi-speedometer2"></i> Dashboard
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

    {{-- Courses --}}
    <a class="nav-link {{ request()->routeIs('admin.courses.*') ? 'active' : '' }}"
       href="{{ route('admin.courses.index') }}">
        <i class="bi bi-book"></i> Courses
    </a>

    {{-- Enrollments --}}
    <a class="nav-link {{ request()->routeIs('admin.enrollments.*') ? 'active' : '' }}"
       href="{{ route('admin.enrollments.index') }}">
        <i class="bi bi-person-check"></i> Enrollments
    </a>



    {{-- Exams --}}
    <a class="nav-link {{ request()->routeIs('admin.exams.*') ? 'active' : '' }}"
       href="{{ route('admin.exams.index') }}">
        <i class="bi bi-file-earmark-text"></i> Exams
    </a>

    {{-- Cheating Logs --}}
    <a class="nav-link {{ request()->routeIs('admin.cheating-logs') ? 'active' : '' }}"
       href="{{ route('admin.cheating-logs') }}">
        <i class="bi bi-shield-exclamation"></i> Cheating Logs
    </a>

    {{-- Re-Attempt Requests --}}
    <a class="nav-link {{ request()->routeIs('admin.reattempts.*') ? 'active' : '' }}"
       href="{{ route('admin.reattempts.index') }}">
        <i class="bi bi-arrow-clockwise"></i> Re-Attempts
    </a>

    {{-- Chat --}}
    <a class="nav-link {{ request()->routeIs('chat.*') ? 'active' : '' }}"
       href="{{ route('chat.index') }}">
        <i class="bi bi-chat-dots"></i> Chat
    </a>

    {{-- Notifications --}}
    <a class="nav-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}"
       href="{{ route('notifications.index') }}">
        <i class="bi bi-bell"></i> Notifications
    </a>

    {{-- Email Management --}}
    <div class="nav-section-label" style="font-size:0.68rem;font-weight:700;color:#9ca3af;padding:0.6rem 0.85rem 0.2rem;text-transform:uppercase;letter-spacing:0.07em">Email</div>
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
        <i class="bi bi-send"></i> Test Email
    </a>
    <a class="nav-link {{ request()->routeIs('admin.email.smtp*') ? 'active' : '' }}"
       href="{{ route('admin.email.smtp') }}">
        <i class="bi bi-gear"></i> SMTP Settings
    </a>


</nav>

