@extends('layouts.app')
@section('content')
<section class="card welcome">
    <p class="record-kicker">For parents and grandparents</p>
    <h1>Thank you for offering to help</h1>
    <p>This page is for parents and grandparents of Living School. It is not the form for casual teachers.</p>
    <p>The paper is the course in full. Read it whenever you like. The six short pages here walk the same ground, then ask what you can offer. There is no community to join. HR is told when you finish.</p>
    <p><a class="button ghost" href="{{ asset('parents-as-partners.pdf') }}">Read the Parents as partners paper</a></p>
    <h2>Tell us who you are</h2>
    <form method="post" action="{{ route('partners.join.store') }}">
        @csrf
        <label for="preferred_name">Your name</label>
        <input id="preferred_name" name="preferred_name" value="{{ old('preferred_name') }}" required>
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required>
        <label for="relationship">You are</label>
        <select id="relationship" name="relationship">
            <option value="parent" @selected(old('relationship', 'parent') === 'parent')>A parent</option>
            <option value="grandparent" @selected(old('relationship') === 'grandparent')>A grandparent</option>
        </select>
        <label for="child_name">Child's name</label>
        <input id="child_name" name="child_name" value="{{ old('child_name') }}" required>
        <label for="neighbourhood">Learning neighbourhood</label>
        <select id="neighbourhood" name="neighbourhood" required>
            @foreach($neighbourhoods as $name)
                <option value="{{ $name }}" @selected(old('neighbourhood') === $name)>{{ $name }}</option>
            @endforeach
        </select>
        <p><button type="submit">Start</button></p>
    </form>
</section>
@endsection
