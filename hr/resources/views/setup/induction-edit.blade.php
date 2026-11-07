@extends('layouts.app')
@section('content')
<p class="muted"><a class="link" href="{{ route('setup.induction') }}">Quests</a></p>
<h1>{{ $module->title }}</h1>
<p class="lede">Version {{ $module->version }}. If someone has already acknowledged this version, saving the new wording starts the next version.</p>
<form method="post" action="{{ route('setup.induction.update', $module) }}" class="card">
    @csrf @method('put')
    <label for="title">Title</label>
    <input id="title" name="title" value="{{ old('title', $module->title) }}" required>
    <label for="body">Page</label>
    <textarea id="body" name="body" rows="14" required>{{ old('body', $module->body) }}</textarea>
    <p><button type="submit">Save quest</button></p>
</form>
@endsection
