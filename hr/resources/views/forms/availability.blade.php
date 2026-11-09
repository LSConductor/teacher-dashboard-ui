@extends('layouts.app')
@section('content')
<p class="muted"><a href="{{ route('casuals.forms', $person) }}">Forms</a></p>
<h1>Availability</h1>
<p class="muted">Days you can be offered. This does not turn the light green. A do-not-book request stays on the person page.</p>
<form method="post" action="{{ route('casuals.forms.availability', $person) }}" class="card">
    @csrf
    <label>Days</label>
    @php $days = old('available_days', $person->available_days ?? []); @endphp
    @foreach(['Monday','Tuesday','Wednesday','Thursday','Friday'] as $day)
        <label style="display:inline-block; margin-right:10px;"><input style="width:auto" type="checkbox" name="available_days[]" value="{{ $day }}" @checked(in_array($day, $days, true))> {{ $day }}</label>
    @endforeach
    <label>Neighbourhoods</label>
    @php $areas = old('neighbourhoods', $person->neighbourhoods ?? []); @endphp
    @foreach(['Ngulingah','SoTOS'] as $area)
        <label style="display:inline-block; margin-right:10px;"><input style="width:auto" type="checkbox" name="neighbourhoods[]" value="{{ $area }}" @checked(in_array($area, $areas, true))> {{ $area }}</label>
    @endforeach
    <label>Stages</label>
    @php $selected = old('stages', $person->stages ?? []); @endphp
    @foreach(['ES1','S1','S2','S3','S4','S5','S6'] as $stage)
        <label style="display:inline-block; margin-right:10px;"><input style="width:auto" type="checkbox" name="stages[]" value="{{ $stage }}" @checked(in_array($stage, $selected, true))> {{ $stage }}</label>
    @endforeach
    <label for="earliest_start">Earliest start</label>
    <input id="earliest_start" name="earliest_start" value="{{ old('earliest_start', $person->earliest_start) }}" placeholder="8:30" required>
    <p><button type="submit">Save availability</button></p>
</form>
@endsection
