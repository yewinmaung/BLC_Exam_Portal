<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Welcome to {{ config('app.name', 'Believe Exam') }}</title>
<style>
  body { margin: 0; padding: 0; background: #f4f6fb; font-family: 'Inter', Arial, sans-serif; }
  .wrap { max-width: 520px; margin: 40px auto; background: #fff; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 24px rgba(11,42,91,0.10); }
  .header { background: linear-gradient(135deg, #071d40, #2d27a0); padding: 32px 36px 24px; text-align: center; }
  .header h1 { color: #fff; font-size: 1.3rem; font-weight: 800; margin: 0; letter-spacing: 0.01em; }
  .header p  { color: rgba(255,255,255,0.80); font-size: 0.84rem; margin: 8px 0 0; }
  .body  { padding: 32px 36px; }
  .greeting { font-size: 0.95rem; color: #1a2540; font-weight: 600; margin-bottom: 12px; }
  .text  { font-size: 0.88rem; color: #4b5563; line-height: 1.7; margin-bottom: 24px; }
  /* Credential box */
  .cred-box { background: #f0f4ff; border: 2px solid #c7d2fe; border-radius: 12px; padding: 20px 22px; margin-bottom: 24px; }
  .cred-box-title { font-size: 0.72rem; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.07em; margin-bottom: 14px; }
  .cred-row { display: flex; align-items: center; gap: 0; margin-bottom: 10px; }
  .cred-row:last-child { margin-bottom: 0; }
  .cred-label { font-size: 0.78rem; font-weight: 700; color: #374151; width: 90px; flex-shrink: 0; }
  .cred-value { font-family: 'Courier New', monospace; font-size: 0.9rem; font-weight: 700; color: #2d27a0; background: #fff; border: 1px solid #c7d2fe; border-radius: 6px; padding: 5px 12px; letter-spacing: 0.03em; }
  /* Role badge */
  .role-badge { display: inline-block; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; padding: 3px 10px; border-radius: 20px; margin-left: 8px; vertical-align: middle; }
  .role-badge.teacher { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
  .role-badge.student { background: #fef9c3; color: #854d0e; border: 1px solid #fde68a; }
  /* Warning notice */
  .warning { background: #fff7ed; border: 1.5px solid #fed7aa; border-radius: 8px; padding: 12px 16px; font-size: 0.82rem; color: #92400e; margin-bottom: 24px; }
  .warning strong { display: block; margin-bottom: 3px; }
  /* CTA button */
  .cta-wrap { text-align: center; margin: 24px 0; }
  .cta-btn { display: inline-block; background: linear-gradient(135deg, #071d40, #2d27a0); color: #fff; padding: 13px 36px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 0.92rem; letter-spacing: 0.01em; }
  .steps { margin-bottom: 24px; }
  .step { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 10px; font-size: 0.85rem; color: #4b5563; }
  .step-num { flex-shrink: 0; width: 22px; height: 22px; background: #2d27a0; color: #fff; border-radius: 50%; font-size: 0.72rem; font-weight: 700; display: flex; align-items: center; justify-content: center; margin-top: 1px; }
  .sign-off { font-size: 0.85rem; color: #6b7280; }
  .footer { border-top: 1px solid #f0f0f0; padding: 18px 36px; text-align: center; font-size: 0.73rem; color: #9ca3af; }
</style>
</head>
<body>
<div class="wrap">

  {{-- Header --}}
  <div class="header">
    <h1>{{ config('app.name', 'Believe Exam') }}</h1>
    <p>🎉 Welcome — Your Account Is Ready</p>
  </div>

  {{-- Body --}}
  <div class="body">

    <div class="greeting">
      Hello, {{ $userName }}
      @if($userRole === 'teacher')
        <span class="role-badge teacher">Teacher</span>
      @elseif($userRole === 'student')
        <span class="role-badge student">Student</span>
      @endif
    </div>

    <div class="text">
      Your account has been created on the <strong>{{ config('app.name', 'Believe Exam') }}</strong> portal.
      Please use the temporary credentials below to log in for the first time.
    </div>

    {{-- Credentials --}}
    <div class="cred-box">
      <div class="cred-box-title">🔑 Your Login Credentials</div>
      <div class="cred-row">
        <span class="cred-label">Portal URL</span>
        <a href="{{ config('app.url') }}" class="cred-value" style="color:#2d27a0;text-decoration:none">
          {{ config('app.url') }}
        </a>
      </div>
      <div class="cred-row">
        <span class="cred-label">Email</span>
        <span class="cred-value">{{ $userEmail }}</span>
      </div>
      <div class="cred-row">
        <span class="cred-label">Password</span>
        <span class="cred-value">{{ $temporaryPassword }}</span>
      </div>
    </div>

    {{-- Security notice --}}
    <div class="warning">
      <strong>⚠ You must change your password immediately after logging in.</strong>
      This temporary password will no longer work once you set a new one.
      Keep your new password safe and do not share it with anyone.
    </div>

    {{-- Steps --}}
    <div class="steps">
      <div class="step">
        <div class="step-num">1</div>
        <div>Visit the portal and click <strong>Sign In</strong></div>
      </div>
      <div class="step">
        <div class="step-num">2</div>
        <div>Enter your email address and the temporary password above</div>
      </div>
      <div class="step">
        <div class="step-num">3</div>
        <div>You will be prompted to <strong>set a new password</strong> immediately</div>
      </div>
      <div class="step">
        <div class="step-num">4</div>
        <div>After setting your password, you will have full access to your dashboard</div>
      </div>
    </div>

    {{-- CTA --}}
    <div class="cta-wrap">
      <a href="{{ config('app.url') }}/login" class="cta-btn">Sign In to Portal →</a>
    </div>

    <div class="sign-off">
      If you have any issues logging in, please contact your administrator.<br><br>
      — The {{ config('app.name', 'Believe Exam') }} Team
    </div>

  </div>

  <div class="footer">
    {{ config('app.name', 'Believe Exam') }}<br>
    This is an automated message. Please do not reply directly to this email.
  </div>

</div>
</body>
</html>
