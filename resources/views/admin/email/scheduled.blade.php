@extends('layouts.app')
@section('title', 'Scheduled Emails')
@section('page-title', 'Scheduled Emails')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Email', 'url' => route('admin.email.index')],
        ['label' => 'Scheduled'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')

{{-- Schedule New --}}
<div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-calendar-plus" style="color:var(--blc-royal,#2d27a0)"></i>
        Schedule a New Email
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.email.scheduled.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-600" style="font-size:0.82rem;font-weight:600">Name / Label</label>
                    <input type="text" name="name" class="form-control form-control-sm @error('name') is-invalid @enderror"
                           value="{{ old('name') }}" placeholder="e.g. Semester Start Reminder" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-600" style="font-size:0.82rem;font-weight:600">Send At</label>
                    <input type="datetime-local" name="send_at" class="form-control form-control-sm @error('send_at') is-invalid @enderror"
                           value="{{ old('send_at') }}" required>
                    @error('send_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-600" style="font-size:0.82rem;font-weight:600">Recipients</label>
                    <select name="recipients" class="form-select form-select-sm @error('recipients') is-invalid @enderror" required>
                        <option value="">— Select group —</option>
                        @foreach($groups as $key => $label)
                        <option value="{{ $key }}" {{ old('recipients') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('recipients')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-600" style="font-size:0.82rem;font-weight:600">Subject</label>
                    <input type="text" name="subject" class="form-control form-control-sm @error('subject') is-invalid @enderror"
                           value="{{ old('subject') }}" maxlength="255" required>
                    @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label fw-600" style="font-size:0.82rem;font-weight:600">Body (HTML)</label>
                    <textarea name="body_html" rows="6" class="form-control form-control-sm @error('body_html') is-invalid @enderror"
                              placeholder="<p>Hello @{{student_name}},</p>" required>{{ old('body_html') }}</textarea>
                    @error('body_html')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-calendar-check me-1"></i> Schedule
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- List --}}
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-calendar-check me-2"></i>Scheduled Emails</span>
        <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">
            {{ $scheduled->total() }} total
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0" style="font-size:0.845rem">
                <thead style="background:#f8f9fc">
                    <tr>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 1rem;border-bottom:1.5px solid #e8eaf2">Name</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Recipients</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Send At</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Status</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($scheduled as $item)
                    <tr>
                        <td style="padding:0.7rem 1rem;font-weight:600;color:#111827">{{ $item->name }}</td>
                        <td style="padding:0.7rem 0.75rem;color:#374151">
                            {{ $groups[$item->recipients] ?? $item->recipients }}
                        </td>
                        <td style="padding:0.7rem 0.75rem;color:#374151;white-space:nowrap">
                            {{ $item->send_at->format('d M Y H:i') }}
                            @if(!$item->is_sent && $item->send_at->isPast())
                            <span style="font-size:0.68rem;color:#dc2626;display:block">Overdue</span>
                            @endif
                        </td>
                        <td style="padding:0.7rem 0.75rem">
                            @if($item->is_sent)
                            <span style="font-size:0.72rem;font-weight:700;padding:3px 9px;border-radius:5px;background:#f0fdf4;color:#059669">Sent</span>
                            @else
                            <span style="font-size:0.72rem;font-weight:700;padding:3px 9px;border-radius:5px;background:#fffbeb;color:#d97706">Pending</span>
                            @endif
                        </td>
                        <td style="padding:0.7rem 0.75rem">
                            @if(!$item->is_sent)
                            <form action="{{ route('admin.email.scheduled.destroy', $item) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('Cancel this scheduled email?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="Cancel">
                                    <i class="bi bi-x-circle"></i>
                                </button>
                            </form>
                            @else
                            <span class="text-muted" style="font-size:0.78rem">{{ $item->sent_at?->format('d M H:i') }}</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="bi bi-calendar-x d-block mb-2" style="font-size:2rem;opacity:0.3"></i>
                            No scheduled emails.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($scheduled->hasPages())
        <div class="px-3 py-2 border-top" style="background:#fafbff">
            {{ $scheduled->links() }}
        </div>
        @endif
    </div>
</div>

@endsection
