@extends('layouts.app')
@section('title', 'Academic Results')
@section('page-title', 'Academic Results')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Results'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')

@if(empty($summary))

<div class="card">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-bar-chart d-block mb-2" style="font-size:3rem;opacity:0.3"></i>
        <h6>No results yet</h6>
        <p class="small mb-0">Results will appear here once exams are published and students have taken them.</p>
    </div>
</div>

@else

@php
    $tableRows = [];
    foreach ($summary as $ayId => $ylGroups) {
        $ay      = $ayMap[$ayId] ?? null;
        $ayLabel = $ay ? $ay->name : 'Unknown Year';
        foreach ($ylGroups as $yl => $semGroups) {
            $ylLabel = \App\Models\Course::$yearLevelLabels[$yl] ?? 'Year ' . $yl;
            foreach ($semGroups as $sem => $courseGroups) {
                $semLabel = \App\Models\Course::$semesterLabels[$sem] ?? 'Semester ' . $sem;
                $totalSubjects = count($courseGroups);
                $totalStudents = 0; $totalPassed = 0; $totalFailed = 0; $totalCheating = 0;
                foreach ($courseGroups as $cg) {
                    $totalStudents += $cg['students'];
                    $totalPassed   += $cg['passed'];
                    $totalFailed   += $cg['failed'];
                    $totalCheating += $cg['cheating'];
                }
                $rowKey = $ayId . '_' . $yl . '_' . $sem;
                $tableRows[] = compact(
                    'rowKey','ayLabel','ylLabel','semLabel',
                    'totalSubjects','totalStudents','totalPassed','totalFailed','totalCheating',
                    'courseGroups'
                );
            }
        }
    }
@endphp

