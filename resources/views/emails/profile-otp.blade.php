<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Password Change Verification Code</title>
<style>
  body { margin: 0; padding: 0; background: #f4f6fb; font-family: 'Inter', Arial, sans-serif; }
  .wrap { max-width: 520px; margin: 40px auto; background: #fff; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 24px rgba(11,42,91,0.10); }
  .header { background: linear-gradient(135deg, #071d40, #2d27a0); padding: 32px 36px 24px; text-align: center; }
  .header h1 { color: #fff; font-size: 1.3rem; font-weight: 800; margin: 0; letter-spacing: 0.01em; }
  .header p  { color: rgba(255,255,255,0.75); font-size: 0.82rem; margin: 6px 0 0; }
  .body  { padding: 32px 36px; }
  .greeting { font-size: 0.95rem; color: #1a2540; font-weight: 600; margin-bottom: 12px; }
  .text  { font-size: 0.88rem; color: #4b5563; line-height: 1.65; margin-bottom: 24px; }
  .otp-box { background: #f0f4ff; border: 2px solid #c7d2fe; border-radius: 12px; padding: 20px; text-align: center; margin-bottom: 24px; }
  .otp-label { font-size: 0.72rem; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 8px; }
  .otp-code  { font-size: 2.8rem; font-weight: 900; letter-spacing: 0.25em; color: #2d27a0; font-family: 'Courier New', monospace; }
  .expire { font-size: 0.78rem; color: #9ca3af; text-align: center; margin-bottom: 24px; }
  .warning { background: #fff7ed; border: 1px solid #fed7aa; border-radius: 8px; padding: 12px 16px; font-size: 0.8rem; color: #92400e; margin-bottom: 24px; }
  .footer { border-top: 1px solid #f0f0f0; padding: 18px 36px; text-align: center; font-size: 0.73rem; color: #9ca3af; }
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h1>{{ config('app.name', 'Believe Exam') }}</h1>
    <p>Password Change Verification</p>
  </div>
  <div class="body">
    <div class="greeting">Hello, {{ $user->name }}</div>
    <div class="text">
      We received a request to change the password for your account
      (<strong>{{ $user->email }}</strong>). Enter the verification code below to confirm this action.
    </div>

    <div class="otp-box">
      <div class="otp-label">Your verification code</div>
      <div class="otp-code">{{ $code }}</div>
    </div>

    <div class="expire">
      ⏱ This code expires in <strong>5 minutes</strong>.
    </div>

    <div class="warning">
      <strong>Didn't request this?</strong> If you did not initiate a password change, please
      ignore this email. Your password will remain unchanged.
    </div>

    <div class="text" style="margin-bottom:0;">
      — The {{ config('app.name', 'Believe Exam') }} Team
    </div>
  </div>
  <div class="footer">
    This is an automated message. Please do not reply directly to this email.
  </div>
</div>
</body>
</html>
