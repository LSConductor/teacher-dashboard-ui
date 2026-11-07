<?php

namespace App\Http\Controllers;

use App\Hr\ContractDesk;
use App\Hr\Roles;
use App\Models\Person;
use App\Services\Audit;
use App\Services\ClearToTeach;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function store(Request $request, Person $person, ClearToTeach $gate)
    {
        $this->authorize('view', $person);
        abort_unless(in_array(Roles::kind($request->user()), ['manage', 'front_desk', 'neighbourhood'], true), 403);

        $data = $request->validate([
            'booked_on' => ['required', 'date'],
            'class_label' => ['required', 'string', 'max:120'],
            'teacher_name' => ['nullable', 'string', 'max:120'],
            'plan_url' => ['nullable', 'url'],
            'external_program_id' => ['nullable', 'string', 'max:80'],
            'conductor_note' => ['nullable', 'string'],
            'start_time' => ['nullable', 'string', 'max:20'],
            'finish_time' => ['nullable', 'string', 'max:20'],
        ]);

        $assessment = $gate->assess($person);
        if ($assessment['light'] === 'red') {
            return back()->withErrors(['booking' => 'This casual is red and cannot be booked. There is no override.']);
        }
        if ($assessment['light'] === 'amber') {
            if ($request->user()->role !== 'conductor' || blank($data['conductor_note'] ?? null)) {
                return back()->withErrors(['booking' => 'An amber casual can be booked only when the Conductor writes a note on this booking.']);
            }
        }

        $booking = $person->bookings()->create([
            'school_id' => $person->school_id,
            'booked_on' => $data['booked_on'],
            'class_label' => $data['class_label'],
            'teacher_name' => $data['teacher_name'] ?? null,
            'plan_url' => $data['plan_url'] ?? null,
            'external_program_id' => $data['external_program_id'] ?? null,
            'program_source' => 'wordpress',
            'light_at_booking' => $assessment['light'],
            'conductor_note' => $data['conductor_note'] ?? null,
            'created_by' => $request->user()->id,
        ]);
        Audit::record('booking.created', $booking, ['light' => $assessment['light']]);

        ContractDesk::issue($person, 'day-booking', array_filter([
            'class_label' => $data['class_label'],
            'booked_on' => $data['booked_on'],
            'start_time' => $data['start_time'] ?? null,
            'finish_time' => $data['finish_time'] ?? null,
        ]));

        return back()->with('status', 'Booked. The one-day confirmation is with the contracts.');
    }
}
