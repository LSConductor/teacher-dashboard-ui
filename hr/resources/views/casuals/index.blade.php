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
            <option value="expiring" @selected(request('focus')==='expiring')>WWCC expiring in 30 days</option>
        </select>
    </div>
    @endif
    <button type="submit">Show</button>
</form>

@forelse($people as $person)
    <article class="person {{ $person->gate['light'] }}">
        <div>
            <span class="status {{ $person->gate['light'] }}">{{ $labels[$person->gate['light']] }}</span>
        </div>
        <div>
            <h2><a href="{{ route('casuals.show', $person) }}">{{ $person->preferred_name }}</a></h2>
            <p class="meta">{{ $person->legal_name }} · {{ $person->neighbourhood }} · {{ implode(', ', $person->stages ?? []) ?: 'Stages not set' }}</p>
            @if($person->status === 'applicant')
                <p class="meta">Application received. Not on the bookable list yet.</p>
            @endif
            <p class="meta">
                Mentor {{ $person->mentor->name ?? 'not named' }}
                @if($person->director_name) · Director {{ $person->director_name }} @endif
                @if($person->today_booking) · Today {{ $person->today_booking->class_label }} @else · No class today @endif
            </p>
            @if($sensitive)
                <p class="meta">
                    {{ ucfirst($person->accreditation->level ?? 'accreditation unknown') }}
                    · alignment {{ $person->accreditation->alignment ?? 'pending' }}
                    · WWCC {{ optional($person->wwcc->expires_on ?? null)->toDateString() ?? 'not verified' }}
                    · maintenance {{ optional($person->accreditation->maintenance_end ?? null)->toDateString() ?? 'not recorded' }}
                    · last day {{ optional($person->last_day)->toDateString() ?? 'none' }}
                </p>
            @endif
            @if($person->gate['reasons'])
                <p class="meta">{{ $person->gate['reasons'][0]['text'] }}</p>
            @endif
            <div class="actions">
                @if($person->mobile)
                    <a class="button" href="tel:{{ preg_replace('/\s+/', '', $person->mobile) }}">Call {{ $person->mobile }}</a>
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
            </div>
        </div>
    </article>
@empty
    <div class="card">No casuals in this view.</div>
@endforelse
@endsection