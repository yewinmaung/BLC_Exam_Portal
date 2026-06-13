<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('subject', config('app.name'))</title>
    <style>
        body { margin:0; padding:0; background:#f3f4f6; font-family:Arial,Helvetica,sans-serif; }
        .wrapper { max-width:600px; margin:32px auto; background:#fff; border-radius:10px; border:1px solid #e5e7eb; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.07); }
        .header { background:linear-gradient(135deg,#1e1b6e,#3730a3); padding:28px 32px; text-align:center; }
        .header-logo { color:#fff; font-size:20px; font-weight:800; letter-spacing:-0.5px; }
        .header-tagline { color:rgba(255,255,255,0.75); font-size:12px; margin-top:4px; }
        .body { padding:32px; color:#374151; font-size:15px; line-height:1.7; }
        .body h2 { margin-top:0; color:#1e1b6e; font-size:20px; }
        .body p { margin:0 0 16px; }
        .info-box { background:#f0f4ff; border-left:4px solid #3730a3; border-radius:4px; padding:14px 18px; margin:20px 0; font-size:14px; color:#1e1b6e; }
        .btn { display:inline-block; background:#3730a3; color:#fff !important; padding:12px 28px; border-radius:6px; text-decoration:none; font-weight:700; font-size:14px; margin:8px 0; }
        .footer { background:#f9fafb; padding:18px 32px; text-align:center; font-size:11px; color:#9ca3af; border-top:1px solid #e5e7eb; }
        .divider { border:none; border-top:1px solid #e5e7eb; margin:24px 0; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <div class="header-logo">{{ config('app.name') }}</div>
        <div class="header-tagline">University Examination System</div>
    </div>
    <div class="body">
        @yield('content')
    </div>
    <div class="footer">
        © {{ date('Y') }} {{ config('app.name') }} · This is an automated message, please do not reply directly.<br>
        <a href="{{ config('app.url') }}" style="color:#6b7280;text-decoration:none">{{ config('app.url') }}</a>
    </div>
</div>
</body>
</html>
