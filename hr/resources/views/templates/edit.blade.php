@extends('layouts.app')
@section('content')
<h1>{{ $template->name }}</h1>
<p class="muted">Version {{ $template->version }} · {{ $template->status }}</p>
<form method="post" action="{{ route('templates.update', $template) }}" class="card">
    @csrf @method('put')
    <label for="name">Name</label>
    <input id="name" name="name" value="{{ $template->name }}" @disabled($template->status !== 'draft')>
    <label for="body">Body</label>
    <textarea id="body" name="body" rows="14" @disabled($template->status !== 'draft')>{{ $template->body }}</textarea>
    <p class="muted">Merge fields: @{{preferred_name}}, @{{legal_name}}, @{{email}}, @{{mobile}}, @{{neighbourhood}}, @{{nesa_number}}, @{{director_name}}, @{{director_mobile}}, @{{today}}. WWCC number and date of birth are not merge fields.</p>
    @if($template->status === 'draft')
        <p><button type="submit">Save draft</button></p>
    @endif
</form>
@if($template->status === 'draft')
<form method="post" action="{{ route('templates.publish', $template) }}">
    @csrf
    <button type="submit">Publish this version</button>
</form>
@endif
@endsection
