<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Reset Password — Believe Learning Center</title>
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

        /* ── Brand ── */
        .login-brand { text-align: center; margin-bottom: 1.75rem; }
        .login-brand img {
            width: 72px; height: 72px; object-fit: contain;
            filter: drop-shadow(0 4px 16px rgba(11,42,91,0.18));
            display: block; margin: 0 auto 0.75rem;
        }
        .login-brand h1 {
            font-size: 1.45rem; font-weight: 800; color: var(--navy);
            margin-bottom: 0.15rem; letter-spacing: -0.3px;
        }
        .login-brand .sub { font-size: 0.82rem; color: var(--gold); font-weight: 600; }

        /* ── Card ── */
        .login-card {
            background: #fff;
            border-radius: 20px;
            padding: 2.25rem 2rem;
            box-shadow: 0 8px 48px rgba(11,42,91,0.12), 0 1px 4px rgba(11,42,91,0.06);
            border: 1px solid rgba(11,42,91,0.06);
        }

        /* ── Step heading ── */
        .step-icon {
            width: 52px; height: 52px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1rem; font-size: 1.4rem;
        }
        .step-icon.otp-icon  { background: #eef2ff; color: var(--navy-2); }
        .step-icon.pw-icon   { background: #f0fdf4; color: #166534; }

        .card-title {
            font-size: 1.35rem; font-weight: 800; color: var(--navy);
            text-align: center; margin-bottom: 0.3rem; letter-spacing: -0.3px;
        }
        .card-sub {
            font-size: 0.85rem; color: #6b7280;
            text-align: center; margin-bottom: 1.5rem; line-height: 1.55;
        }

        /* ── Alerts ── */
        .alert-err {
            background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px;
            padding: 0.75rem 1rem; margin-bottom: 1.25rem;
            font-size: 0.83rem; color: #991b1b;
            display: flex; align-items: flex-start; gap: 0.5rem;
        }
        .alert-info {
            background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px;
            padding: 0.75rem 1rem; margin-bottom: 1.25rem;
            font-size: 0.83rem; color: #1e40af;
            display: flex; align-items: flex-start; gap: 0.5rem;
        }
        .alert-success {
            background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px;
            padding: 0.75rem 1rem; margin-bottom: 1.25rem;
            font-size: 0.83rem; color: #166534;
            display: flex; align-items: flex-start; gap: 0.5rem;
        }

        /* ── OTP boxes ── */
        .otp-group {
            display: flex; gap: 8px; justify-content: center;
            margin: 0 0 0.5rem;
        }
        .otp-digit {
            flex: 1; min-width: 0; max-width: 60px; height: 64px;
            border: 1.5px solid #d1d5db; border-radius: 10px;
            text-align: center; font-size: 1.6rem; font-weight: 800;
            color: var(--navy); font-family: 'Inter', sans-serif;
            background: #fff; outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .otp-digit:focus {
            border-color: var(--navy-2);
            box-shadow: 0 0 0 3px rgba(15,58,122,0.12);
        }
        .otp-expire {
            text-align: center; font-size: 0.76rem;
            color: #9ca3af; margin-bottom: 1.25rem;
        }

        /* ── Form fields ── */
        .field-group { margin-bottom: 1.1rem; }
        .field-label {
            display: block; font-size: 0.82rem; font-weight: 600;
            color: #374151; margin-bottom: 0.4rem;
        }
        .field-input-wrap { position: relative; }
        .field-input {
            width: 100%; padding: 0.72rem 1rem;
            border: 1.5px solid #e2e8f0; border-radius: 10px;
            font-size: 0.9rem; font-family: 'Inter', sans-serif;
            color: #1a2540; background: #fff; outline: none;
            transition: border-color 0.18s, box-shadow 0.18s;
        }
        .field-input:focus {
            border-color: var(--navy-2);
            box-shadow: 0 0 0 3.5px rgba(15,58,122,0.10);
        }
        .field-input.pw-input { padding-right: 2.8rem; }
        .field-input::placeholder { color: #c4cad6; }

        .pw-toggle-btn {
            position: absolute; right: 0.85rem; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: #9ca3af;
            cursor: pointer; font-size: 1rem; padding: 0; line-height: 1;
            transition: color 0.15s;
        }
        .pw-toggle-btn:hover { color: var(--navy-2); }

        /* ── Strength bar ── */
        .pw-strength {
            height: 4px; border-radius: 2px;
            transition: width .3s, background .3s; margin-top: 6px;
        }
        .pw-hint { font-size: 0.72rem; color: #9ca3af; margin-top: 4px; }

        /* ── Buttons ── */
        .btn-submit {
            width: 100%; padding: 0.82rem;
            background: var(--navy-2); color: #fff; border: none; border-radius: 10px;
            font-size: 0.95rem; font-weight: 700; font-family: 'Inter', sans-serif;
            cursor: pointer; transition: all 0.2s ease; letter-spacing: 0.2px;
            box-shadow: 0 4px 16px rgba(11,42,91,0.28); margin-top: 0.5rem;
        }
        .btn-submit:hover  { background: var(--navy-dark); transform: translateY(-1px); box-shadow: 0 6px 24px rgba(11,42,91,0.4); }
        .btn-submit:active { transform: translateY(0); }
        .btn-submit:disabled { opacity: 0.65; cursor: not-allowed; transform: none; }

        .btn-resend {
            background: none; border: none; cursor: pointer;
            color: var(--navy-2); font-size: 0.83rem; font-weight: 600;
            font-family: 'Inter', sans-serif; padding: 0; transition: opacity 0.15s;
        }
        .btn-resend:hover { opacity: 0.7; }

        /* ── Footer links ── */
        .helper-row {
            text-align: center; margin-top: 1rem;
            font-size: 0.83rem; color: #6b7280;
        }
        .helper-row a, .helper-row button { color: var(--navy-2); font-weight: 600; text-decoration: none; }
        .helper-row a:hover { text-decoration: underline; }

        /* ── Email highlight chip ── */
        .email-chip {
            background: #eef2ff; color: var(--navy); font-weight: 700;
            padding: 0.1rem 0.45rem; border-radius: 5px; font-size: 0.88rem;
        }

        /* ── Progress dots ── */
        .step-dots {
            display: flex; justify-content: center; gap: 6px; margin-bottom: 1.5rem;
        }
        .step-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: #e2e8f0; transition: background 0.2s;
        }
        .step-dot.active { background: var(--navy-2); }
        .step-dot.done   { background: #22c55e; }

        .login-footer {
            text-align: center; margin-top: 1.5rem;
            font-size: 0.75rem; color: #9ca3af;
        }

        @media (max-width: 480px) {
            body { padding: 1rem; }
            .login-card { padding: 1.75rem 1.25rem; border-radius: 16px; }
            .login-brand img { width: 60px; height: 60px; }
            .login-brand h1 { font-size: 1.25rem; }
            .otp-digit { height: 56px; font-size: 1.4rem; }
        }
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

    <div class="login-card">

        {{-- Progress dots --}}
        <div class="step-dots">
            <div class="step-dot {{ $otpVerified ? 'done' : 'active' }}"></div>
            <div class="step-dot {{ $otpVerified ? 'active' : '' }}"></div>
        </div>

        @if (!$otpVerified)
        {{-- ════════════════════════════════════════════════════
             STEP 1 — Enter OTP
        ════════════════════════════════════════════════════ --}}

            <div class="step-icon otp-icon">
                <i class="bi bi-envelope-check-fill"></i>
            </div>
            <div class="card-title">Check Your Email</div>
            @if(isset($user))
                <p class="card-sub">
                    We sent a 6-digit code to
                    <span class="email-chip">{{ $user->email }}</span>.
                    Enter it below to continue.
                </p>
            @else
                <p class="card-sub">Enter the 6-digit code sent to your email.</p>
            @endif

            {{-- Flash --}}
            @if(session('info'))
                <div class="alert-info">
                    <i class="bi bi-info-circle-fill flex-shrink-0" style="margin-top:1px"></i>
                    <span>{{ session('info') }}</span>
                </div>
            @endif

            {{-- Errors --}}
            @if($errors->has('otp'))
                <div class="alert-err">
                    <i class="bi bi-exclamation-triangle-fill flex-shrink-0" style="margin-top:1px"></i>
                    <span>{{ $errors->first('otp') }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('forgot-password.check-otp') }}" id="otpForm">
                @csrf
                <input type="hidden" name="otp" id="otpHidden">

                <div class="otp-group" id="otpGroup" role="group" aria-label="6-digit verification code">
                    @for ($i = 0; $i < 6; $i++)
                        <input type="text" class="otp-digit"
                               maxlength="1" inputmode="numeric" pattern="[0-9]"
                               autocomplete="one-time-code"
                               aria-label="Digit {{ $i + 1 }}">
                    @endfor
                </div>

                <div class="otp-expire">⏱ Code expires in 5 minutes.</div>

                <button type="submit" class="btn-submit" id="btnVerify">
                    <i class="bi bi-check2-circle me-1"></i> Verify Code
                </button>
            </form>

            <div class="helper-row" style="margin-top:0.85rem;">
                Didn't receive it?&nbsp;
                <form method="POST" action="{{ route('forgot-password.resend') }}" style="display:inline">
                    @csrf
                    <button type="submit" class="btn-resend">Resend code</button>
                </form>
            </div>
            <div class="helper-row" style="margin-top:0.5rem;">
                <a href="{{ route('forgot-password') }}">
                    <i class="bi bi-arrow-left"></i> Use a different email
                </a>
            </div>

        @else
        {{-- ════════════════════════════════════════════════════
             STEP 2 — Set new password
        ════════════════════════════════════════════════════ --}}

            <div class="step-icon pw-icon">
                <i class="bi bi-shield-lock-fill"></i>
            </div>
            <div class="card-title">Set New Password</div>
            <p class="card-sub">
                Code verified. Choose a strong new password for your account.
            </p>

            {{-- Validation errors --}}
            @if($errors->has('password'))
                <div class="alert-err">
                    <i class="bi bi-exclamation-triangle-fill flex-shrink-0" style="margin-top:1px"></i>
                    <span>{{ $errors->first('password') }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('forgot-password.reset') }}" id="pwForm">
                @csrf

                <div class="field-group">
                    <label class="field-label" for="password">New Password</label>
                    <div class="field-input-wrap">
                        <input type="password" id="password" name="password"
                               class="field-input pw-input"
                               placeholder="Min 8 chars, upper + lower + number"
                               autocomplete="new-password" required autofocus>
                        <button type="button" class="pw-toggle-btn" tabindex="-1"
                                onclick="togglePw('password','pwIcon1')">
                            <i class="bi bi-eye" id="pwIcon1"></i>
                        </button>
                    </div>
                    <div class="pw-strength" id="pwStrengthBar" style="width:0%;background:#ef4444"></div>
                    <div class="pw-hint"     id="pwHint"></div>
                </div>

                <div class="field-group">
                    <label class="field-label" for="password_confirmation">Confirm New Password</label>
                    <div class="field-input-wrap">
                        <input type="password" id="password_confirmation" name="password_confirmation"
                               class="field-input pw-input"
                               placeholder="Repeat new password"
                               autocomplete="new-password" required>
                        <button type="button" class="pw-toggle-btn" tabindex="-1"
                                onclick="togglePw('password_confirmation','pwIcon2')">
                            <i class="bi bi-eye" id="pwIcon2"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="btnReset">
                    <i class="bi bi-shield-check me-1"></i> Change Password
                </button>
            </form>

        @endif

    </div><!-- /.login-card -->

    <div class="login-footer">
        © {{ date('Y') }} Believe Learning Center. All rights reserved.
    </div>

</div><!-- /.login-wrap -->

<script>
/* ── Show / hide password ─────────────────────────────────────────────── */
function togglePw(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    if (!input || !icon) return;
    input.type     = input.type === 'password' ? 'text' : 'password';
    icon.className = input.type === 'text' ? 'bi bi-eye-slash' : 'bi bi-eye';
}

/* ── Password strength meter ──────────────────────────────────────────── */
const pwInput     = document.getElementById('password');
const strengthBar = document.getElementById('pwStrengthBar');
const pwHint      = document.getElementById('pwHint');

function measureStrength(pw) {
    let s = 0;
    if (pw.length >= 8)          s++;
    if (pw.length >= 12)         s++;
    if (/[A-Z]/.test(pw))        s++;
    if (/[a-z]/.test(pw))        s++;
    if (/[0-9]/.test(pw))        s++;
    if (/[^A-Za-z0-9]/.test(pw)) s++;
    return s;
}

pwInput?.addEventListener('input', function () {
    const s      = measureStrength(this.value);
    const colors = ['#ef4444','#f97316','#eab308','#84cc16','#22c55e','#16a34a'];
    const labels = ['Very weak','Weak','Fair','Good','Strong','Very strong'];
    if (strengthBar) {
        strengthBar.style.width      = Math.round((s / 6) * 100) + '%';
        strengthBar.style.background = colors[Math.min(s, 5)];
    }
    if (pwHint) pwHint.textContent = this.value.length ? labels[Math.min(s, 5)] : '';
});

/* ── Password form validation ─────────────────────────────────────────── */
document.getElementById('pwForm')?.addEventListener('submit', function (e) {
    const pw  = document.getElementById('password')?.value || '';
    const cpw = document.getElementById('password_confirmation')?.value || '';

    if (pw.length < 8) {
        e.preventDefault();
        alert('Password must be at least 8 characters.');
        document.getElementById('password')?.focus();
        return;
    }
    if (pw !== cpw) {
        e.preventDefault();
        alert('Passwords do not match.');
        document.getElementById('password_confirmation')?.focus();
        return;
    }

    const btn = document.getElementById('btnReset');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Changing…'; }
});

/* ── OTP digit-box navigation ─────────────────────────────────────────── */
const otpGroup  = document.getElementById('otpGroup');
const otpHidden = document.getElementById('otpHidden');
const digits    = otpGroup ? Array.from(otpGroup.querySelectorAll('.otp-digit')) : [];

function assembleOtp() {
    if (otpHidden) otpHidden.value = digits.map(d => d.value).join('');
}

digits.forEach((box, idx) => {

    box.addEventListener('input', function () {
        const val  = this.value.replace(/\D/g, '').slice(-1);
        this.value = val;
        if (val && idx < digits.length - 1) digits[idx + 1].focus();
        assembleOtp();
    });

    box.addEventListener('keydown', function (e) {
        if (e.key === 'Backspace') {
            e.preventDefault();
            if (this.value) {
                this.value = '';
            } else if (idx > 0) {
                digits[idx - 1].value = '';
                digits[idx - 1].focus();
            }
            assembleOtp();
            return;
        }
        if (e.key === 'ArrowLeft'  && idx > 0)                digits[idx - 1].focus();
        if (e.key === 'ArrowRight' && idx < digits.length - 1) digits[idx + 1].focus();
    });

    box.addEventListener('paste', function (e) {
        e.preventDefault();
        const pasted = (e.clipboardData || window.clipboardData)
            .getData('text').replace(/\D/g, '').slice(0, 6);
        pasted.split('').forEach((ch, i) => { if (digits[i]) digits[i].value = ch; });
        digits[Math.min(pasted.length, digits.length - 1)]?.focus();
        assembleOtp();
    });
});

/* ── OTP form validation ──────────────────────────────────────────────── */
document.getElementById('otpForm')?.addEventListener('submit', function (e) {
    assembleOtp();
    if ((otpHidden?.value || '').length < 6) {
        e.preventDefault();
        alert('Please enter all 6 digits of the verification code.');
        digits[0]?.focus();
        return;
    }
    const btn = document.getElementById('btnVerify');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Verifying…'; }
});

/* Auto-focus first OTP box on page load */
digits[0]?.focus();
</script>
</body>
</html>
