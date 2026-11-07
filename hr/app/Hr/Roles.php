<?php

namespace App\Hr;

use App\Models\StaffRole;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Roles
{
    public const KINDS = [
        'manage' => 'Manage the school. This role opens Setup, contracts, audit, and staff.',
        'neighbourhood' => 'Lead a neighbourhood. This role sees that neighbourhood’s casuals. A neighbourhood is required.',
        'support' => 'Support on site. This role sees the casuals they mentor.',
        'front_desk' => 'Front desk. This role sees every casual, and the card shows for the whole school.',
    ];

    /** @var array<int, Collection<string, StaffRole>> */
    private static array $cache = [];

    public static function ensure(int $schoolId): void
    {
        foreach (self::defaults() as $role) {
            StaffRole::query()->firstOrCreate(
                ['school_id' => $schoolId, 'slug' => $role['slug']],
                ['name' => $role['name'], 'kind' => $role['kind'], 'sort' => $role['sort']]
            );
        }
        self::forget($schoolId);
    }

    public static function forget(int $schoolId): void
    {
        unset(self::$cache[$schoolId]);
    }

    public static function forgetAll(): void
    {
        self::$cache = [];
    }

    public static function kind(User $user): string
    {
        return self::kindOf((int) $user->school_id, (string) $user->role);
    }

    public static function kindOf(int $schoolId, string $slug): string
    {
        $row = self::rows($schoolId)->get($slug);

        return $row?->kind ?? self::builtinKind($slug);
    }

    public static function manages(User $user): bool
    {
        return self::kind($user) === 'manage';
    }

    public static function isCasual(User $user): bool
    {
        return self::kind($user) === 'casual';
    }

    public static function labelFor(User $user): string
    {
        $row = self::rows((int) $user->school_id)->get($user->role);
        if ($row) {
            return $row->name;
        }

        return $user->role === 'hr' ? 'HR' : ucfirst((string) $user->role);
    }

    public static function slugsOfKind(int $schoolId, string $kind): array
    {
        return self::rows($schoolId)
            ->filter(fn (StaffRole $role) => $role->kind === $kind)
            ->keys()
            ->values()
            ->all();
    }

    public static function choices(int $schoolId, bool $includeCasual = false): Collection
    {
        return self::rows($schoolId)
            ->filter(fn (StaffRole $role) => $includeCasual || $role->kind !== 'casual')
            ->sortBy(fn (StaffRole $role) => sprintf('%05d-%s', $role->sort, $role->name))
            ->values();
    }

    public static function kindLabel(string $kind): string
    {
        return match ($kind) {
            'manage' => 'Manage the school',
            'neighbourhood' => 'Lead a neighbourhood',
            'support' => 'Support on site',
            'front_desk' => 'Front desk',
            'casual' => 'Casual teacher',
            default => ucfirst($kind),
        };
    }

    public static function slugFromName(int $schoolId, string $name): ?string
    {
        $slug = Str::slug($name);
        if ($slug === '' || strlen($slug) > 40) {
            return null;
        }
        if (self::rows($schoolId)->has($slug)) {
            return null;
        }

        return $slug;
    }

    /** @return list<array{slug: string, name: string, kind: string, sort: int}> */
    public static function defaults(): array
    {
        return [
            ['slug' => 'hr', 'name' => 'HR', 'kind' => 'manage', 'sort' => 1],
            ['slug' => 'conductor', 'name' => 'Conductor', 'kind' => 'manage', 'sort' => 2],
            ['slug' => 'director', 'name' => 'Director', 'kind' => 'neighbourhood', 'sort' => 3],
            ['slug' => 'mentor', 'name' => 'Mentor', 'kind' => 'support', 'sort' => 4],
            ['slug' => 'office', 'name' => 'Office', 'kind' => 'front_desk', 'sort' => 5],
            ['slug' => 'casual', 'name' => 'Casual', 'kind' => 'casual', 'sort' => 6],
        ];
    }

    /** @return Collection<string, StaffRole> */
    private static function rows(int $schoolId): Collection
    {
        if (! array_key_exists($schoolId, self::$cache)) {
            $found = StaffRole::query()->where('school_id', $schoolId)->get();
            if ($found->isEmpty()) {
                self::ensure($schoolId);
                $found = StaffRole::query()->where('school_id', $schoolId)->get();
            }
            self::$cache[$schoolId] = $found->keyBy('slug');
        }

        return self::$cache[$schoolId];
    }

    private static function builtinKind(string $slug): string
    {
        return match ($slug) {
            'hr', 'conductor' => 'manage',
            'director' => 'neighbourhood',
            'office' => 'front_desk',
            'casual' => 'casual',
            default => 'support',
        };
    }
}
