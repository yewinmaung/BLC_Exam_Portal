@extends('layouts.app')
@section('title', 'My Courses')
@section('page-title', 'My Courses')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Student', 'url' => route('student.dashboard')],
        ['label' => 'Courses'],
    ]])
@endsection
@section('sidebar')
<nav class="nav flex-column gap-1">
    <a class="nav-link" href="{{ route('student.dashboard') }}"><i class="bi bi-speedometer2"></i> Dashboard</a>
    <a class="nav-link active" href="{{ route('student.courses.index') }}"><i class="bi bi-book"></i> My Courses</a>
    <a class="nav-link" href="{{ route('student.exams.index') }}"><i class="bi bi-pencil-square"></i> Exams</a>
    <a class="nav-link" href="{{ route('student.reattempts.index') }}"><i class="bi bi-arrow-repeat"></i> Re-attempts</a>
    <a class="nav-link" href="{{ route('chat.index') }}"><i class="bi bi-chat-dots"></i> Chat</a>
    <a class="nav-link" href="{{ route('notifications.index') }}"><i class="bi bi-bell"></i> Notifications</a>
</nav>

@endsection

@section('content')
<div class="row g-3">
    @forelse($courses as $e)
    <div class="col-md-6 col-xl-4">
        <div class="card h-100" style="transition:transform 0.18s,box-shadow 0.18s"
             onmouseover="this.style.transform='translateY(-3px)'"
             onmouseout="this.style.transform=''">
            <div class="card-body d-flex flex-column">
                {{-- Year level badge --}}
                @php $yl = $e->course->year_level ?? 0; @endphp
                <div class="d-flex align-items-center justify-content-between mb-2">
                    @if($yl > 0)
                    <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3);font-size:0.72rem;font-weight:700">
                        {{ \App\Models\Course::$yearLevelLabels[$yl] ?? 'Year '.$yl }}
                    </span>
                    @else
                    <span class="badge" style="background:#f1f5f9;color:#64748b;font-size:0.72rem">All Years</span>
                    @endif
                    @if($e->course->is_active)
                    <span class="status-pill status-approved" style="font-size:0.68rem">Active</span>
                    @endif
                </div>

                <h6 style="font-weight:700;color:var(--text-1,#111827);margin-bottom:0.25rem">
                    {{ $e->course->title }}
                </h6>
                <div class="text-muted small mb-2"><i class="bi bi-tag me-1"></i>{{ $e->course->code }}</div>

                @if($e->course->teacher)
                <div class="d-flex align-items-center gap-2 mt-auto pt-2 border-top">
                    <div style="width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,#1e1b6e,#3730a3);color:#fff;display:flex;align-items:center;justify-content:center;font-size:0.65rem;font-weight:700;flex-shrink:0">
                        {{ strtoupper(substr($e->course->teacher->name,0,1)) }}
                    </div>
                    <span style="font-size:0.78rem;color:#6b7280">{{ $e->course->teacher->name }}</span>
                </div>
                @endif
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center py-5 text-muted">
                <i class="bi bi-book d-block mb-3" style="font-size:3rem;opacity:0.3"></i>
                <h6>No courses enrolled yet</h6>
                <p class="small mb-0">Ask your admin to enroll you in courses for your year level.</p>
            </div>
        </div>
    </div>
    @endforelse
</div>

@if($courses->hasPages())
<div class="mt-3">{{ $courses->links() }}</div>
@endif
@endsection
