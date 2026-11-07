<?php

namespace App\Hr;

use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class Neighbourhood
{
    public static function coversAll(?string $name): bool
    {
        return in_array(strtolower(trim((string) $name)), ['whole school', 'all', 'all neighbourhoods'], true);
    }

    public static function teaches(Person $person, ?string $neighbourhood): bool
    {
        if (self::coversAll($neighbourhood)) {
            return true;
        }
        $neighbourhood = trim((string) $neighbourhood);
        if ($neighbourhood === '') {
            return false;
        }
        if ($person->neighbourhood === $neighbourhood) {
            return true;
        }

        return in_array($neighbourhood, $person->neighbourhoods ?? [], true);
    }

    public static function restrict(Builder $query, string $neighbourhood): void
    {
        $query->where(function (Builder $inner) use ($neighbourhood) {
            $inner->where('neighbourhood', $neighbourhood)
                ->orWhereJsonContains('neighbourhoods', $neighbourhood);
        });
    }

    /** @return list<string> */
    public static function names(int $schoolId): array
    {
        $home = Person::query()
            ->where('school_id', $schoolId)
            ->whereNotNull('neighbourhood')
            ->pluck('neighbourhood');
        $listed = Person::query()
            ->where('school_id', $schoolId)
            ->pluck('neighbourhoods')
            ->flatten();
        $staff = User::query()
            ->where('school_id', $schoolId)
            ->whereNotNull('neighbourhood')
            ->pluck('neighbourhood');

        return $home->merge($listed)->merge($staff)
            ->map(fn ($name) => trim((string) $name))
            ->filter(fn ($name) => $name !== '' && ! self::coversAll($name))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
