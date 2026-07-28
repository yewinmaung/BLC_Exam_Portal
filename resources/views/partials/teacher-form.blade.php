@php($isAdminEdit = $isAdminEdit ?? false)
<div class="mb-3">
    <label class="form-label">Full Name <span class="text-danger">*</span></label>
    <input type="text" name="name" class="form-control" value="{{ old('name', $teacher->name) }}" required>
</div>
<div class="mb-3">
    <label class="form-label">Email <span class="text-danger">*</span></label>
    <input type="email" name="email" class="form-control" value="{{ old('email', $teacher->email) }}" required>
</div>
<div class="mb-3">
    <label class="form-label">Phone</label>
    <input type="text" name="phone" class="form-control" value="{{ old('phone', $teacher->phone) }}">
</div>
@if(!empty($teacher->id))
{{-- Edit mode: allow optional password reset --}}
<div class="mb-3">
    <label class="form-label">Password <span class="text-muted">(leave blank to keep current)</span></label>
    <input type="password" name="password" class="form-control" minlength="8" placeholder="Leave blank to keep current password">
</div>
@else
{{-- Create mode: password is auto-generated and emailed --}}
<div class="mb-3">
    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:10px 14px;font-size:0.83rem;color:#1e40af">
        <i class="bi bi-info-circle-fill me-2"></i>
        <strong>Password is auto-generated.</strong>
        A secure temporary password will be emailed to the teacher. They will be required to change it on first login.
    </div>
</div>
@endif
@if($isAdminEdit)
<div class="form-check mb-3">
    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active"
           @checked(old('is_active', $teacher->is_active ?? true))>
    <label class="form-check-label" for="is_active">Active account</label>
</div>
@endif
