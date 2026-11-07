@extends('layouts.app')
@section('content')
<h1>This month</h1>
<p class="lede">Clearances and maintenance due within 30 days, and a standards reply that is still open. A clearance number is not on this page.</p>
@forelse($lines->groupBy(fn ($line) => $line['person']->id) as $group)
    @php
        $person = $group->first()['person'];
        $red = $group->contains(fn ($line) => $line['level'] === 'red');
        $label = $group->firstWhere('level', 'red')['label'] ?? $group->first()['label'];
    @endphp
    <article class="person {{ $red ? 'red' : 'amber' }}">
        <div><span class="status {{ $red ? 'red' : 'amber' }}">{{ $label }}</span></div>
        <div>
            <h2><a href="{{ route('casuals.show', $person) }}">{{ $person->preferred_name }}</a></h2>
            @foreach($group as $line)
                <p>{{ $line['text'] }}</p>
            @endforeach
        </div>
    </article>
@empty
    <p>Nothing is due in the next 30 days.</p>
@endforelse
@endsection
