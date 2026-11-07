@extends('layouts.app')
@section('content')
<p class="muted"><a class="link" href="{{ route('setup.partners') }}">Parents as partners</a></p>
<h1>{{ $page->title }}</h1>
<p class="lede">Version {{ $page->version }}. A video link can stay blank. The writing on the page is the record.</p>
<form method="post" action="{{ route('setup.partners.update', $page) }}" class="card">
    @csrf @method('put')
    <label for="title">Title</label>
    <input id="title" name="title" value="{{ old('title', $page->title) }}" required>
    <label for="body">Page</label>
    <textarea id="body" name="body" rows="14" required>{{ old('body', $page->body) }}</textarea>
    <label for="video_url">Video link</label>
    <input id="video_url" name="video_url" type="url" value="{{ old('video_url', $page->video_url) }}" placeholder="https://">
    <p class="muted">Paste the full link for the introduction, starting with https://. Leave it blank until the video exists.</p>
    <p><button type="submit">Save page</button></p>
</form>
@endsection
