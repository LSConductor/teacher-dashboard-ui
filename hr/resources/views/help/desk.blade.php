@extends('layouts.app')
@section('content')
@if($kind === 'manage')
<div class="guide">
    <p class="record-kicker">For {{ $label }}</p>
    <h1>How this desk works</h1>
    <p class="lede">Three jobs cover the day. The rest of this page can wait until that job is in front of you.</p>

    <article class="guide-job">
        <p class="record-kicker">1</p>
        <h2>A teacher wants to join</h2>
        <p>Put this address on the website. It does not create a login.</p>
        <p><a class="link" href="{{ route('apply') }}">{{ route('apply') }}</a></p>
        <ol>
            <li>They send their name, a way to reach them, the stages they can teach, and their right to work.</li>
            <li>They appear at the top of the List as a new candidate, with what they wrote. They are not bookable yet.</li>
            <li>On Person, press Create invite link. Email that link only to them. It lasts 7 days and works once.</li>
            <li>They set a password and start day-one induction.</li>
            <li>Record the WWCC and the accreditation on Clearances. The Conductor records that eTAMS lines up.</li>
            <li>On Records, send the casual engagement. They sign it. When the light is green, they can be booked.</li>
        </ol>
        <p class="meta">You can correct what they wrote on Person. The questions on the public form stay as they are.</p>
    </article>

    <article class="guide-job">
        <p class="record-kicker">2</p>
        <h2>Someone is teaching today</h2>
        <p>Open Today. The office holds a code that changes at midnight. The casual types it to sign on and to sign off. It is not their password.</p>
        <ol>
            <li>You can record the arrival or the departure from the desk.</li>
            <li>If they left without signing off, close the day with a short note.</li>
            <li>A student incident opens the incident desk. The student’s story is not written in this file.</li>
        </ol>
    </article>

    <article class="guide-job">
        <p class="record-kicker">3</p>
        <h2>A parent or grandparent wants to help</h2>
        <p>Put this address on the parents hub.</p>
        <p><a class="link" href="{{ route('partners.join') }}">{{ route('partners.join') }}</a></p>
        <ol>
            <li>They read six pages, answer a short check, and say what they can offer.</li>
            <li>Open Partners. When someone is waiting, open their name and record the volunteer check.</li>
            <li>An APP number is not a clearance. A director sees ready or not ready, and never the number.</li>
        </ol>
        <p class="meta">To change a page, a video, or the check, open Setup, then Parents as partners. The short join form stays as it is.</p>
    </article>

    <h2 class="guide-later">When you need it</h2>
    <p class="meta">Leave these until the job comes up.</p>

    <article class="guide-job">
        <h2>Open a file, or take someone off the list</h2>
        <p>On the casual list, press Open the file. The name is a link as well.</p>
        <p>To take someone off the list, open the file and press Archive. Nothing is deleted. Choose Archived in the File menu, then Bring back, when they return.</p>
    </article>

    <article class="guide-job">
        <h2>The light is not green</h2>
        <p>Green can be booked from the person’s file, on The day. Amber needs a note from the Conductor on that day. Red is not booked. A WWCC or accreditation problem is not overridden.</p>
        <p>Watch lists what falls due in the next 30 days. Open the file and record the new date. The clearance number is not on that page.</p>
    </article>

    <article class="guide-job">
        <h2>A careful record</h2>
        <p>On Records, the 28-day reply is for the day you told them their practice may not meet the Standards. Watch keeps the name until the answer is saved. They can write the answer on their own sign-in.</p>
        <p>Where the full file is kept is for an allegation, a child-protection matter, or a serious complaint. Write the kind, the day, and the place the full file lives. The story stays in that file. A director does not see this record.</p>
    </article>

    <article class="guide-job">
        <h2>A class needs a teacher</h2>
        <p>Cover lists who is free for that day. Ask them one at a time, or all at once. The first yes is the booking, and the one-day confirmation is filed. Red is not asked. Amber is asked only when the Conductor writes the note on that ask. A person who works across the school appears for every neighbourhood.</p>
    </article>

    <article class="guide-job">
        <h2>A casual says the screen is wrong</h2>
        <p>On their file, press See what they see. You are looking at their day, their sign-on, and their people page. Nothing you press is saved. Back to your desk returns you. If they have no sign-in yet, send the invite first.</p>
    </article>

    <article class="guide-job">
        <h2>Changing the words</h2>
        <p>Setup holds the induction quests, the parent course, the contracts, the staff, and the roles. Edit a contract draft, then publish it. A signed contract stays with the person who signed it. A quest someone has already finished keeps that version.</p>
        <p>The timetable and the class plan stay on portal.living.school. A plan link on the booking is how the casual opens it.</p>
    </article>
