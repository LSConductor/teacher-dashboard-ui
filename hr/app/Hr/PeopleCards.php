<?php

namespace App\Hr;

use App\Models\Booking;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Collection;

class PeopleCards
{
    public static function forPerson(Person $person): Collection
    {
        return self::staff($person->school_id)
            ->filter(fn (User $member) => self::visibleToPerson($member, $person))
            ->sortBy(fn (User $member) => [self::rank($member), $member->name])
            ->values();
    }

    public static function forNeighbourhood(int $schoolId, ?string $neighbourhood): Collection
    {
        return self::staff($schoolId)
            ->filter(fn (User $member) => self::visibleInNeighbourhood($member, $neighbourhood))
            ->sortBy(fn (User $member) => [self::rank($member), $member->name])
            ->values();
    }

    public static function schoolwide(int $schoolId): Collection
    {
        return self::staff($schoolId)
            ->filter(fn (User $member) => self::isKeyPerson($member))
            ->sortBy(fn (User $member) => [self::rank($member), $member->neighbourhood ?? '', $member->name])
            ->values();
    }

    public static function hidden(int $schoolId, Collection $shown): Collection
    {
        $ids = $shown->pluck('id');

        return self::staff($schoolId)
            ->reject(fn (User $member) => $ids->contains($member->id))
            ->sortBy('name')
            ->values();
    }

    public static function visits(?Person $person): Collection
    {
        if (! $person) {
            return collect();
        }

        $today = now()->toDateString();
        $bookings = $person->bookings->sortBy(fn (Booking $booking) => $booking->booked_on->toDateString().'-'.$booking->id);
        $current = $bookings
            ->filter(fn (Booking $booking) => $booking->booked_on->toDateString() === $today);
        if ($current->isNotEmpty()) {
            return self::oneCardPerClass($current);
        }

        $next = $bookings->first(fn (Booking $booking) => $booking->booked_on->toDateString() > $today);
        if (! $next) {
            return collect();
        }

        $nextDate = $next->booked_on->toDateString();

        return self::oneCardPerClass(
            $bookings->filter(fn (Booking $booking) => $booking->booked_on->toDateString() === $nextDate)
        );
    }

    private static function oneCardPerClass(Collection $bookings): Collection
    {
        return $bookings
            ->groupBy('class_label')
            ->map(fn (Collection $rows) => $rows->first(fn (Booking $row) => filled($row->teacher_name)) ?? $rows->first())
            ->values();
    }

    public static function title(User $member): string
    {
        if (filled($member->people_title)) {
            return $member->people_title;
        }

        return $member->role === 'hr' ? 'HR' : ucfirst($member->role);
    }

    public static function note(User $member): string
    {
        if (filled($member->people_note)) {
            return $member->people_note;
        }

        return match ($member->role) {
            'director' => 'Your neighbourhood director. Call if the plan is unclear or a student needs a director.',
            'office' => 'Sign in at the office when you arrive. School day 9:00–3:15.',
            'mentor' => 'Your mentor.',
            default => 'A person to know when you are on site.',
        };
    }

    private static function staff(int $schoolId): Collection
    {
        return User::query()
            ->where('school_id', $schoolId)
            ->where('role', '!=', 'casual')
            ->orderBy('name')
            ->get();
    }

    private static function visibleToPerson(User $member, Person $person): bool
    {
        if ($member->role === 'director' && $member->neighbourhood === $person->neighbourhood) {
            return true;
        }
        if ($member->role === 'office') {
            return true;
        }
        if ($person->mentor_user_id && (int) $member->id === (int) $person->mentor_user_id) {
            return true;
        }

        return self::flaggedFor($member, $person->neighbourhood);
    }

    private static function visibleInNeighbourhood(User $member, ?string $neighbourhood): bool
    {
        if ($member->role === 'director' && $member->neighbourhood === $neighbourhood) {
            return true;
        }
        if ($member->role === 'office') {
            return true;
        }
        if ($member->role === 'mentor' && (blank($member->neighbourhood) || $member->neighbourhood === $neighbourhood)) {
            return true;
        }

        return self::flaggedFor($member, $neighbourhood);
    }

    private static function isKeyPerson(User $member): bool
    {
        if (in_array($member->role, ['director', 'office', 'mentor'], true)) {
            return true;
        }

        return (bool) $member->on_people_page;
    }

    private static function flaggedFor(User $member, ?string $neighbourhood): bool
    {
        if (! $member->on_people_page) {
            return false;
        }

        return blank($member->neighbourhood) || $member->neighbourhood === $neighbourhood;
    }

    private static function rank(User $member): int
    {
        return match ($member->role) {
            'director' => 0,
            'office' => 1,
            'mentor' => 2,
            'conductor' => 3,
            'hr' => 4,
            default => 5,
        };
    }
}
