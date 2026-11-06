@extends('layouts.app')
@section('content')
<p class="muted"><a href="{{ route('casuals.pack', $person) }}">Day-one induction</a></p>
<h1>{{ $module->title }}</h1>
<p class="muted">{{ $module->phase === 'day_one' ? 'Day one' : 'Later' }} · version {{ $module->version }} · {{ $person->preferred_name }}</p>
<div class="card">
    <pre style="white-space:pre-wrap; font-family:Georgia, serif; font-size:18px; line-height:1.45;">{{ $module->body }}</pre>
</div>
@if($done)
    <p class="card">{{ $person->preferred_name }} acknowledged this page {{ $done->acknowledged_at->timezone('Australia/Sydney')->format('j M Y g:i a') }}.</p>
@elseif($canAcknowledge)
    <form method="post" action="{{ route('casuals.acknowledge', [$person, $module]) }}">
        @csrf
        <button type="submit">I, {{ $person->preferred_name }}, acknowledge this page</button>
    </form>
@endif
@endsection
