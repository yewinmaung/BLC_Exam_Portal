@extends('layouts.app')
@section('title', 'Chat — '.$user->name)
@section('page-title', 'Live Chat')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => ucfirst(auth()->user()->role->slug ?? 'User')],
        ['label' => 'Chat', 'url' => route('chat.index')],
        ['label' => $user->name],
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
    <a class="nav-link active" href="{{ route('chat.index') }}"><i class="bi bi-chat-dots"></i> Chat</a>
    <a class="nav-link" href="{{ route('notifications.index') }}"><i class="bi bi-bell"></i> Notifications</a>
</nav>
@endif

@endsection

@section('content')
<div class="chat-layout">

    {{-- Contacts sidebar --}}
    <div class="chat-contacts-panel">
        <div class="chat-contacts-header">
            <i class="bi bi-people me-2"></i>Contacts
        </div>
        <div class="chat-contacts-search">
            <input type="text" id="contactSearch" class="form-control form-control-sm"
                   placeholder="Search..." autocomplete="off">
        </div>
        <div class="chat-contacts-list">
            @foreach($contacts as $c)
            <a href="{{ route('chat.conversation', $c) }}"
               class="chat-contact-item {{ $c->id === $user->id ? 'active' : '' }}">
                <div class="chat-contact-avatar">{{ strtoupper(substr($c->name, 0, 1)) }}</div>
                <div class="chat-contact-info">
                    <div class="chat-contact-name">{{ $c->name }}</div>
                    <div class="chat-contact-role">{{ ucfirst($c->role->slug ?? '') }}</div>
                </div>
                @if($c->id === $user->id)
                <i class="bi bi-chevron-right ms-auto" style="font-size:0.75rem;color:var(--blc-royal,#2d27a0)"></i>
                @endif
            </a>
            @endforeach
        </div>
    </div>

    {{-- Conversation area --}}
    <div class="chat-main">

        {{-- Chat header --}}
        <div class="chat-header">
            <div class="chat-contact-avatar" style="width:36px;height:36px;font-size:0.8rem">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>
            <div>
                <div style="font-weight:700;font-size:0.9rem;color:#1f2937">{{ $user->name }}</div>
                <div style="font-size:0.72rem;color:#9ca3af">{{ ucfirst($user->role->slug ?? '') }}</div>
            </div>
        </div>

        {{-- Messages --}}
        <div class="chat-messages" id="chatBox">
            @forelse($messages as $m)
            @php
                $isMine   = $m->sender_id === auth()->id();
                $fileUrl  = $m->file_path ? url('storage/' . $m->file_path) : null;
                $ext      = $m->file_path ? strtolower(pathinfo($m->file_path, PATHINFO_EXTENSION)) : null;
                $isImage  = in_array($ext, ['jpg','jpeg','png','gif','webp']);
            @endphp
            <div class="chat-msg-wrap {{ $isMine ? 'mine' : 'theirs' }}">
                @if(!$isMine)
                <div class="chat-msg-avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
                @endif
                <div class="chat-bubble {{ $isMine ? 'bubble-mine' : 'bubble-theirs' }}">
                    @if($fileUrl)
                        @if($isImage)
                            <a href="{{ $fileUrl }}" target="_blank" class="chat-img-link">
                                <img src="{{ $fileUrl }}" class="chat-img-preview" alt="image">
                            </a>
                        @else
                            <a href="{{ $fileUrl }}" target="_blank" class="chat-file-link {{ $isMine ? 'mine' : '' }}">
                                <i class="bi bi-file-earmark-fill me-1"></i>
                                {{ basename($m->file_path) }}
                            </a>
                        @endif
                    @endif
                    @if($m->message)
                        <div class="{{ $fileUrl ? 'mt-1' : '' }}">{{ $m->message }}</div>
                    @endif
                    <div class="chat-msg-time">{{ $m->created_at->format('H:i') }}</div>
                </div>
            </div>
            @empty
            <div class="chat-empty-state">
                <i class="bi bi-chat-dots" style="font-size:2.5rem;opacity:0.2"></i>
                <p class="mt-2 mb-0 small text-muted">No messages yet. Say hello!</p>
            </div>
            @endforelse
        </div>

        {{-- Input area --}}
        <div class="chat-input-area">
            {{-- File preview strip --}}
            <div id="filePreviewStrip" class="file-preview-strip d-none">
                <div id="filePreviewContent"></div>
                <button type="button" id="clearFile" class="clear-file-btn" title="Remove">
                    <i class="bi bi-x-circle-fill"></i>
                </button>
            </div>
            <form id="chatForm" action="{{ route('chat.send', $user) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="file" id="fileInput" name="file"
                       accept="image/*,.pdf,.doc,.docx,.txt" style="display:none">
                <div class="chat-input-wrap">
                    <button type="button" id="attachBtn" class="attach-btn" title="Attach file or image">
                        <i class="bi bi-paperclip"></i>
                    </button>
                    <input type="text" name="message" id="chatInput"
                           class="chat-input" placeholder="Type a message..."
                           autocomplete="off">
                    <button type="submit" class="chat-send-btn" id="sendBtn">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>
