@extends('layouts.app')
@section('title', 'Cheating Logs')
@section('page-title', 'Cheating Logs')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Cheating Logs'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')

{{-- ── Filter Bar ── --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('admin.cheating-logs') }}"
              class="d-flex flex-wrap gap-2 align-items-end">

            {{-- Search --}}
            <div style="min-width:220px;flex:1">
                <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600">Search Student / Exam</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text" style="background:#f8f9fc;border-right:0">
                        <i class="bi bi-search" style="font-size:0.75rem;color:#6b7280"></i>
                    </span>
                    <input type="text" name="search"
                           value="{{ request('search') }}"
                           class="form-control form-control-sm"
                           style="border-left:0"
                           placeholder="Student name, email or exam…"
                           maxlength="100"
                           autocomplete="off">
                </div>
            </div>

            {{-- Violation Type --}}
            <div style="min-width:180px">
                <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600">Violation Type</label>
                <select name="violation_type" class="form-select form-select-sm">
                    <option value="">All Violations</option>
                    @foreach($violationTypes as $vt)
                    <option value="{{ $vt }}" {{ request('violation_type') === $vt ? 'selected' : '' }}>
                        {{ ucfirst(str_replace('_', ' ', $vt)) }}
                    </option>
                    @endforeach
                </select>
            </div>

            {{-- Buttons --}}
            <div class="d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
                <a href="{{ route('admin.cheating-logs') }}" class="btn btn-outline-secondary btn-sm" title="Clear filters">
                    <i class="bi bi-x"></i>
                </a>
            </div>
        </form>
    </div>
</div>

{{-- ── Table Card ── --}}
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-shield-exclamation me-2" style="color:#ef4444"></i>Cheating Logs</span>
        <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">
            {{ $logs->total() }} total
        </span>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0" style="font-size:0.855rem">
                <thead>
                    <tr>
                        <th style="width:220px">Student</th>
                        <th>Exam</th>
                        <th>Violation</th>
                        <th style="width:90px;text-align:center">Warning #</th>
                        <th style="width:160px">Time</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr>
                        {{-- Student --}}
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#b91c1c,#ef4444);color:#fff;display:flex;align-items:center;justify-content:center;font-size:0.72rem;font-weight:700;flex-shrink:0">
                                    {{ strtoupper(substr($log->student->name ?? '?', 0, 1)) }}
                                </div>
                                <div>
                                    <div style="font-weight:600;font-size:0.855rem">
                                        {{ $log->student->name ?? '—' }}
                                    </div>
                                    <div style="font-size:0.7rem;color:#9ca3af">
                                        {{ $log->student->email ?? '' }}
                                    </div>
                                </div>
                            </div>
                        </td>

                        {{-- Exam --}}
                        <td style="font-size:0.855rem;color:var(--text-1,#111827)">
                            {{ $log->attempt->exam->title ?? '—' }}
                        </td>

                        {{-- Violation Type --}}
                        <td>
                            @php
                                $vtype = $log->violation_type ?? '';
                                $vColor = match(true) {
                                    str_contains($vtype, 'tab')      => ['bg'=>'#fff7ed','color'=>'#c2410c'],
                                    str_contains($vtype, 'copy')     => ['bg'=>'#fef9c3','color'=>'#854d0e'],
                                    str_contains($vtype, 'focus')    => ['bg'=>'#fef2f2','color'=>'#b91c1c'],
                                    str_contains($vtype, 'fullscreen')=> ['bg'=>'#f5f3ff','color'=>'#6d28d9'],
                                    default                          => ['bg'=>'#f1f5f9','color'=>'#475569'],
                                };
                            @endphp
                            <span style="display:inline-flex;align-items:center;gap:0.3rem;padding:0.22rem 0.65rem;border-radius:20px;font-size:0.75rem;font-weight:700;background:{{ $vColor['bg'] }};color:{{ $vColor['color'] }}">
                                <i class="bi bi-exclamation-triangle-fill" style="font-size:0.65rem"></i>
                                {{ ucfirst(str_replace('_', ' ', $vtype)) ?: '—' }}
                            </span>
                        </td>

                        {{-- Warning Number --}}
                        <td style="text-align:center">
                            @php
                                $wn = (int) ($log->warning_number ?? 0);
                                $wStyle = $wn >= 3
                                    ? 'background:#fef2f2;color:#b91c1c'
                                    : ($wn == 2 ? 'background:#fff7ed;color:#c2410c' : 'background:#f0fdf4;color:#166534');
                            @endphp
                            <span style="display:inline-block;min-width:28px;padding:0.2rem 0.5rem;border-radius:20px;font-size:0.78rem;font-weight:800;{{ $wStyle }}">
                                {{ $wn }}
                            </span>
                        </td>

                        {{-- Time --}}
                        <td style="font-size:0.78rem;color:#6b7280;white-space:nowrap">
                            <div>{{ $log->created_at->format('M d, Y') }}</div>
                            <div style="font-size:0.7rem;color:#9ca3af">{{ $log->created_at->format('H:i:s') }}</div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="bi bi-shield-check d-block mb-2" style="font-size:2rem;opacity:0.3;color:#22c55e"></i>
                            No cheating logs found
                            @if(request('search') || request('violation_type'))
                                for the current filters.
                                <a href="{{ route('admin.cheating-logs') }}" class="d-block mt-1" style="font-size:0.8rem">Clear filters</a>
                            @else
                                .
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($logs->hasPages())
        <div class="p-3 border-top d-flex align-items-center justify-content-between flex-wrap gap-2">
            <span class="text-muted" style="font-size:0.8rem">
                Showing {{ $logs->firstItem() }} to {{ $logs->lastItem() }} of {{ $logs->total() }} entries
            </span>
            {{ $logs->links() }}
        </div>
        @endif
    </div>
</div>

@endsection
