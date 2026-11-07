@extends('layouts.app')
@section('content')
<p class="muted"><a class="link" href="{{ route('setup.staff') }}">Staff</a></p>
<h1>{{ $member->name }}</h1>
<p class="lede">Change the role, the neighbourhood, or the password. Your own role stays as it is.</p>
<form method="post" action="{{ route('setup.staff.update', $member) }}" class="card">
    @csrf @method('put')
    <label for="name">Name</label>
    <input id="name" name="name" value="{{ old('name', $member->name) }}" required>
    <label for="email">Email</label>
    <input id="email" name="email" type="email" value="{{ old('email', $member->email) }}" required>
    <label for="role">Role</label>
    <select id="role" name="role">
        @foreach(['hr' => 'HR', 'conductor' => 'Conductor', 'director' => 'Director', 'mentor' => 'Mentor', 'office' => 'Office', 'casual' => 'Casual'] as $value => $label)
            <option value="{{ $value }}" @selected(old('role', $member->role) === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <label for="neighbourhood">Neighbourhood</label>
    <input id="neighbourhood" name="neighbourhood" value="{{ old('neighbourhood', $member->neighbourhood) }}">
    <label for="mobile">Mobile</label>
    <input id="mobile" name="mobile" value="{{ old('mobile', $member->mobile) }}">
    <label for="password">New password</label>
    <input id="password" name="password" type="password" autocomplete="new-password">
    <label for="password_confirmation">Confirm password</label>
    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password">
    <p><button type="submit">Save staff</button></p>
</form>
@endsection
