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
            ->sortBy(fn (User $member) => self::placement($member))
            ->values();
    }

    public static function forNeighbourhood(int $schoolId, ?string $neighbourhood): Collection
    {
        return self::staff($schoolId)
            ->filter(fn (User $member) => self::visibleInNeighbourhood($member, $neighbourhood))
            ->sortBy(fn (User $member) => self::placement($member))
            ->values();
    }

    public static function schoolwide(int $schoolId): Collection
    {
        return self::staff($schoolId)
            ->filter(fn (User $member) => self::isKeyPerson($member))
            ->sortBy(fn (User $member) => self::placement($member))
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

        return Roles::labelFor($member);
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
        $kind = Roles::kind($member);
        if ($kind === 'neighbourhood' && self::sameOrWholeSchool($member->neighbourhood, $person->neighbourhood)) {
            return true;
        }
        if ($kind === 'front_desk') {
            return true;
        }
        if ($person->mentor_user_id && (int) $member->id === (int) $person->mentor_user_id) {
            return true;
        }

        return self::flaggedFor($member, $person->neighbourhood);
    }

    private static function visibleInNeighbourhood(User $member, ?string $neighbourhood): bool
    {
        $kind = Roles::kind($member);
        if ($kind === 'neighbourhood' && self::sameOrWholeSchool($member->neighbourhood, $neighbourhood)) {
            return true;
        }
        if ($kind === 'front_desk') {
            return true;
        }
        if ($kind === 'support' && self::sameOrWholeSchool($member->neighbourhood, $neighbourhood)) {
            return true;
        }

        return self::flaggedFor($member, $neighbourhood);
    }

    private static function isKeyPerson(User $member): bool
    {
        if (in_array(Roles::kind($member), ['neighbourhood', 'front_desk', 'support'], true)) {
            return true;
        }

        return (bool) $member->on_people_page;
    }

    private static function flaggedFor(User $member, ?string $neighbourhood): bool
    {
        if (! $member->on_people_page) {
            return false;
        }

        return self::sameOrWholeSchool($member->neighbourhood, $neighbourhood);
    }

    public static function viewerCanSee(User $viewer, User $member): bool
    {
        if ((int) $viewer->school_id !== (int) $member->school_id || Roles::isCasual($member)) {
            return false;
        }
        $viewerKind = Roles::kind($viewer);
        if ($viewerKind === 'manage') {
            return true;
        }
        if ($viewerKind === 'casual' && $viewer->person_id) {
            $person = Person::query()->where('school_id', $viewer->school_id)->find($viewer->person_id);

            return $person ? self::visibleToPerson($member, $person) : false;
        }
        if ($viewerKind === 'neighbourhood') {
            return self::visibleInNeighbourhood($member, $viewer->neighbourhood);
        }

        return self::isKeyPerson($member);
    }

    private static function sameOrWholeSchool(?string $cardNeighbourhood, ?string $casualNeighbourhood): bool
    {
        if (blank($cardNeighbourhood) || self::coversAll($cardNeighbourhood)) {
            return true;
        }

        return $cardNeighbourhood === $casualNeighbourhood;
    }

    private static function coversAll(?string $neighbourhood): bool
    {
        return in_array(strtolower(trim((string) $neighbourhood)), ['whole school', 'all', 'all neighbourhoods'], true);
    }

    private static function placement(User $member): array
    {
        $placed = (int) $member->people_sort;

        return [
            $placed > 0 ? 0 : 1,
            $placed > 0 ? $placed : self::rank($member),
            $member->name,
        ];
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