{{-- ══ SUMMARY TABLE ══════════════════════════════════════════════════════ --}}
<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-table me-2"></i>Academic Result Summary</span>
        <span class="badge" style="background:#ede9fe;color:#3730a3">
            {{ count($tableRows) }} group{{ count($tableRows) !== 1 ? 's' : '' }}
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0 align-middle" style="font-size:0.84rem">
                <thead style="background:#f8faff">
                    <tr>
                        <th class="ps-3" style="white-space:nowrap">Academic Year</th>
                        <th style="white-space:nowrap">Year Level</th>
                        <th style="white-space:nowrap">Semester</th>
                        <th class="text-center">Subjects</th>
                        <th class="text-center">Students</th>
                        <th class="text-center" style="color:#22c55e">Passed</th>
                        <th class="text-center" style="color:#ef4444">Failed</th>
                        <th class="text-center" style="color:#f59e0b">Cheating</th>
                        <th class="text-center">Detail</th>
                    </tr>
                </thead>
                <tbody>

                @foreach($tableRows as $row)

                <tr>
                    <td class="ps-3" style="font-weight:600;color:var(--blc-navy,#0b2a5b)">{{ $row['ayLabel'] }}</td>
                    <td>{{ $row['ylLabel'] }}</td>
                    <td><span class="badge" style="background:#ede9fe;color:#3730a3;font-size:0.72rem">{{ $row['semLabel'] }}</span></td>
                    <td class="text-center" style="font-weight:700">{{ $row['totalSubjects'] }}</td>
                    <td class="text-center" style="font-weight:700">{{ $row['totalStudents'] }}</td>
                    <td class="text-center"><span style="font-weight:700;color:#166534">{{ $row['totalPassed'] }}</span></td>
                    <td class="text-center"><span style="font-weight:700;color:#991b1b">{{ $row['totalFailed'] }}</span></td>
                    <td class="text-center"><span style="font-weight:700;color:#92400e">{{ $row['totalCheating'] }}</span></td>
                    <td class="text-center">
                        <button type="button"
                                class="btn btn-sm btn-outline-primary detail-toggle"
                                data-target="detail-{{ $row['rowKey'] }}"
                                style="font-size:0.75rem;padding:3px 10px">
                            <i class="bi bi-chevron-down me-1"></i>Detail
                        </button>
                    </td>
                </tr>

                {{-- Detail accordion row --}}
                <tr id="detail-{{ $row['rowKey'] }}" class="detail-row" style="display:none">
                    <td colspan="9" style="padding:0;background:#fafbff;border-top:none">
                        <div style="padding:1rem 1.5rem 1.5rem">

                            @foreach($row['courseGroups'] as $cgi => $cg)
                            @php $subjectKey = $row['rowKey'] . '_subj_' . $cg['course']->id; @endphp

                            <div class="mb-2" style="border:1.5px solid #e2e8f0;border-radius:10px;overflow:hidden">
                                <div class="d-flex align-items-center justify-content-between px-3 py-2"
                                     style="background:#fff;cursor:pointer"
                                     onclick="toggleSection('{{ $subjectKey }}')">
                                    <div class="d-flex align-items-center gap-3">
                                        <i class="bi bi-book-fill" style="color:var(--blc-navy,#0b2a5b)"></i>
                                        <span style="font-weight:700;color:var(--blc-navy,#0b2a5b)">{{ $cg['course']->title }}</span>
                                        <span class="text-muted small">{{ $cg['course']->code }}</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="small text-muted">Students: <strong>{{ $cg['students'] }}</strong></span>
                                        <span class="small" style="color:#166534">Passed: <strong>{{ $cg['passed'] }}</strong></span>
                                        <span class="small" style="color:#991b1b">Failed: <strong>{{ $cg['failed'] }}</strong></span>
                                        @if($cg['cheating'] > 0)
                                        <span class="small" style="color:#92400e">Cheating: <strong>{{ $cg['cheating'] }}</strong></span>
                                        @endif
                                        <i class="bi bi-chevron-down subject-chevron-{{ $subjectKey }}"
                                           style="font-size:0.8rem;color:#6b7280;transition:transform 0.2s"></i>
                                    </div>
                                </div>

                                <div id="{{ $subjectKey }}"
                                     style="display:none;border-top:1px solid #e2e8f0;background:#f8faff;padding:1rem 1.25rem">

                                    <div class="d-flex flex-wrap gap-3 mb-3">
                                        @foreach([
                                            ['label'=>'Students',        'val'=>$cg['students'], 'color'=>'#374151'],
                                            ['label'=>'Passed',          'val'=>$cg['passed'],   'color'=>'#166534'],
                                            ['label'=>'Failed',          'val'=>$cg['failed'],   'color'=>'#991b1b'],
                                            ['label'=>'Cheating Failed', 'val'=>$cg['cheating'], 'color'=>'#92400e'],
                                            ['label'=>'Absent',          'val'=>$cg['absent'],   'color'=>'#6b7280'],
                                        ] as $stat)
                                        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:0.4rem 0.85rem;min-width:90px">
                                            <div style="font-size:0.68rem;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:0.04em">{{ $stat['label'] }}</div>
                                            <div style="font-size:1.1rem;font-weight:800;color:{{ $stat['color'] }}">{{ $stat['val'] }}</div>
                                        </div>
                                        @endforeach
                                    </div>

                                    @foreach($cg['exams'] as $esi => $es)
                                    @php $examKey = $subjectKey . '_exam_' . $es['exam']->id; @endphp

                                    <div class="mb-2" style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;background:#fff">
                                        <div class="d-flex align-items-center justify-content-between px-3 py-2"
                                             style="cursor:pointer;background:#fff"
                                             onclick="toggleSection('{{ $examKey }}')">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="bi bi-file-earmark-text" style="color:#6b7280"></i>
                                                <span style="font-weight:600;font-size:0.875rem">{{ $es['exam']->title }}</span>
                                                @if($es['schedule'])
                                                <span class="text-muted" style="font-size:0.72rem">{{ $es['schedule']->ends_at->format('M d, Y') }}</span>
                                                @endif
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge bg-success bg-opacity-10 text-success" style="font-size:0.7rem">P: {{ $es['passed'] }}</span>
                                                <span class="badge bg-danger  bg-opacity-10 text-danger"  style="font-size:0.7rem">F: {{ $es['failed'] }}</span>
                                                @if($es['cheating'] > 0)
                                                <span class="badge bg-warning bg-opacity-10 text-warning" style="font-size:0.7rem">C: {{ $es['cheating'] }}</span>
                                                @endif
                                                @if($es['absent'] > 0)
                                                <span class="badge bg-secondary bg-opacity-10 text-secondary" style="font-size:0.7rem">A: {{ $es['absent'] }}</span>
                                                @endif
                                                <i class="bi bi-chevron-down" style="font-size:0.75rem;color:#9ca3af"></i>
                                            </div>
                                        </div>

                                        <div id="{{ $examKey }}" style="display:none;border-top:1px solid #f0f0f0">
                                            <div class="table-responsive">
                                                <table class="table table-sm mb-0" style="font-size:0.8rem">
                                                    <thead style="background:#f8faff">
                                                        <tr>
                                                            <th class="ps-3">Student</th>
                                                            <th class="text-center">Score</th>
                                                            <th class="text-center">%</th>
                                                            <th>Status</th>
                                                            <th>Notes</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                    @foreach($es['studentRows'] as $sr)
                                                    <tr @if($sr['status'] === 'DISQUALIFIED') style="background:#fffbeb" @endif>
                                                        <td class="ps-3">
                                                            <div style="font-weight:600">{{ $sr['student']->name }}</div>
                                                            <div style="font-size:0.68rem;color:#9ca3af">{{ $sr['student']->email }}</div>
                                                        </td>
                                                        <td class="text-center" style="font-weight:700">
                                                            @if($sr['status'] === 'ABSENT')
                                                                <span class="text-muted">—</span>
                                                            @else
                                                                {{ $sr['score'] }}
                                                            @endif
                                                        </td>
                                                        <td class="text-center">
                                                            @if($sr['percentage'] !== null)
                                                            <div class="d-flex align-items-center gap-1 justify-content-center">
                                                                <div style="width:40px;height:4px;background:#e5e7eb;border-radius:2px;overflow:hidden">
                                                                    <div style="height:100%;border-radius:2px;width:{{ min($sr['percentage'],100) }}%;background:{{ $sr['status']==='PASSED' ? '#22c55e' : ($sr['status']==='DISQUALIFIED' ? '#f59e0b' : '#ef4444') }}"></div>
                                                                </div>
                                                                <span style="font-size:0.75rem;font-weight:600">{{ $sr['percentage'] }}%</span>
                                                            </div>
                                                            @else
                                                                <span class="text-muted">—</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if($sr['status'] === 'PASSED')
                                                                <span class="badge bg-success" style="font-size:0.7rem">Passed</span>
                                                            @elseif($sr['status'] === 'DISQUALIFIED')
                                                                <span class="badge bg-warning text-dark" style="font-size:0.7rem">
                                                                    <i class="bi bi-exclamation-triangle me-1"></i>Cheating Terminated
                                                                </span>
                                                            @elseif($sr['status'] === 'ABSENT')
                                                                <span class="badge bg-secondary" style="font-size:0.7rem">Absent</span>
                                                            @else
                                                                <span class="badge bg-danger" style="font-size:0.7rem">Failed</span>
                                                            @endif
                                                        </td>
                                                        <td style="color:#6b7280;font-size:0.75rem">
                                                            @if($sr['status'] === 'DISQUALIFIED')
                                                                {{-- Passes all data to the single shared modal via data-* attributes --}}
                                                                <button type="button"
                                                                        class="btn btn-sm cheat-detail-btn"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#cheatingModal"
                                                                        data-student="{{ $sr['student']->name }} ({{ $sr['student']->email }})"
                                                                        data-exam="{{ $es['exam']->title }}"
                                                                        data-reason="{{ $sr['result']?->violation_reason ?? 'Cheating detected' }}"
                                                                        data-disqualified="{{ $sr['result']?->disqualified_at?->format('M d, Y H:i:s') ?? '—' }}"
                                                                        data-score="{{ $sr['score'] }}"
                                                                        data-percentage="{{ $sr['percentage'] }}%"
                                                                        data-warnings="{{ $sr['warningCount'] }}/3"
                                                                        data-violations="{{ implode('|', $sr['violations']) }}"
                                                                        style="font-size:0.7rem;padding:2px 8px;background:#fef3c7;color:#92400e;border:1px solid #f59e0b;border-radius:5px;font-weight:600">
                                                                    <i class="bi bi-shield-exclamation me-1"></i>View Details
                                                                </button>
                                                            @elseif($sr['status'] === 'ABSENT')
                                                                No Attempt
                                                            @elseif($sr['status'] === 'FAILED')
                                                                Low Score
                                                            @else
                                                                —
                                                            @endif
                                                        </td>
                                                    </tr>
                                                    @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>{{-- /exam card --}}
                                    @endforeach

                                </div>{{-- /subject body --}}
                            </div>{{-- /subject card --}}
                            @endforeach

                        </div>
                    </td>
                </tr>{{-- /detail row --}}

                @endforeach

                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ══ SHARED CHEATING VIOLATION MODAL ══════════════════════════════════ --}}
