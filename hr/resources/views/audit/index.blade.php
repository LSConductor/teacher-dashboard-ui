@extends('layouts.app')
@section('content')
<h1>Audit</h1>
<table>
    <thead><tr><th>When</th><th>Who</th><th>Action</th></tr></thead>
    <tbody>
    @foreach($logs as $log)
        <tr>
            <td>{{ $log->created_at->timezone('Australia/Sydney')->format('j M Y g:i a') }}</td>
            <td>{{ $log->actor->name ?? 'System' }}</td>
            <td>{{ $log->action }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endsection
