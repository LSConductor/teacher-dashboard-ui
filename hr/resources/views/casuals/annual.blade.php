@extends('layouts.app')
@section('content')
<p class="muted"><a class="link" href="{{ route('casuals.show', $person) }}">{{ $person->preferred_name }}</a></p>
<h1>Short annual page</h1>
<p class="lede">{{ $person->legal_name }}. One page for the year: what was observed, what students did, what to keep, and what to change.</p>
@if($annual)
    <p class="muted">Written {{ $annual->recorded_on?->timezone('Australia/Sydney')->format('j M Y') }}@if($annual->author) by {{ $annual->author->name }}@endif.</p>
@endif

@if($canWrite)
<form class="card" method="post" action="{{ route('casuals.annual', $person) }}">
    @csrf
    <label for="body">What was observed</label>
    <textarea id="body" name="body" required>{{ old('body', $annual?->body ?? '') }}</textarea>
    <label for="students_did">What students did</label>
    <textarea id="students_did" name="students_did" required>{{ old('students_did', $annual?->students_did ?? '') }}</textarea>
    <label for="keep_doing">What to keep</label>
    <textarea id="keep_doing" name="keep_doing" required>{{ old('keep_doing', $annual?->keep_doing ?? '') }}</textarea>
    <label for="change_next">What to change</label>
    <textarea id="change_next" name="change_next" required>{{ old('change_next', $annual?->change_next ?? '') }}</textarea>
    <p><button type="submit">Save annual page</button></p>
</form>
@elseif($annual)
<article class="card">
    <h2>What was observed</h2>
    <p>{{ $annual->body }}</p>
    <h2>What students did</h2>
    <p>{{ $annual->students_did }}</p>
    <h2>What to keep</h2>
    <p>{{ $annual->keep_doing }}</p>
    <h2>What to change</h2>
    <p>{{ $annual->change_next }}</p>
</article>
@else
    <p>Not written yet.</p>
@endif
@endsection
