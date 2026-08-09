@extends('layouts.app')
@section('title', 'Exam Timetable Notification Logs')
@section('page-title', 'Exam Timetable Notification Logs')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Email', 'url' => route('admin.email.compose')],
        ['label' => 'Timetable Logs'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span>
            <i class="bi bi-calendar2-week me-2" style="color:var(--blc-royal,#2d27a0)"></i>
            Exam Timetable Notification History
        </span>
        <a href="{{ route('admin.email.compose') }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-plus-lg me-1"></i> New Notification
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0" style="font-size:0.845rem">
                <thead style="background:#f8f9fc">
                    <tr>
                        <th class="th-small">Academic Group</th>
                        <th class="th-small">Exams</th>
                        <th class="th-small">Recipients</th>
                        <th class="th-small">Sent By</th>
                        <th class="th-small">Status</th>
                        <th class="th-small">Sent At</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr>
                        <td style="padding:.7rem 1rem">
                            <div style="font-weight:700;color:#111827;font-size:.84rem">
                                {{ $log->academicYear?->name ?? '—' }}
                            </div>
                            <div style="font-size:.74rem;color:#6b7280;margin-top:2px">
                                {{ $log->yearLevel?->name ?? '—' }}
                                @if($log->major)
                                    · {{ $log->major->name }}
                                @endif
                                · Semester {{ $log->semester }}
                            </div>
                        </td>
                        <td style="padding:.7rem .75rem">
                            <span style="font-size:.78rem;background:#eef2ff;color:#3730a3;padding:2px 8px;border-radius:4px;font-weight:700">
                                {{ count($log->exam_schedule_ids ?? []) }} exam(s)
                            </span>
                        </td>
                        <td style="padding:.7rem .75rem">
                            <span style="font-weight:700;color:#1a2540">{{ number_format($log->recipient_count) }}</span>
                            <span style="font-size:.74rem;color:#9ca3af;margin-left:3px">students</span>
                        </td>
                        <td style="padding:.7rem .75rem;color:#374151">
                            {{ $log->sender?->name ?? '—' }}
                        </td>
                        <td style="padding:.7rem .75rem">
                            @php
                                $statusStyle = match($log->status) {
                                    'sent'    => 'background:#f0fdf4;color:#059669',
                                    'queued'  => 'background:#fffbeb;color:#d97706',
                                    'partial' => 'background:#fff7ed;color:#ea580c',
                                    'failed'  => 'background:#fef2f2;color:#dc2626',
                                    default   => 'background:#f3f4f6;color:#6b7280',
                                };
                            @endphp
                            <span style="font-size:.72rem;font-weight:700;padding:3px 9px;border-radius:5px;{{ $statusStyle }}">
                                {{ ucfirst($log->status) }}
                            </span>
                        </td>
                        <td style="padding:.7rem .75rem;color:#9ca3af;font-size:.78rem;white-space:nowrap">
                            {{ $log->sent_at?->format('d M Y H:i') ?? $log->created_at->format('d M Y H:i') }}
                        </td>
                    </tr>
                    @if($log->exam_policy || $log->additional_instructions)
                    <tr style="background:#fafbff">
                        <td colspan="6" style="padding:.4rem 1rem .7rem 2.5rem">
                            @if($log->exam_policy)
                            <div style="font-size:.74rem;margin-bottom:4px">
                                <span style="font-weight:700;color:#92400e">Policy:</span>
                                <span style="color:#6b7280">{{ Str::limit($log->exam_policy, 120) }}</span>
                            </div>
                            @endif
                            @if($log->additional_instructions)
                            <div style="font-size:.74rem">
                                <span style="font-weight:700;color:#166534">Instructions:</span>
                                <span style="color:#6b7280">{{ Str::limit($log->additional_instructions, 120) }}</span>
                            </div>
                            @endif
                        </td>
                    </tr>
                    @endif
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-calendar2-x d-block mb-2" style="font-size:2rem;opacity:.3"></i>
                            No timetable notifications sent yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($logs->hasPages())
        <div class="px-3 py-2 border-top d-flex align-items-center justify-content-between flex-wrap gap-2" style="background:#fafbff">
            <span style="font-size:.78rem;color:#6b7280">
                Showing <strong>{{ $logs->firstItem() }}</strong>–<strong>{{ $logs->lastItem() }}</strong>
                of <strong>{{ $logs->total() }}</strong>
            </span>
            {{ $logs->links() }}
        </div>
        @endif
    </div>
</div>

@push('styles')
<style>
.th-small { font-size:.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.04em;padding:.65rem .75rem;border-bottom:1.5px solid #e8eaf2 }
.th-small:first-child { padding-left:1rem }
</style>
@endpush

@endsection
