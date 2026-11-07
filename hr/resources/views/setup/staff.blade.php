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
            <p class="meta">{{ $member->email }}@if($member->neighbourhood) · {{ $member->neighbourhood }}@endif @if($member->mobile) · {{ $member->mobile }}@endif @if($member->on_people_page) · On the people page @endif</p>
            <div class="actions"><a class="button ghost" href="{{ route('setup.staff.edit', $member) }}">Edit</a></div>
        </div>
    </article>
@endforeach
<div class="card">
    <h2>Add a staff account</h2>
    <form method="post" action="{{ route('setup.staff.store') }}" enctype="multipart/form-data">
        @csrf
        <label for="name">Name</label>
        <input id="name" name="name" value="{{ old('name') }}" required>
        <label for="email">Work email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required>
        <p class="muted">This is also the sign-in address.</p>
        <label for="role">Role</label>
        <select id="role" name="role" required>
            @foreach(['hr' => 'HR', 'conductor' => 'Conductor', 'director' => 'Director', 'mentor' => 'Mentor', 'office' => 'Office'] as $value => $label)
                <option value="{{ $value }}" @selected(old('role') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <label for="neighbourhood">Neighbourhood</label>
        <input id="neighbourhood" name="neighbourhood" value="{{ old('neighbourhood') }}" placeholder="Whole School">
        <p class="muted">Write Whole School when every casual should see this card.</p>
        <label for="mobile">Work mobile</label>
        <input id="mobile" name="mobile" value="{{ old('mobile') }}">
        <p class="muted">School business only. Do not enter a home number.</p>
        <label for="photo">Photo</label>
        <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp">
        <p class="muted">Optional. A casual sees it only while signed in.</p>
        <label for="on_people_page">
            <input id="on_people_page" name="on_people_page" type="checkbox" value="1" @checked(old('on_people_page')) style="width:auto;">
            Show on the people page
        </label>
        <label for="people_title">Card title</label>
        <input id="people_title" name="people_title" value="{{ old('people_title') }}" placeholder="Front office">
        <label for="days_on_site">Days on site</label>
        <input id="days_on_site" name="days_on_site" value="{{ old('days_on_site') }}" placeholder="Monday to Friday">
        <label for="people_note">What a casual should know</label>
        <textarea id="people_note" name="people_note" maxlength="280">{{ old('people_note') }}</textarea>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required autocomplete="new-password">
        <label for="password_confirmation">Confirm password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
        <p><button type="submit">Add staff</button></p>
    </form>
</div>
@endsection
