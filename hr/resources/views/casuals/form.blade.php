@extends('layouts.app')
@section('content')
<h1>Expression of interest</h1>
<form method="post" action="{{ route('casuals.store') }}" class="card">
    @csrf
    @include('casuals.fields')
    <p><button type="submit">Save</button></p>
</form>
@endsection
