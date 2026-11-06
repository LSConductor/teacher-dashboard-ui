@extends('layouts.app')

@section('content')
    <p class="meta">Today is {{ \Carbon\Carbon::parse($today, 'Australia/Sydney')->format('l j F Y') }}.</p>

    @forelse ($groups as $neighbourhood => $cards)
        <h2>{{ $neighbourhood }}</h2>
        <div class="grid">
            @foreach ($cards as $card)
                @include('casual.card', ['card' => $card, 'today' => $today])
            @endforeach
        </div>
    @empty
        <p>No casuals in the pool for this list.</p>
    @endforelse
@endsection
