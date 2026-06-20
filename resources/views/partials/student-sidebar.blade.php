<nav class="nav flex-column gap-1">
    <a class="nav-link {{ request()->routeIs('student.dashboard') ? 'active' : '' }}" href="{{ route('student.dashboard') }}"><i class="bi bi-speedometer2"></i> Dashboard</a>
    <a class="nav-link {{ request()->routeIs('student.courses.*') ? 'active' : '' }}" href="{{ route('student.courses.index') }}"><i class="bi bi-book"></i> My Courses</a>
    <a class="nav-link {{ request()->routeIs('student.exams.*') || request()->routeIs('student.exam.*') ? 'active' : '' }}" href="{{ route('student.exams.index') }}"><i class="bi bi-pencil-square"></i> Exams</a>
    <a class="nav-link {{ request()->routeIs('student.results.*') ? 'active' : '' }}" href="{{ route('student.results.index') }}"><i class="bi bi-bar-chart-line"></i> My Results</a>
    <a class="nav-link {{ request()->routeIs('student.reattempts.*') ? 'active' : '' }}" href="{{ route('student.reattempts.index') }}"><i class="bi bi-arrow-repeat"></i> Re-attempts</a>
    <a class="nav-link {{ request()->routeIs('chat.*') ? 'active' : '' }}" href="{{ route('chat.index') }}"><i class="bi bi-chat-dots"></i> Chat</a>
    <a class="nav-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}" href="{{ route('notifications.index') }}"><i class="bi bi-bell"></i> Notifications</a>
</nav>