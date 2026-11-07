<?php

namespace App\Http\Controllers;

use App\Hr\Neighbourhood;
use App\Hr\Roll;
use App\Hr\Roles;
use App\Models\DayRoll;
use App\Models\InductionModule;
use App\Models\Person;
use App\Models\School;
use App\Models\User;
use App\Services\Audit;
use App\Services\ClearToTeach;
use Illuminate\Http\Request;

class CasualController extends Controller
{
    public function index(Request $request, ClearToTeach $gate)
    {
        $user = $request->user();
        $query = Person::query()
            ->where('school_id', $user->school_id)
            ->with(['accreditation', 'wwcc', 'contracts.template', 'acknowledgements', 'mentor', 'bookings']);

        $kind = Roles::kind($user);
        $lockedNeighbourhood = null;
        if ($kind === 'casual') {
            $query->where('id', $user->person_id);
        } elseif ($kind === 'neighbourhood') {
            if (Neighbourhood::coversAll($user->neighbourhood)) {
                $lockedNeighbourhood = null;
            } elseif (filled($user->neighbourhood)) {
                $lockedNeighbourhood = $user->neighbourhood;
                Neighbourhood::restrict($query, $lockedNeighbourhood);
            } else {
                $query->whereRaw('0 = 1');
            }
        } elseif ($kind === 'support') {
            $query->where('mentor_user_id', $user->id);
        }

        if ($lockedNeighbourhood === null && $request->filled('neighbourhood') && in_array($kind, ['manage', 'front_desk', 'neighbourhood'], true)) {
            Neighbourhood::restrict($query, $request->string('neighbourhood')->toString());
        }
        if ($request->filled('q')) {
            $term = '%'.$request->string('q').'%';
            $query->where(function ($inner) use ($term) {
                $inner->where('preferred_name', 'like', $term)->orWhere('legal_name', 'like', $term);
            });
        }

        $rolls = DayRoll::query()
            ->where('school_id', $user->school_id)
            ->whereDate('on_date', Roll::today())
            ->get()
            ->keyBy('person_id');
        $dayOne = InductionModule::query()
            ->where('school_id', $user->school_id)
            ->where('phase', 'day_one')
            ->get(['id', 'version']);
        $directors = User::query()
            ->where('school_id', $user->school_id)
            ->whereIn('role', Roles::slugsOfKind((int) $user->school_id, 'neighbourhood'))
            ->get()
            ->keyBy('neighbourhood');

        $people = $query->orderBy('preferred_name')->get()->map(function (Person $person) use ($gate, $dayOne, $directors, $rolls) {
            $person->gate = $gate->assess($person);
            $person->day_one_total = $dayOne->count();
            $person->day_one_done = $person->acknowledgements->filter(function ($ack) use ($dayOne) {
                $module = $dayOne->firstWhere('id', $ack->module_id);

                return $module && (int) $ack->module_version >= (int) $module->version;
            })->unique('module_id')->count();
            $person->director_name = $directors->get($person->neighbourhood)?->name;
            $person->today_booking = $person->bookings->first(
                fn ($booking) => $booking->booked_on->isSameDay(now())
            );
            $person->roll_label = $person->today_booking ? Roll::label($rolls->get($person->id)) : null;
            $person->last_day = $person->bookings->sortByDesc('booked_on')->first()?->booked_on;
            $person->wwcc_watch = $gate->datePhrase($person->wwcc?->expires_on);
            $person->maintenance_watch = $gate->datePhrase($person->accreditation?->maintenance_end);

            return $person;
        });

        $sensitive = $kind === 'manage';

        if ($request->filled('light')) {
            $people = $people->where('gate.light', $request->string('light'))->values();
        }
        if ($sensitive && $request->filled('focus')) {
            $focus = $request->string('focus')->toString();
            $people = $people->filter(function (Person $person) use ($focus) {
                return match ($focus) {
                    'induction' => collect($person->gate['reasons'])->contains(
                        fn ($reason) => str_contains($reason['text'], 'Day-one')
                    ),
                    'alignment' => ($person->accreditation->alignment ?? 'pending') === 'pending',
                    'provisional' => in_array($person->accreditation->level ?? '', ['conditional', 'provisional'], true),
                    'expiring' => $person->wwcc_watch !== 'in date' || $person->maintenance_watch !== 'in date',
                    default => true,
                };
            })->values();
        }

        return view('casuals.index', [
            'people' => $people,
            'sensitive' => $sensitive,
            'lockedNeighbourhood' => $lockedNeighbourhood,
            'neighbourhoods' => in_array($kind, ['manage', 'front_desk'], true) || ($kind === 'neighbourhood' && $lockedNeighbourhood === null)
                ? Neighbourhood::names((int) $user->school_id)
                : [],
        ]);
    }

