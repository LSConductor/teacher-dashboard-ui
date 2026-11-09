@extends('layouts.app')
@section('content')
@php
    $roleLabel = fn ($member) => \App\Hr\Roles::labelFor($member);
@endphp
@if($managing)
    <p class="muted"><a class="link" href="{{ route('setup.staff') }}">Edit staff cards</a></p>
@endif
<h1>People</h1>
@if($person)
    @include('partials.place', ['placeName' => $person->neighbourhood])
    <p class="lede">These are the people to find when you arrive. The teacher is the class you are with. Call or email for school business while you are on site. If a child is in immediate danger, call 000, then your director. Do not pass these numbers or photos on.</p>
@elseif($managing)
    <p class="lede">A casual opens this page on the day they attend. Drag a card to the level you want, then save. That order is what a casual sees. Use a work mobile and a work email. A photo is optional. Home addresses, personal email, and WWCC numbers stay off this page.</p>
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

@if($managing && $cards->isNotEmpty())
    <form id="people-order" method="post" action="{{ route('people.order') }}">
        @csrf
        <p><button type="submit">Save order</button></p>
        <div id="people-sortable">
@endif
@forelse($cards as $member)
    <article class="person {{ $member->role === 'director' ? 'green' : '' }}" @if($managing) draggable="true" @endif>
        <div>
            @if($managing)
                <input type="hidden" name="order[]" value="{{ $member->id }}">
            @endif
            @if($member->photo_path)
                <img class="portrait" src="{{ route('people.photo', $member, false) }}" alt="Photo of {{ $member->name }}">
            @endif
            <span class="status {{ $member->role === 'director' ? 'green' : ($member->role === 'office' ? 'amber' : '') }}">{{ \App\Hr\PeopleCards::title($member) }}</span>
        </div>
        <div>
            <h2>{{ $member->name }}</h2>
            <p class="meta">
                {{ $roleLabel($member) }}
                @if($member->neighbourhood) · {{ $member->neighbourhood }}@endif
                @if($member->days_on_site) · {{ $member->days_on_site }}@endif
            </p>
            <p class="meta">
                @if($member->mobile)Work mobile {{ $member->mobile }}@endif
                @if($member->mobile && $member->email) · @endif
                @if($member->email)Work email {{ $member->email }}@endif
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
                    <button class="ghost" type="button" data-move="up">Move up</button>
                    <button class="ghost" type="button" data-move="down">Move down</button>
                    <a class="button ghost" href="{{ route('setup.staff.edit', $member) }}">Edit card</a>
                @endif
            </div>
        </div>
    </article>
@empty
    <p class="lede">No people are on this page yet.</p>
@endforelse
@if($managing && $cards->isNotEmpty())
        </div>
    </form>
    <script>
        const list = document.getElementById('people-sortable');
        let dragged = null;
        list.addEventListener('dragstart', (event) => {
            dragged = event.target.closest('article');
            if (!dragged) return;
            dragged.classList.add('dragging');
            event.dataTransfer.effectAllowed = 'move';
        });
        list.addEventListener('dragover', (event) => {
            event.preventDefault();
            const card = event.target.closest('article');
            if (!dragged || !card || card === dragged) return;
            const midpoint = card.getBoundingClientRect().top + card.getBoundingClientRect().height / 2;
            list.insertBefore(dragged, event.clientY > midpoint ? card.nextSibling : card);
        });
        list.addEventListener('dragend', () => {
            if (dragged) dragged.classList.remove('dragging');
            dragged = null;
        });
        list.addEventListener('click', (event) => {
            const button = event.target.closest('[data-move]');
            if (!button) return;
            const card = button.closest('article');
            if (button.dataset.move === 'up' && card.previousElementSibling) {
                list.insertBefore(card, card.previousElementSibling);
            }
            if (button.dataset.move === 'down' && card.nextElementSibling) {
                list.insertBefore(card.nextElementSibling, card);
            }
        });
    </script>
@endif

@if($managing && $hidden->isNotEmpty())
    <h2>Not on the people page</h2>
    <p class="lede">These accounts can sign in. A casual does not see them until you edit the card and tick the box.</p>
    @foreach($hidden as $member)
        <article class="person">
            <div><span class="status">{{ $roleLabel($member) }}</span></div>
            <div>
                <h2>{{ $member->name }}</h2>
                <p class="meta">{{ $member->email }}</p>
                <div class="actions"><a class="button ghost" href="{{ route('setup.staff.edit', $member) }}">Edit card</a></div>
            </div>
        </article>
    @endforeach
@endif
@endsection
