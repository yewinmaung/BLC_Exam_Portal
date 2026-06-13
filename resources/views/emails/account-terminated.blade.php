@extends('emails.layout')
@section('subject', 'Your Account Has Been Suspended')
@section('content')
<h2>⚠️ Account Suspended</h2>
<p>Dear <strong>{{ $user->name }}</strong>,</p>
<p>We regret to inform you that your account on <strong>{{ config('app.name') }}</strong> has been suspended by an administrator.</p>

<div class="info-box">
    <strong>Account:</strong> {{ $user->email }}<br>
    <strong>Date:</strong> {{ now()->format('M d, Y H:i') }}
</div>

<p>If you believe this is a mistake or would like to appeal this decision, please contact your institution's administration office directly.</p>

<hr class="divider">
<p style="font-size:13px;color:#6b7280">
    This action was taken by a system administrator. Please do not reply to this automated message.
</p>
@endsection
