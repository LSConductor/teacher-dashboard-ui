@extends('layouts.app')
@section('content')
<h1>Setup</h1>
<p class="lede">HR and the Conductor manage the library. A director sees the casual list and day-one, and does not open this page. A published contract and an acknowledged quest keep the version already used.</p>
<article class="person green">
    <div><span class="status green">Quests</span></div>
    <div>
        <h2>Induction quests</h2>
        <p class="meta">Edit the wording, add a page, and move a quest between day one and later. Retiring a quest takes it off the light.</p>
        <div class="actions"><a class="button" href="{{ route('setup.induction') }}">Open quests</a></div>
    </div>
</article>
<article class="person">
    <div><span class="status amber">Contracts</span></div>
    <div>
        <h2>Contract library</h2>
        <p class="meta">Edit a draft, publish it, retire a version, and move the list. A signed contract stays with the person who signed it.</p>
        <div class="actions"><a class="button" href="{{ route('templates.index') }}">Open contracts</a></div>
    </div>
</article>
<article class="person">
    <div><span class="status">Staff</span></div>
    <div>
        <h2>Staff accounts</h2>
        <p class="meta">Add a director, mentor, or office account, and write the card a casual sees on the people page. You cannot change your own role.</p>
        <div class="actions"><a class="button" href="{{ route('setup.staff') }}">Open staff</a></div>
    </div>
</article>
@endsection
