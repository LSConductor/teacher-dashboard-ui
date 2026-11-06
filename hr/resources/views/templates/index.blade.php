@extends('layouts.app')
@section('content')
<h1>Contract templates</h1>
<p class="muted">Edit a draft, then publish it. A sent contract keeps the version that was published.</p>
<p><a href="{{ route('export.nesa') }}">Download the NESA check (no WWCC number, no date of birth)</a></p>
<table>
    <thead><tr><th>Name</th><th>Version</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @foreach($templates as $template)
        <tr>
            <td>{{ $template->name }}</td>
            <td>{{ $template->version }}</td>
            <td>{{ $template->status }}</td>
            <td><a href="{{ route('templates.edit', $template) }}">Open</a></td>
        </tr>
    @endforeach
    </tbody>
</table>
@endsection
