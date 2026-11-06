<?php

namespace App\Hr;

use App\Models\Person;
use App\Support\Phone;
use App\Support\Sydney;

class DirectorContactCard
{
    /**
     * @param  list<string>  $stages
     * @param  list<string>  $reasons
     */
    public function __construct(
        public readonly int $personId,
        public readonly string $name,
        public readonly string $mobile,
        public readonly string $email,
        public readonly array $stages,
        public readonly ?string $todaysBooking,
        public readonly ?int $todayBookingId,
        public readonly string $light,
        public readonly array $reasons,
        public readonly ?string $mentorName,
        public readonly string $neighbourhood,
        public readonly ?int $holdId,
        public readonly bool $holdHasConductorNote,
        public readonly ?string $conductorNote,
    ) {}

    public static function for(Person $person, ClearToTeach $lights): self
    {
        $today = Sydney::today()->toDateString();
        $light = $lights->assess($person);
        $bookedToday = $person->bookings
            ->filter(fn ($booking) => $booking->status === 'booked' && $booking->on_date->toDateString() === $today);
        $booked = $bookedToday->pluck('class_or_group')->implode(', ');
        $hold = $person->bookings->first(
            fn ($booking) => $booking->status === 'held' && $booking->on_date->toDateString() === $today,
        );

        return new self(
            personId: $person->id,
            name: $person->preferred_name,
            mobile: $person->mobile,
            email: $person->email,
            stages: $person->stages ?? [],
            todaysBooking: $booked !== '' ? $booked : null,
            todayBookingId: $bookedToday->count() === 1 ? $bookedToday->first()->id : null,
            light: $light->colour,
            reasons: $light->reasonLabels(),
            mentorName: $person->mentorship?->mentor?->preferred_name,
            neighbourhood: $person->neighbourhood?->name ?? '',
            holdId: $hold?->id,
            holdHasConductorNote: filled($hold?->conductor_note),
            conductorNote: $hold?->conductor_note,
        );
    }

    /**
     * Fields a Director is allowed to see.
     *
     * @return array<string, mixed>
     */
    public function visible(): array
    {
        return [
            'name' => $this->name,
            'mobile' => $this->mobile,
            'email' => $this->email,
            'stages' => $this->stages,
            'todays_booking' => $this->todaysBooking,
            'light' => $this->light,
            'mentor_name' => $this->mentorName,
        ];
    }

    public function mobileDisplay(): string
    {
        return Phone::display($this->mobile);
    }

    public function tel(): string
    {
        return Phone::tel($this->mobile);
    }

    public function word(): string
    {
        return match ($this->light) {
            TeachingLight::GREEN => 'Green',
            TeachingLight::AMBER => 'Amber',
            default => 'Red',
        };
    }
}
