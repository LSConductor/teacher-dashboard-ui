<?php

namespace App\Http\Controllers;

use App\Hr\BookingRefused;
use App\Hr\BookingService;
use App\Models\Booking;
use App\Models\Person;
use App\Support\Sydney;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookings) {}

    public function store(Request $request, Person $person): RedirectResponse
    {
        $this->authorize('book', $person);

        $data = $request->validate([
            'on_date' => ['required', 'date', 'after_or_equal:today'],
            'class_or_group' => ['required', 'string', 'max:120'],
            'wordpress_program_id' => ['nullable', 'regex:/^\d+$/'],
        ]);

        $data['wordpress_program_id'] = $data['wordpress_program_id'] ?? null;

        try {
            $booking = $this->bookings->open(
                $request->user(),
                $person,
                $data,
                $request->boolean('override'),
            );
        } catch (BookingRefused $e) {
            return back()->withInput()->withErrors(['booking' => $e->getMessage()]);
        }

        $message = $booking->status === 'booked'
            ? $person->preferred_name.' is booked for '.$booking->class_or_group.' on '.$booking->on_date->timezone(Sydney::timezone())->format('j M Y').'.'
            : 'Cover for '.$person->preferred_name.' is held, not booked. A Conductor note has to be saved on this booking before it can be confirmed.';

        $redirect = back()->with('status', $message);

        if ($request->filled('conductor_note')) {
            $redirect->with('note_ignored', 'A Conductor note is saved on its own, before the booking is confirmed. The note in this request was not kept.');
        }

        return $redirect;
    }

    public function note(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorize('note', $booking);

        $data = $request->validate([
            'conductor_note' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $this->bookings->addConductorNote($request->user(), $booking, $data['conductor_note']);
        } catch (BookingRefused $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return back()->with('status', 'Conductor note saved on the booking.');
    }

    public function confirm(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorize('confirm', $booking);

        try {
            $booking = $this->bookings->confirm($request->user(), $booking, $request->boolean('override'));
        } catch (BookingRefused $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return back()->with('status', $booking->person->preferred_name.' is booked.');
    }

    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorize('cancel', $booking);
        $this->bookings->cancel($request->user(), $booking);

        return back()->with('status', 'Booking cancelled.');
    }
}
