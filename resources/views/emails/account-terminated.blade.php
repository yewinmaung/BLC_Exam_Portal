<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Account Suspended</title>
<style>
  body { margin: 0; padding: 0; background: #f4f6fb; font-family: 'Inter', Arial, sans-serif; }
  .wrap { max-width: 520px; margin: 40px auto; background: #fff; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 24px rgba(11,42,91,0.10); }
  .header { background: linear-gradient(135deg, #4c0519, #9f1239); padding: 32px 36px 24px; text-align: center; }
  .header h1 { color: #fff; font-size: 1.3rem; font-weight: 800; margin: 0; letter-spacing: 0.01em; }
  .header p  { color: rgba(255,255,255,0.75); font-size: 0.82rem; margin: 6px 0 0; }
  .body  { padding: 32px 36px; }
  .greeting { font-size: 0.95rem; color: #1a2540; font-weight: 600; margin-bottom: 12px; }
  .text  { font-size: 0.88rem; color: #4b5563; line-height: 1.65; margin-bottom: 24px; }
  .alert-box { background: #fef2f2; border: 1.5px solid #fecaca; border-radius: 12px; padding: 18px 20px; text-align: center; margin-bottom: 24px; }
  .alert-icon { font-size: 2rem; margin-bottom: 8px; }
  .alert-label { font-size: 0.78rem; font-weight: 700; color: #991b1b; text-transform: uppercase; letter-spacing: 0.07em; }
  .info-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 18px; font-size: 0.82rem; color: #4b5563; margin-bottom: 24px; }
  .info-box strong { color: #1a2540; }
  .warning { background: #fff7ed; border: 1px solid #fed7aa; border-radius: 8px; padding: 12px 16px; font-size: 0.8rem; color: #92400e; margin-bottom: 24px; }
  .footer { border-top: 1px solid #f0f0f0; padding: 18px 36px; text-align: center; font-size: 0.73rem; color: #9ca3af; }
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h1>{{ config('app.name', 'Believe Exam') }}</h1>
    <p>Account Suspended</p>
  </div>
  <div class="body">
    <div class="greeting">Hello, {{ $user->name }}</div>

    <div class="alert-box">
      <div class="alert-icon">🚫</div>
      <div class="alert-label">Your account has been suspended</div>
    </div>

    <div class="text">
      This is to inform you that your account associated with
      <strong>{{ $user->email }}</strong> has been suspended by an administrator.
      You will not be able to log in until your account is reinstated.
    </div>

    <div class="info-box">
      <strong>Account:</strong> {{ $user->email }}<br>
      <strong>Status:</strong> Suspended<br>
      <strong>Date:</strong> {{ now()->format('d M Y \a\t H:i') }}
    </div>

    <div class="warning">
      <strong>Believe this is a mistake?</strong> Please contact your administrator to appeal
      this decision and request account reinstatement.
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
