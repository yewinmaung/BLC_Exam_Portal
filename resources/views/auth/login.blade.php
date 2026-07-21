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
            --gold-2:    #f2c94c;
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

        /* ── Page wrapper ── */
        .login-wrap {
            width: 100%;
            max-width: 460px;
        }

        /* ── Brand header ── */
        .login-brand {
            text-align: center;
            margin-bottom: 1.75rem;
        }

        .login-brand img {
            width: 72px; height: 72px;
            object-fit: contain;
            filter: drop-shadow(0 4px 16px rgba(11,42,91,0.18));
            margin-bottom: 0.75rem;
        }

        .login-brand h1 {
            font-size: 1.45rem;
            font-weight: 800;
            color: var(--navy);
            margin-bottom: 0.15rem;
            letter-spacing: -0.3px;
        }

        .login-brand .sub {
            font-size: 0.82rem;
            color: var(--gold);
            font-weight: 600;
            letter-spacing: 0.2px;
        }

        /* ── Card ── */
        .login-card {
            background: #fff;
            border-radius: 20px;
            padding: 2.25rem 2rem;
            box-shadow: 0 8px 48px rgba(11,42,91,0.12), 0 1px 4px rgba(11,42,91,0.06);
            border: 1px solid rgba(11,42,91,0.06);
        }

        .login-card h2 {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--navy);
            margin-bottom: 0.3rem;
            letter-spacing: -0.3px;
        }

        .login-card .card-sub {
            font-size: 0.85rem;
            color: #6b7280;
            margin-bottom: 1.75rem;
        }

        /* ── Form fields ── */
        .field-group {
            margin-bottom: 1.1rem;
        }

        .field-label {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.4rem;
        }

        .field-input-wrap {
            position: relative;
        }

        .field-input {
            width: 100%;
            padding: 0.72rem 1rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            color: #1a2540;
            background: #fff;
            outline: none;
            transition: border-color 0.18s, box-shadow 0.18s;
        }

        .field-input:focus {
            border-color: var(--navy-2);
            box-shadow: 0 0 0 3.5px rgba(15,58,122,0.10);
        }

        .field-input::placeholder { color: #c4cad6; }

        /* Password row — label + forgot link */
        .pw-label-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.4rem;
        }

        .pw-label-row .field-label { margin-bottom: 0; }

        .forgot-link {
            font-size: 0.78rem;
            color: var(--navy-2);
            text-decoration: none;
            font-weight: 500;
        }

        .forgot-link:hover { text-decoration: underline; }

        /* Password toggle */
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

        .pw-toggle-btn:hover { color: var(--navy-2); }

        /* Remember me */
        .remember-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 1.1rem 0 1.5rem;
        }

        .remember-row input[type="checkbox"] {
            width: 16px; height: 16px;
            accent-color: var(--navy-2);
            cursor: pointer;
            border-radius: 4px;
        }

        .remember-row label {
            font-size: 0.83rem;
            color: #6b7280;
            cursor: pointer;
            user-select: none;
        }

        /* Submit button */
        .btn-submit {
            width: 100%;
            padding: 0.82rem;
            background: var(--navy-2);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: all 0.2s ease;
            letter-spacing: 0.2px;
            box-shadow: 0 4px 16px rgba(11,42,91,0.28);
        }

        .btn-submit:hover {
            background: var(--navy-dark);
            box-shadow: 0 6px 24px rgba(11,42,91,0.4);
            transform: translateY(-1px);
        }

        .btn-submit:active { transform: translateY(0); }

        /* Register link */
        .register-row {
            text-align: center;
            margin-top: 1.25rem;
            font-size: 0.83rem;
            color: #6b7280;
        }

        .register-row a {
            color: var(--navy-2);
            font-weight: 600;
            text-decoration: none;
        }

        .register-row a:hover { text-decoration: underline; }

        /* Error alert */
        .alert-err {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.25rem;
            font-size: 0.83rem;
            color: #991b1b;
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
        }

        /* Footer */
        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.75rem;
            color: #9ca3af;
        }

        /* ── Demo credentials box ── */
        .demo-creds-box {
            width: 100%;
            max-width: 480px;
            background: #fff;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 20px rgba(11,42,91,0.08);
            overflow: hidden;
            margin-top: 1rem;
        }
        .demo-creds-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            background: #f8faff;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.8rem;
            font-weight: 700;
            color: #1e1b6e;
        }
        .demo-creds-note {
            font-size: 0.68rem;
            font-weight: 400;
            color: #9ca3af;
            margin-left: auto;
        }
        .demo-creds-table { padding: 0.5rem 0; }
        .demo-creds-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-bottom: 1px solid #f0f3fa;
        }
        .demo-creds-row:last-child { border-bottom: none; }
        .demo-role-badge {
            font-size: 0.68rem;
            font-weight: 700;
            padding: 0.18rem 0.55rem;
            border-radius: 20px;
            min-width: 54px;
            text-align: center;
            flex-shrink: 0;
        }
        .demo-role-badge.admin   { background: #eef2ff; color: #2d27a0; }
        .demo-role-badge.teacher { background: #f0fdf4; color: #166534; }
        .demo-role-badge.student { background: #fef9c3; color: #854d0e; }
        .demo-email { font-size: 0.78rem; color: #374151; background: none; }
        .demo-sep   { color: #d1d5db; font-size: 0.75rem; }
        .demo-pass  { font-size: 0.78rem; color: #6b7280; background: none; }
        .demo-fill-btn {
            margin-left: auto;
            background: none;
            border: none;
            color: #2d27a0;
            cursor: pointer;
            font-size: 1rem;
            padding: 0;
            opacity: 0.6;
            transition: opacity 0.15s;
            flex-shrink: 0;
        }
        .demo-fill-btn:hover { opacity: 1; }

        /* ── Responsive ── */
        @media (max-width: 480px) {
            body { padding: 1rem; }
            .login-card { padding: 1.75rem 1.25rem; border-radius: 16px; }
            .login-brand img { width: 60px; height: 60px; }
            .login-brand h1 { font-size: 1.25rem; }
            .demo-creds-note { display: none; }
        }

        @media (min-width: 768px) {
            .login-wrap { max-width: 480px; }
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

    {{-- Card --}}
    <div class="login-card">

        <h2>Welcome back</h2>
        <p class="card-sub">Sign in to your account to continue</p>

        {{-- Errors --}}
        @if($errors->any())
        <div class="alert-err">
            <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
            <div>@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
        </div>
        @endif

        @if(session('success'))
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:0.75rem 1rem;margin-bottom:1.25rem;font-size:0.83rem;color:#166534;display:flex;gap:0.5rem;align-items:center">
            <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
        </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            {{-- Email --}}
            <div class="field-group">
                <label class="field-label" for="email">Email Address</label>
                <div class="field-input-wrap">
                    <input type="email" id="email" name="email" class="field-input"
                           value="{{ old('email') }}"
                           placeholder="Enter your email"
                           required autofocus autocomplete="email">
                </div>
            </div>

            {{-- Password --}}
            <div class="field-group">
                <div class="pw-label-row">
                    <label class="field-label" for="password">Password</label>
                    <a href="{{ route('forgot-password') }}" class="forgot-link">Forgot password?</a>
                </div>
                <div class="field-input-wrap">
                    <input type="password" id="password" name="password" class="field-input"
                           placeholder="Enter your password"
                           required autocomplete="current-password"
                           style="padding-right:2.8rem">
                    <button type="button" class="pw-toggle-btn" id="pwToggle" tabindex="-1">
                        <i class="bi bi-eye" id="pwIcon"></i>
                    </button>
                </div>
            </div>

            {{-- Remember me --}}
            <div class="remember-row">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Remember me</label>
            </div>

            <button type="submit" class="btn-submit">Sign In</button>
        </form>

        <!-- <div class="register-row">
            Don't have an account?
            <a href="{{ route('register') }}">Create account</a>
        </div> -->

    </div>

    {{-- Demo credentials box --}}
    <!-- <div class="demo-creds-box">
        <div class="demo-creds-title">
            <i class="bi bi-shield-lock-fill"></i>
            Demo Accounts
            <span class="demo-creds-note">Passwords stored with Argon2id · Messages encrypted with AES-256</span>
        </div>
        <div class="demo-creds-table">
            <div class="demo-creds-row">
                <span class="demo-role-badge admin">Admin</span>
                <code class="demo-email">admin@blc.edu.mm</code>
                <span class="demo-sep">/</span>
                <code class="demo-pass">password</code>
                <button class="demo-fill-btn" data-email="admin@blc.edu.mm" data-pass="password" title="Fill in">
                    <i class="bi bi-arrow-up-circle"></i>
                </button>
            </div>
            <div class="demo-creds-row">
                <span class="demo-role-badge teacher">Teacher</span>
                <code class="demo-email">teacher@blc.edu.mm</code>
                <span class="demo-sep">/</span>
                <code class="demo-pass">password</code>
                <button class="demo-fill-btn" data-email="teacher@blc.edu.mm" data-pass="password" title="Fill in">
                    <i class="bi bi-arrow-up-circle"></i>
                </button>
            </div>
            <div class="demo-creds-row">
                <span class="demo-role-badge student">Student</span>
                <code class="demo-email">student@blc.edu.mm</code>
                <span class="demo-sep">/</span>
                <code class="demo-pass">password</code>
                <button class="demo-fill-btn" data-email="student@blc.edu.mm" data-pass="password" title="Fill in">
                    <i class="bi bi-arrow-up-circle"></i>
                </button>
            </div>
        </div>
    </div> -->

    <div class="login-footer">
        © {{ date('Y') }} Believe Learning Center. All rights reserved.
    </div>

</div>

<script>
    document.getElementById('pwToggle')?.addEventListener('click', function () {
        const input = document.getElementById('password');
        const icon  = document.getElementById('pwIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'bi bi-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'bi bi-eye';
        }
    });

    // One-click fill credentials
    document.querySelectorAll('.demo-fill-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('email').value    = this.dataset.email;
            document.getElementById('password').value = this.dataset.pass;
            document.getElementById('email').focus();
        });
    });
</script>
</body>
</html>
