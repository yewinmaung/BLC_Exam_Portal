@extends('layouts.app')
@section('title', 'Preview — '.$template->name)
@section('page-title', 'Template Preview')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Email', 'url' => route('admin.email.index')],
        ['label' => 'Templates', 'url' => route('admin.email.templates')],
        ['label' => 'Preview'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')
<div class="d-flex gap-2 mb-3">
    <a href="{{ route('admin.email.templates.edit', $template) }}" class="btn btn-sm btn-primary">
        <i class="bi bi-pencil me-1"></i> Edit Template
    </a>
    <a href="{{ route('admin.email.templates') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
</div>

<div class="row g-3">
    <div class="col-lg-3">
        <div class="card">
            <div class="card-header" style="font-size:0.82rem"><i class="bi bi-info-circle me-2"></i>Template Info</div>
            <div class="card-body" style="font-size:0.82rem">
                <table class="table table-sm mb-0">
                    <tr><th class="text-muted fw-normal">Name</th><td>{{ $template->name }}</td></tr>
                    <tr><th class="text-muted fw-normal">Slug</th><td><code>{{ $template->slug }}</code></td></tr>
                    <tr><th class="text-muted fw-normal">Event</th><td>{{ $template->event ?? '—' }}</td></tr>
                    <tr><th class="text-muted fw-normal">Status</th>
                        <td><span class="badge {{ $template->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $template->is_active ? 'Active' : 'Inactive' }}</span></td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header" style="font-size:0.82rem"><i class="bi bi-braces me-2"></i>Sample Variables</div>
            <div class="card-body" style="font-size:0.78rem">
                <div class="text-muted mb-1">These sample values are used in the preview:</div>
                @foreach(['student_name'=>'John Doe','exam_name'=>'Midterm Exam','course_name'=>'CS101','teacher_name'=>'Prof. Smith','result'=>'Passed','gpa'=>'3.75','student_id'=>'STU-001'] as $k => $v)
                <div class="d-flex justify-content-between border-bottom py-1">
                    <code style="font-size:0.7rem">{{"{{$k}}"}}</code>
                    <span class="text-muted">{{ $v }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="col-lg-9">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-envelope me-2"></i>Preview</span>
                <span class="text-muted" style="font-size:0.78rem">Subject: {{ $rendered['subject'] }}</span>
            </div>
            <div class="card-body p-0">
                {{-- Email client simulation --}}
                <div style="background:#f3f4f6;padding:1rem">
                    <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;border:1px solid #e5e7eb;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.08)">
                        {{-- Email header bar --}}
                        <div style="background:linear-gradient(135deg,#1e1b6e,#3730a3);padding:1.5rem;text-align:center">
                            <div style="color:#fff;font-size:1.1rem;font-weight:700">{{ config('app.name') }}</div>
                        </div>
                        {{-- Email body --}}
                        <div style="padding:2rem;font-family:Arial,sans-serif;font-size:14px;color:#374151;line-height:1.6">
                            {!! $rendered['bodyHtml'] !!}
                        </div>
                        {{-- Email footer --}}
                        <div style="background:#f9fafb;padding:1rem;text-align:center;font-size:11px;color:#9ca3af;border-top:1px solid #e5e7eb">
                            © {{ date('Y') }} {{ config('app.name') }} · This is an automated message, please do not reply.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($rendered['bodyText'])
        <div class="card mt-3">
            <div class="card-header" style="font-size:0.82rem"><i class="bi bi-text-left me-2"></i>Plain-text Version</div>
            <div class="card-body">
                <pre style="font-size:0.78rem;color:#374151;white-space:pre-wrap;margin:0">{{ $rendered['bodyText'] }}</pre>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
