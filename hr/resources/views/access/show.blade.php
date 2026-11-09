@extends('layouts.app')
@section('content')
<h1>What you can open</h1>
<p class="lede">{{ $label }}. This is the scope of this sign-in.</p>
<div class="card">
    <h2>You can open</h2>
    <p>{{ $mine['opens'] }}</p>
    <h2>Stays closed</h2>
    <p>{{ $mine['closed'] }}</p>
</div>
@if($matrix)
    <h2>Every role</h2>
    @foreach(['manage' => 'HR and Conductor', 'neighbourhood' => 'Director', 'support' => 'Mentor', 'front_desk' => 'Office', 'casual' => 'Casual teacher'] as $kind => $name)
        <article class="card">
            <h2>{{ $name }}</h2>
            <p>{{ $matrix[$kind]['opens'] }}</p>
            <p class="muted">{{ $matrix[$kind]['closed'] }}</p>
        </article>
    @endforeach
@endif
@endsection
