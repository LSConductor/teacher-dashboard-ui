@extends('layouts.app')
@section('content')
<p class="muted"><a class="link" href="{{ route('setup.roles') }}">Roles</a></p>
<h1>{{ $role->name }}</h1>
<p class="lede">{{ $people }} {{ $people === 1 ? 'person has' : 'people have' }} this role. Renaming it does not change who can sign in.</p>
<form method="post" action="{{ route('setup.roles.update', $role) }}" class="card">
    @csrf @method('put')
    <label for="name">Name</label>
    <input id="name" name="name" value="{{ old('name', $role->name) }}" required maxlength="40">
    @if($role->kind === 'casual')
        <p class="muted">This role is the casual teacher. What it can do stays as it is. A casual still joins from an invite.</p>
    @else
        <label for="kind">What this role can do</label>
        <select id="kind" name="kind" required>
            @foreach(\App\Hr\Roles::KINDS as $value => $help)
                <option value="{{ $value }}" @selected(old('kind', $role->kind) === $value)>{{ \App\Hr\Roles::kindLabel($value) }}</option>
            @endforeach
        </select>
        <p class="muted">Manage the school opens Setup. Lead a neighbourhood sees that neighbourhood only. Support on site sees the casuals they mentor. Front desk sees every casual.</p>
        @if($role->slug === 'conductor')
            <p class="muted">Approving a casual’s alignment stays with this Conductor role.</p>
        @endif
    @endif
    <p><button type="submit">Save role</button></p>
</form>
@if($role->kind !== 'casual')
    <form method="post" action="{{ route('setup.roles.destroy', $role) }}">
        @csrf @method('delete')
        <p><button class="ghost" type="submit">Remove role</button></p>
        <p class="muted">You can remove it when nobody has it.</p>
    </form>
@endif
@endsection