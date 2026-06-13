@extends('layouts.app')
@section('title', 'Test Email')
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
<div class="row justify-content-center">
<div class="col-lg-6">

    {{-- SMTP status card --}}
    <div class="card mb-3">
        <div class="card-header" style="font-size:0.82rem"><i class="bi bi-gear me-2"></i>Current SMTP Configuration</div>
        <div class="card-body" style="font-size:0.82rem">
            <table class="table table-sm mb-0">
                <tr><th class="text-muted fw-normal" style="width:120px">Host</th><td>{{ config('mail.mailers.smtp.host') }}</td></tr>
                <tr><th class="text-muted fw-normal">Port</th><td>{{ config('mail.mailers.smtp.port') }}</td></tr>
                <tr><th class="text-muted fw-normal">Encryption</th><td>{{ config('mail.mailers.smtp.encryption') ?: 'none' }}</td></tr>
                <tr><th class="text-muted fw-normal">Username</th><td>{{ config('mail.mailers.smtp.username') ?: '—' }}</td></tr>
                <tr><th class="text-muted fw-normal">From</th><td>{{ config('mail.from.address') }} ({{ config('mail.from.name') }})</td></tr>
            </table>
            <a href="{{ route('admin.email.smtp') }}" class="btn btn-xs btn-outline-secondary mt-2">
                <i class="bi bi-pencil me-1"></i> Edit SMTP Settings
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><i class="bi bi-send me-2"></i>Send Test Email</div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.email.test.send') }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label fw-semibold">Send To <span class="text-danger">*</span></label>
                    <input type="email" name="to_email" class="form-control @error('to_email') is-invalid @enderror"
                           value="{{ old('to_email', auth()->user()->email) }}" required>
                    @error('to_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
                    <input type="text" name="subject" class="form-control @error('subject') is-invalid @enderror"
                           value="{{ old('subject', 'Test Email from '.config('app.name')) }}" required>
                    @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Message <span class="text-danger">*</span></label>
                    <textarea name="body" class="form-control @error('body') is-invalid @enderror"
                              rows="6" required>{{ old('body', "Hello,\n\nThis is a test email from ".config('app.name').".\n\nIf you received this, your SMTP configuration is working correctly.\n\nRegards,\n".config('app.name')." Team") }}</textarea>
                    @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Sent synchronously — result shown immediately.</div>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-send me-1"></i> Send Test Email Now
                </button>
            </form>
        </div>
    </div>
</div>
</div>
@endsection
