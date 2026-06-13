@extends('layouts.app')
@section('title', 'Chat')
@section('page-title', 'Live Chat')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => ucfirst(auth()->user()->role->slug ?? 'User')],
        ['label' => 'Chat'],
    ]])
@endsection

@section('sidebar')
@php $role = auth()->user()->role->slug ?? 'student'; @endphp
@if($role === 'admin')
    @include('partials.admin-sidebar')
@elseif($role === 'teacher')
<nav class="nav flex-column gap-1">
    <a class="nav-link" href="{{ route('teacher.dashboard') }}"><i class="bi bi-speedometer2"></i> Dashboard</a>
    <a class="nav-link" href="{{ route('teacher.exams.index') }}"><i class="bi bi-file-earmark-text"></i> My Exams</a>
    <a class="nav-link active" href="{{ route('chat.index') }}"><i class="bi bi-chat-dots"></i> Chat</a>
    <a class="nav-link" href="{{ route('notifications.index') }}"><i class="bi bi-bell"></i> Notifications</a>
</nav>
@else
<nav class="nav flex-column gap-1">
    <a class="nav-link" href="{{ route('student.dashboard') }}"><i class="bi bi-speedometer2"></i> Dashboard</a>
    <a class="nav-link" href="{{ route('student.courses.index') }}"><i class="bi bi-book"></i> My Courses</a>
    <a class="nav-link" href="{{ route('student.exams.index') }}"><i class="bi bi-pencil-square"></i> Exams</a>
    <a class="nav-link {{ request()->routeIs('student.reattempts.*') ? 'active' : '' }}" href="{{ route('student.reattempts.index') }}"><i class="bi bi-arrow-repeat"></i> Re-attempts</a>
    <a class="nav-link active" href="{{ route('chat.index') }}"><i class="bi bi-chat-dots"></i> Chat</a>
    <a class="nav-link" href="{{ route('notifications.index') }}"><i class="bi bi-bell"></i> Notifications</a>
</nav>
@endif

@endsection

@section('content')
<div class="chat-layout">

    {{-- Contacts panel --}}
    <div class="chat-contacts-panel">
        <div class="chat-contacts-header">
            <i class="bi bi-people me-2"></i>Contacts
            <span class="badge ms-auto" style="background:var(--blc-royal-light,#eef2ff);color:var(--blc-royal,#2d27a0)">
                {{ $contacts->count() }}
            </span>
        </div>
        <div class="chat-contacts-search">
            <input type="text" id="contactSearch" class="form-control form-control-sm"
                   placeholder="Search contacts..." autocomplete="off">
        </div>
        <div class="chat-contacts-list" id="contactsList">
            @forelse($contacts as $c)
            <a href="{{ route('chat.conversation', $c) }}" class="chat-contact-item">
                <div class="chat-contact-avatar">{{ strtoupper(substr($c->name, 0, 1)) }}</div>
                <div class="chat-contact-info">
                    <div class="chat-contact-name">{{ $c->name }}</div>
                    <div class="chat-contact-role">{{ ucfirst($c->role->slug ?? '') }}</div>
                </div>
            </a>
            @empty
            <div class="text-center py-4 text-muted small">No contacts available</div>
            @endforelse
        </div>
    </div>

    {{-- Empty state --}}
    <div class="chat-main-empty">
        <i class="bi bi-chat-dots" style="font-size:3rem;opacity:0.2;color:var(--blc-royal,#2d27a0)"></i>
        <h6 class="mt-3 mb-1" style="color:#374151">Select a contact</h6>
        <p class="text-muted small mb-0">Choose someone from the list to start chatting</p>
    </div>

</div>
@endsection

@push('styles')
<style>
.chat-layout {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 0;
    height: calc(100vh - var(--topbar-h, 60px) - 3.2rem);
    min-height: 500px;
    background: var(--surface, #fff);
    border-radius: var(--radius, 13px);
    border: 1px solid var(--border-2, #e4e5f0);
    overflow: hidden;
    box-shadow: var(--shadow-sm, 0 2px 10px rgba(45,39,160,0.08));
}
.chat-contacts-panel {
    border-right: 1px solid var(--border-2, #e4e5f0);
    display: flex; flex-direction: column;
    background: var(--surface, #fff);
}
.chat-contacts-header {
    padding: 1rem 1.1rem;
    font-weight: 700; font-size: 0.875rem;
    color: var(--blc-royal-dark, #1e1b6e);
    border-bottom: 1px solid var(--border-2, #e4e5f0);
    display: flex; align-items: center;
}
.chat-contacts-search { padding: 0.65rem 0.85rem; border-bottom: 1px solid var(--border-2, #e4e5f0); }
.chat-contacts-list { flex: 1; overflow-y: auto; }
.chat-contact-item {
    display: flex; align-items: center; gap: 0.7rem;
    padding: 0.75rem 1rem;
    text-decoration: none; color: inherit;
    border-bottom: 1px solid var(--border-2, #e4e5f0);
    transition: background 0.13s;
}
.chat-contact-item:hover { background: var(--blc-royal-light, #eef2ff); }
.chat-contact-item.active { background: var(--blc-royal-light, #eef2ff); }
.chat-contact-avatar {
    width: 38px; height: 38px; border-radius: 50%;
    background: linear-gradient(135deg, var(--blc-royal-dark,#1e1b6e), var(--blc-royal,#2d27a0));
    color: #fff; display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem; font-weight: 700; flex-shrink: 0;
}
.chat-contact-name { font-size: 0.855rem; font-weight: 600; color: #1f2937; }
.chat-contact-role { font-size: 0.72rem; color: #9ca3af; }
.chat-main-empty {
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    text-align: center;
}
[data-theme="dark"] .chat-layout { background: var(--surface); border-color: var(--border-2); }
[data-theme="dark"] .chat-contacts-panel { background: var(--surface); border-color: var(--border-2); }
[data-theme="dark"] .chat-contacts-header { color: #d0d0f0; border-color: var(--border-2); }
[data-theme="dark"] .chat-contact-item { border-color: var(--border-2); }
[data-theme="dark"] .chat-contact-item:hover { background: var(--surface-2); }
[data-theme="dark"] .chat-contact-name { color: #e8e8f4; }
@media (max-width: 768px) {
    .chat-layout { grid-template-columns: 1fr; }
    .chat-main-empty { display: none; }
}
</style>
@endpush

@push('scripts')
<script>
document.getElementById('contactSearch')?.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.chat-contact-item').forEach(item => {
        const name = item.querySelector('.chat-contact-name')?.textContent.toLowerCase() || '';
        item.style.display = name.includes(q) ? '' : 'none';
    });
});
</script>
@endpush
