@extends('layouts.app')
@section('title', 'Results — '.$exam->title)
@section('page-title', 'Exam Results')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Teacher', 'url' => route('teacher.dashboard')],
        ['label' => 'My Exams', 'url' => route('teacher.exams.index')],
        ['label' => $exam->title, 'url' => route('teacher.exams.show', $exam)],
        ['label' => 'Results'],
    ]])
@endsection
@section('sidebar')
@include('partials.teacher-sidebar')
@endsection

@section('content')

{{-- Exam info strip --}}
<div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
    <div>
        <h6 class="mb-0" style="font-weight:700;color:var(--text-1)">{{ $exam->title }}</h6>
        <small class="text-muted"><i class="bi bi-book me-1"></i>{{ $exam->course->title }}</small>
    </div>
    <div class="ms-auto d-flex gap-2">
        <a href="{{ route('teacher.exams.show', $exam) }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Exam
        </a>
    </div>
</div>

{{-- Filters and Search --}}
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('teacher.exams.results', $exam) }}" class="row g-3">
            <div class="col-md-4">
                <label class="form-label small text-muted">Filter Students</label>
                <select name="filter" class="form-select" onchange="this.form.submit()">
                    <option value="all" {{ ($filter ?? 'all') === 'all' ? 'selected' : '' }}>All Students</option>
                    <option value="failed" {{ ($filter ?? '') === 'failed' ? 'selected' : '' }}>Failed Students</option>
                    <option value="incomplete" {{ ($filter ?? '') === 'incomplete' ? 'selected' : '' }}>Incomplete / Not Attempted</option>
                    <option value="eligible" {{ ($filter ?? '') === 'eligible' ? 'selected' : '' }}>Eligible for Re-Attempt</option>
                    <option value="requested" {{ ($filter ?? '') === 'requested' ? 'selected' : '' }}>Re-Attempt Requested</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label small text-muted">Search Students</label>
                <input type="text" name="search" class="form-control" placeholder="Search by name or email..." value="{{ $search ?? '' }}">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search me-1"></i> Search
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Results Table with Multi-Select --}}
<form id="bulkReattemptForm" method="POST" action="{{ route('teacher.reattempts.store') }}">
    @csrf
    <input type="hidden" name="exam_id" value="{{ $exam->id }}">
    
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <span><i class="bi bi-bar-chart me-2"></i>Student Results</span>
            <div class="d-flex align-items-center gap-2">
                <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">
                    {{ $results->count() }} students
                </span>
                <span id="selectedCount" class="badge bg-primary" style="display:none">
                    <span id="selectedCountText">0</span> selected
                </span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th style="width:40px">
                                <input type="checkbox" id="selectAll" class="form-check-input" title="Select All Eligible">
                            </th>
                            <th>Student</th>
                            <th>Score</th>
                            <th>Percentage</th>
                            <th>Status</th>
                            <th>Re-attempt</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($results as $r)
                        @php
                            // Determine eligibility
                            $isEligible = false;
                            $eligibilityReason = '';
                            
                            // Check if incomplete (no attempt)
                            $isIncomplete = isset($r->is_incomplete) && $r->is_incomplete;
                            
                            // Check if has cheating/terminated attempt
                            $hasCheating = false;
                            if ($r->attempt) {
                                $hasCheating = in_array($r->attempt->status, ['terminated', 'suspicious', 'terminated_pending_review']);
                            }
                            
                            // Check if already has pending/approved request
                            $hasRequest = \App\Models\ReAttemptRequest::where('exam_id', $exam->id)
                                ->where('student_id', $r->student->id)
                                ->whereIn('status', ['pending', 'approved'])
                                ->exists();
                            
                            // Determine eligibility
                            if ($hasRequest) {
                                $isEligible = false;
                                $eligibilityReason = 'Already requested';
                            } elseif ($hasCheating) {
                                $isEligible = false;
                                $eligibilityReason = 'Security violation';
                            } elseif ($r->is_passed) {
                                $isEligible = false;
                                $eligibilityReason = 'Passed';
                            } elseif (!$r->is_passed || $isIncomplete) {
                                $isEligible = true;
                            }
                        @endphp
                        <tr class="{{ $isEligible ? 'eligible-row' : '' }}">
                            <td>
                                @if($isEligible)
                                <input type="checkbox" name="student_ids[]" value="{{ $r->student->id }}" 
                                       class="form-check-input student-checkbox">
                                @else
                                <input type="checkbox" class="form-check-input" disabled title="{{ $eligibilityReason }}">
                                @endif
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,var(--royal-deeper,#1e1b6e),var(--royal,#3730a3));color:#fff;display:flex;align-items:center;justify-content:center;font-size:0.72rem;font-weight:700;flex-shrink:0">
                                        {{ strtoupper(substr($r->student->name,0,1)) }}
                                    </div>
                                    <div>
                                        <div style="font-weight:600">{{ $r->student->name }}</div>
                                        @if($isIncomplete)
                                        <div class="text-muted small">No attempt recorded</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($isIncomplete)
                                <span class="text-muted">—</span>
                                @else
                                <span style="font-weight:700;color:var(--text-1)">{{ $r->obtained_marks }}</span>
                                <span class="text-muted">/{{ $r->total_marks }}</span>
                                @endif
                            </td>
                            <td>
                                @if($isIncomplete)
                                <span class="text-muted">—</span>
                                @else
                                <div class="d-flex align-items-center gap-2">
                                    <div style="width:60px;height:6px;background:#e5e7eb;border-radius:3px;overflow:hidden">
                                        <div style="width:{{ $r->percentage }}%;height:100%;background:{{ $r->is_passed ? '#22c55e' : '#ef4444' }};border-radius:3px"></div>
                                    </div>
                                    <span style="font-size:0.82rem;font-weight:600">{{ $r->percentage }}%</span>
                                </div>
                                @endif
                            </td>
                            <td>
                                @if($isIncomplete)
                                    <span class="status-pill" style="background:#f3f4f6;color:#6b7280">Not Attempted</span>
                                @elseif($hasCheating)
                                    <span class="status-pill status-closed" style="background:#fef3c7;color:#92400e">Security Violation</span>
                                @elseif($r->is_passed)
                                    <span class="status-pill status-approved">Passed</span>
                                @else
                                    <span class="status-pill status-closed">Failed</span>
                                @endif
                            </td>
                            <td>
                                @if($hasRequest)
                                    <span class="status-pill status-pending" style="font-size:0.72rem">Requested</span>
                                @elseif(!$isEligible)
                                    <span class="text-muted" style="font-size:0.8rem" title="{{ $eligibilityReason }}">—</span>
                                @else
                                    <span class="badge bg-success" style="font-size:0.7rem">
                                        <i class="bi bi-check-circle"></i> Eligible
                                    </span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-bar-chart d-block mb-2" style="font-size:2rem;opacity:0.3"></i>
                                No results match your filter.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        
        {{-- Bulk Action Footer --}}
        <div id="bulkActionFooter" class="card-footer bg-light" style="display:none">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearSelection()">
                        <i class="bi bi-x-circle me-1"></i> Clear Selection
                    </button>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bulkReattemptModal">
                        <i class="bi bi-send me-1"></i> Submit Re-Attempt Request (<span id="bulkSelectedCount">0</span>)
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

{{-- Bulk Re-attempt Request Modal --}}
<div class="modal fade" id="bulkReattemptModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;border:none;box-shadow:0 20px 60px rgba(0,0,0,0.2)">
            <div class="modal-header" style="background:linear-gradient(135deg,var(--royal-deeper,#1e1b6e),var(--royal,#3730a3));border-radius:16px 16px 0 0;border:none">
                <h5 class="modal-title text-white">
                    <i class="bi bi-send me-2"></i>Submit Re-Attempt Request
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3 p-3 rounded" style="background:var(--royal-light,#ede9fe);border:1px solid rgba(55,48,163,0.15)">
                    <div class="text-muted small mb-1">Selected Students</div>
                    <div style="font-weight:700;color:var(--royal,#3730a3)">
                        <span id="modalSelectedCount">0</span> student(s) selected
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Reason for Re-attempt <span class="text-danger">*</span></label>
                    <textarea form="bulkReattemptForm" name="reason" id="bulkReason" class="form-control" rows="4"
                              placeholder="Explain why these students need a re-attempt..."
                              required></textarea>
                    <div class="form-text">This reason will apply to all selected students.</div>
                </div>
                <div class="p-3 rounded" style="background:#fefce8;border:1px solid #fde68a;font-size:0.8rem;color:#854d0e">
                    <i class="bi bi-info-circle me-1"></i>
                    These requests will be sent to admin for approval. Previous attempt records will be kept.
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitBulkRequest()">
                    <i class="bi bi-send me-1"></i> Send to Admin
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
// Multi-select functionality
const checkboxes = document.querySelectorAll('.student-checkbox');
const selectAll = document.getElementById('selectAll');
const selectedCount = document.getElementById('selectedCount');
const selectedCountText = document.getElementById('selectedCountText');
const bulkSelectedCount = document.getElementById('bulkSelectedCount');
const modalSelectedCount = document.getElementById('modalSelectedCount');
const bulkActionFooter = document.getElementById('bulkActionFooter');

function updateSelectionCount() {
    const checked = document.querySelectorAll('.student-checkbox:checked').length;
    selectedCountText.textContent = checked;
    bulkSelectedCount.textContent = checked;
    modalSelectedCount.textContent = checked;
    
    if (checked > 0) {
        selectedCount.style.display = 'inline-flex';
        bulkActionFooter.style.display = 'block';
    } else {
        selectedCount.style.display = 'none';
        bulkActionFooter.style.display = 'none';
    }
    
    // Update select all checkbox state
    const total = checkboxes.length;
    selectAll.checked = checked === total && total > 0;
    selectAll.indeterminate = checked > 0 && checked < total;
}

// Select all functionality
selectAll?.addEventListener('change', function() {
    checkboxes.forEach(cb => {
        cb.checked = this.checked;
    });
    updateSelectionCount();
});

// Individual checkbox change
checkboxes.forEach(cb => {
    cb.addEventListener('change', updateSelectionCount);
});

function clearSelection() {
    checkboxes.forEach(cb => cb.checked = false);
    selectAll.checked = false;
    updateSelectionCount();
}

function submitBulkRequest() {
    const reason = document.getElementById('bulkReason').value.trim();
    if (!reason) {
        alert('Please provide a reason for the re-attempt request.');
        return;
    }
    
    const checked = document.querySelectorAll('.student-checkbox:checked').length;
    if (checked === 0) {
        alert('Please select at least one student.');
        return;
    }
    
    // Submit the form
    document.getElementById('bulkReattemptForm').submit();
}

// Initialize on page load
updateSelectionCount();
</script>
@endpush

@push('styles')
<style>
.eligible-row {
    background-color: #fafafa;
}
.eligible-row:hover {
    background-color: #f0f9ff !important;
}
</style>
@endpush
