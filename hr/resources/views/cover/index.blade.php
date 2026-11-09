@extends('layouts.app')
@section('content')
@php
    $when = \Carbon\Carbon::parse($on)->timezone('Australia/Sydney')->format('l j F Y');
    $ready = count($board['ready']);
@endphp
<div class="dayboard">
    <article class="dayhero">
        <p class="kicker">Cover</p>
        <h1>Who can take a class</h1>
        <p class="lede">{{ $when }}. Tick the people who fit, in the order to ask them. The first yes is the booking. Red is left off this list.</p>
        <p class="meta">{{ $ready }} can be asked.
            @if(count($board['aside'])) {{ count($board['aside']) }} {{ count($board['aside']) === 1 ? 'is' : 'are' }} not free.
            @endif
            @if($board['red']) {{ $board['red'] }} {{ $board['red'] === 1 ? 'is' : 'are' }} red and not shown.
            @endif
        </p>
    </article>

    @if($filled->isNotEmpty())
        <article class="card edge-green">
            <h2>Filled</h2>
            @foreach($filled as $cover)
                <p>{{ $cover->when() }} · {{ $cover->class_label }}
                    · {{ $cover->asks->first(fn ($ask) => $ask->status === 'accepted')?->person->preferred_name }}
                </p>
            @endforeach
        </article>
    @endif

    @forelse($open as $cover)
        <article class="card edge-amber">
            <h2>{{ $cover->class_label }} · {{ $cover->when() }}</h2>
            <p class="meta">
                @if($cover->teacher_name) With {{ $cover->teacher_name }}. @endif
                @if($cover->neighbourhood) {{ $cover->neighbourhood }}. @endif
                {{ $cover->mode === 'all' ? 'Asked together. The first yes is booked.' : 'Asked one at a time.' }}
            </p>
            @foreach($cover->asks as $ask)
                <p>
                    <strong>{{ $ask->person->preferred_name }}</strong>
                    ·
                    @switch($ask->status)
                        @case('sent') Asked, waiting @break
                        @case('queued') Next @break
                        @case('accepted') Yes, booked @break
                        @case('declined') Said no @break
                        @case('passed') Moved on @break
                        @default Closed
                    @endswitch
                    @if($ask->mail_error) · {{ $ask->mail_error }} @endif
                </p>
                @if($ask->status === 'sent')
                    <form method="post" action="{{ route('cover.resend', $ask) }}">
                        @csrf
                        <p><button class="ghost" type="submit">Send the link again</button></p>
                    </form>
                @endif
            @endforeach
            <div class="actions">
                @if($cover->mode === 'ordered' && $cover->asks->contains(fn ($ask) => $ask->status === 'queued'))
                    <form method="post" action="{{ route('cover.advance', $cover) }}">
                        @csrf
                        <button type="submit">Ask the next person</button>
                    </form>
                @endif
                <form method="post" action="{{ route('cover.close', $cover) }}">
                    @csrf
                    <button class="ghost" type="submit">Close this ask</button>
                </form>
            </div>
        </article>
    @empty
    @endforelse

    <form method="post" action="{{ route('cover.store') }}" class="card">
        <h2>The class</h2>
        @csrf
        <div class="grid">
            <div>
                <label for="on">Day</label>
                <input id="on" type="date" name="on" value="{{ $on }}" required>
            </div>
            <div>
                <label for="class_label">Class</label>
                <input id="class_label" name="class_label" value="{{ $classLabel }}" required placeholder="S3">
            </div>
            <div>
                <label for="teacher_name">Teacher who is away</label>
                <input id="teacher_name" name="teacher_name" value="{{ $teacherName }}" placeholder="The class teacher">
            </div>
            @if($lockedNeighbourhood)
                <div>
                    <label for="neighbourhood">Learning neighbourhood</label>
                    <input id="neighbourhood" value="{{ $neighbourhood }}" readonly>
                    <input type="hidden" name="neighbourhood" value="{{ $neighbourhood }}">
                </div>
            @else
                <div>
                    <label for="neighbourhood">Learning neighbourhood</label>
                    <select id="neighbourhood" name="neighbourhood">
                        <option value="">Choose for this class</option>
                        @foreach($neighbourhoods as $name)
                            <option value="{{ $name }}" @selected($neighbourhood === $name)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <label for="start_time">Start</label>
                <input id="start_time" name="start_time" value="{{ $startTime }}">
            </div>
            <div>
                <label for="finish_time">Finish</label>
                <input id="finish_time" name="finish_time" value="{{ $finishTime }}">
            </div>
            <div>
                <label for="plan_url">Plan link</label>
                <input id="plan_url" name="plan_url" type="url" value="{{ $planUrl }}" placeholder="https://portal.living.school/...">
            </div>
        </div>
        <p><button class="ghost" type="submit" formmethod="get" formaction="{{ route('cover') }}">Show this day</button></p>

        <h2>Ask these people</h2>
        @if($ready === 0)
            <p>Nobody on this list is free for {{ $board['day'] }}.</p>
        @endif
        @foreach($board['ready'] as $row)
            <div class="pick">
                <input id="person_{{ $row['person']->id }}" type="checkbox" name="person_ids[]" value="{{ $row['person']->id }}">
                <div>
                    <label for="order_{{ $row['person']->id }}">Order</label>
                    <input id="order_{{ $row['person']->id }}" name="order[{{ $row['person']->id }}]" value="{{ $row['order'] }}" inputmode="numeric">
                </div>
                <div>
                    <label for="person_{{ $row['person']->id }}">{{ $row['person']->preferred_name }}</label>
                    <p class="meta">{{ $row['line'] }}
                        @if($row['phone'])
                            <a class="link" href="tel:{{ preg_replace('/\s+/', '', $row['phone']) }}">Call {{ $row['phone'] }}</a>
                        @endif
                    </p>
                </div>
            </div>
        @endforeach

        @if($isConductor && count($board['held']))
            <h2>Amber, with your note</h2>
            <label for="conductor_note">Conductor note</label>
            <textarea id="conductor_note" name="conductor_note" placeholder="Why this amber casual can be asked for this day"></textarea>
            @foreach($board['held'] as $row)
                <div class="pick">
                    <input id="person_{{ $row['person']->id }}" type="checkbox" name="person_ids[]" value="{{ $row['person']->id }}">
                    <div>
                        <label for="order_{{ $row['person']->id }}">Order</label>
                        <input id="order_{{ $row['person']->id }}" name="order[{{ $row['person']->id }}]" value="{{ $ready + $loop->iteration }}" inputmode="numeric">
                    </div>
                    <div>
                        <label for="person_{{ $row['person']->id }}">{{ $row['person']->preferred_name }}</label>
                        <p class="meta">{{ $row['line'] }}</p>
                    </div>
                </div>
            @endforeach
        @elseif(count($board['held']))
            <h2>Needs the Conductor</h2>
            @foreach($board['held'] as $row)
                <p><strong>{{ $row['person']->preferred_name }}</strong> <span class="meta">{{ $row['line'] }}</span></p>
            @endforeach
        @endif

        <fieldset class="entry">
            <legend>How to ask</legend>
            <label><input style="width:auto" type="radio" name="mode" value="ordered" @checked($mode !== 'all')> One at a time, in this order</label>
            <label><input style="width:auto" type="radio" name="mode" value="all" @checked($mode === 'all')> Everyone now. The first yes is booked.</label>
        </fieldset>
        <p><button type="submit">Ask for cover</button></p>
        <p class="meta">Casual teachers only. Cover from staff inside the school comes next.</p>
    </form>

    @if(count($board['aside']))
        <details class="done-list">
            <summary>Not free this day</summary>
            @foreach($board['aside'] as $row)
                <p><strong>{{ $row['person']->preferred_name }}</strong> <span class="meta">{{ $row['line'] }}</span></p>
            @endforeach
        </details>
    @endif

</div>
@endsection
