@extends('layouts.app')
@section('content')
<div class="card" style="max-width:480px">
    <h1>Set your password</h1>
    <p class="muted">{{ $invite->person->preferred_name }}, this invite opens only your casual record.</p>
    <form method="post" action="{{ route('invites.accept', $token) }}">
        @csrf
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required>
        <label for="password_confirmation">Confirm</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required>
        <p><button type="submit">Open my record</button></p>
    </form>
</div>
@endsection
