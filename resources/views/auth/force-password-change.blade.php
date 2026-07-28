<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Set New Password — Believe Learning Center</title>
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

        .wrap { width: 100%; max-width: 460px; }

        /* Brand */
        .brand {
            text-align: center;
            margin-bottom: 1.75rem;
        }
        .brand img {
            width: 72px; height: 72px;
            object-fit: contain;
            filter: drop-shadow(0 4px 16px rgba(11,42,91,0.18));
            margin-bottom: 0.75rem;
        }
        .brand h1 { font-size: 1.45rem; font-weight: 800; color: var(--navy); margin-bottom: 0.15rem; }
        .brand .sub { font-size: 0.82rem; color: var(--gold); font-weight: 600; }

        /* Card */
        .card {
            background: #fff;
            border-radius: 20px;
            padding: 2.25rem 2rem;
            box-shadow: 0 8px 48px rgba(11,42,91,0.12), 0 1px 4px rgba(11,42,91,0.06);
            border: 1px solid rgba(11,42,91,0.06);
        }

        /* Notice banner */
        .notice {
            background: #fff7ed;
            border: 1.5px solid #fed7aa;
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 1.75rem;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 0.85rem;
            color: #92400e;
        }
        .notice i { flex-shrink: 0; font-size: 1.1rem; margin-top: 1px; }
        .notice strong { display: block; font-size: 0.88rem; margin-bottom: 3px; }

        .card-title { font-size: 1.4rem; font-weight: 800; color: var(--navy); margin-bottom: 0.25rem; }
        .card-sub   { font-size: 0.85rem; color: #6b7280; margin-bottom: 1.5rem; }

        /* Field */
        .field-group { margin-bottom: 1.1rem; }
        .field-label { display: block; font-size: 0.82rem; font-weight: 600; color: #374151; margin-bottom: 0.4rem; }
        .field-wrap  { position: relative; }
        .field-input {
            width: 100%; padding: 0.72rem 2.8rem 0.72rem 1rem;
            border: 1.5px solid #e2e8f0; border-radius: 10px;
            font-size: 0.9rem; font-family: 'Inter', sans-serif;
            color: #1a2540; background: #fff; outline: none;
            transition: border-color .18s, box-shadow .18s;
        }
        .field-input:focus { border-color: var(--navy-2); box-shadow: 0 0 0 3.5px rgba(15,58,122,0.10); }
        .field-input::placeholder { color: #c4cad6; }
        .field-input.is-invalid   { border-color: #ef4444; }
        .invalid-msg { font-size: 0.78rem; color: #ef4444; margin-top: 4px; }

        /* Toggle */
        .pw-toggle {
            position: absolute; right: 0.85rem; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: #9ca3af; cursor: pointer;
            font-size: 1rem; padding: 0; transition: color .15s;
        }
        .pw-toggle:hover { color: var(--navy-2); }

        /* Requirements */
        .pw-reqs { font-size: 0.78rem; color: #6b7280; margin-top: 8px; }
        .pw-reqs li { list-style: none; display: flex; align-items: center; gap: 6px; margin-bottom: 3px; }
        .pw-reqs li i { font-size: 0.75rem; }
        .req-ok  { color: #059669; }
        .req-bad { color: #9ca3af; }

        /* Submit */
        .btn-submit {
            width: 100%; padding: 0.82rem;
            background: var(--navy-2); color: #fff;
            border: none; border-radius: 10px;
            font-size: 0.95rem; font-weight: 700;
            font-family: 'Inter', sans-serif; cursor: pointer;
            transition: all .2s; letter-spacing: 0.2px;
            box-shadow: 0 4px 16px rgba(11,42,91,0.28);
            margin-top: 1.5rem;
        }
        .btn-submit:hover { background: var(--navy-dark); box-shadow: 0 6px 24px rgba(11,42,91,0.4); transform: translateY(-1px); }

        /* Error alert */
        .alert-err {
            background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px;
            padding: 0.75rem 1rem; margin-bottom: 1.25rem;
            font-size: 0.83rem; color: #991b1b;
            display: flex; align-items: flex-start; gap: 0.5rem;
        }

        /* Logout link */
        .logout-row { text-align: center; margin-top: 1.25rem; font-size: 0.82rem; color: #6b7280; }
        .logout-row a { color: #6b7280; font-weight: 500; }
        .logout-row a:hover { color: var(--navy-2); text-decoration: underline; }

        .footer { text-align: center; margin-top: 1.5rem; font-size: 0.75rem; color: #9ca3af; }

        @media (max-width: 480px) {
            .card { padding: 1.75rem 1.25rem; border-radius: 16px; }
        }
    </style>
</head>
<body>

<div class="wrap">

    <div class="brand">
        <img src="{{ asset('images/logo.png') }}" alt="Believe Learning Center">
        <h1>Believe Learning Center</h1>
        <div class="sub">University Management System</div>
    </div>

    <div class="card">

        {{-- Security notice --}}
        <div class="notice">
            <i class="bi bi-shield-lock-fill"></i>
            <div>
                <strong>Action Required — Set Your Password</strong>
                Your account was created with a temporary password.
                You must set a new personal password before continuing.
            </div>
        </div>

        <div class="card-title">Set New Password</div>
        <p class="card-sub">Choose a strong password you haven't used before.</p>

        {{-- Validation errors --}}
        @if($errors->any())
        <div class="alert-err">
            <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
            <div>@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
        </div>
        @endif

        <form method="POST" action="{{ route('password.force-change.update') }}" id="changeForm">
            @csrf

            {{-- New password --}}
            <div class="field-group">
                <label class="field-label" for="password">New Password <span style="color:#ef4444">*</span></label>
                <div class="field-wrap">
                    <input type="password"
                           id="password"
                           name="password"
                           class="field-input {{ $errors->has('password') ? 'is-invalid' : '' }}"
                           placeholder="Enter new password"
                           required
                           autocomplete="new-password"
                           oninput="checkReqs(this.value)">
                    <button type="button" class="pw-toggle" onclick="togglePw('password', 'icon1')" tabindex="-1">
                        <i class="bi bi-eye" id="icon1"></i>
                    </button>
                </div>
                {{-- Live requirements list --}}
                <ul class="pw-reqs" id="pwReqs">
                    <li id="req-len">  <i class="bi bi-circle req-bad" id="icon-len"></i>  At least 8 characters</li>
                    <li id="req-upper"><i class="bi bi-circle req-bad" id="icon-upper"></i> One uppercase letter</li>
                    <li id="req-num">  <i class="bi bi-circle req-bad" id="icon-num"></i>  One number</li>
                </ul>
            </div>

            {{-- Confirm password --}}
            <div class="field-group">
                <label class="field-label" for="password_confirmation">Confirm New Password <span style="color:#ef4444">*</span></label>
                <div class="field-wrap">
                    <input type="password"
                           id="password_confirmation"
                           name="password_confirmation"
                           class="field-input"
                           placeholder="Repeat new password"
                           required
                           autocomplete="new-password">
                    <button type="button" class="pw-toggle" onclick="togglePw('password_confirmation', 'icon2')" tabindex="-1">
                        <i class="bi bi-eye" id="icon2"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-submit">
                <i class="bi bi-shield-check me-1"></i> Set Password &amp; Continue
            </button>
        </form>

        <div class="logout-row">
            Not your account?
            <form method="POST" action="{{ route('logout') }}" style="display:inline">
                @csrf
                <button type="submit" style="background:none;border:none;padding:0;cursor:pointer;font-size:0.82rem;color:#6b7280;font-family:inherit">
                    Sign out
                </button>
            </form>
        </div>

    </div>

    <div class="footer">
        © {{ date('Y') }} Believe Learning Center. All rights reserved.
    </div>

</div>

<script>
function togglePw(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    input.type  = input.type === 'password' ? 'text' : 'password';
    icon.className = input.type === 'text' ? 'bi bi-eye-slash' : 'bi bi-eye';
}

function checkReqs(val) {
    const rules = {
        'req-len':   { icon: 'icon-len',   pass: val.length >= 8 },
        'req-upper': { icon: 'icon-upper', pass: /[A-Z]/.test(val) },
        'req-num':   { icon: 'icon-num',   pass: /[0-9]/.test(val) },
    };
    for (const [id, rule] of Object.entries(rules)) {
        const iconEl = document.getElementById(rule.icon);
        if (iconEl) {
            iconEl.className = rule.pass
                ? 'bi bi-check-circle-fill req-ok'
                : 'bi bi-circle req-bad';
        }
    }
}
</script>
</body>
</html>
