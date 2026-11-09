@extends('layouts.app')
@section('content')
<p class="muted"><a class="link" href="{{ route('setup') }}">Setup</a></p>
<h1>Parents as partners</h1>
<p class="lede">Edit a page or the check. If someone has already finished that version, saving new wording starts the next version.</p>
@foreach($pages as $page)
    <article class="person">
        <div><span class="status">{{ $page->sort }}</span></div>
        <div>
            <h2>{{ $page->title }}</h2>
            <p class="meta">Version {{ $page->version }}</p>
            <div class="actions"><a class="button" href="{{ route('setup.partners.edit', $page) }}">Edit</a></div>
        </div>
    </article>
@endforeach
<p><a class="button" href="{{ route('setup.partners.check') }}">Edit the check</a></p>
@endsection
