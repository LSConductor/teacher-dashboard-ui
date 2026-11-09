@extends('layouts.app')
@section('content')
<p class="muted"><a href="{{ route('casuals.forms', $person) }}">Forms</a></p>
<h1>Personal and emergency</h1>
<form method="post" action="{{ route('casuals.forms.personal', $person) }}" class="card">
    @csrf
    <label for="postal_address">Postal address</label>
    <input id="postal_address" name="postal_address" value="{{ old('postal_address', $person->postal_address) }}" required>
    <label for="emergency_contact_name">Emergency contact name</label>
    <input id="emergency_contact_name" name="emergency_contact_name" value="{{ old('emergency_contact_name', $person->emergency_contact_name) }}" required>
    <label for="emergency_contact_mobile">Emergency mobile</label>
    <input id="emergency_contact_mobile" name="emergency_contact_mobile" value="{{ old('emergency_contact_mobile', $person->emergency_contact_mobile) }}" required>
    <label for="adjustments">Adjustment the school needs to host you safely</label>
    <textarea id="adjustments" name="adjustments">{{ old('adjustments', $person->adjustments) }}</textarea>
    <label><input style="width:auto" type="checkbox" name="payroll_declaration_lodged" value="1" @checked(old('payroll_declaration_lodged', $person->payroll_declaration_lodged))> Tax declaration is lodged with payroll. Do not type a tax file number here.</label>
    <p><button type="submit">Save on the person</button></p>
</form>
@endsection
