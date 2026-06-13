@extends('emails.layout')
@section('subject', 'Exam Pending Approval: '.$exam->title)
@section('content')
<h2>📋 New Exam Pending Your Approval</h2>
<p>A teacher has submitted an exam for your review and approval.</p>

<div class="info-box">
    <strong>Exam:</strong> {{ $exam->title }}<br>
    <strong>Teacher:</strong> {{ $exam->teacher->name ?? '—' }}<br>
    <strong>Course:</strong> {{ $exam->course->title ?? '—' }}<br>
    <strong>Questions:</strong> {{ $exam->questions->count() }}<br>
    <strong>Total Marks:</strong> {{ $exam->total_marks }}<br>
    <strong>Submitted:</strong> {{ $exam->submitted_at?->format('M d, Y H:i') ?? now()->format('M d, Y H:i') }}
</div>

<p style="text-align:center">
    <a href="{{ route('admin.exams.show', $exam) }}" class="btn">Review Exam →</a>
</p>
@endsection
