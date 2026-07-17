@extends('layouts.app')
@section('title', 'Preview — ' . $template->name)
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
<div style="max-width:720px">

    <div class="d-flex gap-2 mb-3">
        <a href="{{ route('admin.email.templates') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
        <a href="{{ route('admin.email.templates.edit', $template) }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-pencil me-1"></i> Edit
        </a>
    </div>

    <div class="card mb-3">
        <div class="card-body py-2 px-3" style="font-size:0.83rem">
            <strong>Slug:</strong> <code>{{ $template->slug }}</code> &nbsp;|&nbsp;
            <strong>Subject:</strong> {{ $rendered['subject'] }} &nbsp;|&nbsp;
            <strong>Status:</strong>
            <span style="color:{{ $template->is_active ? '#059669' : '#6b7280' }};font-weight:700">
                {{ $template->is_active ? 'Active' : 'Inactive' }}
            </span>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><i class="bi bi-eye me-2"></i>Rendered Preview (sample data)</div>
        <div class="card-body p-0" style="background:#f4f6fb">
            <iframe srcdoc="{{ e($rendered['bodyHtml']) }}"
                    style="width:100%;border:none;min-height:540px;display:block"
                    sandbox="allow-same-origin"
                    title="Template Preview">
            </iframe>
        </div>
    </div>

</div>
@endsection
