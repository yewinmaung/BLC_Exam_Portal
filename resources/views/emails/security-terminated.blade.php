@extends('emails.layout')
@section('subject', '🚨 [HIGH PRIORITY] Exam Security Incident — Action Required')
@section('content')
<?php
    $student  = $attempt->student  ?? null;
    $exam     = $attempt->exam     ?? null;
    $course   = $exam?->course     ?? null;
    $teacher  = $exam?->teacher    ?? null;

    // Violation timeline from all cheating logs for this attempt.
    $logs = $attempt->cheatingLogs ?? collect();
?>

<h2 style="color:#dc2626">🚨 Exam Locked — Security Incident</h2>
<p>
    A student's exam session has been <strong>automatically locked</strong> after reaching the maximum
    number of security violations. <strong>Immediate review and action is required.</strong>
</p>

{{-- Core incident info --}}
<div class="info-box" style="border-color:#dc2626;background:#fef2f2;color:#7f1d1d">
    <strong>Student Name:</strong> {{ $student?->name ?? '—' }}<br>
    <strong>Student ID:</strong> STU-{{ str_pad($student?->id ?? 0, 4, '0', STR_PAD_LEFT) }}<br>
    <strong>Exam:</strong> {{ $exam?->title ?? '—' }}<br>
    <strong>Course / Subject:</strong> {{ $course?->title ?? '—' }}<br>
    <strong>Responsible Teacher:</strong> {{ $teacher?->name ?? '—' }}<br>
    <strong>Exam Session ID:</strong> #{{ $attempt->id ?? '—' }}<br>
    <strong>Total Violations:</strong> {{ $attempt->warning_count ?? '—' }}<br>
    <strong>Exam Status:</strong>
        @if(($attempt->status ?? '') === 'terminated_pending_review')
            <span style="color:#dc2626;font-weight:700">LOCKED — Pending Review</span>
        @else
            {{ $attempt->status ?? '—' }}
        @endif
    <br>
    <strong>Terminated At:</strong> {{ $attempt->terminated_at?->format('M d, Y H:i') ?? now()->format('M d, Y H:i') }}<br>
    <strong>IP Address:</strong> {{ $logs->last()?->ip_address ?? '—' }}
</div>

{{-- Violation Timeline --}}
@if($logs->isNotEmpty())
<h3 style="color:#1e1b6e;font-size:16px;margin-top:24px">Violation Timeline</h3>
<table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead>
        <tr style="background:#f0f4ff">
            <th style="padding:8px 10px;text-align:left;border:1px solid #e5e7eb">#</th>
            <th style="padding:8px 10px;text-align:left;border:1px solid #e5e7eb">Type</th>
            <th style="padding:8px 10px;text-align:left;border:1px solid #e5e7eb">Time</th>
            <th style="padding:8px 10px;text-align:left;border:1px solid #e5e7eb">IP</th>
        </tr>
    </thead>
    <tbody>
        @foreach($logs as $log)
        <tr>
            <td style="padding:7px 10px;border:1px solid #e5e7eb">{{ $log->warning_number }}</td>
            <td style="padding:7px 10px;border:1px solid #e5e7eb">{{ str_replace('_', ' ', ucfirst($log->violation_type)) }}</td>
            <td style="padding:7px 10px;border:1px solid #e5e7eb">{{ $log->created_at->format('H:i:s') }}</td>
            <td style="padding:7px 10px;border:1px solid #e5e7eb">{{ $log->ip_address ?? '—' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- Action required --}}
<div class="info-box" style="border-color:#b45309;background:#fffbeb;color:#78350f;margin-top:24px">
    <strong>⚠️ Action Required</strong><br>
    Please review the security incident and approve or reject the student's exam session.
    The student cannot continue until a decision is made.
</div>

<p style="text-align:center;margin-top:24px">
    <a href="{{ url('/admin/cheating-logs') }}" class="btn" style="background:#dc2626">Review Incident →</a>
</p>
@endsection
