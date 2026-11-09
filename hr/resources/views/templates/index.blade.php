@extends('layouts.app')
@section('content')
<p class="muted"><a class="link" href="{{ route('setup') }}">Setup</a></p>
<h1>Contract templates</h1>
<p class="lede">Edit a draft, then publish it. A sent contract keeps the version that was published. Retiring a version hides it from the send list.</p>
@unless($canSort)
    <div class="card error">The library order is waiting on <code>php8.4 artisan migrate --force</code>.</div>
@endunless
<p><a class="button" href="{{ route('templates.complete') }}">Complete a contract</a></p>
@foreach($groups as $key => $versions)
    <article class="person">
        <div><span class="status">{{ $versions->firstWhere('status', 'published')?->status === 'published' ? 'Published' : ucfirst($versions->first()->status) }}</span></div>
        <div>
            <h2>{{ $versions->sortByDesc('version')->first()->name }}</h2>
            @foreach($versions as $template)
                <p class="meta">Version {{ $template->version }} · {{ $template->status }} · <a class="link" href="{{ route('templates.edit', $template) }}">Open</a></p>
            @endforeach
            <div class="actions">
                @if($canSort)
                    <form method="post" action="{{ route('templates.move', $versions->first()) }}">@csrf<button class="ghost" name="direction" value="up" type="submit">Move up</button></form>
                    <form method="post" action="{{ route('templates.move', $versions->first()) }}">@csrf<button class="ghost" name="direction" value="down" type="submit">Move down</button></form>
                @endif
                @foreach($versions as $template)
                    @if($template->status !== 'archived')
                        <form method="post" action="{{ route('templates.archive', $template) }}">@csrf<button class="ghost" type="submit">Retire version {{ $template->version }}</button></form>
                    @endif
                @endforeach
            </div>
        </div>
    </article>
@endforeach
<div class="card">
    <h2>New contract</h2>
    <form method="post" action="{{ route('templates.store') }}">
        @csrf
        <label for="name">Name</label>
        <input id="name" name="name" required>
        <label for="body">Body</label>
        <textarea id="body" name="body" rows="8" required></textarea>
        <p class="muted">Merge fields: @{{preferred_name}}, @{{legal_name}}, @{{email}}, @{{mobile}}, @{{neighbourhood}}, @{{nesa_number}}, @{{director_name}}, @{{director_mobile}}, @{{today}}. WWCC number and date of birth are not merge fields.</p>
        <p><button type="submit">Create draft</button></p>
    </form>
</div>
<p><a class="link" href="{{ route('export.nesa') }}">Download the NESA check (no WWCC number, no date of birth)</a></p>
@endsection
