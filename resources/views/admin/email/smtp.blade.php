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
<div style="max-width:640px">

    <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
            <i class="bi bi-gear-fill" style="color:var(--blc-royal,#2d27a0)"></i>
            SMTP Configuration
        </div>
        <div class="card-body">

            <div class="alert alert-info d-flex align-items-start gap-2 mb-4" style="font-size:0.83rem">
                <i class="bi bi-info-circle-fill mt-1" style="flex-shrink:0"></i>
                <div>
                    Changes are written to <code>.env</code> and the config cache is cleared automatically.
                    The queue worker must be restarted for SMTP changes to take effect on background jobs.
                </div>
            </div>

            <form method="POST" action="{{ route('admin.email.smtp.update') }}">
                @csrf

                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <label class="form-label fw-600" style="font-size:0.82rem;font-weight:600">SMTP Host</label>
                        <input type="text" name="host" class="form-control @error('host') is-invalid @enderror"
                               value="{{ old('host', $settings['host']) }}"
                               placeholder="smtp.gmail.com">
                        @error('host')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-600" style="font-size:0.82rem;font-weight:600">Port</label>
                        <input type="number" name="port" class="form-control @error('port') is-invalid @enderror"
                               value="{{ old('port', $settings['port']) }}"
                               placeholder="587" min="1" max="65535">
                        @error('port')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-600" style="font-size:0.82rem;font-weight:600">Encryption</label>
                    <select name="encryption" class="form-select @error('encryption') is-invalid @enderror">
                        @foreach(['tls'=>'TLS (Recommended)','ssl'=>'SSL','none'=>'None'] as $val => $label)
                        <option value="{{ $val }}" {{ old('encryption', $settings['encryption']) === $val ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                        @endforeach
                    </select>
                    @error('encryption')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-600" style="font-size:0.82rem;font-weight:600">Username / Email</label>
                    <input type="text" name="username" class="form-control @error('username') is-invalid @enderror"
                           value="{{ old('username', $settings['username']) }}"
                           placeholder="youremail@gmail.com"
                           autocomplete="username">
                    @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text" style="font-size:0.75rem">For Gmail, this must be the full Gmail address.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-600" style="font-size:0.82rem;font-weight:600">Password / App Password</label>
                    <input type="password" name="password" class="form-control @error('password') is-invalid @enderror"
                           placeholder="Leave blank to keep current password"
                           autocomplete="new-password">
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text" style="font-size:0.75rem">For Gmail with 2FA, use an App Password, not your account password.</div>
                </div>

                <hr class="my-4">

                <div class="mb-3">
                    <label class="form-label fw-600" style="font-size:0.82rem;font-weight:600">From Address</label>
                    <input type="email" name="from_address" class="form-control @error('from_address') is-invalid @enderror"
                           value="{{ old('from_address', $settings['from_address']) }}"
                           placeholder="noreply@example.com">
                    @error('from_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-4">
                    <label class="form-label fw-600" style="font-size:0.82rem;font-weight:600">From Name</label>
                    <input type="text" name="from_name" class="form-control @error('from_name') is-invalid @enderror"
                           value="{{ old('from_name', $settings['from_name']) }}"
                           placeholder="Believe Learning Center">
                    @error('from_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Save Settings
                    </button>
                    <a href="{{ route('admin.email.test') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-send me-1"></i> Send Test Email
                    </a>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
