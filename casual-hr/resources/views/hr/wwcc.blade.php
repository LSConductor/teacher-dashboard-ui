@extends('layouts.app', ['title' => 'WWCC · '.$person->preferred_name, 'intro' => 'HR only. This page is audited.'])

@section('content')
    <form class="panel" method="post" action="{{ route('hr.people.wwcc.update', $person) }}">
        @csrf
        @method('put')
        <label for="wwcc_number">WWCC number</label>
        <input id="wwcc_number" name="wwcc_number" value="{{ old('wwcc_number', $wwcc->wwcc_number ?? '') }}" required>
        <p class="meta">An APP number is rejected. A clearance is required.</p>
        <label for="name_used">Name used for the check</label>
        <input id="name_used" name="name_used" value="{{ old('name_used', $wwcc->name_used ?? $person->legal_name) }}" required>
        <label for="date_of_birth">Date of birth used</label>
        <input id="date_of_birth" type="date" name="date_of_birth" value="{{ old('date_of_birth', $wwcc?->date_of_birth?->toDateString()) }}" required>
        <label for="outcome">Outcome</label>
        <select id="outcome" name="outcome" required>
            @foreach (\App\Models\Wwcc::OUTCOMES as $outcome)
                <option value="{{ $outcome }}" @selected(old('outcome', $wwcc->outcome ?? 'pending') === $outcome)>{{ str_replace('_', ' ', $outcome) }}</option>
            @endforeach
        </select>
        <label for="verified_on">Verified on</label>
        <input id="verified_on" type="date" name="verified_on" value="{{ old('verified_on', $wwcc?->verified_at?->timezone('Australia/Sydney')->toDateString()) }}">
        <label for="expires_on">Expiry</label>
        <input id="expires_on" type="date" name="expires_on" value="{{ old('expires_on', $wwcc?->expires_on?->toDateString()) }}">
        <p><button type="submit">Save WWCC</button></p>
    </form>
    <p><a href="{{ route('hr.people.show', $person) }}">Back to the file</a></p>
@endsection
