@extends('layouts.app', ['title' => $person->preferred_name, 'intro' => 'HR file for this school'])

@section('content')
    <p><span class="light {{ $light->colour }}">{{ $light->word() }}</span></p>
    @if ($light->reasonLabels())
        <ul class="reasons">
            @foreach ($light->reasonLabels() as $reason)
                <li>{{ $reason }}</li>
            @endforeach
        </ul>
    @endif
    <p class="meta">Google subject: {{ $person->google_subject ? 'linked' : 'not linked' }}</p>

    @if ($isHr)
        <form class="panel" method="post" action="{{ route('hr.people.update', $person) }}">
            @csrf
            @method('put')
            @include('hr.partials.person-fields', ['person' => $person, 'neighbourhoods' => $neighbourhoods])

            <label for="postal_address">Postal address</label>
            <textarea id="postal_address" name="postal_address">{{ old('postal_address', $person->postal_address) }}</textarea>

            <label for="emergency_contact_name">Emergency contact</label>
            <input id="emergency_contact_name" name="emergency_contact_name" value="{{ old('emergency_contact_name', $person->emergency_contact_name) }}">

            <label for="emergency_contact_mobile">Emergency mobile</label>
            <input id="emergency_contact_mobile" name="emergency_contact_mobile" value="{{ old('emergency_contact_mobile', $person->emergency_contact_mobile) }}">

            <label for="payroll_reference">Payroll reference</label>
            <input id="payroll_reference" name="payroll_reference" value="{{ old('payroll_reference', $person->payroll_reference) }}">
            <p class="meta">A pointer only. Tax and pay stay in payroll.</p>

            <label for="google_subject">Google subject</label>
            <input id="google_subject" name="google_subject" value="{{ old('google_subject', $person->google_subject) }}">
            <p class="meta">Paste the subject from the Staff Portal Google account once it exists. Do not put an OAuth secret here.</p>

            <label for="engagement_signed_on">Engagement signed on</label>
            <input id="engagement_signed_on" type="date" name="engagement_signed_on" value="{{ old('engagement_signed_on', $person->engagement_signed_at?->timezone('Australia/Sydney')->toDateString()) }}">

            <label for="day_one_completed_on">Day-one induction completed on</label>
            <input id="day_one_completed_on" type="date" name="day_one_completed_on" value="{{ old('day_one_completed_on', $person->day_one_induction_completed_at?->timezone('Australia/Sydney')->toDateString()) }}">

            <label for="later_induction_window_ends_on">Later induction window ends</label>
            <input id="later_induction_window_ends_on" type="date" name="later_induction_window_ends_on" value="{{ old('later_induction_window_ends_on', $person->later_induction_window_ends_on?->toDateString()) }}">

            <label for="pd_conversation_due_on">PD conversation due on</label>
            <input id="pd_conversation_due_on" type="date" name="pd_conversation_due_on" value="{{ old('pd_conversation_due_on', $person->pd_conversation_due_on?->toDateString()) }}">
            <p class="meta">Amber is only for a later induction still inside its window, or a PD conversation that is due. It is never for WWCC or accreditation.</p>

            <label for="mentor_person_id">Mentor</label>
            <select id="mentor_person_id" name="mentor_person_id">
                <option value="">No mentor</option>
                @foreach ($mentors as $mentor)
                    <option value="{{ $mentor->id }}" @selected((string) old('mentor_person_id', $person->mentorship->mentor_person_id ?? '') === (string) $mentor->id)>{{ $mentor->preferred_name }}</option>
                @endforeach
            </select>
            <label><input type="checkbox" name="is_accreditation_supervisor" value="1" @checked(old('is_accreditation_supervisor', $person->mentorship->is_accreditation_supervisor ?? false))> Accreditation supervisor</label>

            <p><button type="submit">Save person</button></p>
        </form>

        <p><a class="button" href="{{ route('hr.people.wwcc', $person) }}">WWCC record</a></p>

        <form method="post" action="{{ route('hr.people.invites', $person) }}">
            @csrf
            <button type="submit">Send a single-use invite</button>
        </form>

        <form class="panel" method="post" action="{{ route('hr.people.accreditation', $person) }}">
            @csrf
            @method('put')
            <h2>eTAMS</h2>
            <label for="nesa_number">NESA number</label>
            <input id="nesa_number" name="nesa_number" value="{{ old('nesa_number', $person->accreditation?->nesa_number) }}">
            <label for="level">Level</label>
            <select id="level" name="level">
                <option value="">Not recorded</option>
                @foreach (\App\Models\Accreditation::LEVELS as $level)
                    <option value="{{ $level }}" @selected(old('level', $person->accreditation?->level) === $level)>{{ str_replace('_', ' ', $level) }}</option>
                @endforeach
            </select>
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">Not recorded</option>
                @foreach (\App\Models\Accreditation::STATUSES as $status)
                    <option value="{{ $status }}" @selected(old('status', $person->accreditation?->status) === $status)>{{ str_replace('_', ' ', $status) }}</option>
                @endforeach
            </select>
            <label for="maintenance_start">Maintenance start</label>
            <input id="maintenance_start" type="date" name="maintenance_start" value="{{ old('maintenance_start', $person->accreditation?->maintenance_start?->toDateString()) }}">
            <label for="maintenance_end">Maintenance end</label>
            <input id="maintenance_end" type="date" name="maintenance_end" value="{{ old('maintenance_end', $person->accreditation?->maintenance_end?->toDateString()) }}">
            <label for="fee_year">Fee year the teacher declared</label>
            <input id="fee_year" name="fee_year" inputmode="numeric" value="{{ old('fee_year', $person->accreditation?->fee_year) }}">
            <label for="etams_checked_on">eTAMS checked on</label>
            <input id="etams_checked_on" type="date" name="etams_checked_on" value="{{ old('etams_checked_on', $person->accreditation?->etams_checked_at?->timezone('Australia/Sydney')->toDateString()) }}">
            <label for="evidence_reference">Where the eTAMS screen or letter is kept</label>
            <textarea id="evidence_reference" name="evidence_reference">{{ old('evidence_reference', $person->accreditation?->evidence_reference) }}</textarea>
            <label><input type="checkbox" name="casual_evidence_given" value="1" @checked(old('casual_evidence_given', $person->accreditation?->casual_evidence_given ?? false))> 7-year casual evidence given</label>
            <p><button type="submit">Save eTAMS</button></p>
        </form>
    @else
        <p>{{ $person->preferred_name }} · {{ $person->mobile }} · {{ $person->email }}</p>
        <p>Stages: {{ implode(', ', $person->stages ?? []) }}</p>
        <p>Neighbourhood: {{ $person->neighbourhood->name ?? '—' }}</p>
        <p>Level: {{ $person->accreditation?->level ?? 'Not recorded' }} · Status: {{ $person->accreditation?->status ?? 'Not recorded' }}</p>
        <p>Maintenance end: {{ $person->accreditation?->maintenance_end?->toDateString() ?? '—' }}</p>
        <p>eTAMS checked: {{ $person->accreditation?->etams_checked_at?->timezone('Australia/Sydney')->format('j M Y') ?? 'Not checked' }}</p>
        <p>NESA number: {{ $person->accreditation?->nesa_number ?? '—' }}</p>
    @endif

    <form class="panel" method="post" action="{{ route('hr.people.alignment', $person) }}">
        @csrf
        @method('put')
        <h2>Conductor alignment</h2>
        @unless (auth()->user()->isConductor())
            <p>Decision: {{ $person->accreditation?->alignment_decision ?? 'No eTAMS record yet' }}</p>
            @if ($person->accreditation?->other_employer_arrangement)
                <p>Other NESA employer arrangement: {{ $person->accreditation->other_employer_arrangement }}</p>
            @endif
        @else
            <label for="alignment_decision">Decision</label>
            <select id="alignment_decision" name="alignment_decision">
                @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'declined' => 'Declined'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('alignment_decision', $person->accreditation?->alignment_decision ?? 'pending') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <label for="other_employer_arrangement">Other NESA employer arrangement</label>
            <textarea id="other_employer_arrangement" name="other_employer_arrangement">{{ old('other_employer_arrangement', $person->accreditation?->other_employer_arrangement) }}</textarea>
            <p class="meta">A decline stays red unless another NESA employer arrangement is recorded.</p>
            <label for="delegate_user_id">Delegate</label>
            <select id="delegate_user_id" name="delegate_user_id">
                <option value="">None</option>
                @foreach ($staff as $member)
                    <option value="{{ $member->id }}" @selected((string) old('delegate_user_id', $person->accreditation?->delegate_user_id) === (string) $member->id)>{{ $member->name }}</option>
                @endforeach
            </select>
            <p><button type="submit">Save alignment</button></p>
        @endunless
    </form>

    @if (auth()->user()->isConductor())
        <section class="card">
            <h2>Held cover</h2>
            @forelse ($person->bookings as $booking)
                <form method="post" action="{{ route('bookings.note', $booking) }}">
                    @csrf
                    <p>{{ $booking->on_date->toDateString() }} · {{ $booking->class_or_group }}</p>
                    <label for="note-{{ $booking->id }}">Conductor note</label>
                    <textarea id="note-{{ $booking->id }}" name="conductor_note" required>{{ $booking->conductor_note }}</textarea>
                    <button type="submit">Save note</button>
                </form>
            @empty
                <p>No held booking.</p>
            @endforelse
        </section>
    @endif
@endsection
