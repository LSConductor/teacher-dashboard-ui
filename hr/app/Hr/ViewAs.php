<?php

namespace App\Hr;

use App\Models\Person;
use App\Models\User;
use Illuminate\Http\Request;

class ViewAs
{
    public const BY = 'viewing_by';

    public const PERSON = 'viewing_person';

    public static function active(?Request $request = null): bool
    {
        $request ??= request();

        return $request->hasSession() && $request->session()->has(self::BY);
    }

    public static function casualFor(Person $person): ?User
    {
        return User::query()
            ->where('school_id', $person->school_id)
            ->where('person_id', $person->id)
            ->orderBy('id')
            ->get()
            ->first(fn (User $user) => Roles::isCasual($user));
    }

    public static function forget(?Request $request = null): void
    {
        ($request ?? request())->session()->forget([self::BY, self::PERSON]);
    }
}
