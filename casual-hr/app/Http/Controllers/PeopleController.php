<?php

namespace App\Http\Controllers;

use App\Hr\AuditLog;
use App\Hr\ClearToTeach;
use App\Hr\WwccRules;
use App\Models\Accreditation;
use App\Models\Invite;
use App\Models\Neighbourhood;
use App\Models\Person;
use App\Models\User;
use App\Models\Wwcc;
use App\Support\Phone;
use App\Support\Sydney;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PeopleController extends Controller
{
    public function __construct(
        private readonly ClearToTeach $lights,
        private readonly AuditLog $audit,
    ) {}

    public function index(Request $request): View
    {
        $people = Person::query()
            ->with([
                'neighbourhood.director',
                'accreditation',
                'wwcc',
                'mentorship.mentor',
                'bookings' => fn ($query) => $query->where('status', 'booked'),
            ])
            ->orderBy('preferred_name')
            ->get();

        $today = Sydney::today()->toDateString();
        $soon = Sydney::today()->addDays(30)->toDateString();

        $rows = $people->map(function (Person $person) use ($today) {
            $last = $person->bookings
                ->filter(fn ($booking) => $booking->on_date->toDateString() <= $today)
                ->max('on_date');

            return [
                'person' => $person,
                'light' => $this->lights->assess($person),
                'last_day' => $last?->toDateString(),
            ];
        });

        $filter = (string) $request->query('filter', '');
        $filtered = $rows->filter(function (array $row) use ($filter, $today, $soon) {
            $person = $row['person'];

            return match ($filter) {
                'clear' => $row['light']->isGreen(),
                'expiring' => ($expiry = $person->wwcc?->expires_on?->toDateString())
                    && $expiry >= $today
                    && $expiry <= $soon,
                'provisional' => $person->accreditation?->level === 'provisional',
                'waiting_on_conductor' => $person->accreditation?->alignment_decision === 'pending',
                'induction_unfinished' => $person->day_one_induction_completed_at === null,
                default => true,
            };
        })->values();

        return view('hr.index', [
            'rows' => $filtered,
            'filter' => $filter,
            'counts' => [
                'clear' => $rows->filter(fn ($row) => $row['light']->isGreen())->count(),
                'expiring' => $rows->filter(function ($row) use ($today, $soon) {
                    $expiry = $row['person']->wwcc?->expires_on?->toDateString();

                    return $expiry && $expiry >= $today && $expiry <= $soon;
                })->count(),
                'provisional' => $rows->filter(fn ($row) => $row['person']->accreditation?->level === 'provisional')->count(),
                'waiting_on_conductor' => $rows->filter(fn ($row) => $row['person']->accreditation?->alignment_decision === 'pending')->count(),
                'induction_unfinished' => $rows->filter(fn ($row) => $row['person']->day_one_induction_completed_at === null)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('hr.create', [
            'neighbourhoods' => Neighbourhood::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Person::class);
        $data = $this->personData($request);

        $person = Person::create($data);
        $this->audit->record($request->user(), 'person.created', $person, $person, [
            'fields' => ['legal_name', 'preferred_name', 'email'],
        ], $request);

        return redirect()->route('hr.people.show', $person)->with('status', $person->preferred_name.' is on the list. The light stays red until WWCC, eTAMS, engagement, and day-one induction are recorded.');
    }

    public function show(Person $person): View
    {
        $this->authorize('viewHr', $person);
        $person->load([
            'neighbourhood',
            'accreditation.checker',
            'mentorship.mentor',
            'bookings' => fn ($query) => $query->where('status', 'held')->orderBy('on_date'),
        ]);

        return view('hr.show', [
            'person' => $person,
            'light' => $this->lights->assess($person),
            'isHr' => request()->user()->isHr(),
            'neighbourhoods' => Neighbourhood::query()->orderBy('name')->get(),
            'mentors' => Person::query()->whereKeyNot($person->id)->orderBy('preferred_name')->get(),
            'staff' => User::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Person $person): RedirectResponse
    {
        $this->authorize('update', $person);
        $data = $this->personData($request, $person);
        $mentorId = $request->input('mentor_person_id');
        $mentorId = $mentorId === null || $mentorId === '' ? null : (int) $mentorId;

        DB::transaction(function () use ($request, $person, $data, $mentorId) {
            $this->changingLight($person, $request->user(), function () use ($person, $data, $mentorId, $request) {
                $person->fill($data);
                $fields = array_keys($person->getDirty());
                $person->save();
                $person->syncMentor($mentorId, $request->boolean('is_accreditation_supervisor'));

                if ($fields !== []) {
                    $this->audit->record($request->user(), 'person.updated', $person, $person, [
                        'fields' => $fields,
                    ], $request);
                }
            });
        });

        return back()->with('status', 'Person record saved.');
    }

    public function updateAccreditation(Request $request, Person $person): RedirectResponse
    {
        $this->authorize('updateAccreditation', $person);

        $data = $request->validate([
            'nesa_number' => ['nullable', 'string', 'max:64'],
            'level' => ['nullable', Rule::in(Accreditation::LEVELS)],
            'status' => ['nullable', Rule::in(Accreditation::STATUSES)],
            'maintenance_start' => ['nullable', 'date'],
            'maintenance_end' => ['nullable', 'date'],
            'casual_evidence_given' => ['nullable', 'boolean'],
            'fee_year' => ['nullable', 'integer', 'between:2000,2100'],
            'etams_checked_on' => ['nullable', 'date'],
            'evidence_reference' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->changingLight($person, $request->user(), function () use ($person, $data, $request) {
            $accreditation = $person->accreditation ?? new Accreditation([
                'school_id' => $person->school_id,
                'person_id' => $person->id,
            ]);

            $checkedOn = $data['etams_checked_on'] ?? null;
            $accreditation->fill([
                'nesa_number' => $data['nesa_number'] ?? null,
                'level' => $data['level'] ?? null,
                'status' => $data['status'] ?? null,
                'maintenance_start' => $data['maintenance_start'] ?? null,
                'maintenance_end' => $data['maintenance_end'] ?? null,
                'casual_evidence_given' => $request->boolean('casual_evidence_given'),
                'fee_year' => $data['fee_year'] ?? null,
                'etams_checked_at' => $checkedOn ? Sydney::date($checkedOn) : null,
                'checked_by' => $checkedOn ? $request->user()->id : null,
                'evidence_reference' => $data['evidence_reference'] ?? null,
            ]);
            $accreditation->save();

            $this->audit->record($request->user(), 'accreditation.updated', $accreditation, $person, [
                'level' => $accreditation->level,
                'status' => $accreditation->status,
                'etams_checked_at' => optional($accreditation->etams_checked_at)->toIso8601String(),
                'maintenance_end' => optional($accreditation->maintenance_end)->toDateString(),
            ], $request);
        });

        return back()->with('status', 'eTAMS record saved.');
    }

    public function updateAlignment(Request $request, Person $person): RedirectResponse
    {
        $this->authorize('align', $person);

        $data = $request->validate([
            'alignment_decision' => ['required', Rule::in(['pending', 'approved', 'declined'])],
            'other_employer_arrangement' => ['nullable', 'string', 'max:2000'],
            'delegate_user_id' => ['nullable', Rule::exists('users', 'id')->where('school_id', $person->school_id)],
        ]);

        $accreditation = $person->accreditation;
        if (! $accreditation) {
            return back()->withErrors(['alignment_decision' => 'HR records the eTAMS check before the Conductor decides alignment.']);
        }

        $decision = $data['alignment_decision'];

        $this->changingLight($person, $request->user(), function () use ($accreditation, $decision, $data, $request, $person) {
            $accreditation->update([
                'alignment_decision' => $decision,
                'other_employer_arrangement' => $data['other_employer_arrangement'] ?? null,
                'delegate_user_id' => $data['delegate_user_id'] ?? null,
                'alignment_decided_at' => $decision === 'pending' ? null : Sydney::now(),
                'alignment_decided_by' => $decision === 'pending' ? null : $request->user()->id,
            ]);

            $this->audit->record($request->user(), 'accreditation.alignment', $accreditation, $person, [
                'alignment_decision' => $decision,
            ], $request);
        });

        return back()->with('status', 'Alignment decision saved.');
    }

    public function showWwcc(Person $person): View
    {
        $this->authorize('viewWwcc', $person);

        return view('hr.wwcc', [
            'person' => $person,
            'wwcc' => $person->wwcc,
        ]);
    }

    public function updateWwcc(Request $request, Person $person): RedirectResponse
    {
        $this->authorize('updateWwcc', $person);

        $data = $request->validate([
            'wwcc_number' => ['required', 'string', 'max:32'],
            'name_used' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date'],
            'outcome' => ['required', Rule::in(Wwcc::OUTCOMES)],
            'expires_on' => ['nullable', 'date'],
            'verified_on' => ['nullable', 'date'],
        ]);

        $normalised = WwccRules::normalise($data['wwcc_number'], $data['outcome'], $data['expires_on'] ?? null);

        if ($normalised['error']) {
            return back()->withInput()->withErrors(['wwcc_number' => $normalised['error']]);
        }

        $this->changingLight($person, $request->user(), function () use ($person, $data, $normalised, $request) {
            $wwcc = $person->wwcc ?? new Wwcc([
                'school_id' => $person->school_id,
                'person_id' => $person->id,
            ]);

            $wwcc->fill([
                'wwcc_number' => WwccRules::normaliseNumber($data['wwcc_number']),
                'name_used' => $data['name_used'],
                'date_of_birth' => $data['date_of_birth'],
                'outcome' => $normalised['outcome'],
                'expires_on' => $data['expires_on'] ?? null,
                'verified_at' => ($data['verified_on'] ?? null) ? Sydney::date($data['verified_on']) : null,
                'verified_by' => ($data['verified_on'] ?? null) ? $request->user()->id : null,
                'app_number_blocked' => $normalised['app_number_blocked'],
                'bar_blocked' => $normalised['bar_blocked'],
                'interim_bar_blocked' => $normalised['interim_bar_blocked'],
                'expired_blocked' => $normalised['expired_blocked'],
            ]);
            $wwcc->save();

            $this->audit->record($request->user(), 'wwcc.updated', $wwcc, $person, [
                'outcome' => $wwcc->outcome,
                'expires_on' => optional($wwcc->expires_on)->toDateString(),
                'app_number_blocked' => $wwcc->app_number_blocked,
                'bar_blocked' => $wwcc->bar_blocked,
                'interim_bar_blocked' => $wwcc->interim_bar_blocked,
                'expired_blocked' => $wwcc->expired_blocked,
            ], $request);
        });

        return back()->with('status', 'WWCC record saved. The number stays on the HR file.');
    }

    public function invite(Request $request, Person $person): RedirectResponse
    {
        $this->authorize('invite', $person);

        $raw = Invite::issue($person, $request->user());
        $this->audit->record($request->user(), 'invite.issued', $person, $person, [
            'expires_in_days' => 7,
        ], $request);

        return back()
            ->with('status', 'Invite created. It opens only this person’s induction shell, once, and then it expires.')
            ->with('invite_url', route('induction.open', ['token' => $raw]));
    }

    /**
     * @return array<string, mixed>
     */
    private function personData(Request $request, ?Person $person = null): array
    {
        $schoolId = $person?->school_id ?? $request->user()->school_id;
        $request->merge([
            'mobile' => Phone::digits((string) $request->input('mobile', '')),
            'emergency_contact_mobile' => $request->filled('emergency_contact_mobile')
                ? Phone::digits((string) $request->input('emergency_contact_mobile'))
                : null,
            'google_subject' => $request->filled('google_subject') ? trim((string) $request->input('google_subject')) : null,
        ]);

        $data = $request->validate([
            'legal_name' => ['required', 'string', 'max:255'],
            'preferred_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('people', 'email')->where('school_id', $schoolId)->ignore($person)],
            'mobile' => ['required', 'regex:/^0\d{9}$/'],
            'postal_address' => ['nullable', 'string', 'max:2000'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_mobile' => ['nullable', 'regex:/^0\d{9}$/'],
            'neighbourhood_id' => ['nullable', Rule::exists('neighbourhoods', 'id')->where('school_id', $schoolId)],
            'stages' => ['nullable', 'array'],
            'stages.*' => [Rule::in(Person::STAGES)],
            'employment_status' => ['required', Rule::in([Person::APPLICANT, Person::CASUAL_POOL, Person::ENDED])],
            'google_subject' => ['nullable', 'string', 'max:255', Rule::unique('people', 'google_subject')->where('school_id', $schoolId)->ignore($person)],
            'payroll_reference' => ['nullable', 'string', 'max:64'],
            'engagement_signed_on' => ['nullable', 'date'],
            'day_one_completed_on' => ['nullable', 'date'],
            'later_induction_window_ends_on' => ['nullable', 'date'],
            'pd_conversation_due_on' => ['nullable', 'date'],
            'mentor_person_id' => ['nullable', Rule::exists('people', 'id')->where('school_id', $schoolId)],
        ]);

        return [
            'legal_name' => $data['legal_name'],
            'preferred_name' => $data['preferred_name'],
            'email' => $data['email'],
            'mobile' => $data['mobile'],
            'postal_address' => $data['postal_address'] ?? null,
            'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
            'emergency_contact_mobile' => $data['emergency_contact_mobile'] ?? null,
            'neighbourhood_id' => $data['neighbourhood_id'] ?? null,
            'stages' => array_values($data['stages'] ?? []),
            'employment_status' => $data['employment_status'],
            'google_subject' => $data['google_subject'] ?? null,
            'payroll_reference' => $data['payroll_reference'] ?? null,
            'engagement_signed_at' => Sydney::date($data['engagement_signed_on'] ?? null),
            'day_one_induction_completed_at' => Sydney::date($data['day_one_completed_on'] ?? null),
            'later_induction_window_ends_on' => $data['later_induction_window_ends_on'] ?? null,
            'pd_conversation_due_on' => $data['pd_conversation_due_on'] ?? null,
        ];
    }

    private function changingLight(Person $person, User $actor, callable $change): void
    {
        $before = $this->lights->assess($person);
        $change();
        $person->refresh();
        $person->unsetRelation('accreditation');
        $person->unsetRelation('wwcc');
        $after = $this->lights->assess($person);
        $this->audit->recordLightChange($person, $before, $after, $actor);
    }
}
