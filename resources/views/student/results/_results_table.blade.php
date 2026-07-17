@php
    /** @var \Illuminate\Support\Collection|\App\Models\Result[] $rows */
    $prefix = $prefix ?? 'result';
@endphp

<div class="table-responsive">
    <table class="table mb-0" style="font-size:0.84rem">
        <thead>
            <tr>
                <th style="width:36px"></th>
                <th>Exam</th>
                <th>Course</th>
                <th>Score</th>
                <th>%</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $r)
            @php $collapseId = $prefix.'-review-'.$r->id; @endphp
            <tr class="result-row" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}"
                aria-expanded="false" aria-controls="{{ $collapseId }}"
                style="cursor:pointer">
                <td class="text-center">
                    <i class="bi bi-chevron-down result-expand-icon text-muted"></i>
                </td>
                <td style="font-weight:600">{{ $r->exam->title ?? '—' }}</td>
                <td style="color:#6b7280">{{ $r->exam->course->title ?? '—' }}</td>
                <td>
                    <span style="font-weight:700">{{ $r->obtained_marks }}</span>
                    <span class="text-muted">/{{ $r->total_marks }}</span>
                </td>
                <td>
                    <div class="d-flex align-items-center gap-1">
                        <div style="width:50px;height:5px;background:#e5e7eb;border-radius:3px;overflow:hidden">
                            <div style="width:{{ min($r->percentage,100) }}%;height:100%;background:{{ $r->is_passed ? '#22c55e' : '#ef4444' }};border-radius:3px"></div>
                        </div>
                        <span>{{ $r->percentage }}%</span>
                    </div>
                </td>
                <td>
                    @if($r->isDisqualified())
                        <span class="badge bg-warning text-dark">Failed (Cheating)</span>
                    @elseif($r->is_passed)
                        <span class="badge bg-success">Passed</span>
                    @else
                        <span class="badge bg-danger">Failed</span>
                    @endif
                </td>
                <td style="font-size:0.75rem;color:#6b7280">{{ $r->created_at->format('M d, Y') }}</td>
            </tr>
            <tr class="result-detail-row">
                <td colspan="7" class="p-0 border-0">
                    <div id="{{ $collapseId }}" class="collapse">
                        <div class="result-review-panel">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <i class="bi bi-eye-fill" style="color:var(--blc-gold,#d4a51c)"></i>
                                <span style="font-weight:700;color:var(--blc-navy,#0b2a5b)">Answer Review</span>
                                <span class="badge ms-auto" style="background:#f0fdf4;color:#166534;font-size:0.72rem">
                                    Question · Your answer · Correct answer
                                </span>
                            </div>
                            @include('student.results._answer_review', ['result' => $r])
                        </div>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center py-4 text-muted">
                    <span class="small">No results for this semester yet.</span>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
