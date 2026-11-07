@extends('layouts.app')
@section('content')
<p class="muted"><a class="link" href="{{ route('casuals.index') }}">Casual list</a></p>
@php
    $labels = ['green' => 'Clear to teach', 'amber' => 'Needs a note', 'red' => 'Do not book'];
    $dayOne = $modules->where('phase', 'day_one');
    $dayDone = $person->acknowledgements->filter(function ($ack) use ($dayOne) {
        $module = $dayOne->firstWhere('id', $ack->module_id);
        return $module && (int) $ack->module_version >= (int) $module->version;
    })->unique('module_id')->count();
@endphp
<h1>{{ $person->preferred_name }}</h1>
<p><span class="status {{ $gate['light'] }}">{{ $labels[$gate['light']] }}</span></p>
<p class="lede">{{ $person->legal_name }} · {{ $person->neighbourhood }} · {{ implode(', ', $person->stages ?? []) }}</p>
<div class="actions" style="margin-bottom:18px;">
    @if($person->mobile)
        <a class="button" href="tel:{{ preg_replace('/\s+/', '', $person->mobile) }}">Call {{ $person->mobile }}</a>
    @endif
    <a class="button ghost" href="mailto:{{ $person->email }}">Email</a>
    <a class="button" href="{{ route('casuals.pack', $person) }}" style="background: {{ $dayDone >= $dayOne->count() && $dayOne->count() > 0 ? '#14663d' : '#8a4b00' }}">Day-one {{ $dayDone }} of {{ $dayOne->count() }}</a>
    @if(in_array(auth()->user()->role, ['hr','conductor','casual']))
        <a class="button ghost" href="{{ route('casuals.forms', $person) }}">Forms</a>
    @endif
</div>
@if($person->mentor)
    <p class="muted">Mentor: {{ $person->mentor->name }} @if($person->mentor->mobile)· {{ $person->mentor->mobile }}@endif</p>
@endif
<ul>
    @forelse($gate['reasons'] as $reason)
        <li>{{ $reason['text'] }}</li>
    @empty
        <li>Clear to teach.</li>
    @endforelse
</ul>

@if($canEdit)
<div class="card">
    <h2>Details</h2>
    <form method="post" action="{{ route('casuals.update', $person) }}">
        @csrf @method('put')
        @include('casuals.fields')
        <p><button type="submit">Save details</button></p>
    </form>
    <form method="post" action="{{ route('casuals.invite', $person) }}">
        @csrf
        <button type="submit">Create invite link</button>
    </form>
</div>
@endif

