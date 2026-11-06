<article class="card">
    <h3 style="font-family: Georgia, serif; margin: 0 0 0.35rem;">{{ $card->name }}</h3>
    <p><span class="light {{ $card->light }}">{{ $card->word() }}</span></p>
    @if ($card->reasons)
        <ul class="reasons">
            @foreach ($card->reasons as $reason)
                <li>{{ $reason }}</li>
            @endforeach
        </ul>
    @endif
    <p>{{ $card->mobileDisplay() }}<br>{{ $card->email }}</p>
    <p>Stages: {{ $card->stages ? implode(', ', $card->stages) : 'None recorded' }}</p>
    <p>Today: {{ $card->todaysBooking ?? 'No booking today' }}</p>
    <p>Mentor: {{ $card->mentorName ?? 'No mentor' }}</p>
    <div class="actions">
        <a class="button" href="tel:{{ $card->tel() }}">Call</a>
        <a class="button secondary" href="mailto:{{ $card->email }}">Email</a>
    </div>

    @if ($card->light === 'red')
        <p>Red cannot be booked.</p>
    @elseif ($card->todaysBooking)
        <p>Already booked today.</p>
        @if ($card->todayBookingId)
            <form method="post" action="{{ route('bookings.cancel', $card->todayBookingId) }}">
                @csrf
                <button class="secondary" type="submit">Cancel booking</button>
            </form>
        @endif
    @elseif ($card->light === 'green')
        <form method="post" action="{{ route('bookings.store', $card->personId) }}">
            @csrf
            <label for="class-{{ $card->personId }}">Class or group</label>
            <input id="class-{{ $card->personId }}" name="class_or_group" required maxlength="120">
            <label for="date-{{ $card->personId }}">Date</label>
            <input id="date-{{ $card->personId }}" type="date" name="on_date" value="{{ $today }}" required>
            <label for="program-{{ $card->personId }}">WordPress program id</label>
            <input id="program-{{ $card->personId }}" name="wordpress_program_id" inputmode="numeric" pattern="[0-9]*" placeholder="Optional portal program id">
            <p class="meta">The id is the WordPress program from my programs. Leave it blank if you do not have it. Laravel does not open the program builder.</p>
            <button type="submit">Book this casual</button>
        </form>
    @elseif ($card->holdId && $card->holdHasConductorNote)
        <p>Conductor note: {{ $card->conductorNote }}</p>
        <form method="post" action="{{ route('bookings.confirm', $card->holdId) }}">
            @csrf
            <button type="submit">Confirm booking</button>
        </form>
    @elseif ($card->holdId)
        <p>Waiting for a Conductor note on this booking.</p>
    @else
        <form method="post" action="{{ route('bookings.store', $card->personId) }}">
            @csrf
            <label for="class-{{ $card->personId }}">Class or group</label>
            <input id="class-{{ $card->personId }}" name="class_or_group" required maxlength="120">
            <label for="date-{{ $card->personId }}">Date</label>
            <input id="date-{{ $card->personId }}" type="date" name="on_date" value="{{ $today }}" required>
            <label for="program-{{ $card->personId }}">WordPress program id</label>
            <input id="program-{{ $card->personId }}" name="wordpress_program_id" inputmode="numeric" pattern="[0-9]*">
            <button type="submit">Request cover</button>
        </form>
        <p class="meta">Requesting cover holds the day. It does not book an amber casual.</p>
    @endif
</article>
