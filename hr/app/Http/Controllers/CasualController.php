<?php

namespace App\Http\Controllers;

use App\Models\InductionModule;
use App\Models\Person;
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

        if ($user->role === 'casual') {
            $query->where('id', $user->person_id);
        } elseif ($user->role === 'director') {
            $query->where('neighbourhood', $user->neighbourhood);
        } elseif ($user->role === 'mentor') {
            $query->where('mentor_user_id', $user->id);
        }

        if ($request->filled('neighbourhood') && in_array($user->role, ['hr', 'conductor', 'office'], true)) {
            $query->where('neighbourhood', $request->string('neighbourhood'));
        }
        if ($request->filled('q')) {
            $term = '%'.$request->string('q').'%';
            $query->where(function ($inner) use ($term) {
                $inner->where('preferred_name', 'like', $term)->orWhere('legal_name', 'like', $term);
            });
        }

        $dayOne = InductionModule::query()
            ->where('school_id', $user->school_id)
            ->where('phase', 'day_one')
            ->get(['id', 'version']);
        $directors = User::query()
            ->where('school_id', $user->school_id)
            ->where('role', 'director')
            ->get()
            ->keyBy('neighbourhood');

        $people = $query->orderBy('preferred_name')->get()->map(function (Person $person) use ($gate, $dayOne, $directors) {
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
            $person->last_day = $person->bookings->sortByDesc('booked_on')->first()?->booked_on;

            return $person;
        });

        $sensitive = in_array($user->role, ['hr', 'conductor'], true);

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
                    'expiring' => $person->wwcc?->expires_on && $person->wwcc->expires_on->lte(now()->addDays(30)),
                    default => true,
                };
            })->values();
        }

        return view('casuals.index', [
            'people' => $people,
            'sensitive' => $sensitive,
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
        ]);
        $modules = InductionModule::query()
            ->where('school_id', $person->school_id)
            ->orderBy('sort')
            ->get();

        return view('casuals.show', [
            'person' => $person,
            'gate' => $gate->assess($person),
            'modules' => $modules,
            'mentors' => $this->mentors(),
            'sensitive' => request()->user()->can('viewSensitive', $person),
            'canEdit' => request()->user()->can('updateFile', $person),
        ]);
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