@if($sensitive)
<div class="grid">
    <form class="card" method="post" action="{{ route('casuals.wwcc', $person) }}">
        @csrf
        <h2>WWCC</h2>
        <p class="muted">HR verifies this with the Office of the Children’s Guardian. An APP number is rejected.</p>
        <label for="wwcc_number">WWCC number</label>
        <input id="wwcc_number" name="wwcc_number" value="{{ old('wwcc_number', $person->wwcc->wwcc_number ?? '') }}" required>
        <label for="name_on_check">Name on the check</label>
        <input id="name_on_check" name="name_on_check" value="{{ old('name_on_check', $person->wwcc->name_on_check ?? $person->legal_name) }}" required>
        <label for="date_of_birth">Date of birth</label>
        <input id="date_of_birth" type="date" name="date_of_birth" value="{{ old('date_of_birth', optional($person->wwcc->date_of_birth ?? null)->toDateString()) }}" required>
        <label for="outcome">Outcome</label>
        <select id="outcome" name="outcome">
            @foreach(['pending','cleared','barred','interim_bar','expired'] as $outcome)
                <option value="{{ $outcome }}" @selected(old('outcome', $person->wwcc->outcome ?? 'pending')===$outcome)>{{ str_replace('_',' ', $outcome) }}</option>
            @endforeach
        </select>
        <label for="expires_on">Expiry</label>
        <input id="expires_on" type="date" name="expires_on" value="{{ old('expires_on', optional($person->wwcc->expires_on ?? null)->toDateString()) }}">
        @if(auth()->user()->role !== 'casual')
            <p><button type="submit">Save WWCC</button></p>
        @else
            <p class="muted">You can correct the number. Only HR can mark it cleared.</p>
            <p><button type="submit">Send details to HR</button></p>
        @endif
    </form>
    @if($canEdit)
    <form class="card" method="post" action="{{ route('casuals.accreditation', $person) }}">
        @csrf
        <h2>Accreditation</h2>
        <label for="nesa_number">NESA number</label>
        <input id="nesa_number" name="nesa_number" value="{{ $person->accreditation->nesa_number ?? '' }}">
        <label for="level">Level</label>
        <select id="level" name="level">
            <option value="">Unknown</option>
            @foreach(['conditional','provisional','proficient','highly_accomplished','lead'] as $level)
                <option value="{{ $level }}" @selected(($person->accreditation->level ?? '')===$level)>{{ str_replace('_',' ', $level) }}</option>
            @endforeach
        </select>
        <label for="status">Status</label>
        <select id="status" name="status">
            @foreach(['active','leave_of_absence','non_practising','suspended','cancelled'] as $status)
                <option value="{{ $status }}" @selected(($person->accreditation->status ?? '')===$status)>{{ str_replace('_',' ', $status) }}</option>
            @endforeach
        </select>
        <label for="maintenance_end">Maintenance end</label>
        <input id="maintenance_end" type="date" name="maintenance_end" value="{{ optional($person->accreditation->maintenance_end ?? null)->toDateString() }}">
        <label for="fee_year">Fee year the teacher declares</label>
        <input id="fee_year" name="fee_year" value="{{ $person->accreditation->fee_year ?? '' }}">
        <label><input style="width:auto" type="checkbox" name="casual_evidence_on_file" value="1" @checked($person->accreditation->casual_evidence_on_file ?? false)> Casual-status evidence is on file for the 7-year period</label>
        <p><button type="submit">Record eTAMS check</button></p>
        @if(auth()->user()->role === 'conductor')
            <p>
                <button formaction="{{ route('casuals.alignment', $person) }}" name="alignment" value="approved">Approve eTAMS alignment</button>
                <button formaction="{{ route('casuals.alignment', $person) }}" name="alignment" value="declined">Decline</button>
            </p>
        @else
            <p class="muted">Alignment: {{ $person->accreditation->alignment ?? 'pending' }}. The Conductor decides this.</p>
        @endif
    </form>
    @endif
</div>
@endif

<div class="card">
    <h2>Induction</h2>
    @foreach($modules as $module)
        @php $done = $person->acknowledgements->first(fn ($row) => $row->module_id === $module->id && $row->module_version >= $module->version); @endphp
        <p><strong>{{ $module->phase === 'day_one' ? 'Day one' : 'Later' }}.</strong> {{ $module->title }}
            @if($done)
                <span class="muted">Acknowledged {{ $done->acknowledged_at->timezone('Australia/Sydney')->format('j M Y g:i a') }}</span>
            @endif
            <a class="link" href="{{ route('casuals.induction', [$person, $module]) }}">Open the quest</a>
        </p>
    @endforeach
</div>

