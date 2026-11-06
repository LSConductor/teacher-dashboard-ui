@extends('layouts.app')
@section('content')
<h1>What this app will grow into</h1>
<p class="muted">{{ $modules['catalyst']['note'] }}</p>
<div class="card">
    <h2>{{ $modules['hr']['label'] }}</h2>
    <p>In use now.</p>
</div>
<div class="card">
    <h2>{{ $modules['catalyst']['label'] }}</h2>
    <p>Switched off. These desks are named so the database and roles can take them later, on this app, without changing the primary portal in this release.</p>
    <ul>
        @foreach($modules['catalyst']['future'] as $label)
            <li>{{ $label }}</li>
        @endforeach
    </ul>
</div>
@endsection
