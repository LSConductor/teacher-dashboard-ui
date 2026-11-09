@extends('layouts.app')
@section('content')
<p class="muted"><a class="link" href="{{ route('setup') }}">Setup</a></p>
<h1>Induction quests</h1>
<p class="lede">Day-one quests keep the light red until they are acknowledged. Later quests can stay open. Retired quests leave the pack and the light.</p>
@php
    $labels = ['day_one' => 'Day one', 'later' => 'Later', 'retired' => 'Retired'];
@endphp
@foreach($modules as $module)
    <article class="person {{ $module->phase === 'day_one' ? 'red' : ($module->phase === 'later' ? 'amber' : '') }}">
        <div><span class="status {{ $module->phase === 'day_one' ? 'red' : ($module->phase === 'later' ? 'amber' : '') }}">{{ $labels[$module->phase] ?? $module->phase }}</span></div>
        <div>
            <h2>{{ $module->title }}</h2>
            <p class="meta">Version {{ $module->version }}</p>
            <div class="actions">
                <a class="button" href="{{ route('setup.induction.edit', $module) }}">Edit</a>
                <form method="post" action="{{ route('setup.induction.move', $module) }}">@csrf<button class="ghost" name="direction" value="up" type="submit">Move up</button></form>
                <form method="post" action="{{ route('setup.induction.move', $module) }}">@csrf<button class="ghost" name="direction" value="down" type="submit">Move down</button></form>
                @if($module->phase !== 'day_one')
                    <form method="post" action="{{ route('setup.induction.move', $module) }}">@csrf<button class="ghost" name="phase" value="day_one" type="submit">Make day one</button></form>
                @endif
                @if($module->phase !== 'later')
                    <form method="post" action="{{ route('setup.induction.move', $module) }}">@csrf<button class="ghost" name="phase" value="later" type="submit">Make later</button></form>
                @endif
                @if($module->phase !== 'retired')
                    <form method="post" action="{{ route('setup.induction.move', $module) }}">@csrf<button class="ghost" name="phase" value="retired" type="submit">Retire</button></form>
                @endif
            </div>
        </div>
    </article>
@endforeach
<div class="card">
    <h2>Add a quest</h2>
    <form method="post" action="{{ route('setup.induction.store') }}">
        @csrf
        <label for="title">Title</label>
        <input id="title" name="title" value="{{ old('title') }}" required>
        <label for="phase">Where it sits</label>
        <select id="phase" name="phase">
            <option value="day_one" @selected(old('phase') === 'day_one')>Day one, blocks the light</option>
            <option value="later" @selected(old('phase', 'later') === 'later')>Later, does not block day one</option>
        </select>
        <label for="body">Page</label>
        <textarea id="body" name="body" rows="8" required>{{ old('body') }}</textarea>
        <p><button type="submit">Add quest</button></p>
    </form>
</div>
@endsection
