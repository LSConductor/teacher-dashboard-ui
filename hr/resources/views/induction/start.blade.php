@extends('layouts.app')
@section('content')
@if($mode === 'gate')
<section id="start" class="card" style="max-width:560px; margin:28px auto;">
    <p class="muted" style="letter-spacing:.08em; text-transform:uppercase; font-weight:700; color:#14324a;">Casual Induction</p>
    <p>Log in to the staff portal to open this desk. After you sign in you return here — invite codes stay on the link.</p>
    <p>
        <a class="button" href="{{ route('login') }}">Log in to portal</a>
        <a class="button ghost" href="{{ route('apply') }}">Apply to teach</a>
    </p>
    @if($inviteState === 'valid')
        <h2>{{ $invite->person->preferred_name }}</h2>
        <p class="lede">This link is your invite. Set a password once. It opens only your induction, your forms, and your contract.</p>
        <form method="post" action="{{ route('invites.accept', ['token' => $code]) }}">
            @csrf
            <label for="password">Password</label>
            <input id="password" name="password" type="password" required autocomplete="new-password">
            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
            <p><button type="submit">Open my induction</button></p>
        </form>
    @elseif($inviteState === 'used')
        <p class="lede">This invite has already been used. Log in to open your desk.</p>
    @elseif($inviteState === 'expired')
        <p class="lede">This invite has expired. Ask HR for a new link.</p>
    @elseif($inviteState === 'missing')
        <p class="lede">This invite link is not valid.</p>
    @endif
</section>
@elseif($mode === 'wrong')
<section id="start" class="card" style="max-width:560px; margin:28px auto;">
    <p class="muted" style="letter-spacing:.08em; text-transform:uppercase; font-weight:700; color:#14324a;">Casual Induction</p>
    <h1>This invite is not yours</h1>
    <p class="lede">
        @if($personName)
            This link is for {{ $personName }}.
        @else
            This link is for another person.
        @endif
        Sign out, then open it again.
    </p>
</section>
@elseif($mode === 'staff')
<section id="start" class="card">
    <p class="muted" style="letter-spacing:.08em; text-transform:uppercase; font-weight:700; color:#14324a;">Casual Induction</p>
    <h1>Start</h1>
    <p class="lede">This is the desk a casual opens from their invite. After they sign in they return here, and the code stays on the link. Your work is the casual list.</p>
    <p><a class="button" href="{{ route('casuals.index') }}">Casual list</a></p>
</section>
@else
@php
    $labels = ['green' => 'Clear to teach', 'amber' => 'Needs a note', 'red' => 'Do not book'];
@endphp
<section id="start">
    <p class="muted" style="letter-spacing:.08em; text-transform:uppercase; font-weight:700; color:#14324a;">Casual Induction</p>
    <h1>{{ $person->preferred_name }}</h1>
    <p><span class="status {{ $gate['light'] }}">{{ $labels[$gate['light']] }}</span></p>
    <p class="lede">
        Office (02) 5632 1218. School day 9:00–3:15.
        @if($director)
            Director {{ $director->name }}@if($director->mobile), {{ $director->mobile }}@endif.
        @else
            Your director is not named yet. Call the office.
        @endif
        @if($person->mentor) Mentor {{ $person->mentor->name }}. @endif
    </p>
    <div class="actions" style="margin-bottom:18px;">
        @if($director?->mobile)
            <a class="button" href="tel:{{ preg_replace('/\s+/', '', $director->mobile) }}">Call {{ $director->name }}</a>
        @endif
        <a class="button ghost" href="tel:0256321218">Call the office</a>
        @if($today?->plan_url)
            <a class="button ghost" href="{{ $today->plan_url }}" target="_blank" rel="noopener">Open the plan</a>
        @endif
        @if($today)
            <a class="button" href="{{ route('today') }}">Sign on for today</a>
        @endif
        @if($today && $incidentDesk)
            <a class="button ghost" href="{{ $incidentDesk }}" target="_blank" rel="noopener">Open the incident desk</a>
        @endif
        <a class="button ghost" href="{{ route('people') }}">People on site</a>
        <a class="button" href="{{ route('casuals.pack', $person) }}" style="background: {{ $dayDone >= $dayTotal && $dayTotal > 0 ? '#14663d' : '#8a4b00' }}">Day-one {{ $dayDone }} of {{ $dayTotal }}</a>
    </div>
    @if($today)
        <p>{{ $rollLabel }}. {{ $today->class_label }}.</p>
    @endif
    @if($gate['reasons'])
        <p class="lede">{{ $gate['reasons'][0]['text'] }}</p>
    @else
        <p class="lede">You are clear to teach.</p>
    @endif
    @foreach($steps as $step)
        <article class="person {{ $step['done'] ? 'green' : 'red' }}">
            <div>
                <span class="status {{ $step['done'] ? 'green' : 'amber' }}">{{ $step['done'] ? 'Done' : 'To do' }}</span>
            </div>
            <div>
                <h2>{{ $step['title'] }}</h2>
                <p class="meta">{{ $step['detail'] }}</p>
                <div class="actions">
                    <a class="button" href="{{ $step['href'] }}">{{ $step['action'] }}</a>
                </div>
            </div>
        </article>
    @endforeach
</section>
@endif
@endsection
