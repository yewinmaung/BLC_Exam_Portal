@extends('emails.layout')
@section('subject', '⚠️ Exam Security Warning — Student Flagged')
@section('content')
<?php
    $student  = $attempt->student  ?? null;
    $exam     = $attempt->exam     ?? null;
    $course   = $exam?->course     ?? null;
    $lastLog  = $attempt->cheatingLogs?->last() ?? null;
?>

<h2 style="color:#b45309">⚠️ Exam Security Warning</h2>
<p>A student has received a second security warning during an online examination. Your instructor account and/or admin account has been notified as required by policy.</p>

{{-- Student & Exam Info --}}
<div class="info-box">
    <strong>Student:</strong> {{ $student?->name ?? '—' }}<br>
    <strong>Exam:</strong> {{ $exam?->title ?? '—' }}<br>
    <strong>Course:</strong> {{ $course?->title ?? '—' }}<br>
    <strong>Warning Count:</strong> {{ $attempt->warning_count ?? '—' }}<br>
    <strong>Exam Session ID:</strong> #{{ $attempt->id ?? '—' }}<br>
    <strong>Date &amp; Time:</strong> {{ now()->format('M d, Y H:i') }}<br>
    <strong>Status:</strong> In Progress — student continues
</div>

{{-- Latest violation type --}}
@if($lastLog)
<div class="info-box" style="border-color:#d97706;background:#fffbeb;color:#78350f">
    <strong>Latest Violation:</strong> {{ str_replace('_', ' ', ucfirst($lastLog->violation_type)) }}<br>
    @if($lastLog->details)<strong>Details:</strong> {{ $lastLog->details }}<br>@endif
    <strong>IP Address:</strong> {{ $lastLog->ip_address ?? '—' }}
</div>
@endif

<p>The student may continue their exam. If a third violation occurs, the exam will be automatically locked pending your review.</p>

<p style="text-align:center">
    <a href="{{ url('/admin/cheating-logs') }}" class="btn" style="background:#b45309">View Cheating Logs →</a>
</p>
@endsection
