<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Forgot Password — Believe Learning Center</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --navy:#0b2a5b; --navy-2:#0f3a7a; --navy-dark:#071d40; --gold:#d4a51c; }

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

        .login-brand { text-align: center; margin-bottom: 1.75rem; }
        .login-brand img {
            width: 72px; height: 72px; object-fit: contain;
            filter: drop-shadow(0 4px 16px rgba(11,42,91,0.18));
            margin-bottom: 0.75rem;
        }
        .login-brand h1 { font-size: 1.45rem; font-weight: 800; color: var(--navy); margin-bottom: 0.15rem; letter-spacing: -0.3px; }
        .login-brand .sub { font-size: 0.82rem; color: var(--gold); font-weight: 600; }

        .login-card {
            background: #fff;
            border-radius: 20px;
            padding: 2.25rem 2rem;
            box-shadow: 0 8px 48px rgba(11,42,91,0.12), 0 1px 4px rgba(11,42,91,0.06);
            border: 1px solid rgba(11,42,91,0.06);
        }
        .login-card h2 { font-size: 1.5rem; font-weight: 800; color: var(--navy); margin-bottom: 0.3rem; letter-spacing: -0.3px; }
        .login-card .card-sub { font-size: 0.85rem; color: #6b7280; margin-bottom: 1.75rem; }

        .field-group { margin-bottom: 1.1rem; }
        .field-label { display: block; font-size: 0.82rem; font-weight: 600; color: #374151; margin-bottom: 0.4rem; }
        .field-input {
            width: 100%; padding: 0.72rem 1rem;
            border: 1.5px solid #e2e8f0; border-radius: 10px;
            font-size: 0.9rem; font-family: 'Inter', sans-serif; color: #1a2540; background: #fff;
            outline: none; transition: border-color 0.18s, box-shadow 0.18s;
        }
        .field-input:focus { border-color: var(--navy-2); box-shadow: 0 0 0 3.5px rgba(15,58,122,0.10); }
        .field-input::placeholder { color: #c4cad6; }

        .btn-submit {
            width: 100%; padding: 0.82rem;
            background: var(--navy-2); color: #fff; border: none; border-radius: 10px;
            font-size: 0.95rem; font-weight: 700; font-family: 'Inter', sans-serif;
            cursor: pointer; transition: all 0.2s ease; letter-spacing: 0.2px;
            box-shadow: 0 4px 16px rgba(11,42,91,0.28); margin-top: 0.5rem;
        }
        .btn-submit:hover { background: var(--navy-dark); box-shadow: 0 6px 24px rgba(11,42,91,0.4); transform: translateY(-1px); }
        .btn-submit:active { transform: translateY(0); }

        .back-row { text-align: center; margin-top: 1.25rem; font-size: 0.83rem; color: #6b7280; }
        .back-row a { color: var(--navy-2); font-weight: 600; text-decoration: none; }
        .back-row a:hover { text-decoration: underline; }

        .alert-err {
            background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px;
            padding: 0.75rem 1rem; margin-bottom: 1.25rem; font-size: 0.83rem; color: #991b1b;
            display: flex; align-items: flex-start; gap: 0.5rem;
        }
        .alert-info {
            background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px;
            padding: 0.75rem 1rem; margin-bottom: 1.25rem; font-size: 0.83rem; color: #1e40af;
            display: flex; align-items: flex-start; gap: 0.5rem;
        }

        /* Icon circle */
        .icon-circle {
            width: 56px; height: 56px; border-radius: 50%;
            background: #eef2ff;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.25rem;
            font-size: 1.5rem; color: var(--navy-2);
        }

        .login-footer { text-align: center; margin-top: 1.5rem; font-size: 0.75rem; color: #9ca3af; }

        @media (max-width: 480px) {
            body { padding: 1rem; }
            .login-card { padding: 1.75rem 1.25rem; border-radius: 16px; }
            .login-brand img { width: 60px; height: 60px; }
            .login-brand h1 { font-size: 1.25rem; }
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

        <div class="icon-circle">
            <i class="bi bi-envelope-open-fill"></i>
        </div>

        <h2 style="text-align:center;">Forgot Password?</h2>
        <p class="card-sub" style="text-align:center;">
            Enter your registered email address. We'll send a 6-digit verification code to reset your password.
        </p>

        {{-- Info flash --}}
        @if(session('info'))
        <div class="alert-info">
            <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
            <div>{{ session('info') }}</div>
        </div>
        @endif

        {{-- Validation errors --}}
        @if($errors->any())
        <div class="alert-err">
            <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
            <div>@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
        </div>
        @endif

        <form method="POST" action="{{ route('forgot-password.send') }}">
            @csrf

            <div class="field-group">
                <label class="field-label" for="email">Email Address</label>
                <input type="email" id="email" name="email" class="field-input"
                       value="{{ old('email') }}"
                       placeholder="Enter your registered email"
                       required autofocus autocomplete="email">
            </div>

            <button type="submit" class="btn-submit">
                <i class="bi bi-send me-1"></i> Send Verification Code
            </button>
        </form>

        <div class="back-row">
            <a href="{{ route('login') }}">
                <i class="bi bi-arrow-left me-1"></i>Back to Sign In
            </a>
        </div>

    </div>

    <div class="login-footer">
        © {{ date('Y') }} Believe Learning Center. All rights reserved.
    </div>

</div>

</body>
</html>
