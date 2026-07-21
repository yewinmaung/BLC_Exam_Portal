@extends('layouts.app')
@section('title', 'My Profile')
@section('page-title', 'My Profile')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'My Profile'],
    ]])
@endsection

@section('sidebar')
    @if($user->isAdmin())
        @include('partials.admin-sidebar')
    @elseif($user->isTeacher())
        @include('partials.teacher-sidebar')
    @else
        @include('partials.student-sidebar')
    @endif
@endsection

@push('styles')
<style>
/* ── Profile page layout ── */
.profile-page { max-width: 780px; margin: 0 auto; }

/* ── Avatar card ── */
.avatar-card {
    background: #fff; border-radius: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 12px rgba(11,42,91,0.06);
    padding: 2rem; text-align: center; margin-bottom: 1.5rem;
}
.avatar-wrap { position: relative; display: inline-block; margin-bottom: 1rem; }
.avatar-ring {
    width: 120px; height: 120px; border-radius: 50%;
    border: 3px solid var(--blc-royal, #2d27a0);
    overflow: hidden; background: #e8edf5;
    display: flex; align-items: center; justify-content: center;
}
.avatar-ring img { width: 100%; height: 100%; object-fit: cover; display: block; }
.avatar-initial {
    font-size: 2.8rem; font-weight: 800;
    color: var(--blc-royal, #2d27a0); user-select: none;
}
.avatar-edit-btn {
    position: absolute; bottom: 4px; right: 4px;
    width: 32px; height: 32px; border-radius: 50%;
    background: var(--blc-royal, #2d27a0); color: #fff;
    border: 2px solid #fff;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 0.8rem;
    transition: background 0.15s;
}
.avatar-edit-btn:hover { background: #1e1b6e; }
.avatar-name { font-size: 1.25rem; font-weight: 800; color: #1a2540; margin-bottom: 0.2rem; }
.avatar-email { font-size: 0.875rem; color: #6b7280; margin-bottom: 0.5rem; }
.avatar-role-badge {
    display: inline-block; font-size: 0.72rem; font-weight: 700;
    padding: 0.25rem 0.75rem; border-radius: 20px;
    background: #eef2ff; color: var(--blc-royal, #2d27a0);
    text-transform: capitalize; letter-spacing: 0.03em;
}

/* ── Section card ── */
.section-card {
    background: #fff; border-radius: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 12px rgba(11,42,91,0.06);
    margin-bottom: 1.5rem; overflow: hidden;
}
.section-card-header {
    background: linear-gradient(135deg, #071d40, #0b2a5b);
    color: #fff; padding: 0.9rem 1.25rem;
    font-size: 0.88rem; font-weight: 700;
    display: flex; align-items: center; gap: 0.5rem;
}
.section-card-body { padding: 1.5rem 1.25rem; }

/* Read-only field */
.ro-field { margin-bottom: 1rem; }
.ro-field label { font-size: 0.75rem; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 0.25rem; }
.ro-field .ro-value { font-size: 0.935rem; color: #1a2540; font-weight: 500; padding: 0.55rem 0.85rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; }

/* Password strength bar */
.pw-strength { height: 4px; border-radius: 2px; transition: width .3s, background .3s; margin-top: 6px; }
.pw-hint { font-size: 0.72rem; color: #9ca3af; margin-top: 4px; }

/* Password show/hide toggle button — matches login page style */
.pw-toggle-btn {
    position: absolute;
    right: 0.85rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #9ca3af;
    cursor: pointer;
    font-size: 1rem;
    padding: 0;
    line-height: 1;
    transition: color 0.15s;
}
.pw-toggle-btn:hover { color: var(--blc-royal, #2d27a0); }

/* Status messages inside the card */
.inline-alert { border-radius: 8px; padding: 0.65rem 1rem; font-size: 0.83rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.4rem; }
.inline-alert.success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
.inline-alert.error   { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
.inline-alert.info    { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }

/* Spinner */
.spinner-sm { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,.35); border-top-color: #fff; border-radius: 50%; animation: spin .6s linear infinite; display: inline-block; vertical-align: middle; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Cropper modal ── */
.cropper-modal-overlay {
    position: fixed; inset: 0; z-index: 9999;
    background: rgba(7,29,64,0.88);
    display: none; align-items: center; justify-content: center;
}
.cropper-modal-overlay.open { display: flex; }
.cropper-modal-box {
    background: #fff; border-radius: 20px;
    padding: 1.5rem; width: 92%; max-width: 500px;
    box-shadow: 0 32px 80px rgba(0,0,0,0.4);
}
.cropper-modal-title { font-size: 1rem; font-weight: 800; color: #1a2540; margin-bottom: 1rem; }

/* Canvas preview area */
#cropperStage {
    width: 100%; aspect-ratio: 1;
    overflow: hidden; border-radius: 12px;
    background: #111; position: relative;
    cursor: grab; user-select: none; touch-action: none;
}
#cropperStage:active { cursor: grabbing; }
#cropperImg {
    position: absolute; transform-origin: 0 0; pointer-events: none;
}
/* Semi-transparent mask that reveals a centred circle */
#cropperStage::after {
    content: '';
    position: absolute; inset: 0;
    pointer-events: none;
    border-radius: 12px;
    box-shadow: inset 0 0 0 9999px rgba(0,0,0,0.55);
    /* punch a circle hole using clip — achieved via radial-gradient mask */
    background: radial-gradient(
        circle at center,
        transparent 40%,
        rgba(0,0,0,0.55) 40.1%
    );
}
/* Zoom slider */
.zoom-row { display: flex; align-items: center; gap: 0.75rem; margin-top: 0.75rem; }
.zoom-row input[type=range] { flex: 1; accent-color: var(--blc-royal, #2d27a0); }
.cropper-actions { display: flex; gap: 0.75rem; margin-top: 1rem; }
.cropper-actions button { flex: 1; padding: 0.65rem; border-radius: 10px; font-weight: 700; font-size: 0.88rem; cursor: pointer; border: none; }
.btn-crop-cancel { background: #f1f5f9; color: #374151; }
.btn-crop-save   { background: var(--blc-royal, #2d27a0); color: #fff; }
</style>
@endpush

@section('content')
<div class="profile-page">

    {{-- ── Read-only info card ── --}}
    <div class="avatar-card">
        <div class="avatar-wrap" id="avatarWrap">
            <div class="avatar-ring" id="avatarRing">
                @if($user->profile_photo)
                    <img id="avatarImg" src="{{ $user->profilePhotoUrl() }}" alt="Profile Photo">
                @else
                    <span class="avatar-initial" id="avatarInitial">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                    <img id="avatarImg" src="" alt="" style="display:none">
                @endif
            </div>
            <button type="button" class="avatar-edit-btn" id="avatarEditBtn" title="Change photo">
                <i class="bi bi-camera-fill"></i>
            </button>
        </div>
        <div class="avatar-name">{{ $user->name }}</div>
        <div class="avatar-email">{{ $user->email }}</div>
        <span class="avatar-role-badge">{{ $user->role->slug ?? 'user' }}</span>

        {{-- Hidden file input --}}
        <input type="file" id="photoFileInput" accept="image/jpeg,image/jpg,image/png,image/webp" style="display:none">

        <div id="photoStatus" class="mt-3" style="min-height:28px"></div>
    </div>

    {{-- ── Password change card ── --}}
    <div class="section-card">
        <div class="section-card-header">
            <i class="bi bi-shield-lock-fill"></i> Change Password
        </div>
        <div class="section-card-body">

            <div id="passwordForm">
                <p class="text-muted" style="font-size:0.83rem;margin-bottom:1.25rem;">
                    Enter and confirm your new password below. A confirmation email will be sent after the change is applied.
                </p>

                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:0.82rem;">New Password</label>
                    <div style="position:relative;">
                        <input type="password" id="newPassword" class="form-control"
                               placeholder="Min 8 chars, upper+lower+number"
                               autocomplete="new-password"
                               style="padding-right:2.8rem">
                        <button type="button" class="pw-toggle-btn" tabindex="-1"
                                onclick="togglePw('newPassword','pwIcon1')">
                            <i class="bi bi-eye" id="pwIcon1"></i>
                        </button>
                    </div>
                    <div class="pw-strength" id="pwStrengthBar" style="width:0%;background:#ef4444"></div>
                    <div class="pw-hint" id="pwHint"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:0.82rem;">Confirm New Password</label>
                    <div style="position:relative;">
                        <input type="password" id="confirmPassword" class="form-control"
                               placeholder="Repeat password"
                               autocomplete="new-password"
                               style="padding-right:2.8rem">
                        <button type="button" class="pw-toggle-btn" tabindex="-1"
                                onclick="togglePw('confirmPassword','pwIcon2')">
                            <i class="bi bi-eye" id="pwIcon2"></i>
                        </button>
                    </div>
                </div>

                <div id="pwStepMsg"></div>

                <button type="button" class="btn btn-primary w-100" id="btnChangePassword" style="font-weight:700;">
                    <i class="bi bi-shield-lock-fill me-1"></i> Change Password
                </button>
            </div>

        </div>
    </div>

</div><!-- /.profile-page -->

{{-- ── Photo Cropper Modal ── --}}
<div class="cropper-modal-overlay" id="cropperOverlay">
    <div class="cropper-modal-box">
        <div class="cropper-modal-title"><i class="bi bi-crop me-2"></i>Adjust Photo</div>

        <div id="cropperStage">
            <img id="cropperImg" draggable="false" alt="">
            {{-- The circle overlay is drawn by the JS canvas, no extra DOM needed --}}
        </div>

        <div class="zoom-row">
            <i class="bi bi-zoom-out" style="color:#9ca3af;font-size:0.9rem;"></i>
            <input type="range" id="zoomSlider" min="0.1" max="4" step="0.01" value="1">
            <i class="bi bi-zoom-in"  style="color:#9ca3af;font-size:0.9rem;"></i>
        </div>

        <div id="cropperMsg" style="min-height:24px;font-size:0.79rem;color:#9ca3af;margin-top:4px;text-align:center;"></div>

        <div class="cropper-actions">
            <button type="button" class="btn-crop-cancel" id="btnCropCancel">Cancel</button>
            <button type="button" class="btn-crop-save"   id="btnCropSave">
                <i class="bi bi-check2 me-1"></i>Save Photo
            </button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
// Pass Blade vars into the profile JS — MUST be defined before profile.js loads
window.PROFILE_CONFIG = {
    photoUrl:     "{{ route('profile.photo') }}",
    passwordUrl:  "{{ route('profile.password') }}",
    csrf:         "{{ csrf_token() }}",
    hasPhoto:     {{ $user->profile_photo ? 'true' : 'false' }},
};

// Password show/hide toggle — same pattern as login page
function togglePw(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    if (!input || !icon) return;
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}
</script>
<script src="{{ asset('js/profile.js') }}"></script>
@endpush
