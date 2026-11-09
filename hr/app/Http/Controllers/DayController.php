<?php

namespace App\Http\Controllers;

use App\Hr\Neighbourhood;
use App\Hr\Roles;
use App\Hr\Roll;
use App\Models\Booking;
use App\Models\CoverRequest;
use App\Models\DayRoll;
use App\Models\Person;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\Request;

class DayController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $kind = Roles::kind($user);
        $holdsCode = in_array($kind, ['manage', 'front_desk'], true);
        $bookings = Booking::query()
            ->where('school_id', $user->school_id)
            ->whereDate('booked_on', Roll::today())
            ->with('person')
            ->orderBy('class_label')
            ->get();

        if ($kind === 'casual') {
            $bookings = $bookings->where('person_id', $user->person_id)->values();
        } elseif ($kind === 'neighbourhood' && ! Neighbourhood::coversAll($user->neighbourhood)) {
            $bookings = $bookings->filter(
                fn (Booking $booking) => Neighbourhood::teaches($booking->person, $user->neighbourhood)
            )->values();
        } elseif ($kind === 'support') {
            $bookings = $bookings->filter(
                fn (Booking $booking) => (int) $booking->person->mentor_user_id === (int) $user->id
            )->values();
        }

        $rolls = DayRoll::query()
            ->where('school_id', $user->school_id)
            ->whereDate('on_date', Roll::today())
            ->get()
            ->keyBy('person_id');
        $names = User::query()
            ->where('school_id', $user->school_id)
            ->pluck('name', 'id');

        $rows = $bookings->groupBy('person_id')->map(function ($day) use ($rolls, $names) {
            $person = $day->first()->person;
            $roll = $rolls->get($person->id);

            return [
                'person' => $person,
                'classes' => $day->pluck('class_label')->unique()->implode(', '),
                'roll' => $roll,
                'label' => Roll::label($roll),
                'signed_on_by' => $roll?->signed_on_by ? $names[$roll->signed_on_by] ?? null : null,
                'closed_by' => $roll?->closed_by ? $names[$roll->closed_by] ?? null : null,
            ];
        })->values();

        $waiting = collect();
        if (in_array($kind, ['manage', 'front_desk', 'neighbourhood'], true)) {
            $waiting = CoverRequest::query()
                ->where('school_id', $user->school_id)
                ->where('pool', 'casual')
                ->where('status', 'open')
                ->with(['asks.person'])
                ->orderBy('booked_on')
                ->orderBy('id');
            if ($kind === 'neighbourhood' && ! Neighbourhood::coversAll($user->neighbourhood)) {
                $waiting->where('neighbourhood', $user->neighbourhood);
            }
            $waiting = $waiting->get();
        }

        return view('today.index', [
            'rows' => $rows,
            'waiting' => $waiting,
            'code' => $holdsCode ? Roll::code((int) $user->school_id)->code : null,
            'holdsCode' => $holdsCode,
            'canRecord' => $holdsCode,
            'incidentDesk' => School::query()->whereKey($user->school_id)->value('incident_desk_url'),
            'ownPerson' => (int) $user->person_id,
        ]);
    }

    public function arrive(Request $request, Person $person)
    {
        $this->authorize('view', $person);

        Roll::arrive($person, $request->user(), $request->input('code'), $request->boolean('at_desk'));

        return back()->with('status', $person->preferred_name.' is signed on.');
    }

    public function leave(Request $request, Person $person)
    {
        $this->authorize('view', $person);

        Roll::leave($person, $request->user(), $request->input('code'), $request->boolean('at_desk'));

        return back()->with('status', $person->preferred_name.' is signed off.');
    }

    public function close(Request $request, Person $person)
    {
        $this->authorize('view', $person);
        $data = $request->validate(['close_note' => ['required', 'string', 'max:280']]);

        Roll::close($person, $request->user(), $data['close_note']);

        return back()->with('status', 'The day is closed.');
    }
}
