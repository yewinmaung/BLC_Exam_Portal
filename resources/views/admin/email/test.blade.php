@extends('layouts.app')
@section('title', 'Send Test Email')
@section('page-title', 'Send Test Email')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Email', 'url' => route('admin.email.index')],
        ['label' => 'Test Email'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')
<div style="max-width:600px">
    <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
            <i class="bi bi-send" style="color:var(--blc-royal,#2d27a0)"></i>
            Send a Test Email
        </div>
        <div class="card-body">

            <p class="text-muted mb-4" style="font-size:0.85rem">
                This sends a test email immediately (synchronous, not queued) to verify your SMTP
                configuration. The result will appear as a log entry in
                <a href="{{ route('admin.email.logs') }}">Email Logs</a>.
            </p>

            <form method="POST" action="{{ route('admin.email.test.send') }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label fw-600" style="font-size:0.82rem;font-weight:600">To Email</label>
                    <input type="email" name="to_email" class="form-control @error('to_email') is-invalid @enderror"
                           value="{{ old('to_email', auth()->user()->email) }}"
                           placeholder="recipient@example.com" required>
                    @error('to_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-600" style="font-size:0.82rem;font-weight:600">Subject</label>
                    <input type="text" name="subject" class="form-control @error('subject') is-invalid @enderror"
                           value="{{ old('subject', 'Test Email from ' . config('app.name')) }}"
                           maxlength="255" required>
                    @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-4">
                    <label class="form-label fw-600" style="font-size:0.82rem;font-weight:600">Message</label>
                    <textarea name="body" rows="5" class="form-control @error('body') is-invalid @enderror"
                              placeholder="Enter your test message here…" required>{{ old('body', 'This is a test email to verify that the SMTP configuration is working correctly.') }}</textarea>
                    @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-send me-1"></i> Send Test Email
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
