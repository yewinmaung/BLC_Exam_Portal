<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Believe Learning Center — University Management System</title>
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

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(160deg, var(--navy-dark) 0%, var(--navy) 50%, #0d3268 100%);
            min-height: 100vh;
            color: #fff;
            overflow-x: hidden;
        }

        /* ── Background decorations ── */
        .bg-deco {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }

        .bg-deco .circle {
            position: absolute;
            border-radius: 50%;
            border: 1px solid rgba(212,165,28,0.10);
        }

        .bg-deco .c1 { width: 700px; height: 700px; top: -250px; right: -200px; }
        .bg-deco .c2 { width: 500px; height: 500px; bottom: -200px; left: -150px; }
        .bg-deco .c3 { width: 300px; height: 300px; top: 40%; left: 10%; border-color: rgba(212,165,28,0.06); }

        .bg-deco .glow {
            position: absolute;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(212,165,28,0.12) 0%, transparent 65%);
        }

        .bg-deco .g1 { width: 800px; height: 800px; top: -200px; right: -200px; }
        .bg-deco .g2 { width: 600px; height: 600px; bottom: -200px; left: -100px; }

        /* ── Navbar ── */
        .navbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 100;
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(7,29,64,0.7);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(255,255,255,0.06);
            transition: all 0.3s;
        }

        .navbar.scrolled {
            padding: 0.7rem 2rem;
            background: rgba(7,29,64,0.92);
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
        }

        .nav-brand img {
            width: 44px; height: 44px;
            object-fit: contain;
            filter: drop-shadow(0 2px 8px rgba(212,165,28,0.4));
        }

        .nav-brand-text strong {
            display: block;
            font-size: 0.95rem;
            font-weight: 700;
            color: #fff;
            line-height: 1.1;
        }

        .nav-brand-text span {
            font-size: 0.7rem;
            color: var(--gold);
            font-weight: 500;
        }

        .nav-signin {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.55rem 1.4rem;
            background: var(--gold);
            color: var(--navy-dark);
            border: none;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 16px rgba(212,165,28,0.35);
        }

        .nav-signin:hover {
            background: var(--gold-2);
            transform: translateY(-1px);
            box-shadow: 0 6px 24px rgba(212,165,28,0.5);
            color: var(--navy-dark);
        }

        /* ── Hero ── */
        .hero {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 7rem 1.5rem 4rem;
        }

        /* Logo */
        .hero-logo-wrap {
            position: relative;
            display: inline-block;
            margin-bottom: 2rem;
        }

        .hero-logo-wrap img {
            width: 120px; height: 120px;
            object-fit: contain;
            filter: drop-shadow(0 8px 32px rgba(212,165,28,0.5))
                    drop-shadow(0 2px 12px rgba(0,0,0,0.4));
            animation: float 4s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50%       { transform: translateY(-10px); }
        }

        .hero-logo-ring {
            position: absolute;
            inset: -18px;
            border-radius: 50%;
            border: 1.5px solid rgba(212,165,28,0.22);
            animation: pulse-ring 3s ease-in-out infinite;
        }

        .hero-logo-ring-2 {
            position: absolute;
            inset: -36px;
            border-radius: 50%;
            border: 1px solid rgba(212,165,28,0.10);
            animation: pulse-ring 3s ease-in-out infinite 1s;
        }

        @keyframes pulse-ring {
            0%, 100% { opacity: 0.5; transform: scale(1); }
            50%       { opacity: 1;   transform: scale(1.05); }
        }

        /* Hero text */
        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(212,165,28,0.12);
            border: 1px solid rgba(212,165,28,0.25);
            border-radius: 50px;
            padding: 0.3rem 1rem;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--gold-2);
            letter-spacing: 0.5px;
            margin-bottom: 1.25rem;
        }

        .hero h1 {
            font-size: clamp(2.5rem, 6vw, 4.5rem);
            font-weight: 900;
            line-height: 1.08;
            letter-spacing: -1px;
            margin-bottom: 0.25rem;
            color: #fff;
        }

        .hero h1 .gold-text {
            background: linear-gradient(90deg, var(--gold), var(--gold-2), var(--gold));
            background-size: 200% auto;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: shimmer 3s linear infinite;
        }

        @keyframes shimmer {
            0%   { background-position: 0% center; }
            100% { background-position: 200% center; }
        }

        .hero-subtitle {
            font-size: clamp(1rem, 2vw, 1.2rem);
            font-weight: 500;
            color: rgba(255,255,255,0.7);
            margin-bottom: 0.75rem;
        }

        .hero-desc {
            font-size: 0.95rem;
            color: rgba(255,255,255,0.5);
            max-width: 520px;
            margin: 0 auto 2.5rem;
            line-height: 1.7;
        }

        /* CTA button */
        .btn-cta {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.9rem 2.2rem;
            background: var(--gold);
            color: var(--navy-dark);
            border: none;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.25s ease;
            box-shadow: 0 6px 28px rgba(212,165,28,0.4);
            letter-spacing: 0.2px;
        }

        .btn-cta:hover {
            background: var(--gold-2);
            transform: translateY(-2px);
            box-shadow: 0 10px 40px rgba(212,165,28,0.55);
            color: var(--navy-dark);
        }

        .btn-cta:active { transform: translateY(0); }

        /* Stats row */
        .hero-stats {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 2.5rem;
            margin-top: 3.5rem;
            flex-wrap: wrap;
        }

        .stat-item {
            text-align: center;
        }

        .stat-item .num {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--gold-2);
            line-height: 1;
        }

        .stat-item .lbl {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.5);
            font-weight: 500;
            margin-top: 0.2rem;
        }

        .stat-divider {
            width: 1px;
            height: 36px;
            background: rgba(255,255,255,0.12);
        }

        /* Feature cards row */
        .features {
            position: relative;
            z-index: 1;
            padding: 0 1.5rem 5rem;
            max-width: 900px;
            margin: 0 auto;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }

        .feature-card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.09);
            border-radius: 16px;
            padding: 1.4rem 1.2rem;
            text-align: center;
            backdrop-filter: blur(8px);
            transition: all 0.25s ease;
        }

        .feature-card:hover {
            background: rgba(255,255,255,0.09);
            border-color: rgba(212,165,28,0.25);
            transform: translateY(-4px);
        }

        .feature-card .fc-icon {
            width: 48px; height: 48px;
            border-radius: 12px;
            background: rgba(212,165,28,0.15);
            border: 1px solid rgba(212,165,28,0.25);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem;
            color: var(--gold-2);
            margin: 0 auto 0.9rem;
        }

        .feature-card h3 {
            font-size: 0.9rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 0.35rem;
        }

        .feature-card p {
            font-size: 0.78rem;
            color: rgba(255,255,255,0.5);
            line-height: 1.5;
        }

        /* Footer */
        .footer {
            position: relative;
            z-index: 1;
            text-align: center;
            padding: 1.5rem;
            border-top: 1px solid rgba(255,255,255,0.06);
            font-size: 0.78rem;
            color: rgba(255,255,255,0.35);
        }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            .navbar { padding: 0.85rem 1.25rem; }
            .nav-brand-text strong { font-size: 0.82rem; }
            .hero { padding: 6rem 1.25rem 3rem; }
            .hero-logo-wrap img { width: 90px; height: 90px; }
            .hero-stats { gap: 1.5rem; }
            .stat-divider { display: none; }
            .features-grid { grid-template-columns: 1fr; gap: 0.75rem; }
            .features { padding: 0 1.25rem 3rem; }
        }

        @media (min-width: 769px) and (max-width: 1024px) {
            .hero h1 { font-size: 3rem; }
            .features-grid { grid-template-columns: repeat(3, 1fr); }
        }
    </style>