    public function create()
    {
        return view('casuals.form', ['mentors' => $this->mentors()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['school_id'] = $request->user()->school_id;
        $data['status'] = 'casual_pool';
        $person = Person::create($data);
        $person->accreditation()->create(['school_id' => $person->school_id, 'alignment' => 'pending']);
        $person->wwcc()->create(['school_id' => $person->school_id, 'outcome' => 'pending']);
        Audit::record('person.created', $person);

        return redirect()->route('casuals.forms', $person);
    }

    public function show(Person $person, ClearToTeach $gate)
    {
        $this->authorize('view', $person);
        $person->load([
            'accreditation', 'wwcc', 'contracts.template', 'acknowledgements',
            'bookings', 'unavailabilities', 'dayNotes.author', 'pdEntries', 'mentor',
            'conductFlags', 'standardsNotices', 'dayRolls',
            'supervisor', 'goal', 'observation', 'annualReview',
        ]);
        $modules = InductionModule::query()
            ->where('school_id', $person->school_id)
            ->whereIn('phase', ['day_one', 'later'])
            ->orderBy('sort')
            ->get();
        $canEdit = request()->user()->can('updateFile', $person);

        return view('casuals.show', [
            'person' => $person,
            'gate' => $gate->assess($person),
            'modules' => $modules,
            'mentors' => $this->mentors(),
            'colleagues' => $canEdit
                ? Person::query()->where('school_id', $person->school_id)->where('id', '!=', $person->id)->orderBy('preferred_name')->get()
                : collect(),
            'sensitive' => request()->user()->can('viewSensitive', $person),
            'canEdit' => $canEdit,
            'canWritePerformance' => in_array(Roles::kind(request()->user()), ['neighbourhood', 'support', 'manage'], true),
            'canRecordLearning' => in_array(Roles::kind(request()->user()), ['manage', 'support'], true)
                || (int) request()->user()->person_id === (int) $person->id,
            'incidentDesk' => School::query()->whereKey($person->school_id)->value('incident_desk_url'),
            'wwccWatch' => $gate->datePhrase($person->wwcc?->expires_on),
            'maintenanceWatch' => $gate->datePhrase($person->accreditation?->maintenance_end),
        ]);
    }

    public function nameMentor(Request $request, Person $person)
    {
        $this->authorize('updateFile', $person);
        $data = $request->validate([
            'mentor_user_id' => ['nullable', 'integer'],
            'supervisor_user_id' => ['nullable', 'integer'],
        ]);
        foreach (['mentor_user_id', 'supervisor_user_id'] as $field) {
            if (empty($data[$field])) {
                $data[$field] = null;
                continue;
            }
            $named = User::query()
                ->where('school_id', $person->school_id)
                ->where('role', 'mentor')
                ->whereKey($data[$field])
                ->exists();
            abort_unless($named, 422);
        }
        $person->update($data);
        Audit::record('person.mentor', $person);

        return back();
    }

    public function update(Request $request, Person $person)
    {
        $this->authorize('updateFile', $person);
        $person->update($this->validated($request));
        Audit::record('person.updated', $person);

        return back();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'legal_name' => ['required', 'string', 'max:160'],
            'preferred_name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email'],
            'mobile' => ['nullable', 'string', 'max:40'],
            'postal_address' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:160'],
            'emergency_contact_mobile' => ['nullable', 'string', 'max:40'],
            'neighbourhood' => ['nullable', 'string', 'max:80'],
            'stages' => ['nullable', 'array'],
            'stages.*' => ['string'],
            'right_to_work' => ['nullable', 'string'],
            'heard_about' => ['nullable', 'string', 'max:160'],
            'adjustments' => ['nullable', 'string'],
            'payroll_declaration_lodged' => ['sometimes', 'boolean'],
            'mentor_user_id' => ['nullable', 'integer'],
        ]);
        $data['payroll_declaration_lodged'] = $request->boolean('payroll_declaration_lodged');

        return $data;
    }

    private function mentors()
    {
        return User::query()
            ->where('school_id', auth()->user()->school_id)
            ->where('role', 'mentor')
            ->orderBy('name')
            ->get();
    }
}
