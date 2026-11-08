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
    $openSteps = collect($steps)->reject(fn ($step) => $step['done'])->values();
    $doneSteps = collect($steps)->filter(fn ($step) => $step['done'])->values();
    $waitingToSign = $today && str_starts_with((string) $rollLabel, 'Not signed');
@endphp
<section id="start" class="dayboard">
    @foreach($coverAsks ?? [] as $ask)
        <article class="card edge-amber">
            <h2>{{ $ask->cover->class_label }} on {{ $ask->cover->when() }}</h2>
            <p>
                @if($ask->cover->teacher_name) With {{ $ask->cover->teacher_name }}. @endif
                @if($ask->cover->start_time && $ask->cover->finish_time) {{ $ask->cover->start_time }} to {{ $ask->cover->finish_time }}. @endif
                Can you teach this class?
            </p>
            <div class="actions">
                <form method="post" action="{{ route('cover.mine.accept', $ask) }}">
                    @csrf
                    <button type="submit">Accept</button>
                </form>
                <form method="post" action="{{ route('cover.mine.decline', $ask) }}">
                    @csrf
                    <button class="ghost" type="submit">Decline</button>
                </form>
            </div>
        </article>
    @endforeach
    <article class="dayhero">
        @include('partials.place', ['placeName' => $person->neighbourhood])
        <p class="kicker">{{ now()->timezone('Australia/Sydney')->format('l j F') }}</p>
        @if($today)
            <h1>{{ $today->class_label }}</h1>
            <p class="lede">
                @if($today->teacher_name)With {{ $today->teacher_name }}. @endif
                {{ $rollLabel }}.
            </p>
            @if($waitingToSign)
                <p><a class="button" href="{{ route('today') }}">Sign on for today</a></p>
            @else
                <p><a class="button ghost" href="{{ route('today') }}">Open today</a></p>
            @endif
        @else
            <h1>{{ $person->preferred_name }}</h1>
            <p class="lede">You are not booked today.</p>
        @endif
        <p><span class="status {{ $gate['light'] }}">{{ $labels[$gate['light']] }}</span></p>
        @if($gate['reasons'])
            <p>{{ $gate['reasons'][0]['text'] }}</p>
        @endif
        <div class="quiet-links">
            @if($director?->mobile)
                <a href="tel:{{ preg_replace('/\s+/', '', $director->mobile) }}">Call {{ $director->name }}</a>
            @endif
            <a href="tel:0256321218">Call the office</a>
            @if($today?->plan_url)
                <a href="{{ $today->plan_url }}" target="_blank" rel="noopener">Open the plan</a>
            @endif
            @if($today && $incidentDesk)
                <a href="{{ $incidentDesk }}" target="_blank" rel="noopener">Open the incident desk</a>
            @endif
            <a href="{{ route('people') }}">People on site</a>
            <a href="{{ route('casuals.pack', $person) }}">Day-one {{ $dayDone }} of {{ $dayTotal }}</a>
        </div>
        <p class="meta">
            Office (02) 5632 1218. School day 9:00–3:15.
            @if($director)
                Your director is {{ $director->name }}@if($director->mobile), {{ $director->mobile }}@endif.
            @else
                Your director is not named yet. Call the office.
            @endif
            @if($person->mentor) Mentor {{ $person->mentor->name }}. @endif
        </p>
    </article>
    @if($openSteps->isNotEmpty())
        <h2>Still to do</h2>
        @foreach($openSteps as $step)
            <article class="person red">
                <div><span class="status amber">To do</span></div>
                <div>
                    <h2>{{ $step['title'] }}</h2>
                    <p class="meta">{{ $step['detail'] }}</p>
                    <div class="actions"><a class="button" href="{{ $step['href'] }}">{{ $step['action'] }}</a></div>
                </div>
            </article>
        @endforeach
    @endif
    @if($doneSteps->isNotEmpty())
        <details class="done-list">
            <summary>Already done</summary>
            @foreach($doneSteps as $step)
                <article class="person green">
                    <div><span class="status green">Done</span></div>
                    <div>
                        <h2>{{ $step['title'] }}</h2>
                        <p class="meta">{{ $step['detail'] }}</p>
                        <div class="actions"><a class="button ghost" href="{{ $step['href'] }}">{{ $step['action'] }}</a></div>
                    </div>
                </article>
            @endforeach
        </details>
    @endif
</section>
@endif
@endsection
