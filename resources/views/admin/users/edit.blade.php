@extends('layouts.app')
@section('page-title', 'Edit User')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Users', 'url' => route('admin.users.index')],
        ['label' => 'Edit'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection
@section('content')
<div class="card col-md-8"><div class="card-body">
<form method="POST" action="{{ route('admin.users.update',$user) }}" id="userForm">@csrf @method('PUT')
<div class="mb-3"><label class="form-label">Name</label><input name="name" class="form-control" value="{{ old('name', $user->name) }}" required></div>
<div class="mb-3"><label class="form-label">Email</label><input name="email" type="email" class="form-control" value="{{ old('email', $user->email) }}" required></div>
<div class="mb-3"><label class="form-label">Password (leave blank to keep)</label><input name="password" type="password" class="form-control"></div>
<div class="mb-3">
    <label class="form-label">Role</label>
    <select name="role_id" id="roleSelect" class="form-select" required>
        @foreach($roles as $r)
        <option value="{{ $r->id }}" @selected(old('role_id', $user->role_id) == $r->id)>{{ $r->name }}</option>
        @endforeach
    </select>
</div>

<div class="mb-3" id="yearField" style="display:none">
    <label class="form-label">Academic Year <span class="text-danger">*</span></label>
    <div class="d-flex gap-2 flex-wrap">
        @foreach($years as $val => $label)
        <label class="year-pill {{ old('academic_year', $user->academic_year) == $val ? 'selected' : '' }}">
            <input type="radio" name="academic_year" value="{{ $val }}"
                   {{ old('academic_year', $user->academic_year) == $val ? 'checked' : '' }}>
            {{ $label }}
        </label>
        @endforeach
    </div>
</div>

<div class="form-check mb-3"><input type="checkbox" name="is_active" value="1" class="form-check-input" @checked(old('is_active', $user->is_active))><label class="form-check-label">Active</label></div>
<button class="btn btn-primary">Update</button>
</form></div></div>
@endsection

@push('styles')
<style>
.year-pill{display:inline-flex;padding:.4rem 1rem;border-radius:50px;border:1.5px solid #d0d8e8;background:#fff;font-size:.82rem;font-weight:600;color:#6b7280;cursor:pointer}
.year-pill input{display:none}
.year-pill.selected,.year-pill:has(input:checked){background:var(--blc-navy-2,#0f3a7a);border-color:var(--blc-navy-2,#0f3a7a);color:#fff}
</style>
@endpush

@push('scripts')
<script>
(function(){
    const studentRoleId = {{ (int) $studentRoleId }};
    const roleSelect = document.getElementById('roleSelect');
    const yearField = document.getElementById('yearField');
    function toggleYear(){
        const show = parseInt(roleSelect.value, 10) === studentRoleId;
        yearField.style.display = show ? 'block' : 'none';
        yearField.querySelectorAll('input[name=academic_year]').forEach(i => i.required = show);
    }
    roleSelect?.addEventListener('change', toggleYear);
    document.querySelectorAll('#yearField .year-pill').forEach(p => p.addEventListener('click', function(){
        document.querySelectorAll('#yearField .year-pill').forEach(x => x.classList.remove('selected'));
        this.classList.add('selected');
    }));
    toggleYear();
})();
</script>
@endpush
