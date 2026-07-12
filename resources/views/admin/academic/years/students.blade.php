@extends('layouts.app')
@section('title', 'Manage Students — '.$year->name)
@section('page-title', 'Manage Students')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Academic Years', 'url' => route('admin.academic.years.index')],
        ['label' => $year->name, 'url' => route('admin.academic.years.show', $year)],
        ['label' => 'Students'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')
<div class="row g-3">

    {{-- Enrolled list (full width now) --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-list-check me-2"></i>Currently Enrolled — {{ $year->name }}</span>
                <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">
                    {{ $records->total() }} total
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Year Level</th>
                                <th>Semester</th>
                                <th>Dept / Major</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($records as $r)
                            <tr>
                                <td>
                                    <div style="font-weight:600;font-size:0.875rem">{{ $r->student->name }}</div>
                                    <div style="font-size:0.72rem;color:#9ca3af">{{ $r->student->email }}</div>
                                </td>
                                <td>{{ $r->yearLevel->name ?? '—' }}</td>
                                <td>Sem {{ $r->semester }}</td>
                                <td style="font-size:0.82rem;color:#6b7280">
                                    {{ $r->department ?? '' }}
                                    @if($r->major) <br><span style="font-size:0.72rem">{{ $r->major }}</span> @endif
                                </td>
                                <td>
                                    <span class="status-pill status-{{ $r->status === 'active' ? 'approved' : ($r->status === 'promoted' ? 'published' : 'closed') }}">
                                        {{ ucfirst($r->status) }}
                                    </span>
                                </td>
                                <td>
                                    <form method="POST"
                                          action="{{ route('admin.academic.years.students.remove', [$year, $r->student]) }}"
                                          onsubmit="return confirm('Remove {{ addslashes($r->student->name) }} from this year?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Remove">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-people d-block mb-2" style="font-size:2rem;opacity:0.35"></i>
                                    No students assigned yet.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($records->hasPages())
                <div class="p-3 border-top">{{ $records->links() }}</div>
                @endif
            </div>
        </div>
    </div>

</div>
@endsection


