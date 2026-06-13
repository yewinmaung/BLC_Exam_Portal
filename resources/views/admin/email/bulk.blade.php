@extends('layouts.app')
@section('title', 'Bulk Email')
@section('page-title', 'Bulk Email')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Email', 'url' => route('admin.email.index')],
        ['label' => 'Bulk Email'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')
<div class="row justify-content-center">
<div class="col-lg-8">
<div class="card">
    <div class="card-header"><i class="bi bi-send-check me-2"></i>Send Bulk Email</div>
    <div class="card-body">

        <div class="alert alert-warning d-flex gap-2 mb-4" style="font-size:0.82rem">
            <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
            <div>Bulk emails are queued and delivered asynchronously. Ensure your queue worker is running:
                <code>php artisan queue:work --queue=emails</code>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.email.bulk.send') }}" id="bulkForm">
            @csrf

            <div class="mb-3">
                <label class="form-label fw-semibold">Recipients <span class="text-danger">*</span></label>
                <select name="recipients" class="form-select" required>
                    <option value="">— Select Recipient Group —</option>
                    @foreach($groups as $key => $label)
                    <option value="{{ $key }}" {{ old('recipients') == $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Load from Template <span class="text-muted fw-normal">(optional)</span></label>
                <select id="templateSelect" class="form-select">
                    <option value="">— Compose manually —</option>
                    @foreach($templates as $t)
                    <option value="{{ $t->slug }}" data-subject="{{ $t->subject }}" data-body="{{ $t->body_html }}">
                        {{ $t->name }}
                    </option>
                    @endforeach
                </select>
                <input type="hidden" name="template_slug" id="templateSlug">
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
                <input type="text" name="subject" id="bulkSubject" class="form-control @error('subject') is-invalid @enderror"
                       value="{{ old('subject') }}" required placeholder="Email subject...">
                @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold">Message Body <span class="text-danger">*</span></label>
                <div class="mb-1 d-flex gap-2 flex-wrap" style="font-size:0.75rem">
                    <span class="text-muted">Variables:</span>
                    @foreach(['student_name','teacher_name','course_name','exam_name','result','gpa'] as $v)
                    <code onclick="insertBulkVar('{{"{{$v}}}"}}')" style="cursor:pointer;background:#ede9fe;color:#3730a3;padding:0.1rem 0.4rem;border-radius:5px" title="Click to insert">{{"{{$v}}"}}</code>
                    @endforeach
                </div>
                <textarea name="body_html" id="bulkBody" class="form-control @error('body_html') is-invalid @enderror"
                          rows="12" style="font-family:monospace;font-size:0.82rem"
                          required placeholder="<p>Dear {{student_name}},</p>...">{{ old('body_html') }}</textarea>
                @error('body_html')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <button type="submit" class="btn btn-primary w-100" onclick="return confirm('Send bulk email to selected recipients?')">
                <i class="bi bi-send-check me-1"></i> Send Bulk Email
            </button>
        </form>
    </div>
</div>
</div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('templateSelect').addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    if (opt.value) {
        document.getElementById('templateSlug').value   = opt.value;
        document.getElementById('bulkSubject').value    = opt.dataset.subject || '';
        document.getElementById('bulkBody').value       = opt.dataset.body || '';
    } else {
        document.getElementById('templateSlug').value   = '';
    }
});

function insertBulkVar(v) {
    const ta  = document.getElementById('bulkBody');
    const pos = ta.selectionStart;
    ta.value  = ta.value.slice(0, pos) + v + ta.value.slice(ta.selectionEnd);
    ta.focus();
    ta.selectionStart = ta.selectionEnd = pos + v.length;
}
</script>
@endpush