</div>
@else
<div class="guide">
    <p class="record-kicker">For {{ $label }}</p>
    <h1>Your part of the desk</h1>
    @if($kind === 'front_desk')
        <p class="lede">You hold the day. HR keeps the clearances, the contracts, and Setup.</p>
        <article class="guide-job">
            <h2>Today</h2>
            <ol>
                <li>Today shows who is booked. The office code is on that page. It changes at midnight.</li>
                <li>Read the code to the casual, or press Record arrival and Record departure yourself.</li>
                <li>If they left without signing off, close the day with a short note.</li>
            </ol>
        </article>
        <article class="guide-job">
            <h2>Booking</h2>
            <p>Cover asks the people who are free, in the order you set. The first yes is the booking. You can still open a green casual and book them from The day. Amber can be booked only when the Conductor has written the note. Red is not booked.</p>
            <p>You can see the light, the phone, and today’s class. A WWCC number stays with HR.</p>
        </article>
    @elseif($kind === 'neighbourhood')
        <p class="lede">You see your neighbourhood. HR keeps the clearances, the contracts, and Setup.</p>
        <article class="guide-job">
            <h2>A class needs a teacher</h2>
            <ol>
                <li>Open Cover. The day starts on the next school day, and you can change it.</li>
                <li>Name the class and the teacher who is away. The list is who is free: your neighbourhood, and anyone who works across the school.</li>
                <li>Tick them in the order to ask. One at a time, the first yes is the booking. Red is not on the list. Amber waits for the Conductor.</li>
                <li>If the first person says no, the next person is asked. You can also ask the next person yourself, or close the ask.</li>
            </ol>
            <p>You can still call a green casual, and book them from their file. A WWCC number stays with HR.</p>
        </article>
        <article class="guide-job">
            <h2>Helpers</h2>
            <p>Partners shows the parents and grandparents HR has cleared in your neighbourhood. You see ready. You do not see the clearance number.</p>
        </article>
    @elseif($kind === 'support')
        <p class="lede">You see the casuals who name you as mentor. HR keeps booking, the clearances, and Setup.</p>
        <article class="guide-job">
            <h2>Your casuals</h2>
            <ol>
                <li>Open a casual from the List.</li>
                <li>Write the goal, a note from the day, an observation, or the short annual page.</li>
                <li>Professional learning hours sit on Learning.</li>
            </ol>
            <p>A WWCC number stays with HR.</p>
        </article>
    @else
        <p class="lede">Your day is the class, the sign-on, and the people on site.</p>
        <article class="guide-job">
            <h2>This morning</h2>
            <ol>
                <li>Today shows the class. Sign on asks the office for today’s code. The code is not your password.</li>
                <li>Sign off with the same code before you leave.</li>
                <li>People shows who is on site in your neighbourhood.</li>
            </ol>
            <p>The timetable stays on portal.living.school. If the booking has a plan, open it from your day.</p>
        </article>
    @endif
    <p class="meta">Access, in the top bar, says what this sign-in can open.</p>
</div>
@endif
@endsection
