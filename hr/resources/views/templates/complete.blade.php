@extends('layouts.app')
@section('content')
<p class="muted"><a href="{{ route('templates.index') }}">Templates</a></p>
<h1>Complete a contract</h1>
<p class="muted">Pick the person and a published version. Check the merge, then send. Editing a draft does not change a version already sent. The WWCC number is not a merge field.</p>
<form method="get" class="card grid">
    <div>
        <label for="person_id">Person</label>
        <select id="person_id" name="person_id">
            @foreach($people as $row)
                <option value="{{ $row->id }}" @selected(optional($person)->id === $row->id)>{{ $row->preferred_name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="template_id">Published template</label>
        <select id="template_id" name="template_id">
            @foreach($templates as $row)
                <option value="{{ $row->id }}" @selected(optional($template)->id === $row->id)>{{ $row->name }} (v{{ $row->version }})</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="class_label">Class, for a day confirmation</label>
        <input id="class_label" name="class_label" value="{{ request('class_label') }}">
    </div>
    <div>
        <label for="booked_on">Date</label>
        <input id="booked_on" type="date" name="booked_on" value="{{ request('booked_on') }}">
    </div>
    <p><button type="submit">Check the merge</button></p>
</form>
@if($preview)
<form method="post" action="{{ route('templates.complete.send') }}" class="card">
    @csrf
    <input type="hidden" name="person_id" value="{{ $person->id }}">
    <input type="hidden" name="template_id" value="{{ $template->id }}">
    <input type="hidden" name="class_label" value="{{ request('class_label') }}">
    <input type="hidden" name="booked_on" value="{{ request('booked_on') }}">
    <h2>{{ $template->name }} for {{ $person->preferred_name }}</h2>
    <pre style="white-space:pre-wrap; font-family:system-ui,sans-serif;">{{ $preview }}</pre>
    <p><button type="submit">Send this version</button></p>
</form>
@endif
@endsection
