@extends('layouts.app')
@section('page-title', 'Cheating Logs')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Cheating Logs'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection
@section('content')
<div class="card"><div class="card-body">
<table class="table datatable"><thead><tr><th>Student</th><th>Exam</th><th>Violation</th><th>Warning #</th><th>Time</th></tr></thead>
<tbody>@foreach($logs as $l)<tr>
<td>{{ $l->student->name }}</td><td>{{ $l->attempt->exam->title ?? '-' }}</td>
<td>{{ $l->violation_type }}</td><td>{{ $l->warning_number }}</td><td>{{ $l->created_at }}</td>
</tr>@endforeach</tbody></table></div></div>
@endsection
