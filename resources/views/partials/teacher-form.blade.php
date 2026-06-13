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
<div class="mb-3">
    <label class="form-label">Password @if(empty($teacher->id))<span class="text-danger">*</span>@else<span class="text-muted">(leave blank to keep)</span>@endif</label>
    <input type="password" name="password" class="form-control" {{ empty($teacher->id) ? 'required' : '' }} minlength="8">
</div>
@if($isAdminEdit)
<div class="form-check mb-3">
    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active"
           @checked(old('is_active', $teacher->is_active ?? true))>
    <label class="form-check-label" for="is_active">Active account</label>
</div>
@endif
