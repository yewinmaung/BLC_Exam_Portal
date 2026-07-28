<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Your New Temporary Password — {{ config('app.name', 'Believe Exam') }}</title>
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
  .cred-row { display: flex; align-items: center; margin-bottom: 10px; }
  .cred-row:last-child { margin-bottom: 0; }
  .cred-label { font-size: 0.78rem; font-weight: 700; color: #374151; width: 90px; flex-shrink: 0; }
  .cred-value { font-family: 'Courier New', monospace; font-size: 0.9rem; font-weight: 700; color: #2d27a0; background: #fff; border: 1px solid #c7d2fe; border-radius: 6px; padding: 5px 12px; letter-spacing: 0.03em; }
  /* Expiry notice */
  .expiry-box { background: #fffbeb; border: 1.5px solid #fde68a; border-radius: 8px; padding: 12px 16px; font-size: 0.82rem; color: #92400e; margin-bottom: 20px; }
  .expiry-box strong { display: block; margin-bottom: 3px; }
  /* Warning */
  .warning { background: #fff7ed; border: 1.5px solid #fed7aa; border-radius: 8px; padding: 12px 16px; font-size: 0.82rem; color: #92400e; margin-bottom: 24px; }
  .warning strong { display: block; margin-bottom: 3px; }
  /* CTA */
  .cta-wrap { text-align: center; margin: 24px 0; }
  .cta-btn { display: inline-block; background: linear-gradient(135deg, #071d40, #2d27a0); color: #fff; padding: 13px 36px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 0.92rem; letter-spacing: 0.01em; }
  .sign-off { font-size: 0.85rem; color: #6b7280; }
  .footer { border-top: 1px solid #f0f0f0; padding: 18px 36px; text-align: center; font-size: 0.73rem; color: #9ca3af; }
</style>
</head>
<body>
<div class="wrap">

  <div class="header">
    <h1>{{ config('app.name', 'Believe Exam') }}</h1>
    <p>🔑 New Temporary Password Issued</p>
  </div>

  <div class="body">

    <div class="greeting">Hello, {{ $userName }}</div>

    <div class="text">
      A new temporary password has been generated for your account on the
      <strong>{{ config('app.name', 'Believe Exam') }}</strong> portal.
      Please use the credentials below to sign in.
    </div>

    <div class="cred-box">
      <div class="cred-box-title">🔑 Your New Login Credentials</div>
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

    <div class="expiry-box">
      <strong>⏰ Password Expiry</strong>
      This temporary password is valid for <strong>24 hours</strong> from the time it was issued.
      It will expire on <strong>{{ $expiresAt }}</strong>.
      After expiry you will need to request another temporary password.
    </div>

    <div class="warning">
      <strong>⚠ You must change this password after logging in.</strong>
      You will be prompted to set a new personal password immediately after signing in.
      Keep your new password safe and do not share it with anyone.
    </div>

    <div class="cta-wrap">
      <a href="{{ config('app.url') }}/login" class="cta-btn">Sign In to Portal →</a>
    </div>

    <div class="sign-off">
      If you did not request this, please contact your administrator immediately.<br><br>
      — The {{ config('app.name', 'Believe Exam') }} Team
    </div>

  </div>

  <div class="footer">
    {{ config('app.name', 'Believe Exam') }}<br>
    This is an automated security notification. Please do not reply directly to this email.
  </div>

</div>
</body>
</html>
