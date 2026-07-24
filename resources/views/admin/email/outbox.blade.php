@extends('layouts.app')
@section('title', 'Outbox')
@section('page-title', 'Outbox')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Email'],
        ['label' => 'Outbox'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')

{{-- ── Section 1: Queued email_logs ── --}}
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span>
            <i class="bi bi-hourglass-split me-2" style="color:#d97706"></i>
            Queued Emails
            <span class="text-muted fw-normal" style="font-size:0.78rem;margin-left:0.4rem">
                — dispatched, awaiting queue worker
            </span>
        </span>
        <span class="badge" style="background:#fffbeb;color:#d97706;border:1px solid #fde68a">
            {{ $queued->total() }} queued
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
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Queued At</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($queued as $log)
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
                            {{ $log->queued_at?->format('d M Y H:i') ?? $log->created_at->format('d M Y H:i') }}
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
                        <td colspan="5" class="text-center py-4 text-muted">
                            <i class="bi bi-check-circle d-block mb-1" style="font-size:1.5rem;opacity:0.3"></i>
                            No emails waiting in queue.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($queued->hasPages())
        <div class="px-3 py-2 border-top d-flex align-items-center justify-content-between flex-wrap gap-2"
             style="background:#fafbff">
            <span style="font-size:0.78rem;color:#6b7280">
                Showing <strong>{{ $queued->firstItem() }}</strong>–<strong>{{ $queued->lastItem() }}</strong>
                of <strong>{{ $queued->total() }}</strong>
            </span>
            {{ $queued->appends(request()->except('queued_page'))->links() }}
        </div>
        @endif
    </div>
</div>

{{-- ── Section 2: Pending scheduled_emails ── --}}
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span>
            <i class="bi bi-calendar-clock me-2" style="color:#7c3aed"></i>
            Scheduled — Pending Dispatch
            <span class="text-muted fw-normal" style="font-size:0.78rem;margin-left:0.4rem">
                — will send when send_at time is reached
            </span>
        </span>
        <div class="d-flex align-items-center gap-2">
            <span class="badge" style="background:#f5f3ff;color:#7c3aed;border:1px solid #ddd6fe">
                {{ $scheduled->total() }} pending
            </span>
            <a href="{{ route('admin.email.scheduled') }}" class="btn btn-sm btn-outline-secondary"
               title="Manage scheduled emails">
                <i class="bi bi-calendar-plus me-1"></i>Manage
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0" style="font-size:0.845rem">
                <thead style="background:#f8f9fc">
                    <tr>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 1rem;border-bottom:1.5px solid #e8eaf2">Name</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Type</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Audience</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Send At</th>
                        <th style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;padding:0.65rem 0.75rem;border-bottom:1.5px solid #e8eaf2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($scheduled as $item)
                    <tr>
                        <td style="padding:0.7rem 1rem;font-weight:600;color:#111827">
                            {{ $item->name }}
                        </td>
                        <td style="padding:0.7rem 0.75rem">
                            @php
                                $typeColors = ['exam_time'=>'#2d27a0','exam_policy'=>'#92400e','exam_reminder'=>'#059669'];
                                $typeBg     = ['exam_time'=>'#eef2ff','exam_policy'=>'#fffbeb','exam_reminder'=>'#f0fdf4'];
                                $tc = $typeColors[$item->notification_type] ?? '#6b7280';
                                $tb = $typeBg[$item->notification_type] ?? '#f3f4f6';
                            @endphp
                            <span style="font-size:0.7rem;background:{{$tb}};color:{{$tc}};padding:2px 8px;border-radius:4px;font-weight:700">
                                {{ \App\Models\ScheduledEmail::$notificationTypes[$item->notification_type] ?? $item->notification_type }}
                            </span>
                        </td>
                        <td style="padding:0.7rem 0.75rem;color:#374151;font-size:0.8rem">
                            {{ $item->filter_summary }}
                        </td>
                        <td style="padding:0.7rem 0.75rem;white-space:nowrap">
                            <span style="color:#374151">{{ $item->send_at->format('d M Y H:i') }}</span>
                            @if($item->send_at->isPast())
                            <span style="font-size:0.68rem;color:#dc2626;display:block;font-weight:600">Overdue</span>
                            @else
                            <span style="font-size:0.68rem;color:#7c3aed;display:block">
                                in {{ $item->send_at->diffForHumans() }}
                            </span>
                            @endif
                        </td>
                        <td style="padding:0.7rem 0.75rem">
                            <form action="{{ route('admin.email.scheduled.destroy', $item) }}" method="POST"
                                  class="d-inline"
                                  onsubmit="return confirm('Cancel scheduled email \'{{ addslashes($item->name) }}\'?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="Cancel">
                                    <i class="bi bi-x-circle"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">
                            <i class="bi bi-calendar-check d-block mb-1" style="font-size:1.5rem;opacity:0.3"></i>
                            No scheduled emails pending.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($scheduled->hasPages())
        <div class="px-3 py-2 border-top d-flex align-items-center justify-content-between flex-wrap gap-2"
             style="background:#fafbff">
            <span style="font-size:0.78rem;color:#6b7280">
                Showing <strong>{{ $scheduled->firstItem() }}</strong>–<strong>{{ $scheduled->lastItem() }}</strong>
                of <strong>{{ $scheduled->total() }}</strong>
            </span>
            {{ $scheduled->appends(request()->except('sched_page'))->links() }}
        </div>
        @endif
    </div>
</div>

@endsection
