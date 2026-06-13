@extends('layouts.app')
@section('title', 'SMTP Settings')
@section('page-title', 'SMTP Settings')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Email', 'url' => route('admin.email.index')],
        ['label' => 'SMTP Settings'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')
<div class="row justify-content-center">
<div class="col-lg-7">
<div class="card">
    <div class="card-header"><i class="bi bi-gear me-2"></i>SMTP Configuration</div>
    <div class="card-body">

        <div class="alert alert-info d-flex gap-2 mb-4" style="font-size:0.82rem">
            <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
            <div>
                <strong>Brevo SMTP:</strong> Host <code>smtp-relay.brevo.com</code> · Port <code>587</code> · Encryption <code>TLS</code><br>
                Get your SMTP key from <a href="https://app.brevo.com/settings/keys/smtp" target="_blank">Brevo → SMTP & API Keys</a>.
                Use your Brevo login email as Username and the SMTP key as Password.
            </div>
        </div>

        <form method="POST" action="{{ route('admin.email.smtp.update') }}">
            @csrf @method('POST')

            <div class="row g-3">
                <div class="col-sm-8">
                    <label class="form-label">SMTP Host <span class="text-danger">*</span></label>
                    <input type="text" name="host" class="form-control @error('host') is-invalid @enderror"
                           value="{{ old('host', $settings['host']) }}" placeholder="smtp-relay.brevo.com">
                    @error('host')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-4">
                    <label class="form-label">Port <span class="text-danger">*</span></label>
                    <input type="number" name="port" class="form-control @error('port') is-invalid @enderror"
                           value="{{ old('port', $settings['port']) }}" placeholder="587">
                    @error('port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-6">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control"
                           value="{{ old('username', $settings['username']) }}" placeholder="your@email.com" autocomplete="off">
                </div>
                <div class="col-sm-6">
                    <label class="form-label">Password / SMTP Key</label>
                    <input type="password" name="password" class="form-control"
                           placeholder="Leave blank to keep current" autocomplete="new-password">
                    <div class="form-text">Leave blank to keep the existing password.</div>
                </div>
                <div class="col-sm-4">
                    <label class="form-label">Encryption <span class="text-danger">*</span></label>
                    <select name="encryption" class="form-select">
                        <option value="tls"  {{ old('encryption', $settings['encryption']) === 'tls'  ? 'selected' : '' }}>TLS</option>
                        <option value="ssl"  {{ old('encryption', $settings['encryption']) === 'ssl'  ? 'selected' : '' }}>SSL</option>
                        <option value="none" {{ old('encryption', $settings['encryption']) === 'none' || old('encryption', $settings['encryption']) === 'null' ? 'selected' : '' }}>None</option>
                    </select>
                </div>
                <div class="col-sm-8">
                    <label class="form-label">From Email <span class="text-danger">*</span></label>
                    <input type="email" name="from_address" class="form-control @error('from_address') is-invalid @enderror"
                           value="{{ old('from_address', $settings['from_address']) }}" placeholder="noreply@believeexam.com">
                    @error('from_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-12">
                    <label class="form-label">From Name <span class="text-danger">*</span></label>
                    <input type="text" name="from_name" class="form-control @error('from_name') is-invalid @enderror"
                           value="{{ old('from_name', $settings['from_name']) }}" placeholder="BelieveExam">
                    @error('from_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-check-circle me-1"></i> Save SMTP Settings
                </button>
                <a href="{{ route('admin.email.test') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-send me-1"></i> Test
                </a>
            </div>
        </form>
    </div>
</div>
</div>
</div>
@endsection
