@extends('layouts.app')
@section('content')
<p class="muted"><a class="link" href="{{ route('setup') }}">Setup</a></p>
<h1>Roles</h1>
<p class="lede">Add a role or rename one, then choose what that person can open. The casual role stays an invite. You cannot remove a role while someone still has it.</p>
@foreach($roles as $role)
    @php $count = (int) ($counts[$role->slug] ?? 0); @endphp
    <article class="person">
        <div><span class="status">{{ \App\Hr\Roles::kindLabel($role->kind) }}</span></div>
        <div>
            <h2>{{ $role->name }}</h2>
            <p class="meta">{{ $count }} {{ $count === 1 ? 'person' : 'people' }}</p>
            <p>{{ $role->kind === 'casual' ? 'A casual joins from an invite on their record.' : \App\Hr\Roles::KINDS[$role->kind] }}</p>
            <div class="actions"><a class="button ghost" href="{{ route('setup.roles.edit', $role) }}">Edit</a></div>
        </div>
    </article>
@endforeach
<div class="card">
    <h2>Add a role</h2>
    <form method="post" action="{{ route('setup.roles.store') }}">
        @csrf
        <label for="name">Name</label>
        <input id="name" name="name" value="{{ old('name') }}" required maxlength="40" placeholder="Learning support">
        <label for="kind">What this role can do</label>
        <select id="kind" name="kind" required>
            @foreach(\App\Hr\Roles::KINDS as $value => $help)
                <option value="{{ $value }}" @selected(old('kind') === $value)>{{ \App\Hr\Roles::kindLabel($value) }}</option>
            @endforeach
        </select>
        <p class="muted">Manage the school opens Setup. Lead a neighbourhood sees that neighbourhood only. Support on site sees the casuals they mentor. Front desk sees every casual.</p>
        <p><button type="submit">Add role</button></p>
    </form>
</div>
@endsection
