@extends('emails.layout')
@section('subject', 'Welcome to '.config('app.name').'!')
@section('content')
<h2>👋 Welcome to the University Portal!</h2>
<p>Dear <strong>{{ $user->name }}</strong>,</p>
<p>Your account has been created successfully on <strong>{{ config('app.name') }}</strong>. You can now log in and access your courses and exams.</p>

<div class="info-box">
    <strong>Login Email:</strong> {{ $user->email }}<br>
    <strong>Portal URL:</strong> <a href="{{ config('app.url') }}" style="color:#3730a3">{{ config('app.url') }}</a>
</div>

<p style="text-align:center;margin-top:24px">
    <a href="{{ config('app.url') }}/login" class="btn">Log In Now →</a>
</p>

<hr class="divider">
<p style="font-size:13px;color:#6b7280">
    If you have any questions, please contact your institution's administration office.
</p>
@endsection
