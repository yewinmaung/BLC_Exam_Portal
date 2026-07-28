<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign In — Believe Learning Center</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --navy:      #0b2a5b;
            --navy-2:    #0f3a7a;
            --navy-dark: #071d40;
            --gold:      #d4a51c;
        }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            background: #f0f4fb;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .login-wrap { width: 100%; max-width: 460px; }

        /* Brand */
        .login-brand { text-align: center; margin-bottom: 1.75rem; }
        .login-brand img { width: 72px; height: 72px; object-fit: contain; filter: drop-shadow(0 4px 16px rgba(11,42,91,0.18)); margin-bottom: 0.75rem; }
        .login-brand h1 { font-size: 1.45rem; font-weight: 800; color: var(--navy); margin-bottom: 0.15rem; letter-spacing: -0.3px; }
        .login-brand .sub { font-size: 0.82rem; color: var(--gold); font-weight: 600; }

        /* Card */
        .login-card {
            background: #fff; border-radius: 20px; padding: 2.25rem 2rem;
            box-shadow: 0 8px 48px rgba(11,42,91,0.12), 0 1px 4px rgba(11,42,91,0.06);
            border: 1px solid rgba(11,42,91,0.06);
        }
        .login-card h2 { font-size: 1.5rem; font-weight: 800; color: var(--navy); margin-bottom: 0.3rem; }
        .login-card .card-sub { font-size: 0.85rem; color: #6b7280; margin-bottom: 1.75rem; }

        /* Fields */
        .field-group { margin-bottom: 1.1rem; }
        .field-label { display: block; font-size: 0.82rem; font-weight: 600; color: #374151; margin-bottom: 0.4rem; }
        .field-input-wrap { position: relative; }
        .field-input {
            width: 100%; padding: 0.72rem 1rem; border: 1.5px solid #e2e8f0; border-radius: 10px;
            font-size: 0.9rem; font-family: 'Inter', sans-serif; color: #1a2540; background: #fff;
            outline: none; transition: border-color 0.18s, box-shadow 0.18s;
        }
        .field-input:focus { border-color: var(--navy-2); box-shadow: 0 0 0 3.5px rgba(15,58,122,0.10); }
        .field-input:disabled { background: #f3f4f6; color: #9ca3af; cursor: not-allowed; }
        .field-input::placeholder { color: #c4cad6; }

        /* Password row */
        .pw-label-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.4rem; }
        .pw-label-row .field-label { margin-bottom: 0; }
        .forgot-link { font-size: 0.78rem; color: var(--navy-2); text-decoration: none; font-weight: 500; }
        .forgot-link:hover { text-decoration: underline; }
        .pw-toggle-btn {
            position: absolute; right: 0.85rem; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: #9ca3af; cursor: pointer; font-size: 1rem;
            padding: 0; line-height: 1; transition: color 0.15s;
        }
        .pw-toggle-btn:hover { color: var(--navy-2); }

        /* Remember me */
        .remember-row { display: flex; align-items: center; gap: 0.5rem; margin: 1.1rem 0 1.5rem; }
        .remember-row input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--navy-2); cursor: pointer; }
        .remember-row label { font-size: 0.83rem; color: #6b7280; cursor: pointer; user-select: none; }

        /* Submit button */
        .btn-submit {
            width: 100%; padding: 0.82rem; background: var(--navy-2); color: #fff;
            border: none; border-radius: 10px; font-size: 0.95rem; font-weight: 700;
            font-family: 'Inter', sans-serif; cursor: pointer; transition: all 0.2s;
            letter-spacing: 0.2px; box-shadow: 0 4px 16px rgba(11,42,91,0.28);
        }
        .btn-submit:hover:not(:disabled) { background: var(--navy-dark); box-shadow: 0 6px 24px rgba(11,42,91,0.4); transform: translateY(-1px); }
        .btn-submit:disabled { background: #9ca3af; box-shadow: none; cursor: not-allowed; transform: none; }

        /* Alerts */
        .alert-err {
            background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px;
            padding: 0.75rem 1rem; margin-bottom: 1.25rem; font-size: 0.83rem; color: #991b1b;
            display: flex; align-items: flex-start; gap: 0.5rem;
        }
        .alert-success {
            background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px;
            padding: 0.75rem 1rem; margin-bottom: 1.25rem; font-size: 0.83rem; color: #166534;
            display: flex; gap: 0.5rem; align-items: center;
        }

        /* ── Lock countdown banner ── */
        .lock-banner {
            background: #fef3c7; border: 1.5px solid #f59e0b; border-radius: 12px;
            padding: 16px 18px; margin-bottom: 1.5rem;
        }
        .lock-banner-title {
            display: flex; align-items: center; gap: 8px;
            font-size: 0.92rem; font-weight: 700; color: #92400e; margin-bottom: 10px;
        }
        .countdown-display {
            font-size: 2rem; font-weight: 900; font-family: 'Courier New', monospace;
            color: #b45309; text-align: center; letter-spacing: 0.05em;
            background: #fffbeb; border-radius: 8px; padding: 8px 16px;
            border: 1px solid #fde68a; margin-bottom: 8px;
        }
        .lock-banner-note { font-size: 0.78rem; color: #92400e; text-align: center; }

        /* ── Expired password section ── */
        .expired-banner {
            background: #fef2f2; border: 1.5px solid #fca5a5; border-radius: 12px;
            padding: 16px 18px; margin-bottom: 1.5rem;
        }
        .expired-banner-title {
            display: flex; align-items: center; gap: 8px;
            font-size: 0.92rem; font-weight: 700; color: #991b1b; margin-bottom: 10px;
        }
        .expired-banner p { font-size: 0.83rem; color: #7f1d1d; margin-bottom: 14px; }
        .expired-email-input {
            width: 100%; padding: 0.65rem 1rem; border: 1.5px solid #fca5a5; border-radius: 8px;
            font-size: 0.87rem; font-family: 'Inter', sans-serif; color: #1a2540;
            background: #fff; outline: none; margin-bottom: 10px;
        }
        .expired-email-input:focus { border-color: #ef4444; box-shadow: 0 0 0 3px rgba(239,68,68,0.12); }
        .btn-request {
            width: 100%; padding: 0.7rem; background: #dc2626; color: #fff;
            border: none; border-radius: 8px; font-size: 0.87rem; font-weight: 700;
            font-family: 'Inter', sans-serif; cursor: pointer; transition: background 0.2s;
        }
        .btn-request:hover { background: #b91c1c; }

        .login-footer { text-align: center; margin-top: 1.5rem; font-size: 0.75rem; color: #9ca3af; }

        @media (max-width: 480px) {
            body { padding: 1rem; }
            .login-card { padding: 1.75rem 1.25rem; border-radius: 16px; }
            .login-brand img { width: 60px; height: 60px; }
            .login-brand h1 { font-size: 1.25rem; }
        }
        @media (min-width: 768px) { .login-wrap { max-width: 480px; } }
    </style>
</head>
<body>

<div class="login-wrap">

    {{-- Brand --}}
    <div class="login-brand">
        <img src="{{ asset('images/logo.png') }}" alt="Believe Learning Center">
        <h1>Believe Learning Center</h1>
        <div class="sub">University Management System</div>
    </div>

    {{-- Card --}}
    <div class="login-card">

        <h2>Welcome back</h2>
        <p class="card-sub">Sign in to your account to continue</p>

        {{-- ── Success flash ── --}}
        @if(session('success'))
        <div class="alert-success">
            <i class="bi bi-check-circle-fill"></i>
            {{ session('success') }}
        </div>
        @endif

        {{-- ── General errors (non-lock, non-expired) ── --}}
        @php
            $isLocked      = session('locked_until') !== null;
            $isTempExpired = session('temp_expired_email') !== null;
            $hasErrors     = $errors->any();
        @endphp

        @if($hasErrors && !$isLocked)
        <div class="alert-err">
            <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
            <div>@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
        </div>
        @endif

        {{-- ── Account lock countdown banner ──
             locked_until is an ISO8601 string from the server (e.g. "2026-07-27T14:35:00+00:00").
             The JS computes the remaining seconds from the SERVER timestamp, not the client clock,
             so the countdown is correct even if the browser clock is wrong or the page is refreshed.
        ── --}}
        @if($isLocked)
        <div class="lock-banner" id="lockBanner">
            <div class="lock-banner-title">
                <i class="bi bi-lock-fill"></i>
                Account Temporarily Locked
            </div>
            <div class="countdown-display" id="countdownDisplay">--:--</div>
            <div class="lock-banner-note">
                Login will be re-enabled automatically when the timer reaches 00:00.
            </div>
        </div>
        @endif

        {{-- ── Temporary password expired section ── --}}
        @if($isTempExpired)
        <div class="expired-banner">
            <div class="expired-banner-title">
                <i class="bi bi-exclamation-circle-fill"></i>
                Temporary Password Expired
            </div>
            <p>
                Your temporary password has expired (valid for 24 hours).
                Enter your email address below to receive a new one.
            </p>
            @if($errors->has('email'))
            <div style="font-size:0.8rem;color:#991b1b;margin-bottom:8px">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>{{ $errors->first('email') }}
            </div>
            @endif
            <form method="POST" action="{{ route('login.request-new-password') }}">
                @csrf
                <input type="email"
                       name="email"
                       class="expired-email-input"
                       value="{{ old('email', session('temp_expired_email')) }}"
                       placeholder="Your email address"
                       required
                       autocomplete="email">
                <button type="submit" class="btn-request">
                    <i class="bi bi-envelope me-1"></i> Request New Temporary Password
                </button>
            </form>
        </div>
        @endif

        {{-- ── Login form ── --}}
        @if(!$isTempExpired)
        <form method="POST" action="{{ route('login') }}" id="loginForm">
            @csrf

            {{-- Email --}}
            <div class="field-group">
                <label class="field-label" for="email">Email Address</label>
                <div class="field-input-wrap">
                    <input type="email" id="email" name="email" class="field-input"
                           value="{{ old('email') }}"
                           placeholder="Enter your email"
                           required autofocus autocomplete="email"
                           {{ $isLocked ? 'disabled' : '' }}>
                </div>
            </div>

            {{-- Password --}}
            <div class="field-group">
                <div class="pw-label-row">
                    <label class="field-label" for="password">Password</label>
                    @if(!session('hide_forgot_password'))
                    <a href="{{ route('forgot-password') }}" class="forgot-link">Forgot password?</a>
                    @endif
                </div>
                <div class="field-input-wrap">
                    <input type="password" id="password" name="password" class="field-input"
                           placeholder="Enter your password"
                           required autocomplete="current-password"
                           style="padding-right:2.8rem"
                           {{ $isLocked ? 'disabled' : '' }}>
                    <button type="button" class="pw-toggle-btn" id="pwToggle" tabindex="-1"
                            {{ $isLocked ? 'disabled' : '' }}>
                        <i class="bi bi-eye" id="pwIcon"></i>
                    </button>
                </div>
            </div>

            {{-- Remember me --}}
            <div class="remember-row">
                <input type="checkbox" id="remember" name="remember" {{ $isLocked ? 'disabled' : '' }}>
                <label for="remember">Remember me</label>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn" {{ $isLocked ? 'disabled' : '' }}>
                @if($isLocked)
                    <i class="bi bi-lock me-1"></i> Account Locked
                @else
                    Sign In
                @endif
            </button>
        </form>
        @endif

    </div>

    <div class="login-footer">
        © {{ date('Y') }} Believe Learning Center. All rights reserved.
    </div>

</div>

<script>
(function () {
    'use strict';

    // ── Password toggle ──────────────────────────────────────────────────
    document.getElementById('pwToggle')?.addEventListener('click', function () {
        const input = document.getElementById('password');
        const icon  = document.getElementById('pwIcon');
        if (!input || input.disabled) return;
        input.type     = input.type === 'password' ? 'text' : 'password';
        icon.className = input.type === 'text' ? 'bi bi-eye-slash' : 'bi bi-eye';
    });

    // ── Server-side lock countdown ────────────────────────────────────────
    // The server passes locked_until as an ISO8601 UTC string via the
    // Blade session flash.  We compute the remaining seconds against the
    // server timestamp so the countdown is accurate even after page refresh
    // and is immune to client clock drift.
    @if(session('locked_until'))
    const lockedUntilISO = @json(session('locked_until'));

    // Parse the server's ISO8601 timestamp into a JS Date (always UTC)
    const unlockAt  = new Date(lockedUntilISO);
    const submitBtn = document.getElementById('submitBtn');
    const display   = document.getElementById('countdownDisplay');

    function pad(n) { return String(n).padStart(2, '0'); }

    function formatTime(totalSeconds) {
        if (totalSeconds <= 0) return '00:00';
        const m = Math.floor(totalSeconds / 60);
        const s = totalSeconds % 60;
        return pad(m) + ':' + pad(s);
    }

    function tick() {
        // Use server-relative remaining time: always computed from the
        // parsed server timestamp vs current local Date.now() — the absolute
        // difference is correct even if the client clock is offset.
        const remainingMs      = unlockAt - Date.now();
        const remainingSeconds = Math.max(0, Math.floor(remainingMs / 1000));

        if (display)   display.textContent = formatTime(remainingSeconds);

        if (remainingSeconds <= 0) {
            // Lock has expired — re-enable the form without a full page reload
            clearInterval(timer);
            if (submitBtn) {
                submitBtn.disabled    = false;
                submitBtn.textContent = 'Sign In';
            }
            document.querySelectorAll('#loginForm input, #loginForm button').forEach(el => {
                el.disabled = false;
            });
            const banner = document.getElementById('lockBanner');
            if (banner) {
                banner.style.background = '#f0fdf4';
                banner.style.borderColor = '#86efac';
                banner.innerHTML = '<div style="display:flex;align-items:center;gap:8px;font-size:0.9rem;font-weight:700;color:#166534"><i class="bi bi-unlock-fill"></i>Account unlocked. You may now sign in.</div>';
            }
        }
    }

    tick(); // run immediately on page load
    const timer = setInterval(tick, 1000);
    @endif

})();
</script>
</body>
</html>
