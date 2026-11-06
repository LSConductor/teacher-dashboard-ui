@extends('layouts.app')
@section('content')
<div style="display:flex; justify-content:space-between; align-items:end; gap:12px;">
    <h1>Casuals</h1>
    @if(in_array(auth()->user()->role, ['hr','conductor']))
        <a class="button" href="{{ route('casuals.create') }}">Expression of interest</a>
    @endif
</div>
<p class="muted">Call or email from this list. Green can be booked. Amber needs a Conductor note. Red stays off the day.</p>
<form method="get" class="card grid">
    <div>
        <label for="q">Name</label>
        <input id="q" name="q" value="{{ request('q') }}">
    </div>
    <div>
        <label for="light">Light</label>
        <select id="light" name="light">
            <option value="">Any</option>
            @foreach(['green','amber','red'] as $light)
                <option value="{{ $light }}" @selected(request('light')===$light)>{{ ucfirst($light) }}</option>
            @endforeach
        </select>
    </div>
    @if($sensitive)
    <div>
        <label for="focus">Also show</label>
        <select id="focus" name="focus">
            <option value="">Everyone in this list</option>
            <option value="induction" @selected(request('focus')==='induction')>Day-one unfinished</option>
            <option value="alignment" @selected(request('focus')==='alignment')>Waiting on the Conductor</option>
            <option value="provisional" @selected(request('focus')==='provisional')>Conditional or Provisional</option>
            <option value="expiring" @selected(request('focus')==='expiring')>WWCC expiring in 30 days</option>
        </select>
    </div>
    @endif
    <p><button type="submit">Filter</button></p>
</form>
<table>
    <thead>
        <tr>
            <th>Name</th>
            <th>Contact</th>
            <th>Stages</th>
            <th>Today</th>
            <th>Light</th>
            <th>Mentor</th>
            @if($sensitive)<th>File</th>@endif
        </tr>
    </thead>
    <tbody>
    @forelse($people as $person)
        @php
            $today = $person->bookings->first(fn ($booking) => $booking->booked_on->isSameDay(now()));
        @endphp
        <tr>
            <td>
                <a href="{{ route('casuals.show', $person) }}">{{ $person->preferred_name }}</a>
                <div class="muted">{{ $person->legal_name }}</div>
                <div class="muted">{{ $person->neighbourhood }}</div>
                <a href="{{ route('casuals.pack', $person) }}">Day-one</a>
                @if(in_array(auth()->user()->role, ['hr','conductor','casual']))
                    · <a href="{{ route('casuals.forms', $person) }}">Forms</a>
                @endif
            </td>
            <td>
                @if($person->mobile)
                    <a class="button" href="tel:{{ preg_replace('/\s+/', '', $person->mobile) }}">Call</a>
                @endif
                <a class="button" href="mailto:{{ $person->email }}">Email</a>
                <div class="muted">{{ $person->mobile }}<br>{{ $person->email }}</div>
            </td>
            <td>{{ implode(', ', $person->stages ?? []) }}</td>
            <td>
                @if($today)
                    <div>{{ $today->class_label }}</div>
                    @if($today->plan_url)
                        <a class="button" href="{{ $today->plan_url }}" target="_blank" rel="noopener">Open the plan</a>
                    @endif
                @else
                    <span class="muted">No class today</span>
                @endif
            </td>
            <td><span class="light {{ $person->gate['light'] }}">{{ $person->gate['light'] }}</span></td>
            <td>{{ $person->mentor->name ?? '—' }}</td>
            @if($sensitive)
                <td>
                    {{ $person->accreditation->level ?? '—' }}
                    · {{ $person->accreditation->alignment ?? 'pending' }}
                    <div class="muted">WWCC {{ optional($person->wwcc->expires_on ?? null)->toDateString() ?? 'not verified' }}</div>
                    <div class="muted">Maintenance {{ optional($person->accreditation->maintenance_end ?? null)->toDateString() ?? '—' }}</div>
                </td>
            @endif
        </tr>
    @empty
        <tr><td colspan="7">No casuals in this view.</td></tr>
    @endforelse
    </tbody>
</table>
@endsection
