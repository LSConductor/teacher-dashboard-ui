@extends('layouts.app')
@section('content')
<h1>{{ $module->title }}</h1>
<p class="muted">
    <a class="link" href="{{ route('casuals.pack', $person) }}">Day-one pack</a>
    @if(auth()->user()->role === 'casual')
        · <a class="link" href="{{ route('start') }}">Start</a>
    @endif
    · {{ $module->phase === 'day_one' ? 'Day one' : 'Later' }} · version {{ $module->version }} · {{ $person->preferred_name }}
</p>
<article class="card">
    @foreach(preg_split("/\n\s*\n/", trim($module->body)) as $paragraph)
        <p style="font-size:18px; line-height:1.5;">{{ $paragraph }}</p>
    @endforeach
    @if($module->video_url)
        <p><a class="button" href="{{ $module->video_url }}" target="_blank" rel="noopener">Watch the video</a></p>
    @elseif(in_array(auth()->user()->role, ['hr','conductor']))
        <p class="muted">No video on this quest yet. <a class="link" href="{{ route('setup.induction.edit', $module) }}">Add the link in Setup</a>. The writing on this page is the record until a video is added.</p>
    @endif
</article>
@if($module->quiz)
    <h2>Check</h2>
    <p class="lede">Answer from the page. A casual’s acknowledgement counts when the check matches the writing.</p>
@endif
@if($done)
    <p class="card">{{ $person->preferred_name }} acknowledged this page {{ $done->acknowledged_at->timezone('Australia/Sydney')->format('j M Y g:i a') }}.</p>
@elseif($canAcknowledge)
    <form method="post" action="{{ route('casuals.acknowledge', [$person, $module]) }}" class="card">
        @csrf
        @foreach($module->quiz ?? [] as $index => $question)
            <fieldset style="border:0; padding:0; margin:0 0 16px;">
                <legend style="font-weight:700; margin-bottom:8px;">{{ $question['prompt'] }}</legend>
                @foreach($question['choices'] as $choice => $label)
                    <label style="font-weight:500;">
                        <input style="width:auto" type="radio" name="answers[{{ $index }}]" value="{{ $choice }}" @checked((string) old('answers.'.$index) === (string) $choice)>
                        {{ $label }}
                    </label>
                @endforeach
            </fieldset>
        @endforeach
        <button type="submit">I, {{ $person->preferred_name }}, acknowledge this page</button>
    </form>
@endif
@endsection
