@extends('emails.layout')
@section('subject', 'New Exam Available: '.$exam->title)
@section('content')
<h2>📝 New Exam Available</h2>
<p>A new exam has been published and is now available for you to take.</p>

<div class="info-box">
    <strong>Exam:</strong> {{ $exam->title }}<br>
    <strong>Course:</strong> {{ $exam->course->title ?? '—' }}<br>
    <strong>Total Marks:</strong> {{ $exam->total_marks }}<br>
    <strong>Passing Marks:</strong> {{ $exam->passing_marks }}
</div>

<p>Please log in to your student portal to view the schedule and begin the exam when it opens.</p>

<p style="text-align:center">
    <a href="{{ config('app.url') }}/student/exams" class="btn">Go to My Exams →</a>
</p>

<hr class="divider">
<p style="font-size:13px;color:#6b7280">
    Good luck! Ensure you have a stable internet connection before starting.
</p>
@endsection
