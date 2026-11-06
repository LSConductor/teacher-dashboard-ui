@extends('layouts.app')
@section('content')
<div style="display:flex; justify-content:space-between; align-items:end; gap:12px;">
    <h1>Casuals</h1>
    @if(in_array(auth()->user()->role, ['hr','conductor']))
        <a class="button" href="{{ route('casuals.create') }}">Add casual</a>
    @endif
</div>
<p class="muted">Green can be booked. Amber needs a Conductor note. Red stays off the day, including any WWCC or accreditation problem.</p>
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
    <p><button type="submit">Filter</button></p>
</form>
<table>
    <thead>
        <tr>
            <th>Name</th>
            <th>Contact</th>
            <th>Neighbourhood</th>
            <th>Light</th>
            @if($sensitive)<th>Accreditation</th>@endif
        </tr>
    </thead>
    <tbody>
    @forelse($people as $person)
        <tr>
            <td><a href="{{ route('casuals.show', $person) }}">{{ $person->preferred_name }}</a><div class="muted">{{ $person->legal_name }}</div></td>
            <td>
                @if($person->mobile)<a href="tel:{{ $person->mobile }}">{{ $person->mobile }}</a><br>@endif
                <a href="mailto:{{ $person->email }}">{{ $person->email }}</a>
            </td>
            <td>{{ $person->neighbourhood }}</td>
            <td><span class="light {{ $person->gate['light'] }}">{{ $person->gate['light'] }}</span></td>
            @if($sensitive)
                <td>{{ $person->accreditation->level ?? '—' }} · {{ $person->accreditation->alignment ?? 'pending' }}</td>
            @endif
        </tr>
    @empty
        <tr><td colspan="5">No casuals in this view.</td></tr>
    @endforelse
    </tbody>
</table>
@endsection
