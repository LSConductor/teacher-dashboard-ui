<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Person;
use Illuminate\Http\Request;

class CasualLetterController extends Controller
{
    public function show(Request $request, Person $person)
    {
        abort_unless((int) $person->school_id === (int) $request->user()->school_id, 404);
        $this->authorize('updateFile', $person);
        $person->load('accreditation');

        $end = now()->timezone('Australia/Sydney')->startOfDay();
        $start = $end->copy()->subYears(7);
        $days = Booking::query()
            ->where('person_id', $person->id)
            ->whereDate('booked_on', '>=', $start->toDateString())
            ->whereDate('booked_on', '<=', $end->toDateString())
            ->orderBy('booked_on')
            ->get();

        return view('casuals.letter', [
            'person' => $person,
            'days' => $days,
            'start' => $start,
            'end' => $end,
        ]);
    }
}
