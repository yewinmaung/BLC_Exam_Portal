@extends('layouts.app')
@section('title', 'Available Exams')
@section('page-title', 'Available Exams')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Student', 'url' => route('student.dashboard')],
        ['label' => 'Exams'],
    ]])
@endsection
@section('sidebar')
@include('partials.student-sidebar')

@endsection

@section('content')
<div class="row g-3">
    @forelse($exams as $e)
    <div class="col-md-6 col-xl-4">
        <div class="card h-100" style="transition:transform 0.2s,box-shadow 0.2s" onmouseover="this.style.transform='translateY(-4px)'" onmouseout="this.style.transform=''">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-start justify-content-between mb-2">
                    <span class="status-pill {{ $e->status === 'approved' ? 'status-approved' : 'status-published' }}">
                        {{ $e->status === 'approved' ? 'Ready' : 'Published' }}
                    </span>
                    @if($e->activeSchedule)
                    <span class="text-muted small"><i class="bi bi-clock me-1"></i>{{ $e->activeSchedule->duration_minutes }}min</span>
                    @endif
                </div>
                <h6 class="fw-700 mb-1" style="font-weight:700;color:var(--blc-navy)">{{ $e->title }}</h6>
                <p class="text-muted small mb-3"><i class="bi bi-book me-1"></i>{{ $e->course->title }}</p>
                @if($e->activeSchedule)
                <div class="mt-auto pt-2 border-top d-flex align-items-center justify-content-between">
                    <small class="text-muted">
                        <i class="bi bi-calendar3 me-1"></i>Ends {{ $e->activeSchedule->ends_at->format('M d, H:i') }}
                    </small>
                    <a href="{{ route('student.exams.show', $e) }}" class="btn btn-sm btn-primary">
                        Start <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
                @else
                <div class="mt-auto pt-2 border-top">
                    <a href="{{ route('student.exams.show', $e) }}" class="btn btn-sm btn-outline-primary w-100">View</a>
                </div>
                @endif
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center py-5 text-muted">
                <i class="bi bi-file-earmark-x d-block mb-3" style="font-size:3rem;opacity:0.35"></i>
                <h6>No exams available</h6>
                <p class="small mb-0">Exams will appear here once your teacher submits and admin approves them.</p>
            </div>
        </div>
    </div>
    @endforelse
</div>
@endsection
