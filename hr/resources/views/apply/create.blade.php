@extends('layouts.app')
@section('content')
<section class="card" style="max-width:640px; margin:0 auto;">
    <p class="muted" style="letter-spacing:.08em; text-transform:uppercase; font-weight:700; color:#14324a;">Casual Induction</p>
    <h1>Apply to teach</h1>
    <p class="lede">This is the expression of interest. It asks for your name, contact, the stages you can teach, and your right to work. It does not ask for a tax file number, a WWCC number, or a date of birth. HR checks those after you are on the list.</p>
    <form method="post" action="{{ route('apply.store') }}">
        @csrf
        <label for="preferred_name">Preferred name</label>
        <input id="preferred_name" name="preferred_name" value="{{ old('preferred_name') }}" required>
        <label for="legal_name">Legal name</label>
        <input id="legal_name" name="legal_name" value="{{ old('legal_name') }}" required>
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required>
        <label for="mobile">Mobile</label>
        <input id="mobile" name="mobile" value="{{ old('mobile') }}" required>
        <label for="neighbourhood">Neighbourhood you can reach</label>
        <input id="neighbourhood" name="neighbourhood" value="{{ old('neighbourhood') }}" required>
        <label>Stages you can teach</label>
        @foreach(['ES1','S1','S2','S3','S4','S5','S6'] as $stage)
            <label style="font-weight:500;"><input style="width:auto" type="checkbox" name="stages[]" value="{{ $stage }}" @checked(in_array($stage, old('stages', []), true))> {{ $stage }}</label>
        @endforeach
        <label for="right_to_work">Right to work in Australia</label>
        <textarea id="right_to_work" name="right_to_work" rows="4" required>{{ old('right_to_work') }}</textarea>
        <label for="heard_about">How you heard of the school</label>
        <input id="heard_about" name="heard_about" value="{{ old('heard_about') }}">
        <p><button type="submit">Send the application</button></p>
    </form>
</section>
@endsection
