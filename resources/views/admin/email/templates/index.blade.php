@extends('layouts.app')
@section('title', 'Email Templates')
@section('page-title', 'Email Templates')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Email', 'url' => route('admin.email.index')],
        ['label' => 'Templates'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
    <div></div>
    <a href="{{ route('admin.email.templates.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i> New Template
    </a>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-file-earmark-code me-2"></i>Email Templates</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table datatable mb-0">
                <thead>
                    <tr><th>Name</th><th>Slug</th><th>Event</th><th>Subject</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    @forelse($templates as $t)
                    <tr>
                        <td style="font-weight:600">{{ $t->name }}</td>
                        <td><code style="font-size:0.75rem">{{ $t->slug }}</code></td>
                        <td><span class="badge bg-secondary" style="font-size:0.7rem">{{ $t->event ?? '—' }}</span></td>
                        <td style="font-size:0.82rem;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $t->subject }}</td>
                        <td>
                            <span class="badge {{ $t->is_active ? 'bg-success' : 'bg-secondary' }}">
                                {{ $t->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="{{ route('admin.email.templates.preview', $t) }}" class="btn btn-xs btn-outline-secondary" title="Preview"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('admin.email.templates.edit', $t) }}"    class="btn btn-xs btn-outline-primary"   title="Edit"><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="{{ route('admin.email.templates.destroy', $t) }}"
                                      onsubmit="return confirm('Delete this template?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center py-5 text-muted">No templates yet. <a href="{{ route('admin.email.templates.create') }}">Create one</a>.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($templates->hasPages())
        <div class="p-3 border-top">{{ $templates->links() }}</div>
        @endif
    </div>
</div>

{{-- Variables reference --}}
<div class="card mt-3">
    <div class="card-header" style="font-size:0.82rem"><i class="bi bi-braces me-2"></i>Available Variables</div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2" style="font-size:0.78rem">
            @foreach(['student_name','student_id','teacher_name','course_name','exam_name','result','gpa'] as $var)
            <code style="background:#f1f3f9;padding:0.2rem 0.5rem;border-radius:6px">{{"{{$var}}"}}</code>
            @endforeach
        </div>
    </div>
</div>
@endsection
