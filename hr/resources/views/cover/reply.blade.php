@extends('layouts.app')
@section('content')
<article class="dayhero" style="max-width:640px;">
    <p class="kicker">Living School</p>
    <h1>{{ $ask->cover->class_label }}</h1>
    <p class="lede">{{ $ask->cover->when() }}.
        @if($ask->cover->teacher_name) With {{ $ask->cover->teacher_name }}. @endif
        @if($ask->cover->start_time && $ask->cover->finish_time) {{ $ask->cover->start_time }} to {{ $ask->cover->finish_time }}. @endif
        @if($ask->cover->neighbourhood) {{ $ask->cover->neighbourhood }}. @endif
    </p>
    @if($ask->status === 'accepted')
        <p>You have this day.</p>
    @elseif($ask->status === 'declined')
        <p>You declined this day.</p>
    @elseif($ask->cover->status === 'filled')
        <p>This cover is filled.</p>
    @elseif(in_array($ask->status, ['passed', 'withdrawn'], true) || $ask->cover->status === 'closed')
        <p>This ask has moved on.</p>
    @else
        <p>{{ $ask->person->preferred_name }}, can you teach this class?</p>
        @if($ask->cover->plan_url)
            <p><a class="link" href="{{ $ask->cover->plan_url }}" target="_blank" rel="noopener">Open the plan</a></p>
        @endif
        <div class="actions">
            <form method="post" action="{{ route('cover.accept', ['token' => $token]) }}">
                @csrf
                <button type="submit">Accept</button>
            </form>
            <form method="post" action="{{ route('cover.decline', ['token' => $token]) }}">
                @csrf
                <button class="ghost" type="submit">Decline</button>
            </form>
        </div>
    @endif
</article>
@endsection