</head>
<body>

<div class="bg-deco">
    <div class="circle c1"></div>
    <div class="circle c2"></div>
    <div class="circle c3"></div>
    <div class="glow g1"></div>
    <div class="glow g2"></div>
</div>

{{-- ── Navbar ── --}}
<nav class="navbar" id="navbar">
    <a href="{{ url('/') }}" class="nav-brand">
        <img src="{{ asset('images/logo.png') }}" alt="Believe Learning Center">
        <div class="nav-brand-text">
            <strong>Believe Learning Center</strong>
            <span>University Management System</span>
        </div>
    </a>
    <a href="{{ route('login') }}" class="nav-signin">
        <i class="bi bi-box-arrow-in-right"></i>
        Sign In
    </a>
</nav>

{{-- ── Hero ── --}}
<section class="hero">

    <div class="hero-logo-wrap">
        <div class="hero-logo-ring-2"></div>
        <div class="hero-logo-ring"></div>
        <img src="{{ asset('images/logo.png') }}" alt="Believe Learning Center">
    </div>

    <div class="hero-eyebrow">
        <i class="bi bi-mortarboard-fill"></i>
        University Management System
    </div>

    <h1>
        Believe Learning<br>
        <span class="gold-text">Center</span>
    </h1>

    <p class="hero-subtitle">University Management System</p>

    <p class="hero-desc">
        A complete platform for managing students, teachers, courses,
        exams, attendance, and results — all in one place.
    </p>

    <a href="{{ route('login') }}" class="btn-cta">
        <i class="bi bi-box-arrow-in-right"></i>
        Sign In to Dashboard
    </a>

    <div class="hero-stats">
        <div class="stat-item">
            <div class="num">3</div>
            <div class="lbl">User Roles</div>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
            <div class="num">100%</div>
            <div class="lbl">Secure Exams</div>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
            <div class="num">∞</div>
            <div class="lbl">Courses</div>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
            <div class="num">24/7</div>
            <div class="lbl">Access</div>
        </div>
    </div>

</section>

{{-- ── Features ── --}}
<section class="features">
    <div class="features-grid">
        <div class="feature-card">
            <div class="fc-icon"><i class="bi bi-shield-lock-fill"></i></div>
            <h3>Secure Examinations</h3>
            <p>End-to-end encrypted questions with anti-cheating detection</p>
        </div>
        <div class="feature-card">
            <div class="fc-icon"><i class="bi bi-people-fill"></i></div>
            <h3>Multi-Role Access</h3>
            <p>Separate portals for Admins, Teachers, and Students</p>
        </div>
        <div class="feature-card">
            <div class="fc-icon"><i class="bi bi-bar-chart-line-fill"></i></div>
            <h3>Real-time Results</h3>
            <p>Instant grading, performance tracking and detailed reports</p>
        </div>
    </div>
</section>

<footer class="footer">
    © {{ date('Y') }} Believe Learning Center. All rights reserved.
</footer>

<script>
    // Navbar scroll effect
    window.addEventListener('scroll', () => {
        document.getElementById('navbar').classList.toggle('scrolled', window.scrollY > 40);
    });
</script>
</body>
</html>
