@extends('layouts.app')
@section('title', 'My Results')
@section('page-title', 'My Results')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Student', 'url' => route('student.dashboard')],
        ['label' => 'My Results'],
    ]])
@endsection
@section('sidebar')@include('partials.student-sidebar')@endsection

@section('content')

{{-- Summary stats (current / selected academic year) --}}
<div class="row g-3 mb-4">
    @foreach([
        ['label'=>'Exams Taken', 'value'=>$totalExams,      'icon'=>'bi-pencil-square',   'color'=>'var(--royal,#3730a3)'],
        ['label'=>'Passed',      'value'=>$passedCount,      'icon'=>'bi-check-circle',    'color'=>'#22c55e'],
        ['label'=>'Average',     'value'=>$avgPct.'%',       'icon'=>'bi-bar-chart',       'color'=>'#f59e0b'],
    ] as $s)
    <div class="col-sm-4">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div style="width:40px;height:40px;border-radius:10px;background:{{ $s['color'] }}1a;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <i class="bi {{ $s['icon'] }}" style="font-size:1.1rem;color:{{ $s['color'] }}"></i>
                </div>
                <div>
                    <div style="font-size:1.3rem;font-weight:800;color:var(--text-1)">{{ $s['value'] }}</div>
                    <div style="font-size:0.72rem;color:#6b7280">{{ $s['label'] }}</div>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Filters --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
            <div style="min-width:180px;flex:1">
                <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600">Academic Year</label>
                <select name="academic_year_id" class="form-select form-select-sm">
                    @foreach($academicYears as $ay)
                    <option value="{{ $ay->id }}" {{ (int) $selectedYearId === (int) $ay->id ? 'selected' : '' }}>
                        {{ $ay->name }}{{ $ay->is_current ? ' (Current)' : '' }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div style="min-width:110px">
                <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600">Semester</label>
                <select name="semester" class="form-select form-select-sm">
                    <option value="" {{ $selectedSemester === null ? 'selected' : '' }}>Sem 1 &amp; Sem 2</option>
                    <option value="1" {{ $selectedSemester === 1 ? 'selected' : '' }}>Semester 1</option>
                    <option value="2" {{ $selectedSemester === 2 ? 'selected' : '' }}>Semester 2</option>
                </select>
            </div>
            <div class="d-flex gap-1">
                <button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('student.results.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
            </div>
        </form>
    </div>
</div>

{{-- Current / selected year results: Sem 1 + Sem 2 --}}
<div class="card">
    <div class="card-header d-flex flex-wrap align-items-center gap-2">
        <span><i class="bi bi-list-check me-2"></i>My Exam Results</span>
        @if($selectedYear)
        <span class="badge ms-auto" style="background:var(--royal,#3730a3);color:#fff;font-weight:600">
            {{ $selectedYear->name }}
            @if($selectedYear->is_current) · Current @endif
        </span>
        @endif
    </div>
    <div class="card-body p-0">
        @if(!$selectedYear)
        <div class="text-center py-5 text-muted">
            <i class="bi bi-calendar-x d-block mb-2" style="font-size:2rem;opacity:0.3"></i>
            <div class="small">No academic year is set as current. Ask your admin to mark the current year.</div>
        </div>
        @else
            @if($selectedSemester === null || $selectedSemester === 1)
            <div class="semester-block {{ ($selectedSemester === null) ? 'border-bottom' : '' }}">
                <div class="semester-header">
                    <i class="bi bi-1-circle-fill me-2" style="color:var(--royal,#3730a3)"></i>
                    <strong>Semester 1</strong>
                    <span class="badge bg-light text-dark ms-2">{{ $sem1Results->count() }} exam{{ $sem1Results->count() !== 1 ? 's' : '' }}</span>
                </div>
                @include('student.results._results_table', ['rows' => $sem1Results, 'prefix' => 'sem1'])
            </div>
            @endif

            @if($selectedSemester === null || $selectedSemester === 2)
            <div class="semester-block">
                <div class="semester-header">
                    <i class="bi bi-2-circle-fill me-2" style="color:var(--royal,#3730a3)"></i>
                    <strong>Semester 2</strong>
                    <span class="badge bg-light text-dark ms-2">{{ $sem2Results->count() }} exam{{ $sem2Results->count() !== 1 ? 's' : '' }}</span>
                </div>
                @include('student.results._results_table', ['rows' => $sem2Results, 'prefix' => 'sem2'])
            </div>
            @endif
        @endif
    </div>
</div>

{{-- Academic history (past years only) --}}
@if(count($history) > 0)
<div class="card mt-3">
    <div class="card-header"><i class="bi bi-calendar3 me-2"></i>Academic Year History</div>
    <div class="card-body p-0">
        @foreach($history as $hi => $h)
        <div class="p-3 {{ !$loop->last ? 'border-bottom' : '' }}">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge" style="background:var(--royal,#3730a3);color:#fff">{{ $h['record']->academicYear->name ?? '—' }}</span>
                <span class="badge bg-secondary">{{ $h['record']->yearLevel->name ?? '—' }}</span>
                <span class="badge bg-light text-dark">Sem {{ $h['record']->semester }}</span>
            </div>
            @if($h['results']->count())
            <div class="table-responsive">
                <table class="table table-sm mb-0" style="font-size:0.79rem">
                    <thead>
                        <tr>
                            <th style="width:28px"></th>
                            <th>Exam</th>
                            <th>Score</th>
                            <th>%</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($h['results'] as $er)
                        @php $histCollapseId = 'hist-review-'.$hi.'-'.$er->id; @endphp
                        <tr class="result-row" data-bs-toggle="collapse" data-bs-target="#{{ $histCollapseId }}"
                            aria-expanded="false" aria-controls="{{ $histCollapseId }}"
                            style="cursor:pointer">
                            <td class="text-center">
                                <i class="bi bi-chevron-down result-expand-icon text-muted"></i>
                            </td>
                            <td>{{ $er->exam->title ?? '—' }}</td>
                            <td>{{ $er->obtained_marks }}/{{ $er->total_marks }}</td>
                            <td>{{ $er->percentage }}%</td>
                            <td>
                                @if($er->isDisqualified())
                                    <span class="badge bg-warning text-dark" style="font-size:0.65rem">Failed (Cheating)</span>
                                @elseif($er->is_passed)
                                    <span class="badge bg-success" style="font-size:0.65rem">Passed</span>
                                @else
                                    <span class="badge bg-danger" style="font-size:0.65rem">Failed</span>
                                @endif
                            </td>
                        </tr>
                        <tr class="result-detail-row">
                            <td colspan="5" class="p-0 border-0">
                                <div id="{{ $histCollapseId }}" class="collapse">
                                    <div class="result-review-panel">
                                        <div class="d-flex align-items-center gap-2 mb-3">
                                            <i class="bi bi-eye-fill" style="color:var(--blc-gold,#d4a51c)"></i>
                                            <span style="font-weight:700;color:var(--blc-navy,#0b2a5b)">Answer Review</span>
                                        </div>
                                        @include('student.results._answer_review', ['result' => $er])
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
                <div class="text-muted small py-1">No results archived for this period.</div>
            @endif
        </div>
        @endforeach
    </div>
</div>
@endif

@endsection

@push('styles')
<style>
.semester-header {
    padding: 0.75rem 1.1rem;
    background: #f8faff;
    border-bottom: 1px solid #e8edf5;
    font-size: 0.9rem;
    color: var(--blc-navy, #0b2a5b);
    display: flex;
    align-items: center;
}
.semester-block + .semester-block .semester-header {
    border-top: 1px solid #e8edf5;
}

.result-row:hover { background:#f8faff; }
.result-row[aria-expanded="true"] { background:#f0f4ff; }
.result-row[aria-expanded="true"] .result-expand-icon { transform: rotate(180deg); }
.result-expand-icon { transition: transform 0.2s ease; display:inline-block; }

.result-review-panel {
    padding: 1rem 1.25rem 1.25rem;
    background: #fafbfd;
    border-top: 1px solid #e8edf5;
    border-bottom: 1px solid #e8edf5;
}

.review-card {
    border-radius: 12px;
    padding: 1rem 1.1rem;
    margin-bottom: 0.85rem;
    border: 1.5px solid #e8edf5;
    background: #fff;
}
.review-card:last-child { margin-bottom: 0; }
.review-correct { background: #f0fdf4; border-color: #bbf7d0; }
.review-wrong   { background: #fef2f2; border-color: #fecaca; }

.student-answer-pill {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.82rem;
    font-weight: 600;
}
.student-answer-pill.correct { background: #dcfce7; color: #166534; }
.student-answer-pill.wrong   { background: #fee2e2; color: #991b1b; }

.q-number {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border-radius: 6px;
    background: var(--blc-navy, #0b2a5b);
    color: #fff;
    font-size: 0.72rem;
    font-weight: 800;
    flex-shrink: 0;
}
</style>
@endpush

@push('scripts')
<script>
document.querySelectorAll('.result-row').forEach(row => {
    const target = document.querySelector(row.getAttribute('data-bs-target'));
    if (!target) return;
    target.addEventListener('show.bs.collapse', () => row.setAttribute('aria-expanded', 'true'));
    target.addEventListener('hide.bs.collapse', () => row.setAttribute('aria-expanded', 'false'));
});
</script>
@endpush
