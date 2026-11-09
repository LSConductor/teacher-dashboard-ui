@extends('layouts.app')
@section('content')
<p class="muted"><a class="link" href="{{ route('partners.index') }}">Parents as partners</a></p>
<h1>{{ $partner->preferred_name }}</h1>
<p><span class="status {{ $status === 'ready' ? 'green' : ($status === 'waiting' ? 'amber' : 'red') }}">{{ $label }}</span></p>
<p class="lede">{{ $partner->relationship === 'grandparent' ? 'Grandparent' : 'Parent' }} of {{ $partner->child_name }} · {{ $partner->neighbourhood }}</p>
<div class="card">
    <p>{{ $partner->email }} @if($partner->mobile)· {{ $partner->mobile }}@endif</p>
    <p>
        @foreach($partner->interests ?? [] as $key)
            {{ $interests[$key] ?? $key }}@if(! $loop->last), @endif
        @endforeach
    </p>
    @if($partner->strengths)<p>{{ $partner->strengths }}</p>@endif
    @if($partner->availability)<p class="muted">{{ $partner->availability }}</p>@endif
</div>
@if($manages)
    <form class="card" method="post" action="{{ route('partners.volunteer', $partner) }}">
        @csrf
        <h2>Volunteer check</h2>
        <p class="muted">Record the clearance you verified. An APP number is not a clearance. A director sees ready or not ready, and never this number.</p>
        <label for="volunteer_number">Clearance number</label>
        <input id="volunteer_number" name="volunteer_number" value="{{ old('volunteer_number', $partner->volunteer_number) }}">
        <label for="volunteer_status">Outcome</label>
        <select id="volunteer_status" name="volunteer_status">
            <option value="cleared" @selected(old('volunteer_status', $partner->volunteer_status) === 'cleared')>Cleared</option>
            <option value="not_cleared" @selected(old('volunteer_status', $partner->volunteer_status) === 'not_cleared')>Not cleared</option>
        </select>
        <div class="grid">
            <div>
                <label for="volunteer_verified_on">Verified</label>
                <input id="volunteer_verified_on" type="date" name="volunteer_verified_on" value="{{ old('volunteer_verified_on', optional($partner->volunteer_verified_on)->toDateString()) }}">
            </div>
            <div>
                <label for="volunteer_expires_on">Expires</label>
                <input id="volunteer_expires_on" type="date" name="volunteer_expires_on" value="{{ old('volunteer_expires_on', optional($partner->volunteer_expires_on)->toDateString()) }}">
            </div>
        </div>
        <p><button type="submit">Save the check</button></p>
    </form>
@endif
@endsection
