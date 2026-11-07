<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Services\Audit;
use App\Services\ClearToTeach;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function store(Request $request, Person $person, ClearToTeach $gate)
    {
        $this->authorize('view', $person);
        abort_unless(in_array($request->user()->role, ['hr', 'conductor', 'office', 'director'], true), 403);

        $data = $request->validate([
            'booked_on' => ['required', 'date'],
            'class_label' => ['required', 'string', 'max:120'],
            'teacher_name' => ['nullable', 'string', 'max:120'],
            'plan_url' => ['nullable', 'url'],
            'external_program_id' => ['nullable', 'string', 'max:80'],
            'conductor_note' => ['nullable', 'string'],
        ]);

        $assessment = $gate->assess($person);
        if ($assessment['light'] === 'red') {
            return back()->withErrors(['booking' => 'This casual is red and cannot be booked. WWCC and accreditation cannot be overridden.']);
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

        return back();
    }
}
