<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $subject }}</title>
<style>
  body { margin:0;padding:0;background:#f4f6fb;font-family:'Inter',Arial,sans-serif; }
  .wrap { max-width:520px;margin:40px auto;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 24px rgba(11,42,91,0.10); }
  .header { background:linear-gradient(135deg,#071d40,#2d27a0);padding:32px 36px 24px;text-align:center; }
  .header h1 { color:#fff;font-size:1.3rem;font-weight:800;margin:0;letter-spacing:0.01em; }
  .header p  { color:rgba(255,255,255,0.75);font-size:0.82rem;margin:6px 0 0; }
  .body  { padding:32px 36px; }
  .message-text { font-size:0.88rem;color:#374151;line-height:1.75;margin-bottom:28px; }
  .divider { border:none;border-top:1px solid #e8eaf2;margin:24px 0; }
  .meta-box { background:#f8f9fc;border-radius:8px;padding:12px 16px;font-size:0.78rem;color:#6b7280;margin-bottom:8px; }
  .meta-box strong { color:#374151; }
  .footer { border-top:1px solid #f0f0f0;padding:18px 36px;text-align:center;font-size:0.73rem;color:#9ca3af; }
</style>
</head>
<body>
<div class="wrap">

  <div class="header">
    <h1>{{ config('app.name', 'Believe Learning Center') }}</h1>
    <p>{{ $subject }}</p>
  </div>

  <div class="body">
    <div class="message-text">
      {!! nl2br(e($body)) !!}
    </div>

    <hr class="divider">

    <div class="meta-box">
      <strong>Sent Date:</strong> {{ $sentAt->format('d F Y') }}<br>
      <strong>Sent Time:</strong> {{ $sentAt->format('h:i A') }}
    </div>
  </div>

  <div class="footer">
    {{ config('app.name', 'Believe Learning Center') }}<br>
    This is an automated message. Please do not reply directly to this email.
  </div>

</div>
</body>
</html>
