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
  .greeting { font-size:0.95rem;color:#1a2540;font-weight:600;margin-bottom:16px; }
  .message-card {
    background:#f0f4ff;border:1.5px solid #c7d2fe;border-radius:12px;
    padding:20px 22px;margin-bottom:24px;
  }
  .message-text { font-size:0.88rem;color:#374151;line-height:1.8;white-space:pre-line; }
  .divider { border:none;border-top:1px solid #e8eaf2;margin:24px 0; }
  .meta-row { display:flex;gap:32px;flex-wrap:wrap;margin-bottom:4px; }
  .meta-item { font-size:0.77rem;color:#6b7280; }
  .meta-item strong { display:block;font-size:0.69rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:2px; }
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

    <div class="greeting">Message from Admin</div>

    <div class="message-card">
      <div class="message-text">{{ $body }}</div>
    </div>

    <hr class="divider">

    <div class="meta-row">
      <div class="meta-item">
        <strong>Sent Date</strong>
        {{ $sentAt->format('d F Y') }}
      </div>
      <div class="meta-item">
        <strong>Sent Time</strong>
        {{ $sentAt->format('h:i A') }}
      </div>
    </div>

  </div>

  <div class="footer">
    {{ config('app.name', 'Believe Learning Center') }}<br>
    This is an automated message. Please do not reply directly to this email.
  </div>

</div>
</body>
</html>
