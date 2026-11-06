@extends('layouts.app', ['title' => 'Casual HR', 'intro' => 'Local staff session'])

@section('content')
    <form class="panel login" method="post" action="{{ url('/login') }}">
        @csrf
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <p><button type="submit">Continue</button></p>
    </form>

    @if (app()->environment('local'))
        <section class="card">
            <h2>Local accounts</h2>
            <p>Password for every seeded account: <strong>local-staff</strong>. These people are fictional.</p>
            <ul>
                <li>morgan.hale@example.test — Conductor</li>
                <li>rowan.ellis@example.test — HR</li>
                <li>quinn.adler@example.test — Director, Fig Neighbourhood</li>
                <li>sage.bennett@example.test — Director, River Neighbourhood</li>
                <li>taylor.brooks@example.test — Office</li>
            </ul>
        </section>
    @endif

    @if (session('invite_person_id'))
        <p><a href="{{ route('induction.shell') }}">Back to your induction shell</a></p>
    @endif
@endsection
