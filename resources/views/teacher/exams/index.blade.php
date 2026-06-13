@extends('layouts.app')
@section('title', 'My Exams')
@section('page-title', 'My Exams')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Teacher', 'url' => route('teacher.dashboard')],
        ['label' => 'My Exams'],
    ]])
@endsection
@section('sidebar')
<nav class="nav flex-column gap-1">
    <a class="nav-link {{ request()->routeIs('teacher.dashboard') ? 'active' : '' }}" href="{{ route('teacher.dashboard') }}"><i class="bi bi-speedometer2"></i> Dashboard</a>
    <a class="nav-link {{ request()->routeIs('teacher.exams.index') || request()->routeIs('teacher.exams.show') ? 'active' : '' }}" href="{{ route('teacher.exams.index') }}"><i class="bi bi-file-earmark-text"></i> My Exams</a>
    <a class="nav-link {{ request()->routeIs('teacher.exams.create') ? 'active' : '' }}" href="{{ route('teacher.exams.create') }}"><i class="bi bi-plus-circle"></i> Create Exam</a>
    <a class="nav-link {{ request()->routeIs('chat.*') ? 'active' : '' }}" href="{{ route('chat.index') }}"><i class="bi bi-chat-dots"></i> Chat</a>
    <a class="nav-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}" href="{{ route('notifications.index') }}"><i class="bi bi-bell"></i> Notifications</a>
</nav>

@endsection
@section('content')
<div class="page-header">
    <div></div>
    <a href="{{ route('teacher.exams.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i> New Exam
    </a>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-file-earmark-text me-2"></i>All Exams</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table datatable mb-0">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Course</th>
                        <th>Questions</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($exams as $e)
                    <tr>
                        <td><span class="fw-600" style="font-weight:600;color:var(--blc-navy)">{{ $e->title }}</span></td>
                        <td class="text-muted">{{ $e->course->title }}</td>
                        <td>
                            <span class="badge" style="background:#f0f4ff;color:var(--blc-navy-2)">
                                {{ $e->questions_count ?? '—' }}
                            </span>
                        </td>
                        <td>
                            <span class="status-pill status-{{ $e->status === 'pending_approval' ? 'pending' : $e->status }}">
                                {{ ucfirst(str_replace('_', ' ', $e->status)) }}
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('teacher.exams.show', $e) }}" class="btn btn-sm btn-primary">
                                <i class="bi bi-arrow-right me-1"></i>Open
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="bi bi-file-earmark-x d-block mb-2" style="font-size:2rem"></i>
                            No exams yet. <a href="{{ route('teacher.exams.create') }}">Create your first exam</a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
