@extends('layouts.app')
@section('title', 'Notifications')
@section('page-title', 'Notifications')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => ucfirst(auth()->user()->role->slug ?? 'User')],
        ['label' => 'Notifications'],
    ]])
@endsection

@section('sidebar')
@php $role = auth()->user()->role->slug ?? 'student'; @endphp
@if($role === 'admin')
    @include('partials.admin-sidebar')
@elseif($role === 'teacher')
@include('partials.teacher-sidebar')

@else
@include('partials.student-sidebar')

@endif

@endsection

@section('content')

@php
$typeConfig = [
    'exam_submitted' => ['icon' => 'bi-file-earmark-arrow-up', 'bg' => '#fef9c3', 'color' => '#854d0e'],
    'exam_published' => ['icon' => 'bi-broadcast',              'bg' => '#dbeafe', 'color' => '#1d4ed8'],
    'exam_approved'  => ['icon' => 'bi-check-circle-fill',      'bg' => '#dcfce7', 'color' => '#166534'],
    'question_added' => ['icon' => 'bi-patch-plus-fill',        'bg' => '#f3e8ff', 'color' => '#7c3aed'],
    'default'        => ['icon' => 'bi-bell-fill',              'bg' => '#f1f5f9', 'color' => '#64748b'],
];
$unreadCount = $notifications->where('is_read', false)->count();
@endphp

{{-- Header --}}
<div class="page-header mb-4">
    <div class="d-flex align-items-center gap-3">
        <h4 class="mb-0">Notifications</h4>
        @if($unreadCount > 0)
        <span class="badge rounded-pill"
              style="background:#dc2626;color:#fff;font-size:0.75rem;padding:0.3em 0.7em">
            {{ $unreadCount }} unread
        </span>
        @endif
    </div>
    @if($unreadCount > 0)
    <form method="POST" action="{{ route('notifications.read-all') }}">@csrf
        <button class="btn btn-outline-primary btn-sm">
            <i class="bi bi-check2-all me-1"></i> Mark all as read
        </button>
    </form>
    @endif
</div>

{{-- Notification list --}}
<div class="card">
    <div class="card-body p-0">
        @forelse($notifications as $n)
        @php
            $cfg = $typeConfig[$n->type] ?? $typeConfig['default'];
        @endphp
        <div class="notif-row {{ $n->is_read ? '' : 'notif-row-unread' }}">

            {{-- Icon --}}
            <div class="notif-row-icon"
                 style="background:{{ $cfg['bg'] }};color:{{ $cfg['color'] }}">
                <i class="bi {{ $cfg['icon'] }}"></i>
            </div>

            {{-- Content --}}
            <div class="notif-row-body">
                <div class="notif-row-title">
                    {{ $n->title }}
                    @if(!$n->is_read)
                    <span class="notif-row-dot"></span>
                    @endif
                </div>
                <div class="notif-row-msg">{{ $n->message }}</div>
                <div class="notif-row-time">
                    <i class="bi bi-clock me-1"></i>{{ $n->created_at->diffForHumans() }}
                </div>
            </div>

            {{-- Actions --}}
            <div class="notif-row-actions">
                @if($n->link)
                <a href="{{ $n->link }}"
                   class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-arrow-right"></i>
                </a>
                @endif
                @if(!$n->is_read)
                <form method="POST" action="{{ route('notifications.read', $n) }}">@csrf
                    <button class="btn btn-sm btn-outline-secondary" title="Mark as read">
                        <i class="bi bi-check2"></i>
                    </button>
                </form>
                @endif
            </div>
        </div>
        @empty
        <div class="text-center py-5 text-muted">
            <i class="bi bi-bell-slash d-block mb-3" style="font-size:3rem;opacity:0.3"></i>
            <h6 class="mb-1">No notifications yet</h6>
            <p class="small mb-0">You'll see updates here when something happens.</p>
        </div>
        @endforelse
    </div>
</div>

@if($notifications->hasPages())
<div class="mt-3">{{ $notifications->links() }}</div>
@endif

@endsection

@push('styles')
<style>
.notif-row {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #f0f3fa;
    transition: background 0.15s;
}
.notif-row:last-child { border-bottom: none; }
.notif-row:hover { background: #f8faff; }
.notif-row-unread { background: #f0f4ff; }
.notif-row-unread:hover { background: #e8eeff; }

.notif-row-icon {
    width: 42px; height: 42px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.notif-row-body { flex: 1; min-width: 0; }

.notif-row-title {
    font-size: 0.875rem;
    font-weight: 700;
    color: #1a2540;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.2rem;
}

.notif-row-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    background: #0f3a7a;
    flex-shrink: 0;
}

.notif-row-msg {
    font-size: 0.82rem;
    color: #6b7280;
    line-height: 1.5;
    margin-bottom: 0.3rem;
}

.notif-row-time {
    font-size: 0.72rem;
    color: #9ca3af;
}

.notif-row-actions {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-shrink: 0;
}

[data-theme="dark"] .notif-row { border-color: rgba(255,255,255,0.05); }
[data-theme="dark"] .notif-row:hover { background: #162040; }
[data-theme="dark"] .notif-row-unread { background: #1a2a50; }
[data-theme="dark"] .notif-row-title { color: #e2e8f0; }
[data-theme="dark"] .notif-row-msg { color: #94a3b8; }
</style>
@endpush
