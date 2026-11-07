@extends('layouts.app')
@section('content')
<section class="card" style="max-width:640px; margin:0 auto;">
    <p class="muted" style="letter-spacing:.08em; text-transform:uppercase; font-weight:700; color:#14324a;">Parents as partners</p>
    <h1>Help at Living School</h1>
    <p class="lede">Six short pages, one check, and a few lines about what you can offer. There is no community to join. HR is told when you finish.</p>
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
