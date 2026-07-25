@extends('layouts.app')
@section('title', 'Exam Results')
@section('page-title', 'Exam Results')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Results'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')

{{-- Stats row --}}
<div class="row g-3 mb-4">
    @foreach([
        ['label'=>'Total',      'value'=>$stats['total'],   'icon'=>'bi-list-check',         'color'=>'var(--royal,#3730a3)'],
        ['label'=>'Passed',     'value'=>$stats['passed'],  'icon'=>'bi-check-circle-fill',   'color'=>'#22c55e'],
        ['label'=>'Failed',     'value'=>$stats['failed'],  'icon'=>'bi-x-circle-fill',       'color'=>'#ef4444'],
        ['label'=>'Avg Score',  'value'=>$stats['avg_pct'].'%', 'icon'=>'bi-bar-chart-fill',  'color'=>'#f59e0b'],
    ] as $s)
    <div class="col-sm-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div style="width:42px;height:42px;border-radius:10px;background:{{ $s['color'] }}1a;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <i class="bi {{ $s['icon'] }}" style="font-size:1.2rem;color:{{ $s['color'] }}"></i>
                </div>
                <div>
                    <div style="font-size:1.4rem;font-weight:800;color:var(--text-1)">{{ $s['value'] }}</div>
                    <div style="font-size:0.75rem;color:#6b7280">{{ $s['label'] }}</div>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Filter bar --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-sm-6 col-md-3">
                <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600">Student</label>
                <select name="student_id" class="form-select form-select-sm">
                    <option value="">All Students</option>
                    @foreach($students as $s)
                    <option value="{{ $s->id }}" {{ request('student_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-6 col-md-2">
                <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600">Academic Year</label>
                <select name="academic_year_id" class="form-select form-select-sm">
                    <option value="">All Years</option>
                    @foreach($academicYears as $ay)
                    <option value="{{ $ay->id }}" {{ request('academic_year_id') == $ay->id ? 'selected' : '' }}>{{ $ay->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-4 col-md-1">
                <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600">Semester</label>
                <select name="semester" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="1" {{ request('semester') === '1' ? 'selected' : '' }}>Sem 1</option>
                    <option value="2" {{ request('semester') === '2' ? 'selected' : '' }}>Sem 2</option>
                </select>
            </div>
            <div class="col-sm-4 col-md-2">
                <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600">Year Level</label>
                <select name="year_level_id" class="form-select form-select-sm">
                    <option value="">All Levels</option>
                    @foreach($yearLevels as $yl)
                    <option value="{{ $yl->id }}" {{ request('year_level_id') == $yl->id ? 'selected' : '' }}>{{ $yl->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-4 col-md-2">
                <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600">Course</label>
                <select name="course_id" class="form-select form-select-sm">
                    <option value="">All Courses</option>
                    @foreach($courses as $c)
                    <option value="{{ $c->id }}" {{ request('course_id') == $c->id ? 'selected' : '' }}>{{ $c->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-4 col-md-1">
                <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600">Status</label>
                <select name="is_passed" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="1" {{ request('is_passed') === '1' ? 'selected' : '' }}>Passed</option>
                    <option value="0" {{ request('is_passed') === '0' ? 'selected' : '' }}>Failed</option>
                </select>
            </div>
            <div class="col-auto d-flex gap-1">
                <button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('admin.results.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
            </div>
        </form>
    </div>
</div>

{{-- Results table --}}
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-list-check me-2"></i>Results</span>
        <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">{{ $results->total() }} total</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0" style="font-size:0.84rem">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Exam</th>
                        <th>Course</th>
                        <th>Score</th>
                        <th>%</th>
                        <th>Grade</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($results as $r)
                    <tr>
                        <td>
                            <div style="font-weight:600">{{ $r->student->name ?? '—' }}</div>
                            <div style="font-size:0.7rem;color:#9ca3af">{{ $r->student->email ?? '' }}</div>
                        </td>
                        <td>{{ $r->exam->title ?? '—' }}</td>
                        <td style="font-size:0.78rem;color:#6b7280">{{ $r->exam->course->title ?? '—' }}</td>
                        <td>
                            <span style="font-weight:700">{{ $r->obtained_marks }}</span>
                            <span class="text-muted">/{{ $r->total_marks }}</span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-1">
                                <div style="width:50px;height:5px;background:#e5e7eb;border-radius:3px;overflow:hidden">
                                    <div style="width:{{ min($r->percentage,100) }}%;height:100%;background:{{ $r->is_passed ? '#22c55e' : '#ef4444' }};border-radius:3px"></div>
                                </div>
                                <span style="font-size:0.78rem;font-weight:600">{{ $r->percentage }}%</span>
                            </div>
                        </td>
                        <td>
                            <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">{{ $r->grade ?? '—' }}</span>
                        </td>
                        <td>
                            @if($r->isDisqualified())
                                <div class="d-flex align-items-center gap-1">
                                    <span style="font-size:0.72rem;font-weight:700;padding:3px 8px;border-radius:5px;background:#fef3c7;color:#92400e;white-space:nowrap">
                                        <i class="bi bi-exclamation-triangle me-1"></i>Failed (Cheating)
                                    </span>
                                    @if($r->violation_reason)
                                    <button class="btn btn-sm btn-outline-warning p-0 d-flex align-items-center justify-content-center"
                                            style="width:24px;height:24px;border-radius:50%;flex-shrink:0"
                                            data-bs-toggle="modal"
                                            data-bs-target="#violationModal{{ $r->id }}"
                                            title="View violation details">
                                        <i class="bi bi-info-circle" style="font-size:0.8rem"></i>
                                    </button>
                                    @endif
                                </div>
                            @elseif($r->is_passed)
                                <span class="badge bg-success">Passed</span>
                            @else
                                <span class="badge bg-danger">Failed</span>
                            @endif
                        </td>
                        <td style="font-size:0.75rem;color:#6b7280">{{ $r->created_at->format('M d, Y') }}</td>
                        <td>
                            <a href="{{ route('admin.results.student', $r->student) }}"
                               class="btn btn-xs btn-outline-secondary" title="Full history">
                                <i class="bi bi-person-lines-fill"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="bi bi-bar-chart d-block mb-2" style="font-size:2rem;opacity:0.3"></i>
                            No results found for the selected filters.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($results->hasPages())
        <div class="p-3 border-top d-flex align-items-center justify-content-between flex-wrap gap-2">
            <span class="text-muted" style="font-size:0.8rem">
                Showing {{ $results->firstItem() }} to {{ $results->lastItem() }} of {{ $results->total() }} entries
            </span>
            {{ $results->links() }}
        </div>
        @endif
    </div>
</div>

{{-- ── Violation Detail Modals ─────────────────────────────────────────── --}}
@foreach($results as $r)
    @if($r->isDisqualified() && $r->violation_reason)
    <div class="modal fade" id="violationModal{{ $r->id }}" tabindex="-1" aria-labelledby="violationLabel{{ $r->id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width:480px">
            <div class="modal-content" style="border-radius:12px;border:none;box-shadow:0 8px 32px rgba(0,0,0,0.18)">
                <div class="modal-header" style="background:#fef3c7;border-bottom:2px solid #f59e0b;border-radius:12px 12px 0 0;padding:16px 20px">
                    <h5 class="modal-title d-flex align-items-center gap-2" id="violationLabel{{ $r->id }}" style="color:#92400e;font-size:1rem;font-weight:700">
                        <i class="bi bi-shield-exclamation"></i>Cheating Violation Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding:20px 22px">

                    <div class="mb-3">
                        <div style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px">Student:</div>
                        <div style="font-size:0.9rem;color:#111827">{{ $r->student->name }} ({{ $r->student->email }})</div>
                    </div>

                    <div class="mb-3">
                        <div style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px">Exam:</div>
                        <div style="font-size:0.9rem;color:#111827">{{ $r->exam->title ?? '—' }}</div>
                    </div>

                    <div class="mb-3">
                        <div style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px">Violation Reason:</div>
                        <div style="background:#fef3c7;border:1.5px solid #f59e0b;border-radius:8px;padding:10px 14px;font-size:0.86rem;color:#92400e">
                            <i class="bi bi-exclamation-triangle me-2"></i>{{ $r->violation_reason }}
                        </div>
                    </div>

                    @if($r->disqualified_at)
                    <div class="mb-3">
                        <div style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px">Disqualified At:</div>
                        <div style="font-size:0.88rem;color:#374151">{{ $r->disqualified_at->format('M d, Y H:i:s') }}</div>
                    </div>
                    @endif

                    <div class="mb-1">
                        <div style="font-size:0.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:8px">Actual Performance:</div>
                        <div class="d-flex gap-4">
                            <div>
                                <div style="font-size:0.72rem;color:#9ca3af;margin-bottom:2px">Score</div>
                                <div style="font-weight:700;font-size:0.9rem">{{ $r->obtained_marks }}/{{ $r->total_marks }}</div>
                            </div>
                            <div>
                                <div style="font-size:0.72rem;color:#9ca3af;margin-bottom:2px">Percentage</div>
                                <div style="font-weight:700;font-size:0.9rem">{{ $r->percentage }}%</div>
                            </div>
                        </div>
                        <div style="font-size:0.75rem;color:#9ca3af;margin-top:8px">
                            <i class="bi bi-info-circle me-1"></i>Marks preserved for audit purposes
                        </div>
                    </div>

                </div>
                <div class="modal-footer" style="padding:12px 20px;border-top:1px solid #f0f0f0">
                    <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    @endif
@endforeach
@endsection


