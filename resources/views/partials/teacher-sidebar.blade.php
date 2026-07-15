<nav class="nav flex-column gap-1">
    <a class="nav-link {{ request()->routeIs('teacher.dashboard') ? 'active' : '' }}" href="{{ route('teacher.dashboard') }}"><i class="bi bi-speedometer2"></i> Dashboard</a>
    <a class="nav-link {{ request()->routeIs('profile.show') || request()->routeIs('teacher.profile.*') ? 'active' : '' }}" href="{{ route('profile.show') }}"><i class="bi bi-person-badge"></i> My Profile</a>
    <a class="nav-link {{ request()->routeIs('teacher.exams.index') || request()->routeIs('teacher.exams.show') ? 'active' : '' }}" href="{{ route('teacher.exams.index') }}"><i class="bi bi-file-earmark-text"></i> My Exams</a>
    <a class="nav-link {{ request()->routeIs('teacher.exams.create') ? 'active' : '' }}" href="{{ route('teacher.exams.create') }}"><i class="bi bi-plus-circle"></i> Create Exam</a>
    <a class="nav-link {{ request()->routeIs('teacher.results.*') ? 'active' : '' }}" href="{{ route('teacher.results.index') }}"><i class="bi bi-bar-chart-line"></i> Results</a>
    <a class="nav-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}" href="{{ route('notifications.index') }}"><i class="bi bi-bell"></i> Notifications</a>
</nav>