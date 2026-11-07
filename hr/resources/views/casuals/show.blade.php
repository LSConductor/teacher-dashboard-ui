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
    @if($person->mobile && (\App\Hr\Roles::kind(auth()->user()) !== 'neighbourhood' || $gate['light'] === 'green'))
        <a class="button" href="tel:{{ preg_replace('/\s+/', '', $person->mobile) }}">Call {{ $person->preferred_name }}</a>
    @endif
    @if($canEdit)
        <a class="button ghost" href="{{ route('casuals.letter', $person) }}">Casual-status letter</a>
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

<div class="card">
    <h2>Expiry watch</h2>
    <p>WWCC @if($person->wwcc?->expires_on){{ $person->wwcc->expires_on->timezone('Australia/Sydney')->format('j M Y') }} · @endif{{ $wwccWatch }}</p>
    <p>Accreditation maintenance @if($person->accreditation?->maintenance_end){{ $person->accreditation->maintenance_end->timezone('Australia/Sydney')->format('j M Y') }} · @endif{{ $maintenanceWatch }}</p>
    <p class="muted">A date inside 30 days is a warning. A missing or lapsed date keeps the light red, and a Director cannot book that casual.</p>
</div>

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

@if($sensitive)
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
        @php
            $ceis = \App\Hr\ContractDesk::published($person, 'ceis-receipt');
            $ceisSent = \App\Hr\ContractDesk::alreadySent($person, 'ceis-receipt');
        @endphp
        @if($ceis && ! $ceisSent)
            <form method="post" action="{{ route('casuals.contracts.issue', $person) }}">
                @csrf
                <input type="hidden" name="template_id" value="{{ $ceis->id }}">
                <p><button type="submit">Record the Casual Employment Information Statement receipt</button></p>
            </form>
        @endif
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
@endif

@if(in_array(auth()->user()->role, ['hr','conductor','office','director']))
<div class="card">
    <h2>Book a day</h2>
    @if($gate['light'] === 'red')
        <p>This casual is red and cannot be booked. There is no override.</p>
    @else
    <p class="muted">The plan link points at the primary portal. This app does not edit programs. Booking also files the one-day confirmation.</p>
    <form method="post" action="{{ route('casuals.bookings', $person) }}" class="grid">
        @csrf
        <div><label for="booked_on">Date</label><input id="booked_on" type="date" name="booked_on" required></div>
        <div><label for="class_label">Class</label><input id="class_label" name="class_label" required></div>
        <div><label for="teacher_name">Teacher they are with</label><input id="teacher_name" name="teacher_name" placeholder="The class teacher"></div>
        <div><label for="start_time">Start</label><input id="start_time" name="start_time" value="9:00"></div>
        <div><label for="finish_time">Finish</label><input id="finish_time" name="finish_time" value="3:15"></div>
        <div><label for="plan_url">Plan link</label><input id="plan_url" name="plan_url" type="url" placeholder="https://portal.living.school/..."></div>
        <div><label for="external_program_id">Primary program id</label><input id="external_program_id" name="external_program_id"></div>
        @if(auth()->user()->role === 'conductor')
            <div><label for="conductor_note">Conductor note for amber</label><textarea id="conductor_note" name="conductor_note"></textarea></div>
        @endif
        <p><button type="submit">Book</button></p>
    </form>
    @endif
    @foreach($person->bookings->sortByDesc('booked_on') as $booking)
        <p>{{ $booking->booked_on->timezone('Australia/Sydney')->toFormattedDateString() }} · {{ $booking->class_label }}@if($booking->teacher_name) · {{ $booking->teacher_name }}@endif · {{ $booking->light_at_booking }}
            @if($booking->plan_url)<a href="{{ $booking->plan_url }}">Open plan</a>@endif
        </p>
    @endforeach
</div>
@endif

