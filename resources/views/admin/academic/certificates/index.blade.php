@extends('layouts.app')
@section('title', 'Certificates')
@section('page-title', 'Certificates')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Certificates'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')
<div class="row g-3">

    {{-- Issue new certificate form --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="bi bi-award me-2"></i>Issue Certificate</div>
            <div class="card-body">

                {{-- Eligibility notice --}}
                <div class="alert alert-info py-2 px-3 mb-3" style="font-size:0.78rem">
                    <i class="bi bi-info-circle me-1"></i>
                    <strong>Completion</strong> certificate: student must have passed <strong>Year 4 or Year 5</strong>.<br>
                    <strong>Other types</strong>: student must have passed <strong>Final Year (Year 5)</strong>.
                    @if($students->isEmpty())
                        <div class="mt-1 text-warning fw-semibold">No eligible students found.</div>
                    @endif
                </div>

                @if($errors->any())
                <div class="alert alert-danger py-2 px-3 mb-3" style="font-size:0.82rem">
                    @foreach($errors->all() as $e)
                    <div><i class="bi bi-exclamation-triangle me-1"></i>{{ $e }}</div>
                    @endforeach
                </div>
                @endif

                @if(session('success'))
                <div class="alert alert-success py-2 px-3 mb-3" style="font-size:0.82rem">
                    <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
                </div>
                @endif

                <form method="POST" id="issueForm" action="">
                    @csrf

                    {{-- Certificate Type (moved above Student so filtering works on load) --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Certificate Type <span class="text-danger">*</span></label>
                        <select name="type" id="certType" class="form-select" required>
                            <option value="completion" {{ old('type','completion') === 'completion' ? 'selected':'' }}>Completion</option>
                            <option value="transcript" {{ old('type') === 'transcript' ? 'selected':'' }}>Transcript</option>
                            <option value="promotion"  {{ old('type') === 'promotion'  ? 'selected':'' }}>Promotion</option>
                            <option value="achievement"{{ old('type') === 'achievement'? 'selected':'' }}>Achievement</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Student <span class="text-danger">*</span>
                            <span id="studentEligibilityHint" class="text-muted fw-normal" style="font-size:0.72rem"></span>
                        </label>
                        <select name="student_select" id="studentSelect" class="form-select" required>
                            <option value="">— Select Student —</option>
                            @foreach($students as $s)
                            <option value="{{ $s->id }}"
                                    data-url="{{ route('admin.academic.certificates.issue', $s) }}"
                                    data-completion="{{ $s->eligible_completion ? '1' : '0' }}"
                                    data-other="{{ $s->eligible_other ? '1' : '0' }}"
                                    {{ old('student_select') == $s->id ? 'selected' : '' }}>
                                {{ $s->name }}
                            </option>
                            @endforeach
                        </select>
                        <div id="noEligibleStudentsMsg" class="text-danger mt-1" style="font-size:0.78rem;display:none">
                            <i class="bi bi-exclamation-triangle me-1"></i>No eligible students for this certificate type.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Academic Year <span class="text-danger">*</span></label>
                        <select name="academic_year_id" class="form-select" required>
                            <option value="">— Select —</option>
                            @foreach($academicYears as $ay)
                            <option value="{{ $ay->id }}">{{ $ay->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Year Level <span class="text-danger">*</span></label>
                        <select name="year_level_id" class="form-select" required>
                            <option value="">— Select —</option>
                            @foreach(\App\Models\YearLevel::orderBy('level')->get() as $yl)
                            <option value="{{ $yl->id }}">{{ $yl->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Issued By <span class="text-danger">*</span></label>
                        <input type="text" name="issued_by" class="form-control" required
                               value="{{ auth()->user()->name }}" placeholder="Authorizing officer name">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-award me-1"></i> Issue Certificate
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Certificates list --}}
    <div class="col-lg-8">
        {{-- Filter bar --}}
        <div class="card mb-3">
            <div class="card-body py-2">
                <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
                    <div style="flex:1;min-width:160px">
                        <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600">Student</label>
                        <select name="student_id" class="form-select form-select-sm">
                            <option value="">All</option>
                            @foreach($allStudents as $s)
                            <option value="{{ $s->id }}" {{ request('student_id') == $s->id ? 'selected':'' }}>{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="flex:1;min-width:140px">
                        <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600">Academic Year</label>
                        <select name="academic_year_id" class="form-select form-select-sm">
                            <option value="">All</option>
                            @foreach($academicYears as $ay)
                            <option value="{{ $ay->id }}" {{ request('academic_year_id') == $ay->id ? 'selected':'' }}>{{ $ay->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="min-width:120px">
                        <label class="form-label mb-1" style="font-size:0.75rem;font-weight:600">Type</label>
                        <select name="type" class="form-select form-select-sm">
                            <option value="">All Types</option>
                            <option value="completion"  {{ request('type')=='completion'  ? 'selected':'' }}>Completion</option>
                            <option value="transcript"  {{ request('type')=='transcript'  ? 'selected':'' }}>Transcript</option>
                            <option value="promotion"   {{ request('type')=='promotion'   ? 'selected':'' }}>Promotion</option>
                            <option value="achievement" {{ request('type')=='achievement' ? 'selected':'' }}>Achievement</option>
                        </select>
                    </div>
                    <div class="d-flex gap-1">
                        <button class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
                        <a href="{{ route('admin.academic.certificates.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x"></i></a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-award me-2"></i>Issued Certificates</span>
                <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">{{ $certificates->total() }}</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table datatable mb-0" style="font-size:0.82rem">
                        <thead>
                            <tr><th>Serial</th><th>Student</th><th>Year</th><th>Type</th><th>Issued By</th><th>Date</th><th></th></tr>
                        </thead>
                        <tbody>
                            @forelse($certificates as $c)
                            <tr>
                                <td><code style="font-size:0.72rem">{{ $c->serial_number }}</code></td>
                                <td>
                                    <div style="font-weight:600">{{ $c->student->name ?? '—' }}</div>
                                    <div style="font-size:0.7rem;color:#9ca3af">{{ $c->student->email ?? '' }}</div>
                                </td>
                                <td>
                                    <div>{{ $c->academicYear->name ?? '—' }}</div>
                                    <div style="font-size:0.7rem;color:#9ca3af">{{ $c->yearLevel->name ?? '' }}</div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">{{ ucfirst($c->type) }}</span>
                                </td>
                                <td style="font-size:0.78rem">{{ $c->issued_by }}</td>
                                <td style="font-size:0.75rem;color:#6b7280">{{ \Carbon\Carbon::parse($c->issued_at)->format('M d, Y') }}</td>
                                <td>
                                    <a href="{{ route('admin.academic.certificates.pdf', $c) }}"
                                       class="btn btn-xs btn-outline-danger" title="Download PDF" target="_blank">
                                        <i class="bi bi-file-earmark-pdf"></i>
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-award d-block mb-2" style="font-size:2rem;opacity:0.3"></i>
                                    No certificates issued yet.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($certificates->hasPages())
                <div class="p-3 border-top">{{ $certificates->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const certType     = document.getElementById('certType');
    const studentSel   = document.getElementById('studentSelect');
    const issueForm    = document.getElementById('issueForm');
    const noStudentMsg = document.getElementById('noEligibleStudentsMsg');
    const hintSpan     = document.getElementById('studentEligibilityHint');

    // Store all original options (except placeholder) to restore on type change
    const allOptions = Array.from(studentSel.options).filter(o => o.value !== '');

    function filterStudents() {
        const type        = certType.value;
        const isCompletion = type === 'completion';
        const dataKey     = isCompletion ? 'completion' : 'other';

        hintSpan.textContent = isCompletion
            ? '(Year 4 or Year 5 passed)'
            : '(Final Year / Year 5 passed)';

        // Remove all non-placeholder options
        while (studentSel.options.length > 1) {
            studentSel.remove(1);
        }

        let count = 0;
        allOptions.forEach(opt => {
            if (opt.dataset[dataKey] === '1') {
                studentSel.appendChild(opt.cloneNode(true));
                count++;
            }
        });

        const hasNone = count === 0;
        studentSel.disabled = hasNone;
        noStudentMsg.style.display = hasNone ? 'block' : 'none';

        // Reset selection and clear form action
        studentSel.value = '';
        issueForm.action = '';
    }

    // Wire student selection → form action
    studentSel.addEventListener('change', function () {
        const url = this.options[this.selectedIndex]?.dataset?.url || '';
        issueForm.action = url;
    });

    // Wire type change → filter students
    certType.addEventListener('change', filterStudents);

    // Run on page load to apply correct filter immediately
    filterStudents();
})();
</script>
@endpush
