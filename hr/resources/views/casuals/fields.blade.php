<label for="preferred_name">Preferred name</label>
<input id="preferred_name" name="preferred_name" value="{{ old('preferred_name', $person->preferred_name ?? '') }}" required>
<label for="legal_name">Legal name</label>
<input id="legal_name" name="legal_name" value="{{ old('legal_name', $person->legal_name ?? '') }}" required>
<label for="email">Email</label>
<input id="email" name="email" type="email" value="{{ old('email', $person->email ?? '') }}" required>
<label for="mobile">Mobile</label>
<input id="mobile" name="mobile" value="{{ old('mobile', $person->mobile ?? '') }}">
<label for="neighbourhood">Neighbourhood</label>
<input id="neighbourhood" name="neighbourhood" value="{{ old('neighbourhood', $person->neighbourhood ?? '') }}">
<label>Stages</label>
@php $selected = old('stages', $person->stages ?? []); @endphp
@foreach(['ES1','S1','S2','S3','S4','S5','S6'] as $stage)
    <label style="display:inline-block; margin-right:10px;"><input style="width:auto" type="checkbox" name="stages[]" value="{{ $stage }}" @checked(in_array($stage, $selected))> {{ $stage }}</label>
@endforeach
<label for="postal_address">Postal address</label>
<input id="postal_address" name="postal_address" value="{{ old('postal_address', $person->postal_address ?? '') }}">
<label for="emergency_contact_name">Emergency contact</label>
<input id="emergency_contact_name" name="emergency_contact_name" value="{{ old('emergency_contact_name', $person->emergency_contact_name ?? '') }}">
<label for="emergency_contact_mobile">Emergency mobile</label>
<input id="emergency_contact_mobile" name="emergency_contact_mobile" value="{{ old('emergency_contact_mobile', $person->emergency_contact_mobile ?? '') }}">
<label for="heard_about">How they heard of the school</label>
<input id="heard_about" name="heard_about" value="{{ old('heard_about', $person->heard_about ?? '') }}">
<label for="right_to_work">Right to work</label>
<textarea id="right_to_work" name="right_to_work">{{ old('right_to_work', $person->right_to_work ?? '') }}</textarea>
<label for="mentor_user_id">Mentor</label>
<select id="mentor_user_id" name="mentor_user_id">
    <option value="">None yet</option>
    @foreach($mentors as $mentor)
        <option value="{{ $mentor->id }}" @selected(old('mentor_user_id', $person->mentor_user_id ?? '') == $mentor->id)>{{ $mentor->name }}</option>
    @endforeach
</select>
<label><input style="width:auto" type="checkbox" name="payroll_declaration_lodged" value="1" @checked(old('payroll_declaration_lodged', $person->payroll_declaration_lodged ?? false))> Tax declaration is lodged with payroll. This app does not store a tax file number.</label>
