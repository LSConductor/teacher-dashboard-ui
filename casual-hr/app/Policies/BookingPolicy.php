<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function note(User $user, Booking $booking): bool
    {
        return $user->role === User::CONDUCTOR
            && (int) $user->school_id === (int) $booking->school_id;
    }

    public function confirm(User $user, Booking $booking): bool
    {
        $person = $booking->person;

        return $person !== null && (new PersonPolicy)->book($user, $person);
    }

    public function cancel(User $user, Booking $booking): bool
    {
        return $this->confirm($user, $booking);
    }
}
