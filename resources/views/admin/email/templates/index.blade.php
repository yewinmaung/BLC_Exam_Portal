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

<div class="d-flex justify-content-end mb-3">
    <a href="{{ route('admin.email.templates.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i> New Template
    </a>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-file-earmark-code me-2"></i>Email Templates</span>
        <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">
            {{ $templates->total() }} total
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0" style="font-size:0.845rem">
                <thead style="background:#f8f9fc">
                    <tr>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 1rem;border-bottom:1.5px solid #e8eaf2">Name</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Slug</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Subject</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Event</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Status</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($templates as $tmpl)
                    <tr>
                        <td style="padding:0.7rem 1rem;font-weight:600;color:#111827">{{ $tmpl->name }}</td>
                        <td style="padding:0.7rem 0.75rem">
                            <code style="font-size:0.78rem;background:#f3f4f6;padding:2px 6px;border-radius:4px">{{ $tmpl->slug }}</code>
                        </td>
                        <td style="padding:0.7rem 0.75rem;color:#374151;max-width:200px">
                            <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $tmpl->subject }}</div>
                        </td>
                        <td style="padding:0.7rem 0.75rem">
                            @if($tmpl->event)
                            <span style="font-size:0.7rem;background:#eef2ff;color:#3730a3;padding:2px 7px;border-radius:4px;font-weight:600">{{ $tmpl->event }}</span>
                            @else
                            <span class="text-muted" style="font-size:0.75rem">—</span>
                            @endif
                        </td>
                        <td style="padding:0.7rem 0.75rem">
                            @if($tmpl->is_active)
                            <span style="font-size:0.72rem;font-weight:700;padding:3px 9px;border-radius:5px;background:#f0fdf4;color:#059669">Active</span>
                            @else
                            <span style="font-size:0.72rem;font-weight:700;padding:3px 9px;border-radius:5px;background:#f3f4f6;color:#6b7280">Inactive</span>
                            @endif
                        </td>
                        <td style="padding:0.7rem 0.75rem">
                            <div class="d-flex gap-1">
                                <a href="{{ route('admin.email.templates.preview', $tmpl) }}"
                                   class="btn btn-sm btn-outline-secondary" title="Preview">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('admin.email.templates.edit', $tmpl) }}"
                                   class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('admin.email.templates.destroy', $tmpl) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Delete template \'{{ addslashes($tmpl->name) }}\'?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-file-earmark-x d-block mb-2" style="font-size:2rem;opacity:0.3"></i>
                            No templates yet.
                            <div class="mt-2">
                                <a href="{{ route('admin.email.templates.create') }}" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus-circle me-1"></i> Create First Template
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($templates->hasPages())
        <div class="px-3 py-2 border-top" style="background:#fafbff">
            {{ $templates->links() }}
        </div>
        @endif
    </div>
</div>

@endsection
