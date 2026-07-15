<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Password Changed</title>
<style>
  body { margin: 0; padding: 0; background: #f4f6fb; font-family: 'Inter', Arial, sans-serif; }
  .wrap { max-width: 520px; margin: 40px auto; background: #fff; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 24px rgba(11,42,91,0.10); }
  .header { background: linear-gradient(135deg, #064e3b, #059669); padding: 32px 36px 24px; text-align: center; }
  .header h1 { color: #fff; font-size: 1.3rem; font-weight: 800; margin: 0; }
  .header p  { color: rgba(255,255,255,0.75); font-size: 0.82rem; margin: 6px 0 0; }
  .body  { padding: 32px 36px; }
  .greeting { font-size: 0.95rem; color: #1a2540; font-weight: 600; margin-bottom: 12px; }
  .text  { font-size: 0.88rem; color: #4b5563; line-height: 1.65; margin-bottom: 20px; }
  .success-badge { display: inline-flex; align-items: center; gap: 8px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 10px 16px; font-size: 0.85rem; color: #166534; font-weight: 600; margin-bottom: 24px; }
  .info-row { display: flex; gap: 8px; font-size: 0.82rem; color: #6b7280; margin-bottom: 8px; }
  .warning { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px 16px; font-size: 0.8rem; color: #991b1b; margin-bottom: 24px; }
  .footer { border-top: 1px solid #f0f0f0; padding: 18px 36px; text-align: center; font-size: 0.73rem; color: #9ca3af; }
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h1>{{ config('app.name', 'Believe Exam') }}</h1>
    <p>Password Changed Successfully</p>
  </div>
  <div class="body">
    <div class="greeting">Hello, {{ $user->name }}</div>

    <div class="success-badge">
      ✅ Your password has been changed successfully.
    </div>

    <div class="text">
      This is a confirmation that the password for your account
      (<strong>{{ $user->email }}</strong>) was changed on
      <strong>{{ now()->format('d M Y \a\t H:i') }}</strong>.
    </div>

    <div class="warning">
      <strong>Wasn't you?</strong> If you did not make this change, please contact your
      administrator immediately to secure your account.
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
