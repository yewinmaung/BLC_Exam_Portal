@extends('emails.layout')
@section('subject', 'Cheating Alert — Exam Terminated')
@section('content')
<h2 style="color:#dc2626">🚨 Cheating Detected — Exam Terminated</h2>
<p>An exam attempt has been automatically terminated due to detected cheating violations.</p>

<div class="info-box" style="border-color:#dc2626;background:#fef2f2;color:#7f1d1d">
    <strong>Student:</strong> {{ $attempt->student->name ?? '—' }} ({{ $attempt->student->email ?? '—' }})<br>
    <strong>Exam:</strong> {{ $attempt->exam->title ?? '—' }}<br>
    <strong>Violations:</strong> {{ $attempt->violations ?? 'N/A' }}<br>
    <strong>Terminated At:</strong> {{ now()->format('M d, Y H:i') }}
</div>

<p>Please review the cheating logs in the admin panel for full details.</p>

<p style="text-align:center">
    <a href="{{ route('admin.cheating-logs') }}" class="btn" style="background:#dc2626">View Cheating Logs →</a>
</p>
@endsection
