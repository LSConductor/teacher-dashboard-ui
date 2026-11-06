<label for="legal_name">Legal name</label>
<input id="legal_name" name="legal_name" value="{{ old('legal_name', $person?->legal_name) }}" required>

<label for="preferred_name">Preferred name</label>
<input id="preferred_name" name="preferred_name" value="{{ old('preferred_name', $person?->preferred_name) }}" required>

<label for="email">Email</label>
<input id="email" name="email" type="email" value="{{ old('email', $person?->email) }}" required>

<label for="mobile">Mobile</label>
<input id="mobile" name="mobile" value="{{ old('mobile', $person?->mobile) }}" required placeholder="0400000000">

<label for="neighbourhood_id">Neighbourhood</label>
<select id="neighbourhood_id" name="neighbourhood_id">
    <option value="">None yet</option>
    @foreach ($neighbourhoods as $neighbourhood)
        <option value="{{ $neighbourhood->id }}" @selected((string) old('neighbourhood_id', $person?->neighbourhood_id) === (string) $neighbourhood->id)>{{ $neighbourhood->name }}</option>
    @endforeach
</select>

<fieldset>
    <legend>Stages</legend>
    <div class="checks">
        @foreach (\App\Models\Person::STAGES as $stage)
            <label>
                <input type="checkbox" name="stages[]" value="{{ $stage }}" @checked(in_array($stage, old('stages', $person?->stages ?? []), true))>
                {{ $stage }}
            </label>
        @endforeach
    </div>
</fieldset>

<label for="employment_status">Employment status</label>
<select id="employment_status" name="employment_status" required>
    @foreach (['applicant' => 'Applicant', 'casual_pool' => 'Casual pool', 'ended' => 'Ended'] as $value => $label)
        <option value="{{ $value }}" @selected(old('employment_status', $person?->employment_status ?? 'applicant') === $value)>{{ $label }}</option>
    @endforeach
</select>