<div class="card">
    <h2 id="contracts">Contracts</h2>
    @foreach($person->contracts as $contract)
        <p>{{ $contract->template->name }} · version {{ $contract->version }} · {{ $contract->status }}
            @if($contract->status === 'sent' && (auth()->user()->role === 'casual' || $canEdit))
                <form method="post" action="{{ route('contracts.sign', $contract) }}" style="display:inline">@csrf<button type="submit">Sign</button></form>
            @endif
        </p>
        @if($canEdit && $contract->status === 'sent')
            <form method="post" action="{{ route('contracts.withdraw', $contract) }}" style="margin-bottom:8px;">@csrf<button class="ghost" type="submit">Withdraw</button></form>
            @if($colleagues->isNotEmpty())
                <form method="post" action="{{ route('contracts.move', $contract) }}" class="grid">
                    @csrf
                    <div>
                        <label for="move_person_{{ $contract->id }}">Move this unsigned contract</label>
                        <select id="move_person_{{ $contract->id }}" name="person_id">
                            @foreach($colleagues as $colleague)
                                <option value="{{ $colleague->id }}">{{ $colleague->preferred_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <p><button class="ghost" type="submit">Move</button></p>
                </form>
            @endif
        @endif
        <pre style="white-space:pre-wrap; font-family:system-ui,sans-serif; font-size:14px;">{{ $contract->merged_body }}</pre>
    @endforeach
    @if($canEdit)
        <form method="post" action="{{ route('casuals.contracts.issue', $person) }}">
            @csrf
            <label for="template_id">Send a published template</label>
            <select id="template_id" name="template_id">
                @foreach(\App\Models\ContractTemplate::where('school_id', $person->school_id)->where('status','published')->orderByDesc('version')->get() as $template)
                    <option value="{{ $template->id }}">{{ $template->name }} (v{{ $template->version }})</option>
                @endforeach
            </select>
            <p><button type="submit">Send</button></p>
        </form>
    @endif
</div>

@if(in_array(auth()->user()->role, ['hr','conductor','office','director']))
<div class="card">
    <h2>Book a day</h2>
    <p class="muted">The plan link points at the primary portal. This app does not edit programs.</p>
    <form method="post" action="{{ route('casuals.bookings', $person) }}" class="grid">
        @csrf
        <div><label for="booked_on">Date</label><input id="booked_on" type="date" name="booked_on" required></div>
        <div><label for="class_label">Class</label><input id="class_label" name="class_label" required></div>
        <div><label for="plan_url">Plan link</label><input id="plan_url" name="plan_url" type="url" placeholder="https://portal.living.school/..."></div>
        <div><label for="external_program_id">Primary program id</label><input id="external_program_id" name="external_program_id"></div>
        @if(auth()->user()->role === 'conductor')
            <div><label for="conductor_note">Conductor note for amber</label><textarea id="conductor_note" name="conductor_note"></textarea></div>
        @endif
        <p><button type="submit">Book</button></p>
    </form>
    @foreach($person->bookings->sortByDesc('booked_on') as $booking)
        <p>{{ $booking->booked_on->timezone('Australia/Sydney')->toFormattedDateString() }} · {{ $booking->class_label }} · {{ $booking->light_at_booking }}
            @if($booking->plan_url)<a href="{{ $booking->plan_url }}">Open plan</a>@endif
        </p>
    @endforeach
</div>
@endif

<div class="grid">
    <form class="card" method="post" action="{{ route('casuals.unavailability', $person) }}">
        @csrf
        <h2>Do not book</h2>
        <label for="starts_on">From</label><input id="starts_on" type="date" name="starts_on" required>
        <label for="ends_on">To</label><input id="ends_on" type="date" name="ends_on" required>
        <label for="reason">Reason</label><input id="reason" name="reason">
        <p><button type="submit">Save dates</button></p>
        @foreach($person->unavailabilities as $off)
            <p class="muted">{{ $off->starts_on->toDateString() }} to {{ $off->ends_on->toDateString() }} {{ $off->reason }}</p>
        @endforeach
    </form>
    @if(in_array(auth()->user()->role, ['director','mentor','hr','conductor']))
    <form class="card" method="post" action="{{ route('casuals.notes', $person) }}">
        @csrf
        <h2>Day note</h2>
        <label for="body">Three lines is enough</label>
        <textarea id="body" name="body" required></textarea>
        <label for="descriptors">Standard descriptors</label>
        <input id="descriptors" name="descriptors" placeholder="4.1, 5.2">
        <p><button type="submit">Add note</button></p>
        @foreach($person->dayNotes as $note)
            <p>{{ $note->body }} <span class="muted">{{ $note->author->name }}</span></p>
        @endforeach
    </form>
    @endif
</div>

<form class="card" method="post" action="{{ route('casuals.pd', $person) }}">
    @csrf
    <h2>Professional learning</h2>
    <p class="muted">A school copy for the teacher to paste into eTAMS. eTAMS remains the accreditation record.</p>
    <div class="grid">
        <div><label for="occurred_on">Date</label><input id="occurred_on" type="date" name="occurred_on" required></div>
        <div><label for="hours">Hours</label><input id="hours" name="hours" type="number" step="0.25" min="0.25" required></div>
        <div><label for="name">Activity</label><input id="name" name="name" required></div>
        <div><label for="provider">Provider</label><input id="provider" name="provider"></div>
        <div><label for="standards">Standards</label><input id="standards" name="standards" placeholder="4, 6"></div>
        <div><label for="activity_type">Type</label><input id="activity_type" name="activity_type"></div>
    </div>
    <p><button type="submit">Add hours</button></p>
    @foreach($person->pdEntries as $entry)
        <p>{{ $entry->occurred_on->toDateString() }} · {{ $entry->name }} · {{ $entry->hours }}h</p>
    @endforeach
</form>
@endsection
