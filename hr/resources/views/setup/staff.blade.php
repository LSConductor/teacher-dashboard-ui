@extends('layouts.app')
@section('content')
<p class="muted"><a class="link" href="{{ route('setup') }}">Setup</a></p>
<h1>Staff</h1>
<p class="lede">These are the people who sign in. A casual still joins from an invite on their record. Moving a director changes which neighbourhood they cover.</p>
@foreach($staff as $member)
    <article class="person">
        <div><span class="status">{{ $member->role === 'hr' ? 'HR' : ucfirst($member->role) }}</span></div>
        <div>
            <h2>{{ $member->name }}</h2>
            <p class="meta">{{ $member->email }}@if($member->neighbourhood) · {{ $member->neighbourhood }}@endif @if($member->mobile) · {{ $member->mobile }}@endif</p>
            <div class="actions"><a class="button ghost" href="{{ route('setup.staff.edit', $member) }}">Edit</a></div>
        </div>
    </article>
@endforeach
<div class="card">
    <h2>Add a staff account</h2>
    <form method="post" action="{{ route('setup.staff.store') }}">
        @csrf
        <label for="name">Name</label>
        <input id="name" name="name" value="{{ old('name') }}" required>
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required>
        <label for="role">Role</label>
        <select id="role" name="role" required>
            @foreach(['hr' => 'HR', 'conductor' => 'Conductor', 'director' => 'Director', 'mentor' => 'Mentor', 'office' => 'Office'] as $value => $label)
                <option value="{{ $value }}" @selected(old('role') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <label for="neighbourhood">Neighbourhood</label>
        <input id="neighbourhood" name="neighbourhood" value="{{ old('neighbourhood') }}">
        <label for="mobile">Mobile</label>
        <input id="mobile" name="mobile" value="{{ old('mobile') }}">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required autocomplete="new-password">
        <label for="password_confirmation">Confirm password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
        <p><button type="submit">Add staff</button></p>
    </form>
</div>
@endsection
