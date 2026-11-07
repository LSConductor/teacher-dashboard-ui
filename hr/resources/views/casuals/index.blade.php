@extends('layouts.app')
@section('content')
@php
    $labels = [
        'green' => 'Clear to teach',
        'amber' => 'Needs a note',
        'red' => 'Do not book',
    ];
@endphp
<div style="display:flex; justify-content:space-between; gap:16px; align-items:end; flex-wrap:wrap;">
    <div>
        <h1>Casual list</h1>
        <p class="lede">Office (02) 5632 1218. School day 9:00–3:15. Green can be booked. Amber needs a Conductor note on that day. Red is not booked, and nobody overrides a WWCC or accreditation problem.</p>
        @if($sensitive)
            <p class="meta"><a class="link" href="{{ route('help') }}">How to use this desk</a></p>
        @endif
    </div>
    @if($sensitive)
        <a class="button ghost" href="{{ route('apply') }}">Application form</a>
        <a class="button" href="{{ route('casuals.create') }}">Add a casual</a>
    @endif
</div>
<form method="get" class="toolbar">
    <div>
        <label for="q">Name</label>
        <input id="q" name="q" value="{{ request('q') }}">
    </div>
    @if($lockedNeighbourhood)
    <div>
        <label for="neighbourhood">Learning neighbourhood</label>
        <input id="neighbourhood" value="{{ $lockedNeighbourhood }}" readonly>
    </div>
    @elseif($neighbourhoods)
    <div>
        <label for="neighbourhood">Learning neighbourhood</label>
        <select id="neighbourhood" name="neighbourhood">
            <option value="">Every neighbourhood</option>
            @foreach($neighbourhoods as $name)
                <option value="{{ $name }}" @selected(request('neighbourhood')===$name)>{{ $name }}</option>
            @endforeach
        </select>
    </div>
    @endif
    <div>
        <label for="light">Clear to teach</label>
        <select id="light" name="light">
            <option value="">Any light</option>
            <option value="green" @selected(request('light')==='green')>Clear to teach</option>
            <option value="amber" @selected(request('light')==='amber')>Needs a note</option>
            <option value="red" @selected(request('light')==='red')>Do not book</option>
        </select>
    </div>
    @if($sensitive)
    <div>
        <label for="focus">File</label>
        <select id="focus" name="focus">
            <option value="">Everyone</option>
            <option value="induction" @selected(request('focus')==='induction')>Day-one unfinished</option>
            <option value="alignment" @selected(request('focus')==='alignment')>Waiting on the Conductor</option>
            <option value="provisional" @selected(request('focus')==='provisional')>Conditional or Provisional</option>
            <option value="expiring" @selected(request('focus')==='expiring')>Expiry watch</option>
            <option value="candidates" @selected(request('focus')==='candidates')>New candidates</option>
        </select>
    </div>
    @endif
    <button type="submit">Show</button>
</form>
@if($lockedNeighbourhood)
    <p class="muted">Showing {{ $lockedNeighbourhood }} only.</p>
@endif

@forelse($people as $person)
    @if($person->status === 'applicant')
    <article class="person candidate">
        <div>
            <span class="status candidate">New candidate</span>
        </div>
        <div>
            <h2><a href="{{ route('casuals.show', $person) }}">{{ $person->preferred_name }}</a></h2>
            <p class="meta">{{ $person->legal_name }} · {{ $person->neighbourhood }} · {{ implode(', ', $person->stages ?? []) ?: 'Stages not set' }}</p>
            <p class="meta">{{ $person->email }}@if($person->mobile) · {{ $person->mobile }}@endif</p>
            @if($sensitive)
                @if($person->right_to_work)
                    <p>{{ $person->right_to_work }}</p>
                @endif
                @if($person->heard_about)
                    <p class="meta">Heard about the school from {{ $person->heard_about }}.</p>
                @endif
            @endif
            <p class="meta">Application received. Not bookable yet. Open the file and send the invite when you are ready.</p>
            <div class="actions">
                @if($person->mobile && \App\Hr\Roles::kind(auth()->user()) !== 'neighbourhood')
                    <a class="button" href="tel:{{ preg_replace('/\s+/', '', $person->mobile) }}">Call {{ $person->preferred_name }}</a>
                @endif
                <a class="button ghost" href="mailto:{{ $person->email }}">Email</a>
                <a class="button" href="{{ route('casuals.show', $person) }}">Open the file</a>
            </div>
        </div>
    </article>
    @else
    <article class="person {{ $person->gate['light'] }}">
        <div>
            <span class="status {{ $person->gate['light'] }}">{{ $labels[$person->gate['light']] }}</span>
        </div>
        <div>
            <h2><a href="{{ route('casuals.show', $person) }}">{{ $person->preferred_name }}</a></h2>
            <p class="meta">{{ $person->legal_name }} · {{ $person->neighbourhood }} · {{ implode(', ', $person->stages ?? []) ?: 'Stages not set' }}</p>
            <p class="meta">
                Mentor {{ $person->mentor->name ?? 'not named' }}
                @if($person->director_name) · Director {{ $person->director_name }} @endif
                @if($person->today_booking) · Today {{ $person->today_booking->class_label }} · {{ $person->roll_label }} @else · No class today @endif
            </p>
            @if($sensitive)
                <p class="meta">
                    {{ ucfirst($person->accreditation->level ?? 'accreditation unknown') }}
                    · alignment {{ $person->accreditation->alignment ?? 'pending' }}
                    · WWCC @if($person->wwcc?->expires_on){{ $person->wwcc->expires_on->toDateString() }}@if($person->wwcc_watch !== 'in date') · {{ $person->wwcc_watch }}@endif @else missing @endif
                    · maintenance @if($person->accreditation?->maintenance_end){{ $person->accreditation->maintenance_end->toDateString() }}@if($person->maintenance_watch !== 'in date') · {{ $person->maintenance_watch }}@endif @else missing @endif
                    · last day {{ optional($person->last_day)->toDateString() ?? 'none' }}
                </p>
            @endif
            @if($person->gate['reasons'])
                <p class="meta">{{ $person->gate['reasons'][0]['text'] }}</p>
            @endif
            <div class="actions">
                @if($person->mobile && (\App\Hr\Roles::kind(auth()->user()) !== 'neighbourhood' || $person->gate['light'] === 'green'))
                    <a class="button" href="tel:{{ preg_replace('/\s+/', '', $person->mobile) }}">Call {{ $person->preferred_name }}</a>
                @endif
                <a class="button ghost" href="mailto:{{ $person->email }}">Email</a>
                @if($person->today_booking?->plan_url)
                    <a class="button ghost" href="{{ $person->today_booking->plan_url }}" target="_blank" rel="noopener">Open the plan</a>
                @endif
                <a class="button" href="{{ route('casuals.pack', $person) }}" style="background: {{ $person->day_one_done >= $person->day_one_total && $person->day_one_total > 0 ? '#14663d' : '#8a4b00' }}">
                    Day-one {{ $person->day_one_done }} of {{ $person->day_one_total }}
                </a>
                @if(in_array(auth()->user()->role, ['hr','conductor','casual']))
                    <a class="button ghost" href="{{ route('casuals.forms', $person) }}">Forms</a>
                @endif
                @if($sensitive)
                    <form method="post" action="{{ route('view-as.start', $person) }}">
                        @csrf
                        <button class="ghost" type="submit">See what {{ $person->preferred_name }} sees</button>
                    </form>
                @endif
            </div>
        </div>
    </article>
    @endif
@empty
    <div class="card">No casuals in this view.</div>
@endforelse
@endsection