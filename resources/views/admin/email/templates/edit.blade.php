@extends('layouts.app')
@section('title', 'Edit Template')
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
<div class="row justify-content-center">
<div class="col-lg-9">
<div class="card">
    <div class="card-header"><i class="bi bi-pencil me-2"></i>Edit — {{ $template->name }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.email.templates.update', $template) }}">
            @csrf @method('PUT')
            @include('admin.email.templates._form')
            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-check-circle me-1"></i> Save Changes
                </button>
                <a href="{{ route('admin.email.templates.preview', $template) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-eye me-1"></i> Preview
                </a>
                <a href="{{ route('admin.email.templates') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
</div>
</div>
@endsection
