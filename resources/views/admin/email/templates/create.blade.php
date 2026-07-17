@extends('layouts.app')
@section('title', 'Create Email Template')
@section('page-title', 'Create Email Template')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Email', 'url' => route('admin.email.index')],
        ['label' => 'Templates', 'url' => route('admin.email.templates')],
        ['label' => 'Create'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')
<div style="max-width:760px">
    <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
            <i class="bi bi-file-earmark-plus" style="color:var(--blc-royal,#2d27a0)"></i>
            New Email Template
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.email.templates.store') }}">
                @csrf
                @include('admin.email.templates._form')
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Create Template
                    </button>
                    <a href="{{ route('admin.email.templates') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
