@extends('layouts.app')
@section('content')
@php
    $roleLabel = fn ($role) => $role === 'hr' ? 'HR' : ucfirst($role);
@endphp
@if($managing)
    <p class="muted"><a class="link" href="{{ route('setup.staff') }}">Edit staff cards</a></p>
@endif
<h1>People</h1>
@if($person)
    <p class="lede">These are the people to find when you arrive. The teacher is the class you are with. Your director and the office are the calls to make.</p>
@elseif($managing)
    <p class="lede">A casual opens this page on the day they attend. Their neighbourhood director, the office, and their mentor always show. Open a staff card and tick “Show on the people page” to add anyone else, with the days they are on site. Cards edited on the WordPress site stay there until you write them here.</p>
@else
    <p class="lede">These are the people a casual meets on site: the neighbourhood director, the office, and the mentors.</p>
@endif

@forelse($visits as $visit)
    <article class="person green">
        <div><span class="status green">{{ $visit->booked_on->isSameDay(now()) ? 'Today' : $visit->booked_on->timezone('Australia/Sydney')->format('D j M') }}</span></div>
        <div>
            <h2>{{ $visit->teacher_name ?: 'Teacher not named yet' }}</h2>
            <p class="meta">{{ $visit->class_label }} · the class you are with</p>
            @if($visit->plan_url)
                <div class="actions"><a class="button" href="{{ $visit->plan_url }}" target="_blank" rel="noopener">Open the plan</a></div>
            @endif
        </div>
    </article>
@empty
    @if($person)
    <p class="lede">You have no class booked. The people below are still who to find when you arrive.</p>
    @endif
@endforelse

@forelse($cards as $member)
    <article class="person {{ $member->role === 'director' ? 'green' : '' }}">
        <div>
            <span class="status {{ $member->role === 'director' ? 'green' : ($member->role === 'office' ? 'amber' : '') }}">{{ \App\Hr\PeopleCards::title($member) }}</span>
        </div>
        <div>
            <h2>{{ $member->name }}</h2>
            <p class="meta">
                {{ $roleLabel($member->role) }}
                @if($member->neighbourhood) · {{ $member->neighbourhood }}@endif
                @if($member->days_on_site) · {{ $member->days_on_site }}@endif
                @if($member->mobile) · {{ $member->mobile }}@endif
            </p>
            <p>{{ \App\Hr\PeopleCards::note($member) }}</p>
            <div class="actions">
                @if($member->mobile)
                    <a class="button" href="tel:{{ preg_replace('/\s+/', '', $member->mobile) }}">Call {{ $member->name }}</a>
                @elseif($member->role === 'office')
                    <a class="button" href="tel:0256321218">Call the office</a>
                @endif
                @if($member->email)
                    <a class="button ghost" href="mailto:{{ $member->email }}">Email</a>
                @endif
                @if($managing)
                    <a class="button ghost" href="{{ route('setup.staff.edit', $member) }}">Edit card</a>
                @endif
            </div>
        </div>
    </article>
@empty
    <p class="lede">No people are on this page yet.</p>
@endforelse

@if($managing && $hidden->isNotEmpty())
    <h2>Not on the people page</h2>
    <p class="lede">These accounts can sign in. A casual does not see them until you edit the card and tick the box.</p>
    @foreach($hidden as $member)
        <article class="person">
            <div><span class="status">{{ $roleLabel($member->role) }}</span></div>
            <div>
                <h2>{{ $member->name }}</h2>
                <p class="meta">{{ $member->email }}</p>
                <div class="actions"><a class="button ghost" href="{{ route('setup.staff.edit', $member) }}">Edit card</a></div>
            </div>
        </article>
    @endforeach
@endif
@endsection
