@extends('layouts.app')
@section('title', 'My Exams')
@section('page-title', 'My Exams')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Teacher', 'url' => route('teacher.dashboard')],
        ['label' => 'My Exams'],
    ]])
@endsection
@section('sidebar')
@include('partials.teacher-sidebar')

@endsection
@section('content')
<div class="page-header">
    <div></div>
    <a href="{{ route('teacher.exams.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i> New Exam
    </a>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-file-earmark-text me-2"></i>All Exams</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table datatable mb-0">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Course</th>
                        <th>Questions</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($exams as $e)
                    <tr>
                        <td><span class="fw-600" style="font-weight:600;color:var(--blc-navy)">{{ $e->title }}</span></td>
                        <td class="text-muted">{{ $e->course->title }}</td>
                        <td>
                            <span class="badge" style="background:#f0f4ff;color:var(--blc-navy-2)">
                                {{ $e->questions_count ?? '—' }}
                            </span>
                        </td>
                        <td>
                            <span class="status-pill status-{{ $e->status === 'pending_approval' ? 'pending' : $e->status }}">
                                {{ ucfirst(str_replace('_', ' ', $e->status)) }}
                            </span>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="{{ route('teacher.exams.show', $e) }}" class="btn btn-sm btn-primary">
                                    <i class="bi bi-arrow-right me-1"></i>Open
                                </a>
                                @if($e->status === 'draft')
                                <form action="{{ route('teacher.exams.destroy', $e) }}" method="POST"
                                      onsubmit="return confirm('Delete exam \"{{ addslashes($e->title) }}\"?\nThis will permanently remove all questions and cannot be undone.')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete draft exam">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="bi bi-file-earmark-x d-block mb-2" style="font-size:2rem"></i>
                            No exams yet. <a href="{{ route('teacher.exams.create') }}">Create your first exam</a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
