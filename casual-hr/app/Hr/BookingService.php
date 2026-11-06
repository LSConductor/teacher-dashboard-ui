<?php

namespace App\Hr;

use App\Models\Booking;
use App\Models\Person;
use App\Models\User;
use App\Support\Sydney;
use Illuminate\Database\UniqueConstraintViolationException;

class BookingService
{
    public function __construct(
        private readonly ClearToTeach $lights,
        private readonly AuditLog $audit,
    ) {}

    /**
     * @param  array{on_date: string, class_or_group: string, wordpress_program_id?: ?string}  $data
     */
    public function open(User $actor, Person $person, array $data, bool $overrideAttempt = false): Booking
    {
        $onDate = Sydney::date($data['on_date'])->toDateString();
        $light = $this->lights->assess($person);

        if ($overrideAttempt) {
            $this->audit->record($actor, 'booking.override_refused', $person, $person, [
                'light' => $light->colour,
                'on_date' => $onDate,
            ]);
        }

        if ($light->isRed()) {
            $this->audit->record($actor, 'booking.refused_red', $person, $person, [
                'light' => TeachingLight::RED,
                'reasons' => $light->reasons,
                'on_date' => $onDate,
                'override_attempt' => $overrideAttempt,
            ]);

            throw new BookingRefused('This casual is red and cannot be booked. A red light cannot be overridden.');
        }

        $existing = Booking::query()
            ->where('person_id', $person->id)
            ->whereDate('on_date', $onDate)
            ->where('status', '!=', 'cancelled')
            ->first();

        if ($existing?->status === 'booked') {
            throw new BookingRefused('This casual is already booked on that day.');
        }

        if ($light->isGreen()) {
            if ($existing) {
                return $this->confirmExisting($actor, $existing, $light);
            }

            return $this->insert($actor, $person, $data, $onDate, 'booked', $light);
        }

        if ($existing) {
            return $existing;
        }

        return $this->insert($actor, $person, $data, $onDate, 'held', $light);
    }

    public function addConductorNote(User $actor, Booking $booking, string $note): Booking
    {
        $light = $this->lights->assess($booking->person);

        if ($light->isRed()) {
            $this->audit->record($actor, 'booking.override_refused', $booking, $booking->person, [
                'light' => TeachingLight::RED,
                'reasons' => $light->reasons,
            ]);

            throw new BookingRefused('A Conductor note cannot override a red light.');
        }

        $booking->update([
            'conductor_note' => $note,
            'conductor_note_by' => $actor->id,
            'conductor_note_at' => Sydney::now(),
        ]);

        $this->audit->record($actor, 'booking.conductor_note', $booking, $booking->person, [
            'on_date' => $booking->on_date->toDateString(),
        ]);

        return $booking;
    }

    public function confirm(User $actor, Booking $booking, bool $overrideAttempt = false): Booking
    {
        $person = $booking->person()->firstOrFail();
        $light = $this->lights->assess($person);

        if ($overrideAttempt) {
            $this->audit->record($actor, 'booking.override_refused', $booking, $person, [
                'light' => $light->colour,
            ]);
        }

        if ($light->isRed()) {
            $this->audit->record($actor, 'booking.refused_red', $booking, $person, [
                'light' => TeachingLight::RED,
                'reasons' => $light->reasons,
                'override_attempt' => $overrideAttempt,
            ]);

            throw new BookingRefused('This casual is red and cannot be booked. A red light cannot be overridden.');
        }

        if ($light->isAmber() && ! filled($booking->conductor_note)) {
            throw new BookingRefused('Amber can be booked only when a Conductor note is already on the booking.');
        }

        return $this->confirmExisting($actor, $booking, $light);
    }

    public function cancel(User $actor, Booking $booking): void
    {
        if ($booking->status === 'cancelled') {
            return;
        }

        $booking->update(['status' => 'cancelled']);

        $this->audit->record($actor, 'booking.cancelled', $booking, $booking->person, [
            'on_date' => $booking->on_date->toDateString(),
        ]);
    }

    private function confirmExisting(User $actor, Booking $booking, TeachingLight $light): Booking
    {
        if ($light->isAmber() && ! filled($booking->conductor_note)) {
            throw new BookingRefused('Amber can be booked only when a Conductor note is already on the booking.');
        }

        $booking->update([
            'status' => 'booked',
            'light_at_booking' => $light->colour,
            'booked_by' => $actor->id,
            'booked_at' => Sydney::now(),
        ]);

        $this->audit->record($actor, 'booking.confirmed', $booking, $booking->person, [
            'light' => $light->colour,
            'on_date' => $booking->on_date->toDateString(),
            'wordpress_program_id' => $booking->wordpress_program_id,
        ]);

        return $booking;
    }

    /**
     * @param  array{on_date: string, class_or_group: string, wordpress_program_id?: ?string}  $data
     */
    private function insert(User $actor, Person $person, array $data, string $onDate, string $status, TeachingLight $light): Booking
    {
        $attributes = [
            'school_id' => $person->school_id,
            'person_id' => $person->id,
            'neighbourhood_id' => $person->neighbourhood_id,
            'on_date' => $onDate,
            'class_or_group' => $data['class_or_group'],
            'director_user_id' => $person->neighbourhood?->director?->id,
            'wordpress_program_id' => $data['wordpress_program_id'] ?? null,
            'light_at_booking' => $status === 'booked' ? $light->colour : null,
            'status' => $status,
            'booked_by' => $status === 'booked' ? $actor->id : null,
            'booked_at' => $status === 'booked' ? Sydney::now() : null,
            'conductor_note' => null,
        ];

        try {
            $booking = Booking::create($attributes);
        } catch (UniqueConstraintViolationException) {
            throw new BookingRefused('This casual is already booked on that day.');
        }

        if ($status === 'booked') {
            $this->audit->record($actor, 'booking.confirmed', $booking, $person, [
                'light' => $light->colour,
                'on_date' => $onDate,
                'wordpress_program_id' => $booking->wordpress_program_id,
            ]);
        } else {
            $this->audit->record($actor, 'booking.held', $booking, $person, [
                'light' => $light->colour,
                'on_date' => $onDate,
            ]);
        }

        return $booking;
    }
}
