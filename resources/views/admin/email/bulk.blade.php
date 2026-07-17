@extends('layouts.app')
@section('title', 'Bulk Email')
@section('page-title', 'Bulk Email')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Email', 'url' => route('admin.email.index')],
        ['label' => 'Bulk Email'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')
<div style="max-width:700px">
    <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
            <i class="bi bi-send-check" style="color:var(--blc-royal,#2d27a0)"></i>
            Send Bulk Email
        </div>
        <div class="card-body">

            <div class="alert alert-info d-flex align-items-start gap-2 mb-4" style="font-size:0.83rem">
                <i class="bi bi-info-circle-fill mt-1" style="flex-shrink:0"></i>
                <div>
                    Emails are queued and sent to every recipient in the selected group.
                    Variables like <code>@{{student_name}}</code>, <code>@{{email}}</code>,
                    <code>@{{course_name}}</code> are substituted per recipient.
                </div>
            </div>

            <form method="POST" action="{{ route('admin.email.bulk.send') }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label fw-600" style="font-size:0.82rem;font-weight:600">Recipients</label>
                    <select name="recipients" class="form-select @error('recipients') is-invalid @enderror" required>
                        <option value="">— Select recipient group —</option>
                        @foreach($groups as $key => $label)
                        <option value="{{ $key }}" {{ old('recipients') === $key ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                        @endforeach
                    </select>
                    @error('recipients')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-600" style="font-size:0.82rem;font-weight:600">Use Template (optional)</label>
                    <select name="template_slug" class="form-select" id="templateSelect">
                        <option value="">— Compose manually below —</option>
                        @foreach($templates as $tmpl)
                        <option value="{{ $tmpl->slug }}" {{ old('template_slug') === $tmpl->slug ? 'selected' : '' }}>
                            {{ $tmpl->name }} ({{ $tmpl->slug }})
                        </option>
                        @endforeach
                    </select>
                    <div class="form-text" style="font-size:0.75rem">If a template is selected, its subject and body override the fields below.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-600" style="font-size:0.82rem;font-weight:600">Subject</label>
                    <input type="text" name="subject" class="form-control @error('subject') is-invalid @enderror"
                           value="{{ old('subject') }}" maxlength="255" required>
                    @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-4">
                    <label class="form-label fw-600" style="font-size:0.82rem;font-weight:600">Body (HTML)</label>
                    <textarea name="body_html" rows="10" id="bodyHtml"
                              class="form-control @error('body_html') is-invalid @enderror"
                              placeholder="<p>Hello @{{student_name}},</p><p>Your message here…</p>"
                              required>{{ old('body_html') }}</textarea>
                    @error('body_html')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text mt-1" style="font-size:0.75rem">
                        Available variables: <code>@{{student_name}}</code> <code>@{{teacher_name}}</code>
                        <code>@{{email}}</code> <code>@{{course_name}}</code> <code>@{{year_level}}</code>
                        <code>@{{academic_year}}</code> <code>@{{app_name}}</code>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary"
                        onclick="return confirm('Send to all recipients in the selected group? This cannot be undone.')">
                    <i class="bi bi-send-check me-1"></i> Queue & Send
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
