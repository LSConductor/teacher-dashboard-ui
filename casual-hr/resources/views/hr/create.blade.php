@extends('layouts.app', ['title' => 'Add a person', 'intro' => 'Fictional or real, this record stays in this school.'])

@section('content')
    <form class="panel" method="post" action="{{ route('hr.people.store') }}">
        @csrf
        @include('hr.partials.person-fields', ['person' => null, 'neighbourhoods' => $neighbourhoods])
        <p><button type="submit">Save person</button></p>
    </form>
@endsection
