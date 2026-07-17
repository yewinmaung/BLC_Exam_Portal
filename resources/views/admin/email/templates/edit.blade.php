@extends('layouts.app')
@section('title', 'Edit Template — ' . $template->name)
@section('page-title', 'Edit Email Template')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Email', 'url' => route('admin.email.index')],
        ['label' => 'Templates', 'url' => route('admin.email.templates')],
        ['label' => 'Edit'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')
<div style="max-width:760px">
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <span class="d-flex align-items-center gap-2">
                <i class="bi bi-pencil-square" style="color:var(--blc-royal,#2d27a0)"></i>
                {{ $template->name }}
            </span>
            <a href="{{ route('admin.email.templates.preview', $template) }}"
               class="btn btn-sm btn-outline-secondary" target="_blank">
                <i class="bi bi-eye me-1"></i> Preview
            </a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.email.templates.update', $template) }}">
                @csrf @method('PUT')
                @include('admin.email.templates._form')
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Save Changes
                    </button>
                    <a href="{{ route('admin.email.templates') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
