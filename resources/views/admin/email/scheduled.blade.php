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
<div class="row g-3">

    {{-- Schedule Form --}}
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><i class="bi bi-calendar-plus me-2"></i>Schedule New Email</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.email.scheduled.store') }}">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Schedule Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name') }}" required placeholder="e.g. Exam Reminder — Week 1">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Recipients <span class="text-danger">*</span></label>
                        <select name="recipients" class="form-select @error('recipients') is-invalid @enderror" required>
                            <option value="">— Select group —</option>
                            @foreach($groups as $key => $label)
                            <option value="{{ $key }}" {{ old('recipients') == $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('recipients')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Send At <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="send_at" class="form-control @error('send_at') is-invalid @enderror"
                               value="{{ old('send_at') }}" required>
                        @error('send_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
                        <input type="text" name="subject" class="form-control @error('subject') is-invalid @enderror"
                               value="{{ old('subject') }}" required>
                        @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Message Body <span class="text-danger">*</span></label>
                        <textarea name="body_html" class="form-control @error('body_html') is-invalid @enderror"
                                  rows="8" style="font-family:monospace;font-size:0.8rem"
                                  required>{{ old('body_html') }}</textarea>
                        @error('body_html')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-calendar-check me-1"></i> Schedule Email
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Scheduled List --}}
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-calendar3 me-2"></i>Scheduled Queue</span>
                <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">
                    {{ $scheduled->total() }} total
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0" style="font-size:0.82rem">
                        <thead>
                            <tr><th>Name</th><th>Recipients</th><th>Send At</th><th>Status</th><th></th></tr>
                        </thead>
                        <tbody>
                            @forelse($scheduled as $s)
                            <tr>
                                <td>
                                    <div style="font-weight:600">{{ $s->name }}</div>
                                    <div style="font-size:0.72rem;color:#9ca3af">{{ Str::limit($s->subject, 50) }}</div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary" style="font-size:0.7rem">
                                        {{ \App\Models\ScheduledEmail::$recipientLabels[$s->recipients] ?? $s->recipients }}
                                    </span>
                                </td>
                                <td class="text-muted" style="font-size:0.75rem">
                                    {{ $s->send_at->format('M d Y, H:i') }}
                                    @if(!$s->is_sent && $s->send_at->isPast())
                                        <span class="badge bg-warning text-dark ms-1" style="font-size:0.65rem">Overdue</span>
                                    @endif
                                </td>
                                <td>
                                    @if($s->is_sent)
                                        <span class="badge bg-success">Sent</span>
                                        <div style="font-size:0.7rem;color:#9ca3af">{{ $s->sent_at?->format('M d, H:i') }}</div>
                                    @else
                                        <span class="badge bg-info text-dark">Pending</span>
                                    @endif
                                </td>
                                <td>
                                    @if(!$s->is_sent)
                                    <form method="POST" action="{{ route('admin.email.scheduled.destroy', $s) }}"
                                          onsubmit="return confirm('Cancel this scheduled email?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-xs btn-outline-danger" title="Cancel">
                                            <i class="bi bi-x-circle"></i>
                                        </button>
                                    </form>
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
                <div class="p-3 border-top">{{ $scheduled->links() }}</div>
                @endif
            </div>
        </div>

        <div class="alert alert-info mt-3 d-flex gap-2" style="font-size:0.8rem">
            <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
            <div>Scheduled emails are processed by the Laravel Scheduler. Ensure the cron job is running:<br>
                <code>* * * * * php /path/to/artisan schedule:run</code>
            </div>
        </div>
    </div>
</div>
@endsection
