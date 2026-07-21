@extends('layouts.app')
@section('title', 'Sent Emails')
@section('page-title', 'Sent Emails')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Email'],
        ['label' => 'Sent'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')

{{-- Filter bar --}}
<div class="card mb-3">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('admin.email.sent') }}"
              class="d-flex flex-wrap gap-2 align-items-end">

            <div style="flex:2;min-width:180px">
                <input type="email" name="email" class="form-control form-control-sm"
                       placeholder="Filter by recipient email…"
                       value="{{ request('email') }}">
            </div>

            <div style="min-width:160px">
                <select name="type" class="form-select form-select-sm">
                    <option value="">All types</option>
                    @foreach([
                        'compose'          => 'Compose',
                        'bulk'             => 'Bulk',
                        'otp'              => 'OTP',
                        'password_changed' => 'Password Changed',
                        'exam_published'   => 'Exam Published',
                        'welcome'          => 'Welcome',
                        'test_email'       => 'Test Email',
                    ] as $val => $label)
                    <option value="{{ $val }}" {{ request('type') === $val ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm px-3">
                    <i class="bi bi-funnel-fill me-1"></i>Filter
                </button>
                <a href="{{ route('admin.email.sent') }}"
                   class="btn btn-outline-secondary btn-sm" title="Reset filters">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>

        </form>
    </div>
</div>

{{-- Sent table --}}
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-send-check-fill me-2" style="color:#059669"></i>Sent Emails</span>
        <span class="badge" style="background:#f0fdf4;color:#059669;border:1px solid #bbf7d0">
            {{ $logs->total() }} sent
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0" style="font-size:0.845rem">
                <thead style="background:#f8f9fc">
                    <tr>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 1rem;border-bottom:1.5px solid #e8eaf2">Recipient</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Subject</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Type</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Sent At</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr>
                        <td style="padding:0.7rem 1rem">
                            <div style="font-weight:600;color:#111827">{{ $log->to_name ?: $log->to_email }}</div>
                            <div style="font-size:0.72rem;color:#9ca3af">{{ $log->to_email }}</div>
                        </td>
                        <td style="padding:0.7rem 0.75rem;color:#374151;max-width:240px">
                            <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis"
                                 title="{{ $log->subject }}">
                                {{ $log->subject }}
                            </div>
                        </td>
                        <td style="padding:0.7rem 0.75rem">
                            @if($log->email_type)
                            <span style="font-size:0.7rem;background:#eef2ff;color:#3730a3;padding:2px 7px;border-radius:4px;font-weight:600">
                                {{ $log->email_type }}
                            </span>
                            @elseif($log->event)
                            <span style="font-size:0.7rem;background:#f3f4f6;color:#6b7280;padding:2px 7px;border-radius:4px">
                                {{ $log->event }}
                            </span>
                            @else
                            <span class="text-muted" style="font-size:0.75rem">—</span>
                            @endif
                        </td>
                        <td style="padding:0.7rem 0.75rem;color:#9ca3af;font-size:0.78rem;white-space:nowrap">
                            {{ $log->sent_at?->format('d M Y H:i') ?? '—' }}
                        </td>
                        <td style="padding:0.7rem 0.75rem">
                            <a href="{{ route('admin.email.logs.show', $log) }}"
                               class="btn btn-sm btn-outline-primary" title="View details">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="bi bi-send d-block mb-2" style="font-size:2rem;opacity:0.3"></i>
                            No sent emails found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($logs->hasPages())
        <div class="px-3 py-2 border-top d-flex align-items-center justify-content-between flex-wrap gap-2"
             style="background:#fafbff">
            <span style="font-size:0.78rem;color:#6b7280">
                Showing <strong>{{ $logs->firstItem() }}</strong>–<strong>{{ $logs->lastItem() }}</strong>
                of <strong>{{ $logs->total() }}</strong>
            </span>
            {{ $logs->withQueryString()->links() }}
        </div>
        @endif
    </div>
</div>

@endsection
