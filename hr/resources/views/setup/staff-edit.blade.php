@extends('layouts.app')
@section('content')
<p class="muted"><a class="link" href="{{ route('setup.staff') }}">Staff</a></p>
<h1>{{ $member->name }}</h1>
<p class="lede">Work mobile and work email only. A photo is optional. Tick the people page when a casual should see this person on the day they attend. Your own role stays as it is.</p>
<form method="post" action="{{ route('setup.staff.update', $member) }}" class="card" enctype="multipart/form-data">
    @csrf @method('put')
    <label for="name">Name</label>
    <input id="name" name="name" value="{{ old('name', $member->name) }}" required>
    <label for="email">Work email</label>
    <input id="email" name="email" type="email" value="{{ old('email', $member->email) }}" required>
    <p class="muted">This is also the sign-in address.</p>
    <label for="role">Role</label>
    <select id="role" name="role">
        @foreach(['hr' => 'HR', 'conductor' => 'Conductor', 'director' => 'Director', 'mentor' => 'Mentor', 'office' => 'Office', 'casual' => 'Casual'] as $value => $label)
            <option value="{{ $value }}" @selected(old('role', $member->role) === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <label for="neighbourhood">Neighbourhood</label>
    <input id="neighbourhood" name="neighbourhood" value="{{ old('neighbourhood', $member->neighbourhood) }}" placeholder="Whole School">
    <p class="muted">Write Whole School when every casual should see this card. A neighbourhood name limits it to that neighbourhood.</p>
    <label for="mobile">Work mobile</label>
    <input id="mobile" name="mobile" value="{{ old('mobile', $member->mobile) }}">
    <p class="muted">School business only, while a casual is signed in. Do not enter a home number.</p>
    <label for="photo">Photo</label>
    @if($member->photo_path)
        <img class="portrait" src="{{ route('people.photo', $member, false) }}" alt="Photo of {{ $member->name }}">
        <label for="remove_photo">
            <input id="remove_photo" name="remove_photo" type="checkbox" value="1" style="width:auto;">
            Remove photo
        </label>
    @endif
    <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp">
    <p class="muted">Optional head and shoulders photo from your computer. It does not need to be a web address. Shown only to people signed in at this school.</p>
    <label for="on_people_page">
        <input id="on_people_page" name="on_people_page" type="checkbox" value="1" @checked(session()->hasOldInput() ? old('on_people_page') : $member->on_people_page) style="width:auto;">
        Show on the people page
    </label>
    <label for="people_title">Card title</label>
    <input id="people_title" name="people_title" value="{{ old('people_title', $member->people_title) }}" placeholder="Ngulingah Director">
    <label for="days_on_site">Days on site</label>
    <input id="days_on_site" name="days_on_site" value="{{ old('days_on_site', $member->days_on_site) }}" placeholder="Monday to Friday">
    <label for="people_note">What a casual should know</label>
    <textarea id="people_note" name="people_note" maxlength="280">{{ old('people_note', $member->people_note) }}</textarea>
    <label for="password">New password</label>
    <input id="password" name="password" type="password" autocomplete="new-password">
    <label for="password_confirmation">Confirm password</label>
    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password">
    <p><button type="submit">Save staff</button></p>
</form>
@endsection
