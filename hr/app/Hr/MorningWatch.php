<?php

namespace App\Hr;

use App\Models\Person;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class MorningWatch
{
    /**
     * @return Collection<int, array{person: Person, level: string, label: string, text: string}>
     */
    public static function lines(User $user): Collection
    {
        $query = Person::query()
            ->where('school_id', $user->school_id)
            ->whereNull('archived_at')
            ->with(['wwcc', 'accreditation', 'standardsNotices'])
            ->orderBy('preferred_name');

        $kind = Roles::kind($user);
        if ($kind === 'neighbourhood') {
            if (! Neighbourhood::coversAll($user->neighbourhood)) {
                if (blank($user->neighbourhood)) {
                    $query->whereRaw('0 = 1');
                } else {
                    Neighbourhood::restrict($query, $user->neighbourhood);
                }
            }
        } elseif ($kind === 'support') {
            $query->where('mentor_user_id', $user->id);
        }

        $lines = collect();
        foreach ($query->get() as $person) {
            $wwcc = self::soon($person->wwcc?->expires_on);
            if ($wwcc) {
                $when = $person->wwcc?->expires_on?->timezone('Australia/Sydney')->format('j M Y');
                $lines->push(self::line($person, $wwcc, 'WWCC', $when));
            }
            $maintenance = self::soon($person->accreditation?->maintenance_end);
            if ($maintenance) {
                $when = $person->accreditation?->maintenance_end?->timezone('Australia/Sydney')->format('j M Y');
                $lines->push(self::line($person, $maintenance, 'Accreditation maintenance', $when));
            }
            if (Roles::manages($user)) {
                foreach ($person->standardsNotices as $notice) {
                    if ($notice->reply_on) {
                        continue;
                    }
                    $days = self::days($notice->due_on);
                    $lines->push([
                        'person' => $person,
                        'level' => $days !== null && $days <= 7 ? 'red' : 'amber',
                        'label' => $days !== null && $days < 0 ? 'Reply overdue' : ($days !== null && $days <= 7 ? 'This week' : 'Reply due'),
                        'text' => '28-day reply is due '.$notice->due_on->timezone('Australia/Sydney')->format('j M Y').'. Told '.$notice->told_on->timezone('Australia/Sydney')->format('j M Y').'.',
                    ]);
                }
            }
        }

        return $lines->sortBy([
            fn ($line) => $line['level'] === 'red' ? 0 : 1,
            fn ($line) => $line['person']->preferred_name,
        ])->values();
    }

    /**
     * @param  array{level: string, label: string, text: string}  $soon
     * @return array{person: Person, level: string, label: string, text: string}
     */
    private static function line(Person $person, array $soon, string $what, ?string $when): array
    {
        return [
            'person' => $person,
            'level' => $soon['level'],
            'label' => $soon['label'],
            'text' => $when ? $what.' '.$soon['text'].', '.$when.'.' : $what.' '.$soon['text'].'.',
        ];
    }

    /**
     * @return array{level: string, label: string, text: string}|null
     */
    private static function soon(?CarbonInterface $date): ?array
    {
        if (! $date) {
            return ['level' => 'red', 'label' => 'Missing', 'text' => 'date is missing'];
        }
        $days = self::days($date);
        if ($days === null) {
            return null;
        }
        if ($days < 0) {
            return ['level' => 'red', 'label' => 'Lapsed', 'text' => 'has lapsed'];
        }
        if ($days <= 7) {
            return ['level' => 'red', 'label' => 'This week', 'text' => 'is due this week'];
        }
        if ($days <= 30) {
            return ['level' => 'amber', 'label' => 'Coming', 'text' => 'is due within 30 days'];
        }

        return null;
    }

    private static function days(CarbonInterface $date): ?int
    {
        $today = now()->timezone('Australia/Sydney')->startOfDay();
        $on = $date->copy()->timezone('Australia/Sydney')->startOfDay();

        return (int) $today->diffInDays($on, false);
    }
}
