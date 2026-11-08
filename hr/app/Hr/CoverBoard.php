<?php

namespace App\Hr;

use App\Models\Person;
use App\Models\User;
use App\Services\ClearToTeach;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CoverBoard
{
    public function __construct(private ClearToTeach $gate) {}

    /**
     * People a director, the office, or HR can consider for one day.
     *
     * @return array{ready: array<int, array<string, mixed>>, held: array<int, array<string, mixed>>, aside: array<int, array<string, mixed>>, red: int, day: string}
     */
    public function gather(User $viewer, string $date, ?string $neighbourhood): array
    {
        $day = $this->dayName($date);
        $place = $this->place($viewer, $neighbourhood);
        $people = Person::query()
            ->where('school_id', $viewer->school_id)
            ->where('status', 'casual_pool')
            ->whereNull('archived_at')
            ->with([
                'accreditation',
                'wwcc',
                'contracts.template',
                'acknowledgements',
                'dayNotes',
                'bookings' => fn ($query) => $query->whereDate('booked_on', $date),
                'unavailabilities' => fn ($query) => $query
                    ->whereDate('starts_on', '<=', $date)
                    ->whereDate('ends_on', '>=', $date),
            ])
            ->orderBy('preferred_name')
            ->get();

        if ($place) {
            $people = $people->filter(fn (Person $person) => $this->canTeach($person, $place))->values();
        }

        $ready = [];
        $held = [];
        $aside = [];
        $red = 0;

        foreach ($people as $person) {
            $light = $this->gate->assess($person)['light'];
            if ($light === 'red') {
                $red++;

                continue;
            }

            $home = $place && $person->neighbourhood === $place;
            $stages = implode(', ', $person->stages ?? []) ?: 'Stages not set';
            $where = $this->spansSchool($person) ? 'Works across the school' : (string) $person->neighbourhood;
            $row = [
                'person' => $person,
                'light' => $light,
                'home' => $home,
                'phone' => $light === 'green' ? $person->mobile : null,
            ];

            if ($light === 'amber') {
                $row['line'] = 'Needs a Conductor’s note before they can be asked. '.$where.'. '.$stages.'.';
                $held[] = $row;

                continue;
            }

            $booking = $person->bookings->first();
            if ($booking) {
                $row['line'] = 'Already booked for '.$booking->class_label.'.';
                $aside[] = $row;

                continue;
            }
            if ($person->unavailabilities->isNotEmpty()) {
                $row['line'] = 'Away that day.';
                $aside[] = $row;

                continue;
            }
            if (! filled($person->email)) {
                $row['line'] = 'No email on the file, so they cannot be asked from here.';
                $aside[] = $row;

                continue;
            }

            $days = $person->available_days ?? [];
            if ($days !== [] && ! in_array($day, $days, true)) {
                $row['line'] = 'Not listed for '.$day.'. '.$where.'.';
                $aside[] = $row;

                continue;
            }

            $known = $days !== [];
            $row['line'] = ($known ? 'Free on '.$day.'.' : 'Days not recorded. They can still be asked.')
                .' '.$where.'. '.$stages.'.';
            $row['sort'] = ($home ? 0 : 1) + ($known ? 0 : 2);
            $ready[] = $row;
        }

        usort($ready, function (array $a, array $b) {
            return [$a['sort'], $a['person']->preferred_name] <=> [$b['sort'], $b['person']->preferred_name];
        });
        $order = 1;
        foreach ($ready as &$row) {
            $row['order'] = $order++;
        }
        unset($row);

        return [
            'ready' => $ready,
            'held' => $held,
            'aside' => $aside,
            'red' => $red,
            'day' => $day,
        ];
    }

    public function place(User $viewer, ?string $neighbourhood): ?string
    {
        if (Roles::kind($viewer) === 'neighbourhood' && ! Neighbourhood::coversAll($viewer->neighbourhood)) {
            return trim((string) $viewer->neighbourhood) ?: null;
        }
        $neighbourhood = trim((string) $neighbourhood);

        return $neighbourhood !== '' ? $neighbourhood : null;
    }

    public function dayName(string $date): string
    {
        return Carbon::parse($date)->locale('en')->format('l');
    }

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, array<string, mixed>>
     */
    public function chosen(User $viewer, string $date, ?string $neighbourhood, array $ids, bool $withAmber): Collection
    {
        $board = $this->gather($viewer, $date, $neighbourhood);
        $rows = collect($board['ready']);
        if ($withAmber) {
            $rows = $rows->merge($board['held']);
        }

        return $rows->filter(fn (array $row) => in_array($row['person']->id, $ids, true))->values();
    }

    private function canTeach(Person $person, string $neighbourhood): bool
    {
        if ($this->spansSchool($person)) {
            return true;
        }

        return Neighbourhood::teaches($person, $neighbourhood);
    }

    private function spansSchool(Person $person): bool
    {
        $names = array_merge([(string) $person->neighbourhood], $person->neighbourhoods ?? []);
        foreach ($names as $name) {
            $flat = preg_replace('/[^a-z0-9]/', '', strtolower((string) $name));
            if (in_array($flat, ['wholeschool', 'all', 'allneighbourhoods', 'k12', 'prep12', 'p12'], true)) {
                return true;
            }
        }

        return false;
    }
}
