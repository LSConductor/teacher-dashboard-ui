@extends('layouts.app')
@section('content')
<p class="muted">
    @if(auth()->user()->role === 'casual')
        <a class="link" href="{{ route('start') }}">Start</a>
    @else
        <a class="link" href="{{ route('casuals.show', $person) }}">{{ $person->preferred_name }}</a>
    @endif
</p>
<h1>Day-one induction</h1>
<p class="lede">{{ $person->preferred_name }}. The light stays red until every day-one page is acknowledged. Later pages can stay open.</p>
@php $laterAnchor = false; @endphp
@foreach($modules as $module)
    @php
        $done = $person->acknowledgements->first(fn ($row) => $row->module_id === $module->id && $row->module_version >= $module->version);
        $markLater = $module->phase === 'later' && ! $laterAnchor;
        if ($markLater) { $laterAnchor = true; }
    @endphp
    <div class="card {{ $done ? 'edge-green' : ($module->phase === 'day_one' ? 'edge-red' : 'edge-amber') }}" @if($markLater) id="later" @endif>
        <h2>{{ $module->phase === 'day_one' ? 'Day one' : 'Later' }}. {{ $module->title }}</h2>
        @if($done)
            <p class="muted">Acknowledged {{ $done->acknowledged_at->timezone('Australia/Sydney')->format('j M Y g:i a') }}</p>
            <a class="button ghost" href="{{ route('casuals.induction', [$person, $module]) }}">Read again</a>
            @if($module->video_url)
                <a class="button ghost" href="{{ $module->video_url }}" target="_blank" rel="noopener">Watch the video</a>
            @endif
        @else
            <p class="muted">Not acknowledged. This page is part of the pack.</p>
            <a class="button" href="{{ route('casuals.induction', [$person, $module]) }}">Read and acknowledge</a>
        @endif
    </div>
@endforeach
@endsection
