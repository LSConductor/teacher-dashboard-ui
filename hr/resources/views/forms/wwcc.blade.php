@extends('layouts.app')
@section('content')
<p class="muted"><a href="{{ route('casuals.forms', $person) }}">Forms</a></p>
<h1>WWCC</h1>
<p class="muted">HR verifies this with the Office of the Children’s Guardian. An APP number is not a clearance and cannot turn the light green.</p>
<form class="card" method="post" action="{{ route('casuals.wwcc', $person) }}">
    @csrf
    <label for="wwcc_number">WWCC number</label>
    <input id="wwcc_number" name="wwcc_number" value="{{ old('wwcc_number', $person->wwcc->wwcc_number ?? '') }}" required>
    <label for="name_on_check">Name on the check</label>
    <input id="name_on_check" name="name_on_check" value="{{ old('name_on_check', $person->wwcc->name_on_check ?? $person->legal_name) }}" required>
    <label for="date_of_birth">Date of birth</label>
    <input id="date_of_birth" type="date" name="date_of_birth" value="{{ old('date_of_birth', optional($person->wwcc->date_of_birth ?? null)->toDateString()) }}" required>
    <label for="outcome">Outcome</label>
    <select id="outcome" name="outcome">
        @foreach(['pending','cleared','barred','interim_bar','expired'] as $outcome)
            <option value="{{ $outcome }}" @selected(old('outcome', $person->wwcc->outcome ?? 'pending')===$outcome)>{{ str_replace('_',' ', $outcome) }}</option>
        @endforeach
    </select>
    <label for="expires_on">Expiry</label>
    <input id="expires_on" type="date" name="expires_on" value="{{ old('expires_on', optional($person->wwcc->expires_on ?? null)->toDateString()) }}">
    <p><button type="submit">{{ auth()->user()->role === 'casual' ? 'Send details to HR' : 'Save WWCC' }}</button></p>
</form>
@endsection
