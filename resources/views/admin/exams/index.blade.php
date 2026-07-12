@extends('layouts.app')
@section('title', 'Exams')
@section('page-title', 'Exams')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Exams'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-file-earmark-text me-2"></i>All Exams</span>
        <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">
            {{ $exams->count() }} total
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table datatable mb-0">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Course</th>
                        <th>Teacher</th>
                        <th>Status</th>
                        <th>Schedule</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($exams as $e)
                    <tr>
                        <td style="font-weight:600;color:var(--text-1,#111827)">{{ $e->title }}</td>
                        <td class="text-muted">{{ $e->course->title }}</td>
                        <td class="text-muted">{{ $e->teacher->name }}</td>
                        <td>
                            <span class="status-pill status-{{ $e->status === 'pending_approval' ? 'pending' : $e->status }}">
                                {{ ucfirst(str_replace('_', ' ', $e->status)) }}
                            </span>
                        </td>
                        <td>
                            @if($e->activeSchedule)
                                <span style="font-size:0.78rem;color:#6b7280">
                                    <i class="bi bi-calendar3 me-1"></i>{{ $e->activeSchedule->starts_at->format('M d, H:i') }}
                                </span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="{{ route('admin.exams.show', $e) }}" class="btn btn-sm btn-primary">
                                    <i class="bi bi-gear me-1"></i>Manage
                                </a>
                                @if(in_array($e->status, ['published', 'closed']))
                                <a href="{{ route('admin.exams.results', $e) }}" 
                                   class="btn btn-sm btn-outline-primary" 
                                   title="View Results">
                                    <i class="bi bi-bar-chart-fill"></i>
                                </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-file-earmark-x d-block mb-2" style="font-size:2rem;opacity:0.3"></i>
                            No exams found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
