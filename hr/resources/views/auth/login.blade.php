@extends('layouts.app')
@section('content')
<div class="card" style="max-width:420px; margin:40px auto;">
    <h1>Staff sign in</h1>
    <p class="muted">Use the Living School Google account already saved for you (@living.school or @livingschool.nsw.edu.au). The password below stays for this build.</p>
    <p><a class="button" href="{{ route('auth.google') }}">Continue with Google</a></p>
    <form method="post" action="{{ route('login') }}">
        @csrf
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required>
        <p><button type="submit">Continue</button></p>
    </form>
</div>
@endsection