<div class="card" id="performance">
    <h2>Performance</h2>
    <p>Mentor: {{ $person->mentor->name ?? 'not named' }}</p>
    <p>Accreditation Supervisor: {{ $person->supervisor->name ?? 'not named' }}</p>
    @if($canEdit)
        <form method="post" action="{{ route('casuals.mentor', $person) }}" class="grid">
            @csrf
            <div>
                <label for="named_mentor">Name a mentor</label>
                <select id="named_mentor" name="mentor_user_id">
                    <option value="">None yet</option>
                    @foreach($mentors as $mentor)
                        <option value="{{ $mentor->id }}" @selected($person->mentor_user_id == $mentor->id)>{{ $mentor->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="named_supervisor">Accreditation Supervisor</label>
                <select id="named_supervisor" name="supervisor_user_id">
                    <option value="">None yet</option>
                    @foreach($mentors as $mentor)
                        <option value="{{ $mentor->id }}" @selected($person->supervisor_user_id == $mentor->id)>{{ $mentor->name }}</option>
                    @endforeach
                </select>
            </div>
            <p><button type="submit">Save names</button></p>
        </form>
        <p class="muted">A Conditional or Provisional casual stays red until an Accreditation Supervisor is named. Choose a mentor who is Proficient or above.</p>
    @endif

    <h3>Goal</h3>
    @if($person->goal)
        <p>{{ $person->goal->body }} @if($person->goal->descriptors)<span class="muted">{{ $person->goal->descriptors }}</span>@endif</p>
    @else
        <p class="muted">No goal yet.</p>
    @endif
    @if($canWritePerformance)
        <form method="post" action="{{ route('casuals.goal', $person) }}">
            @csrf
            <label for="goal_body">One goal</label>
            <textarea id="goal_body" name="body" required>{{ $person->goal?->body ?? '' }}</textarea>
            <label for="goal_descriptors">Standard descriptors</label>
            <input id="goal_descriptors" name="descriptors" value="{{ $person->goal?->descriptors ?? '' }}" placeholder="4.1">
            <p><button type="submit">Save goal</button></p>
        </form>
    @endif

    <h3>Note from the day</h3>
    @forelse($person->dayNotes as $note)
        <p>{{ $note->body }} @if($note->descriptors)<span class="muted">{{ $note->descriptors }}</span>@endif <span class="muted">{{ $note->author->name }}</span></p>
    @empty
        <p class="muted">No note from the day yet.</p>
    @endforelse
    @if($canWritePerformance)
        <form method="post" action="{{ route('casuals.notes', $person) }}">
            @csrf
            <label for="body">Three lines is enough</label>
            <textarea id="body" name="body" required></textarea>
            <label for="descriptors">Standard descriptors</label>
            <input id="descriptors" name="descriptors" placeholder="4.1, 5.2">
            <p><button type="submit">Add note</button></p>
        </form>
    @endif

    <h3>Observation</h3>
    @if($person->observation)
        <p>{{ $person->observation->body }}</p>
        <p>Next: {{ $person->observation->next_step }} @if($person->observation->descriptors)<span class="muted">{{ $person->observation->descriptors }}</span>@endif</p>
    @else
        <p class="muted">No observation yet.</p>
    @endif
    @if($canWritePerformance)
        <form method="post" action="{{ route('casuals.observation', $person) }}">
            @csrf
            <label for="seen">What was seen</label>
            <textarea id="seen" name="body" required>{{ $person->observation?->body ?? '' }}</textarea>
            <label for="next_step">What to try next</label>
            <textarea id="next_step" name="next_step" required>{{ $person->observation?->next_step ?? '' }}</textarea>
            <label for="observation_descriptors">Standard descriptors</label>
            <input id="observation_descriptors" name="descriptors" value="{{ $person->observation?->descriptors ?? '' }}" placeholder="4.1, 5.2">
            <p><button type="submit">Save observation</button></p>
        </form>
    @endif

    <h3>Annual page</h3>
    @if($person->annualReview)
        <p class="muted">Written {{ $person->annualReview->recorded_on?->timezone('Australia/Sydney')->format('j M Y') }}.</p>
    @else
        <p class="muted">Not written yet.</p>
    @endif
    <p><a class="link" href="{{ route('casuals.annual', $person) }}">Open the short annual page</a></p>
</div>

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

<div class="card">
    <h2>Professional learning</h2>
    <p class="muted">Hours against the teacher standards. A school copy for the teacher to paste into eTAMS. eTAMS remains the accreditation record. The casual-status letter uses the days already stored, not these hours.</p>
    @php
        $pdHours = (float) $person->pdEntries->sum('hours');
        $pdHoursLabel = rtrim(rtrim(number_format($pdHours, 2), '0'), '.');
    @endphp
    <p>{{ $pdHoursLabel }} {{ $pdHoursLabel === '1' ? 'hour' : 'hours' }} recorded against the teacher standards.</p>
    @foreach($person->pdEntries as $entry)
        <p>{{ $entry->occurred_on->timezone('Australia/Sydney')->format('j M Y') }} · {{ $entry->name }} · {{ $entry->hours }}h @if($entry->standards)· Standards {{ $entry->standards }}@endif</p>
    @endforeach
    @if($canRecordLearning)
    <form method="post" action="{{ route('casuals.pd', $person) }}">
        @csrf
        <div class="grid">
            <div><label for="occurred_on">Date</label><input id="occurred_on" type="date" name="occurred_on" required></div>
            <div><label for="hours">Hours</label><input id="hours" name="hours" type="number" step="0.25" min="0.25" required></div>
            <div><label for="name">Activity</label><input id="name" name="name" required></div>
            <div><label for="provider">Provider</label><input id="provider" name="provider"></div>
            <div><label for="activity_type">Type</label><input id="activity_type" name="activity_type"></div>
        </div>
        <p>Standards</p>
        @foreach([
            '1' => 'Know students and how they learn',
            '2' => 'Know the content and how to teach it',
            '3' => 'Plan for and implement effective teaching and learning',
            '4' => 'Create and maintain supportive and safe learning environments',
            '5' => 'Assess, provide feedback and report on student learning',
            '6' => 'Engage in professional learning',
            '7' => 'Engage professionally with colleagues, parents and carers',
        ] as $number => $label)
            <label style="display:block;"><input style="width:auto" type="checkbox" name="standards[]" value="{{ $number }}"> {{ $number }}. {{ $label }}</label>
        @endforeach
        <p><button type="submit">Add hours</button></p>
    </form>
    @endif
</div>
@endsection
