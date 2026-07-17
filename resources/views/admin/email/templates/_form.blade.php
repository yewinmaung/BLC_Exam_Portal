{{--
  Shared template form partial.
  Expects: $template (EmailTemplate model — new or existing)
  Used by: create.blade.php and edit.blade.php
--}}
<div class="mb-3">
    <label class="form-label fw-600" style="font-size:0.82rem;font-weight:600">Name <span class="text-danger">*</span></label>
    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
           value="{{ old('name', $template->name) }}"
           placeholder="e.g. Exam Published Notification" required maxlength="255">
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label fw-600" style="font-size:0.82rem;font-weight:600">Slug <span class="text-danger">*</span></label>
    <input type="text" name="slug" class="form-control @error('slug') is-invalid @enderror"
           value="{{ old('slug', $template->slug) }}"
           placeholder="e.g. exam_published"
           pattern="[a-z0-9_]+" title="Lowercase letters, numbers and underscores only"
           required maxlength="100"
           {{ $template->exists ? 'readonly' : '' }}>
    @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
    <div class="form-text" style="font-size:0.75rem">Lowercase letters, numbers, underscores only. Cannot be changed after creation.</div>
</div>

<div class="mb-3">
    <label class="form-label fw-600" style="font-size:0.82rem;font-weight:600">Subject <span class="text-danger">*</span></label>
    <input type="text" name="subject" class="form-control @error('subject') is-invalid @enderror"
           value="{{ old('subject', $template->subject) }}"
           placeholder="e.g. New Exam Available: @{{exam_name}}" required maxlength="255">
    @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label fw-600" style="font-size:0.82rem;font-weight:600">Body HTML <span class="text-danger">*</span></label>
    <textarea name="body_html" rows="14"
              class="form-control @error('body_html') is-invalid @enderror"
              placeholder="<p>Hello @{{student_name}},</p>" required>{{ old('body_html', $template->body_html) }}</textarea>
    @error('body_html')<div class="invalid-feedback">{{ $message }}</div>@enderror
    <div class="form-text mt-1" style="font-size:0.75rem">
        Available variables:
        <code>@{{student_name}}</code> <code>@{{teacher_name}}</code> <code>@{{name}}</code>
        <code>@{{email}}</code> <code>@{{exam_name}}</code> <code>@{{course_name}}</code>
        <code>@{{year_level}}</code> <code>@{{academic_year}}</code>
        <code>@{{app_name}}</code> <code>@{{app_url}}</code>
    </div>
</div>

<div class="mb-3">
    <label class="form-label fw-600" style="font-size:0.82rem;font-weight:600">Event / Trigger (optional)</label>
    <input type="text" name="event" class="form-control @error('event') is-invalid @enderror"
           value="{{ old('event', $template->event) }}"
           placeholder="e.g. exam_published" maxlength="100">
    @error('event')<div class="invalid-feedback">{{ $message }}</div>@enderror
    <div class="form-text" style="font-size:0.75rem">Used to look up this template by event name in code.</div>
</div>

<div class="mb-4">
    <div class="form-check">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActive"
               {{ old('is_active', $template->is_active ?? true) ? 'checked' : '' }}>
        <label class="form-check-label" for="isActive" style="font-size:0.85rem;font-weight:600">Active</label>
    </div>
    <div class="form-text" style="font-size:0.75rem">Inactive templates are not used by automatic email triggers.</div>
</div>
