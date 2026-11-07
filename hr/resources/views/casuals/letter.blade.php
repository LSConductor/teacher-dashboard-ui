@extends('layouts.app')
@section('content')
<p class="muted"><a class="link" href="{{ route('casuals.show', $person) }}">{{ $person->preferred_name }}</a></p>
<article class="card">
    <p class="muted">Living School · {{ $end->timezone('Australia/Sydney')->format('j F Y') }}</p>
    <h1>Casual teacher status</h1>
    <p>{{ $person->legal_name }} worked as a casual teacher at Living School. This letter is evidence for NESA that the work was casual, so the maintenance period can be seven years rather than five. It is not a payslip and it is not an accreditation decision.</p>
    <p>NESA number {{ $person->accreditation->nesa_number ?? 'not recorded' }}. Neighbourhood {{ $person->neighbourhood ?? 'not recorded' }}.</p>
    <h2>Days worked from {{ $start->format('j F Y') }} to {{ $end->format('j F Y') }}</h2>
    <p>These days are the bookings already stored on the file.</p>
    @forelse($days as $day)
        <p>{{ $day->booked_on->timezone('Australia/Sydney')->format('j F Y') }} · {{ $day->class_label }}@if($day->teacher_name) · with {{ $day->teacher_name }}@endif</p>
    @empty
        <p>No days are recorded in this seven-year window.</p>
    @endforelse
    <p>{{ $days->count() }} {{ $days->count() === 1 ? 'day' : 'days' }} in this record.</p>
    <p>John Stewart, Conductor, Living School. Office (02) 5632 1218.</p>
</article>
@endsection