@endsection

@push('styles')
<style>
.chat-layout {
    display: grid;
    grid-template-columns: 260px 1fr;
    gap: 0;
    height: calc(100vh - var(--topbar-h, 60px) - 3.2rem);
    min-height: 500px;
    background: var(--surface, #fff);
    border-radius: var(--radius, 13px);
    border: 1px solid var(--border-2, #e4e5f0);
    overflow: hidden;
    box-shadow: var(--shadow-sm, 0 2px 10px rgba(45,39,160,0.08));
}

/* Contacts panel */
.chat-contacts-panel {
    border-right: 1px solid var(--border-2, #e4e5f0);
    display: flex; flex-direction: column;
    background: var(--surface, #fff);
}
.chat-contacts-header {
    padding: 0.9rem 1rem;
    font-weight: 700; font-size: 0.855rem;
    color: #1f2937;
    border-bottom: 1px solid var(--border-2, #e4e5f0);
    display: flex; align-items: center;
}
.chat-contacts-search { padding: 0.6rem 0.75rem; border-bottom: 1px solid var(--border-2, #e4e5f0); }
.chat-contacts-list { flex: 1; overflow-y: auto; }
.chat-contact-item {
    display: flex; align-items: center; gap: 0.65rem;
    padding: 0.7rem 0.9rem;
    text-decoration: none; color: inherit;
    border-bottom: 1px solid var(--border-2, #e4e5f0);
    transition: background 0.13s;
}
.chat-contact-item:hover { background: var(--blc-royal-light, #eef2ff); }
.chat-contact-item.active { background: var(--blc-royal-light, #eef2ff); border-left: 3px solid var(--blc-royal, #2d27a0); }
.chat-contact-avatar {
    width: 38px; height: 38px; border-radius: 50%;
    background: linear-gradient(135deg, var(--blc-royal-dark,#1e1b6e), var(--blc-royal,#2d27a0));
    color: #fff; display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem; font-weight: 700; flex-shrink: 0;
}
.chat-contact-name { font-size: 0.845rem; font-weight: 600; color: #1f2937; }
.chat-contact-role { font-size: 0.7rem; color: #9ca3af; }

/* Main chat area */
.chat-main { display: flex; flex-direction: column; min-height: 0; }

.chat-header {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 0.85rem 1.1rem;
    border-bottom: 1px solid var(--border-2, #e4e5f0);
    background: var(--surface, #fff);
    flex-shrink: 0;
}

/* Messages */
.chat-messages {
    flex: 1; overflow-y: auto;
    padding: 1rem 1.1rem;
    display: flex; flex-direction: column; gap: 0.6rem;
    background: var(--bg, #f5f6fa);
}
.chat-empty-state {
    flex: 1; display: flex; flex-direction: column;
    align-items: center; justify-content: center; text-align: center;
}
.chat-msg-wrap {
    display: flex; align-items: flex-end; gap: 0.5rem;
    max-width: 72%;
}
.chat-msg-wrap.mine { align-self: flex-end; flex-direction: row-reverse; }
.chat-msg-wrap.theirs { align-self: flex-start; }
.chat-msg-avatar {
    width: 28px; height: 28px; border-radius: 50%;
    background: linear-gradient(135deg, #6b7280, #9ca3af);
    color: #fff; display: flex; align-items: center; justify-content: center;
    font-size: 0.7rem; font-weight: 700; flex-shrink: 0;
}
.chat-bubble {
    padding: 0.6rem 0.9rem;
    border-radius: 14px;
    font-size: 0.875rem; line-height: 1.5;
    max-width: 100%; word-break: break-word;
    position: relative;
}
.bubble-mine {
    background: var(--blc-royal, #2d27a0);
    color: #fff;
    border-bottom-right-radius: 4px;
}
.bubble-theirs {
    background: var(--surface, #fff);
    color: #1f2937;
    border: 1px solid var(--border-2, #e4e5f0);
    border-bottom-left-radius: 4px;
}
.chat-msg-time {
    font-size: 0.65rem;
    opacity: 0.65;
    margin-top: 0.2rem;
    text-align: right;
}
.bubble-theirs .chat-msg-time { text-align: left; }

/* Input */
.chat-input-area {
    padding: 0.6rem 1rem 0.75rem;
    border-top: 1px solid var(--border-2, #e4e5f0);
    background: var(--surface, #fff);
    flex-shrink: 0;
}
.chat-input-wrap {
    display: flex; align-items: center; gap: 0.4rem;
    background: var(--bg, #f5f6fa);
    border: 1.5px solid var(--border-2, #e4e5f0);
    border-radius: 50px;
    padding: 0.3rem 0.3rem 0.3rem 0.5rem;
    transition: border-color 0.15s;
}
.chat-input-wrap:focus-within { border-color: var(--blc-royal, #2d27a0); }
.chat-input {
    flex: 1; border: none; background: transparent;
    font-size: 0.875rem; outline: none;
    font-family: 'Inter', sans-serif;
    color: var(--text-primary, #1f2937);
    padding: 0.3rem 0.4rem;
}
.chat-input::placeholder { color: #9ca3af; }

/* Attach button */
.attach-btn {
    width: 32px; height: 32px; border-radius: 50%;
    background: none; border: none; cursor: pointer;
    color: #9ca3af; font-size: 1rem;
    display: flex; align-items: center; justify-content: center;
    transition: color 0.15s, background 0.15s; flex-shrink: 0;
}
.attach-btn:hover { color: var(--blc-royal, #2d27a0); background: var(--blc-royal-light, #eef2ff); }

.chat-send-btn {
    width: 36px; height: 36px; border-radius: 50%;
    background: var(--blc-royal, #2d27a0);
    color: #fff; border: none; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.9rem; flex-shrink: 0;
    transition: background 0.15s;
}
.chat-send-btn:hover { background: var(--blc-royal-dark, #1e1b6e); }

/* File preview strip */
.file-preview-strip {
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    background: var(--blc-royal-light, #eef2ff);
    border-radius: 10px; margin-bottom: 0.5rem;
    border: 1px solid rgba(45,39,160,0.15);
}
.file-preview-strip img {
    height: 48px; width: 48px; object-fit: cover;
    border-radius: 6px; border: 1px solid rgba(45,39,160,0.15);
}
.file-preview-name {
    font-size: 0.78rem; font-weight: 600;
    color: var(--blc-royal, #2d27a0); flex: 1;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.clear-file-btn {
    background: none; border: none; cursor: pointer;
    color: #9ca3af; font-size: 1.1rem; padding: 0;
    transition: color 0.15s; flex-shrink: 0;
}
.clear-file-btn:hover { color: #ef4444; }

/* Image in bubble */
.chat-img-preview {
    max-width: 220px; max-height: 200px;
    border-radius: 10px; display: block;
    cursor: pointer; object-fit: cover;
    border: 1px solid rgba(255,255,255,0.2);
}
.chat-img-link { display: block; }

/* File link in bubble */
.chat-file-link {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.4rem 0.75rem;
    background: rgba(255,255,255,0.15);
    border-radius: 8px; font-size: 0.8rem; font-weight: 600;
    text-decoration: none; color: #fff;
    border: 1px solid rgba(255,255,255,0.25);
    transition: background 0.15s;
}
.chat-file-link:hover { background: rgba(255,255,255,0.25); color: #fff; }
.chat-file-link.mine { color: #fff; }
.bubble-theirs .chat-file-link {
    background: rgba(45,39,160,0.08);
    color: var(--blc-royal, #2d27a0);
    border-color: rgba(45,39,160,0.15);
}
.bubble-theirs .chat-file-link:hover { background: rgba(45,39,160,0.14); color: var(--blc-royal, #2d27a0); }

/* Dark mode */
[data-theme="dark"] .chat-layout { background: var(--surface); border-color: var(--border-2); }
[data-theme="dark"] .chat-contacts-panel { background: var(--surface); border-color: var(--border-2); }
[data-theme="dark"] .chat-contacts-header { color: #d0d0f0; border-color: var(--border-2); }
[data-theme="dark"] .chat-contact-item { border-color: var(--border-2); }
[data-theme="dark"] .chat-contact-item:hover,
[data-theme="dark"] .chat-contact-item.active { background: var(--surface-2); }
[data-theme="dark"] .chat-contact-name { color: #e8e8f4; }
[data-theme="dark"] .chat-header { background: var(--surface); border-color: var(--border-2); }
[data-theme="dark"] .chat-messages { background: var(--bg); }
[data-theme="dark"] .bubble-theirs { background: var(--surface-2); color: #e8e8f4; border-color: var(--border-2); }
[data-theme="dark"] .chat-input-area { background: var(--surface); border-color: var(--border-2); }
[data-theme="dark"] .chat-input-wrap { background: var(--surface-2); border-color: var(--border-2); }
[data-theme="dark"] .chat-input { color: #e8e8f4; }

@media (max-width: 768px) {
    .chat-layout { grid-template-columns: 1fr; }
    .chat-contacts-panel { display: none; }
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    const chatBox    = document.getElementById('chatBox');
    const chatForm   = document.getElementById('chatForm');
    const chatInput  = document.getElementById('chatInput');
    const fileInput  = document.getElementById('fileInput');
    const attachBtn  = document.getElementById('attachBtn');
    const previewStrip   = document.getElementById('filePreviewStrip');
    const previewContent = document.getElementById('filePreviewContent');
    const clearFileBtn   = document.getElementById('clearFile');
    const csrf       = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const pollUrl    = "{{ route('chat.poll', $user) }}";
    const myId       = {{ auth()->id() }};

    let lastId = {{ $messages->last()?->id ?? 0 }};

    // ── Scroll to bottom ──────────────────────────────────────────
    function scrollBottom() { chatBox.scrollTop = chatBox.scrollHeight; }
    scrollBottom();

    // ── File attachment ───────────────────────────────────────────
    attachBtn.addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;

        previewContent.innerHTML = '';
        if (file.type.startsWith('image/')) {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            previewContent.appendChild(img);
        } else {
            const name = document.createElement('span');
            name.className = 'file-preview-name';
            name.innerHTML = `<i class="bi bi-file-earmark me-1"></i>${escHtml(file.name)}`;
            previewContent.appendChild(name);
        }
        previewStrip.classList.remove('d-none');
        chatInput.removeAttribute('required');
    });

    clearFileBtn.addEventListener('click', () => {
        fileInput.value = '';
        previewStrip.classList.add('d-none');
        previewContent.innerHTML = '';
    });

    // ── Render a message bubble ───────────────────────────────────
    function renderMessage(m, isMine) {
        const wrap = document.createElement('div');
        wrap.className = 'chat-msg-wrap ' + (isMine ? 'mine' : 'theirs');

        const time = m.created_at
            ? new Date(m.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})
            : new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});

        if (!isMine) {
            const av = document.createElement('div');
            av.className = 'chat-msg-avatar';
            av.textContent = "{{ strtoupper(substr($user->name, 0, 1)) }}";
            wrap.appendChild(av);
        }

        const bubble = document.createElement('div');
        bubble.className = 'chat-bubble ' + (isMine ? 'bubble-mine' : 'bubble-theirs');

        let inner = '';

        // File / image attachment
        if (m.file_url) {
            if (m.file_type === 'image') {
                inner += `<a href="${m.file_url}" target="_blank" class="chat-img-link">
                    <img src="${m.file_url}" class="chat-img-preview" alt="image">
                </a>`;
            } else {
                const cls = isMine ? 'mine' : '';
                inner += `<a href="${m.file_url}" target="_blank" class="chat-file-link ${cls}">
                    <i class="bi bi-file-earmark-fill"></i>
                    ${escHtml(m.file_name || 'File')}
                </a>`;
            }
        }

        // Text message
        if (m.message) {
            inner += `<div${m.file_url ? ' class="mt-1"' : ''}>${escHtml(m.message)}</div>`;
        }

        inner += `<div class="chat-msg-time">${time}</div>`;
        bubble.innerHTML = inner;
        wrap.appendChild(bubble);

        // Remove empty state
        const empty = chatBox.querySelector('.chat-empty-state');
        if (empty) empty.remove();

        chatBox.appendChild(wrap);
        scrollBottom();
    }

    function escHtml(str) {
        return String(str || '')
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Send message ──────────────────────────────────────────────
    chatForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const msg  = chatInput.value.trim();
        const file = fileInput.files[0];
        if (!msg && !file) return;

        const fd = new FormData(this);
        const sentMsg  = msg;
        const sentFile = file ? { name: file.name, type: file.type } : null;

        chatInput.value = '';
        fileInput.value = '';
        previewStrip.classList.add('d-none');
        previewContent.innerHTML = '';

        fetch(this.action, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: fd,
        })
        .then(r => r.json())
        .then(data => {
            if (data.id) lastId = Math.max(lastId, data.id);

            // Optimistic render
            renderMessage({
                message:   sentMsg,
                file_url:  data.file_path  || null,
                file_type: data.file_type  || null,
                file_name: data.file_name  || (sentFile?.name ?? null),
                created_at: new Date().toISOString(),
            }, true);
        })
        .catch(() => {
            chatInput.value = sentMsg;
        });
    });

    // ── Poll for new messages ─────────────────────────────────────
    setInterval(() => {
        fetch(pollUrl + '?after_id=' + lastId, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (data.messages?.length) {
                data.messages.forEach(m => {
                    if (m.sender_id !== myId) renderMessage(m, false);
                    if (m.id > lastId) lastId = m.id;
                });
            }
        })
        .catch(() => {});
    }, 3000);

    // ── Contact search ────────────────────────────────────────────
    document.getElementById('contactSearch')?.addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll('.chat-contact-item').forEach(item => {
            const name = item.querySelector('.chat-contact-name')?.textContent.toLowerCase() || '';
            item.style.display = name.includes(q) ? '' : 'none';
        });
    });
})();
</script>
@endpush
