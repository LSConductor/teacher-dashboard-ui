@extends('layouts.app', ['title' => 'Induction', 'intro' => 'Only your shell'])

@section('content')
    <section class="card">
        <h2>{{ $person->preferred_name }}</h2>
        <p>This invite opened only your induction shell. It cannot open the staff list, another casual’s file, or a WWCC record.</p>
        <p>Day-one modules are not in this phase. HR records when day-one induction is complete.</p>
        <form method="post" action="{{ route('induction.leave') }}">
            @csrf
            <button class="secondary" type="submit">Leave this shell</button>
        </form>
    </section>
@endsection