<div class="modal fade" id="cheatingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:480px">
        <div class="modal-content" style="border-radius:12px;border:none;box-shadow:0 8px 32px rgba(0,0,0,0.18)">

            {{-- Header --}}
            <div class="modal-header"
                 style="background:#fef3c7;border-bottom:2px solid #f59e0b;border-radius:12px 12px 0 0;padding:14px 20px">
                <h5 class="modal-title d-flex align-items-center gap-2"
                    style="color:#92400e;font-size:1rem;font-weight:700;margin:0">
                    <i class="bi bi-shield-exclamation"></i>Cheating Violation Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            {{-- Body --}}
            <div class="modal-body" style="padding:20px 22px">

                <div class="mb-3">
                    <div style="font-size:0.7rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:3px">STUDENT:</div>
                    <div id="cm-student" style="font-size:0.9rem;color:#111827;font-weight:500"></div>
                </div>

                <div class="mb-3">
                    <div style="font-size:0.7rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:3px">EXAM:</div>
                    <div id="cm-exam" style="font-size:0.9rem;color:#111827"></div>
                </div>

                <div class="mb-3">
                    <div style="font-size:0.7rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:5px">VIOLATION REASON:</div>
                    <div id="cm-reason"
                         style="background:#fef3c7;border:1.5px solid #f59e0b;border-radius:8px;padding:10px 14px;font-size:0.86rem;color:#92400e">
                    </div>
                </div>

                <div class="mb-3" id="cm-violations-wrap" style="display:none">
                    <div style="font-size:0.7rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:5px">
                        VIOLATIONS &nbsp;&middot;&nbsp; WARNINGS: <span id="cm-warnings" style="color:#92400e"></span>
                    </div>
                    <div id="cm-violations" style="display:flex;flex-wrap:wrap;gap:5px"></div>
                </div>

                <div class="mb-3">
                    <div style="font-size:0.7rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:3px">DISQUALIFIED AT:</div>
                    <div id="cm-disqualified" style="font-size:0.88rem;color:#374151"></div>
                </div>

                <div style="border-top:1px solid #f0f0f0;padding-top:14px">
                    <div style="font-size:0.7rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:8px">ACTUAL PERFORMANCE:</div>
                    <div class="d-flex gap-4">
                        <div>
                            <div style="font-size:0.72rem;color:#9ca3af;margin-bottom:2px">Score</div>
                            <div id="cm-score" style="font-weight:700;font-size:0.95rem"></div>
                        </div>
                        <div>
                            <div style="font-size:0.72rem;color:#9ca3af;margin-bottom:2px">Percentage</div>
                            <div id="cm-percentage" style="font-weight:700;font-size:0.95rem"></div>
                        </div>
                    </div>
                    <div style="font-size:0.75rem;color:#9ca3af;margin-top:8px">
                        <i class="bi bi-info-circle me-1"></i>Marks preserved for audit purposes
                    </div>
                </div>

            </div>

            {{-- Footer --}}
            <div class="modal-footer" style="padding:12px 20px;border-top:1px solid #f0f0f0">
                <button type="button" class="btn btn-secondary btn-sm px-4"
                        data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@endif
