<?php

namespace App\Http\Controllers;

use App\Hr\PeopleCards;
use App\Models\Person;
use Illuminate\Http\Request;

class PeopleController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $managing = in_array($user->role, ['hr', 'conductor'], true);
        $person = null;
        if ($user->role === 'casual' && $user->person_id) {
            $person = Person::query()
                ->where('school_id', $user->school_id)
                ->with('bookings')
                ->find($user->person_id);
        }

        if ($person) {
            $cards = PeopleCards::forPerson($person);
            $hidden = collect();
        } elseif ($user->role === 'director') {
            $cards = PeopleCards::forNeighbourhood((int) $user->school_id, $user->neighbourhood);
            $hidden = collect();
        } else {
            $cards = PeopleCards::schoolwide((int) $user->school_id);
            $hidden = $managing ? PeopleCards::hidden((int) $user->school_id, $cards) : collect();
        }

        return view('people.index', [
            'cards' => $cards,
            'hidden' => $hidden,
            'visits' => PeopleCards::visits($person),
            'managing' => $managing,
            'person' => $person,
        ]);
    }
}
