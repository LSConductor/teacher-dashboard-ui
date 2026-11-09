@extends('layouts.app')
@section('content')
<h1>Today</h1>
<p class="lede">The time is the school clock, Australia/Sydney. The office code is the proof someone was at the school. A phone location is not the record.</p>
@if($waiting->isNotEmpty())
    <article class="card edge-amber">
        <h2>Cover still waiting</h2>
        <p class="lede">The first yes books it.</p>
        @foreach($waiting as $cover)
            <p><strong>{{ $cover->class_label }}</strong> · {{ $cover->when() }}@if($cover->teacher_name) · with {{ $cover->teacher_name }}@endif @if($cover->neighbourhood) · {{ $cover->neighbourhood }}@endif</p>
            @foreach($cover->asks as $ask)
                <p class="meta">{{ $ask->person->preferred_name }} ·
                    @switch($ask->status)
                        @case('sent') Asked, waiting @break
                        @case('queued') Next @break
                        @case('accepted') Yes, booked @break
                        @case('declined') Said no @break
                        @case('passed') Moved on @break
                        @default Closed
                    @endswitch
                </p>
            @endforeach
        @endforeach
        <p><a class="button ghost" href="{{ route('cover') }}">Open cover</a></p>
    </article>
@endif
@if(in_array(\App\Hr\Roles::kind(auth()->user()), ['manage', 'front_desk', 'neighbourhood'], true))
    <p><a class="button" href="{{ route('cover') }}">Find cover</a></p>
@endif
@unless($holdsCode)
    <p>Ask the office to read today's code, then type it here. It is not your password.</p>
@endunless
@if($holdsCode)
    <article class="card">
        <h2>Office code</h2>
        <p class="muted">Show this at the office. It changes at midnight.</p>
        <p class="day-code">{{ $code }}</p>
    </article>
@endif
@forelse($rows as $row)
    <article class="person">
        <div><span class="status {{ $row['roll']?->signed_off_at || $row['roll']?->closed_at ? 'green' : ($row['roll']?->signed_on_at ? 'amber' : '') }}">{{ $row['label'] }}</span></div>
        <div>
            <h2><a href="{{ route('casuals.show', $row['person']) }}">{{ $row['person']->preferred_name }}</a></h2>
            <p class="meta">{{ $row['classes'] }}@if($row['person']->neighbourhood) · {{ $row['person']->neighbourhood }}@endif</p>
            @if($row['roll']?->closed_at)
                <p class="meta">Closed by {{ $row['closed_by'] ?? 'the office' }} · {{ $row['roll']->close_note }}</p>
            @elseif($row['signed_on_by'])
                <p class="meta">Arrival recorded by {{ $row['signed_on_by'] }}</p>
            @endif
            @if($incidentDesk)
                <p><a class="link" href="{{ $incidentDesk }}" target="_blank" rel="noopener">Open the incident desk</a> <span class="muted">for {{ $row['classes'] }}. Do not write a student's story into this file.</span></p>
            @endif
            @if((int) $ownPerson === (int) $row['person']->id && ! $row['roll']?->signed_off_at && ! $row['roll']?->closed_at)
                <form method="post" action="{{ route($row['roll']?->signed_on_at ? 'today.off' : 'today.on', $row['person']) }}">
                    @csrf
                    <label for="code_{{ $row['person']->id }}">Office code</label>
                    <input id="code_{{ $row['person']->id }}" name="code" autocomplete="off" required>
                    <p><button type="submit">{{ $row['roll']?->signed_on_at ? 'Sign off' : 'Sign on' }}</button></p>
                </form>
            @endif
            @if($canRecord && ! $row['roll']?->signed_off_at && ! $row['roll']?->closed_at)
                <form method="post" action="{{ route($row['roll']?->signed_on_at ? 'today.off' : 'today.on', $row['person']) }}">
                    @csrf
                    <input type="hidden" name="at_desk" value="1">
                    <p><button class="ghost" type="submit">{{ $row['roll']?->signed_on_at ? 'Record departure' : 'Record arrival' }}</button></p>
                </form>
            @endif
            @if($canRecord && $row['roll']?->signed_on_at && ! $row['roll']?->signed_off_at && ! $row['roll']?->closed_at)
                <form method="post" action="{{ route('today.close', $row['person']) }}">
                    @csrf
                    <label for="close_{{ $row['person']->id }}">Close this day</label>
                    <input id="close_{{ $row['person']->id }}" name="close_note" placeholder="Left at the end of the day" required>
                    <p><button class="ghost" type="submit">Close without a sign-off</button></p>
                </form>
            @endif
        </div>
    </article>
@empty
    <p>Nobody is booked today.</p>
@endforelse
@endsection
