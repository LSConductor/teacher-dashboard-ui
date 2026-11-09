<?php

namespace App\Hr;

use App\Models\DayCode;
use App\Models\DayRoll;
use App\Models\Person;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Validation\ValidationException;

class Roll
{
    public static function today(): string
    {
        return now()->timezone('Australia/Sydney')->toDateString();
    }

    public static function code(int $schoolId): DayCode
    {
        $existing = DayCode::query()
            ->where('school_id', $schoolId)
            ->whereDate('on_date', self::today())
            ->first();
        if ($existing) {
            return $existing;
        }

        return DayCode::query()->create([
            'school_id' => $schoolId,
            'on_date' => self::today(),
            'code' => self::freshCode(),
        ]);
    }

    public static function label(?DayRoll $roll): string
    {
        if (! $roll || ! $roll->signed_on_at) {
            return 'Not signed on';
        }
        $clock = fn ($at) => $at->timezone('Australia/Sydney')->format('g:i a');
        if ($roll->signed_off_at) {
            return 'Signed off '.$clock($roll->signed_off_at);
        }
        if ($roll->closed_at) {
            return 'Signed on '.$clock($roll->signed_on_at).'. Closed by the office';
        }

        return 'Signed on '.$clock($roll->signed_on_at);
    }

    public static function arrive(Person $person, User $actor, ?string $code, bool $atDesk): DayRoll
    {
        self::guard($person, $actor, $code, $atDesk);
        $roll = self::row($person);
        if ($roll->signed_on_at) {
            throw ValidationException::withMessages(['roll' => 'Already signed on.']);
        }
        $roll->update(['signed_on_at' => now(), 'signed_on_by' => $actor->id]);
        Audit::record($atDesk ? 'day.recorded' : 'day.signed_on', $roll);

        return $roll;
    }

    public static function leave(Person $person, User $actor, ?string $code, bool $atDesk): DayRoll
    {
        self::guard($person, $actor, $code, $atDesk);
        $roll = self::row($person);
        if (! $roll->signed_on_at) {
            throw ValidationException::withMessages(['roll' => 'Sign on first.']);
        }
        if ($roll->signed_off_at) {
            throw ValidationException::withMessages(['roll' => 'Already signed off.']);
        }
        if ($roll->closed_at) {
            throw ValidationException::withMessages(['roll' => 'The office has closed this day.']);
        }
        $roll->update(['signed_off_at' => now(), 'signed_off_by' => $actor->id]);
        Audit::record($atDesk ? 'day.recorded' : 'day.signed_off', $roll);

        return $roll;
    }

    public static function close(Person $person, User $actor, string $note): DayRoll
    {
        if (! in_array(Roles::kind($actor), ['manage', 'front_desk'], true)) {
            abort(403);
        }
        if (! self::booked($person)) {
            throw ValidationException::withMessages(['roll' => 'They are not booked today.']);
        }
        $roll = self::row($person);
        if (! $roll->signed_on_at) {
            throw ValidationException::withMessages(['roll' => 'There is no arrival to close.']);
        }
        if ($roll->signed_off_at) {
            throw ValidationException::withMessages(['roll' => 'This day is already signed off.']);
        }
        if ($roll->closed_at) {
            throw ValidationException::withMessages(['roll' => 'This day is already closed.']);
        }
        $roll->update([
            'closed_at' => now(),
            'closed_by' => $actor->id,
            'close_note' => $note,
        ]);
        Audit::record('day.closed', $roll);

        return $roll;
    }

    private static function guard(Person $person, User $actor, ?string $code, bool $atDesk): void
    {
        if ($atDesk && ! in_array(Roles::kind($actor), ['manage', 'front_desk'], true)) {
            abort(403);
        }
        if ((int) $actor->school_id !== (int) $person->school_id) {
            abort(404);
        }
        if (! self::booked($person)) {
            throw ValidationException::withMessages(['roll' => 'They are not booked today.']);
        }
        if ($atDesk) {
            return;
        }
        $expected = DayCode::query()
            ->where('school_id', $person->school_id)
            ->whereDate('on_date', self::today())
            ->value('code');
        $given = strtoupper((string) preg_replace('/\s+/', '', (string) $code));
        $expected = strtoupper((string) $expected);
        if ($expected === '' || strlen($given) !== strlen($expected) || ! hash_equals($expected, $given)) {
            Audit::record('day.rejected', $person);

            throw ValidationException::withMessages(['code' => 'That is not the office code for today.']);
        }
    }

    private static function booked(Person $person): bool
    {
        return $person->bookings()->whereDate('booked_on', self::today())->exists();
    }

    private static function row(Person $person): DayRoll
    {
        $existing = DayRoll::query()
            ->where('person_id', $person->id)
            ->whereDate('on_date', self::today())
            ->first();
        if ($existing) {
            return $existing;
        }

        return DayRoll::query()->create([
            'person_id' => $person->id,
            'school_id' => $person->school_id,
            'on_date' => self::today(),
        ]);
    }

    private static function freshCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }
}