@endsection

@push('styles')
<style>
.detail-row td { background: #fafbff !important; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    // ── Summary row detail toggle ─────────────────────────────────────────
    document.querySelectorAll('.detail-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = this.dataset.target;
            var row = document.getElementById(targetId);
            if (!row) return;
            var isOpen = row.style.display !== 'none';
            row.style.display = isOpen ? 'none' : 'table-row';
            this.innerHTML = isOpen
                ? '<i class="bi bi-chevron-down me-1"></i>Detail'
                : '<i class="bi bi-chevron-up me-1"></i>Close';
        });
    });

    // ── Subject / exam accordion toggle ──────────────────────────────────
    window.toggleSection = function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        var isOpen = el.style.display !== 'none';
        el.style.display = isOpen ? 'none' : (el.tagName === 'TR' ? 'table-row' : 'block');
        var chevron = document.querySelector('.subject-chevron-' + id);
        if (chevron) chevron.style.transform = isOpen ? '' : 'rotate(180deg)';
    };

    // ── Cheating modal — populate from data-* attributes ─────────────────
    document.addEventListener('show.bs.modal', function (e) {
        if (e.target.id !== 'cheatingModal') return;

        var btn = e.relatedTarget;
        if (!btn) return;

        document.getElementById('cm-student').textContent     = btn.dataset.student     || '—';
        document.getElementById('cm-exam').textContent        = btn.dataset.exam        || '—';
        document.getElementById('cm-disqualified').textContent= btn.dataset.disqualified|| '—';
        document.getElementById('cm-score').textContent       = btn.dataset.score       || '—';
        document.getElementById('cm-percentage').textContent  = btn.dataset.percentage  || '—';
        document.getElementById('cm-warnings').textContent    = btn.dataset.warnings    || '';

        // Violation reason
        var reasonEl = document.getElementById('cm-reason');
        reasonEl.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>' +
            (btn.dataset.reason || 'Cheating detected');

        // Violations list
        var violationsRaw = btn.dataset.violations || '';
        var violationsList = violationsRaw ? violationsRaw.split('|').filter(Boolean) : [];
        var wrapEl = document.getElementById('cm-violations-wrap');
        var listEl = document.getElementById('cm-violations');

        if (violationsList.length) {
            listEl.innerHTML = violationsList.map(function (v) {
                return '<span style="background:#fef3c7;color:#92400e;border:1px solid #f59e0b;border-radius:5px;padding:2px 8px;font-size:0.72rem;font-weight:700">' +
                    v.toUpperCase().replace(/_/g, ' ') + '</span>';
            }).join('');
            wrapEl.style.display = 'block';
        } else {
            wrapEl.style.display = 'none';
        }
    });
})();
</script>
@endpush
