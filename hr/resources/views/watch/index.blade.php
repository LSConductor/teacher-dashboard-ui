@extends('layouts.app')
@section('content')
@php $people = $lines->groupBy(fn ($line) => $line['person']->id); @endphp
<h1>This month</h1>
<p class="lede">
    @if($people->isEmpty())
        Nothing is due in the next 30 days.
    @else
        {{ $people->count() }} {{ $people->count() === 1 ? 'person' : 'people' }} to clear. Open the file and record the date. A clearance number is not on this page.
    @endif
</p>
@forelse($people as $group)
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
            <div class="actions"><a class="button" href="{{ route('casuals.show', $person) }}">Open the file</a></div>
        </div>
    </article>
@empty
@endforelse
@endsection
