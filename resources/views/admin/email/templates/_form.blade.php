{{-- Shared form fields for create/edit email templates --}}

<div class="row g-3">
    <div class="col-sm-7">
        <label class="form-label">Template Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $template->name ?? '') }}" placeholder="e.g. Exam Published" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-sm-5">
        <label class="form-label">Slug <span class="text-danger">*</span></label>
        <input type="text" name="slug" id="slugField" class="form-control @error('slug') is-invalid @enderror"
               value="{{ old('slug', $template->slug ?? '') }}" placeholder="exam_published" required
               pattern="[a-z0-9_]+" title="Lowercase letters, numbers and underscores only">
        <div class="form-text">Lowercase, underscores only. Used in code.</div>
        @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-sm-8">
        <label class="form-label">Subject <span class="text-danger">*</span></label>
        <input type="text" name="subject" class="form-control @error('subject') is-invalid @enderror"
               value="{{ old('subject', $template->subject ?? '') }}" placeholder="New Exam: {{exam_name}}" required>
        @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-sm-4">
        <label class="form-label">Event Trigger</label>
        <input type="text" name="event" class="form-control"
               value="{{ old('event', $template->event ?? '') }}" placeholder="exam_published">
        <div class="form-text">Auto-trigger event slug (optional).</div>
    </div>
    <div class="col-12">
        <label class="form-label">HTML Body <span class="text-danger">*</span></label>
        <div class="mb-1 d-flex gap-2 flex-wrap" style="font-size:0.76rem">
            <span class="text-muted">Available variables:</span>
            @foreach(['student_name','student_id','teacher_name','course_name','exam_name','result','gpa'] as $var)
            <code onclick="insertVar('{{"{{$var}}}"}}')" style="cursor:pointer;background:#ede9fe;color:#3730a3;padding:0.1rem 0.4rem;border-radius:5px" title="Click to insert">{{"{{$var}}"}}</code>
            @endforeach
        </div>
        <textarea name="body_html" id="bodyHtml" class="form-control @error('body_html') is-invalid @enderror"
                  rows="14" style="font-family:monospace;font-size:0.82rem" required
                  placeholder="<h2>Hello {{student_name}},</h2>...">{{ old('body_html', $template->body_html ?? '') }}</textarea>
        @error('body_html')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <label class="form-label">Plain-text Fallback <span class="text-muted fw-normal">(optional)</span></label>
        <textarea name="body_text" class="form-control" rows="4"
                  placeholder="Plain text version for email clients that don't support HTML">{{ old('body_text', $template->body_text ?? '') }}</textarea>
    </div>
    <div class="col-12">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive"
                   {{ old('is_active', $template->is_active ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="isActive">Template Active</label>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Auto-generate slug from name
const nameInput = document.querySelector('[name="name"]');
const slugField = document.getElementById('slugField');
const isEdit    = "{{ isset($template) && $template->exists ? '1' : '0' }}" === '1';

if (!isEdit) {
    nameInput?.addEventListener('input', function () {
        slugField.value = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
    });
}

function insertVar(v) {
    const ta  = document.getElementById('bodyHtml');
    const pos = ta.selectionStart;
    ta.value  = ta.value.slice(0, pos) + v + ta.value.slice(ta.selectionEnd);
    ta.focus();
    ta.selectionStart = ta.selectionEnd = pos + v.length;
}
</script>
@endpush
