@extends('layouts.app')
@section('content')
<p class="muted"><a class="link" href="{{ route('templates.index') }}">Contracts</a></p>
<h1>{{ $template->name }}</h1>
<p class="lede">How a sent copy looks. The names below are a sample.</p>
@include('documents.sheet', [
    'title' => $template->name,
    'meta' => 'Version '.$template->version.' · '.$template->status,
    'body' => $preview,
])
<form method="post" action="{{ route('templates.update', $template) }}" class="card">
    @csrf @method('put')
    <h2>Wording</h2>
    @if($template->status !== 'draft')
        <p class="muted">This version is published. A sent copy keeps it. Open the draft to change the wording.</p>
    @endif
    <label for="name">Name</label>
    <input id="name" name="name" value="{{ $template->name }}" @disabled($template->status !== 'draft')>
    <label for="body">Body</label>
    <textarea id="body" name="body" rows="14" @disabled($template->status !== 'draft')>{{ $template->body }}</textarea>
    <p class="muted">Merge fields: @{{preferred_name}}, @{{legal_name}}, @{{email}}, @{{mobile}}, @{{neighbourhood}}, @{{nesa_number}}, @{{director_name}}, @{{director_mobile}}, @{{today}}, @{{booked_on}}, @{{class_label}}, @{{start_time}}, @{{finish_time}}, @{{office_phone}}. WWCC number and date of birth are not merge fields.</p>
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
