@extends('layouts.app')
@section('page-title', 'Re-attempt Requests')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Re-attempt Requests'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection
@section('content')
<div class="card"><div class="card-body">
<table class="table datatable"><thead><tr><th>Exam</th><th>Student</th><th>Teacher</th><th>Admin</th><th>Actions</th></tr></thead>
<tbody>@foreach($requests as $r)<tr>
<td>{{ $r->exam->title }}</td><td>{{ $r->student->name }}</td>
<td>{{ $r->teacher_status }}</td><td>{{ $r->admin_status }}</td>
<td>@if($r->admin_status==='pending' && $r->teacher_status==='approved')
<form action="{{ route('admin.attempt-resets.approve',$r) }}" method="POST" class="d-inline">@csrf<button class="btn btn-sm btn-success">Approve</button></form>
<form action="{{ route('admin.attempt-resets.reject',$r) }}" method="POST" class="d-inline">@csrf<button class="btn btn-sm btn-danger">Reject</button></form>
@endif</td></tr>@endforeach</tbody></table></div></div>
@endsection
