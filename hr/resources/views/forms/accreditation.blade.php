@extends('layouts.app')
@section('content')
<p class="muted"><a href="{{ route('casuals.forms', $person) }}">Forms</a></p>
<h1>Accreditation</h1>
<p class="muted">The casual supplies the number and the level. HR records the eTAMS check. The Conductor approves alignment. This screen does not write to eTAMS.</p>
<form class="card" method="post" action="{{ route('casuals.accreditation', $person) }}">
    @csrf
    <label for="nesa_number">NESA number</label>
    <input id="nesa_number" name="nesa_number" value="{{ old('nesa_number', $person->accreditation->nesa_number ?? '') }}">
    <label for="level">Level</label>
    <select id="level" name="level">
        <option value="">Unknown</option>
        @foreach(['conditional','provisional','proficient','highly_accomplished','lead'] as $level)
            <option value="{{ $level }}" @selected(old('level', $person->accreditation->level ?? '')===$level)>{{ str_replace('_',' ', $level) }}</option>
        @endforeach
    </select>
    <label for="status">Status after the eTAMS check</label>
    <select id="status" name="status">
        @foreach(['active','leave_of_absence','non_practising','suspended','cancelled'] as $status)
            <option value="{{ $status }}" @selected(old('status', $person->accreditation->status ?? 'active')===$status)>{{ str_replace('_',' ', $status) }}</option>
        @endforeach
    </select>
    <label for="maintenance_end">Maintenance end</label>
    <input id="maintenance_end" type="date" name="maintenance_end" value="{{ old('maintenance_end', optional($person->accreditation->maintenance_end ?? null)->toDateString()) }}">
    <label for="fee_year">Fee year the teacher declares</label>
    <input id="fee_year" name="fee_year" value="{{ old('fee_year', $person->accreditation->fee_year ?? '') }}">
    <label><input style="width:auto" type="checkbox" name="casual_evidence_on_file" value="1" @checked(old('casual_evidence_on_file', $person->accreditation->casual_evidence_on_file ?? false))> Casual-status evidence is on file for the 7-year period</label>
    @if(in_array(auth()->user()->role, ['hr','conductor']))
        <p><button type="submit">Record eTAMS check</button></p>
        @if(auth()->user()->role === 'conductor')
            <p>
                <button formaction="{{ route('casuals.alignment', $person) }}" name="alignment" value="approved">Approve eTAMS alignment</button>
                <button formaction="{{ route('casuals.alignment', $person) }}" name="alignment" value="declined">Decline</button>
            </p>
        @endif
    @else
        <p class="muted">HR records the check. You can read what is on file.</p>
    @endif
</form>
@endsection
