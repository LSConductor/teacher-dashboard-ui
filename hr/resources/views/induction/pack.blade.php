@extends('layouts.app')
@section('content')
<p class="muted"><a href="{{ route('casuals.index') }}">Casuals</a></p>
<h1>Day-one induction</h1>
<p class="muted">{{ $person->preferred_name }}. The light stays red until every day-one page is acknowledged. Later pages can stay open.</p>
@foreach($modules as $module)
    @php $done = $person->acknowledgements->first(fn ($row) => $row->module_id === $module->id && $row->module_version >= $module->version); @endphp
    <div class="card">
        <h2>{{ $module->phase === 'day_one' ? 'Day one' : 'Later' }}. {{ $module->title }}</h2>
        @if($done)
            <p class="muted">Acknowledged by {{ $person->preferred_name }} {{ $done->acknowledged_at->timezone('Australia/Sydney')->format('j M Y g:i a') }}</p>
        @else
            <p class="muted">Not yet acknowledged</p>
        @endif
        <a class="button" href="{{ route('casuals.induction', [$person, $module]) }}">Open page</a>
    </div>
@endforeach
@endsection
