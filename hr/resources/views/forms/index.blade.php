@extends('layouts.app')
@section('content')
<p class="muted"><a href="{{ route('casuals.show', $person) }}">{{ $person->preferred_name }}</a></p>
<h1>Forms</h1>
<p class="muted">These write onto the person. Accreditation, the WWCC check, the signed engagement, and day-one acknowledgements are what the light reads.</p>
<div class="card"><h2>Expression of interest</h2><p>Already on the person: {{ $person->preferred_name }}, {{ $person->email }}, {{ $person->neighbourhood }}.</p></div>
<div class="card"><h2>Personal and emergency</h2><p class="muted">{{ $person->emergency_contact_name ? 'Emergency contact is on file.' : 'Still needed for the file.' }}</p><a class="button" href="{{ route('casuals.forms.personal', $person) }}">Open</a></div>
<div class="card"><h2>Accreditation</h2><p class="muted">{{ $person->accreditation->level ?? 'No level yet' }} · {{ $person->accreditation->status ?? 'unchecked' }} · alignment {{ $person->accreditation->alignment ?? 'pending' }}</p><a class="button" href="{{ route('casuals.forms.accreditation', $person) }}">Open</a></div>
<div class="card"><h2>WWCC</h2><p class="muted">Outcome {{ $person->wwcc->outcome ?? 'pending' }}. An APP number is rejected.</p><a class="button" href="{{ route('casuals.forms.wwcc', $person) }}">Open</a></div>
<div class="card"><h2>Availability</h2><p class="muted">{{ $person->earliest_start ? 'Earliest start '.$person->earliest_start : 'Days not recorded yet.' }}</p><a class="button" href="{{ route('casuals.forms.availability', $person) }}">Open</a></div>
<div class="card"><h2>Day-one acknowledgements</h2><a class="button" href="{{ route('casuals.pack', $person) }}">Open the pack</a></div>
@endsection
