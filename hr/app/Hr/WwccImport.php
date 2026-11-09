<?php

namespace App\Hr;

use App\Models\Partner;
use App\Models\Person;
use App\Models\School;
use App\Models\User;
use App\Models\WwccCheck;
use Illuminate\Support\Carbon;

class WwccImport
{
    /**
     * @param  array{rows?: array<int, array<string, mixed>>, ignored_sign_in?: bool, skipped?: array<int, string>}  $register
     * @return array{updated: int, unchanged: int, reported: array<int, array{name: string, sheet: string, why: string}>, ignored_sign_in: bool, skipped: array<int, string>}
     */
    public function load(School $school, array $register, bool $dry = false): array
    {
        $author = User::query()
            ->where('school_id', $school->id)
            ->whereIn('role', Roles::slugsOfKind($school->id, 'manage'))
            ->orderBy('id')
            ->first();
        $people = Person::query()->where('school_id', $school->id)->with('wwcc')->get();
        $partners = Partner::query()->where('school_id', $school->id)->get();
        $peopleByName = $this->index($people, fn (Person $person) => [$person->legal_name, $person->preferred_name]);
        $partnersByName = $this->index($partners, fn (Partner $partner) => [$partner->preferred_name]);
        $result = [
            'updated' => 0,
            'unchanged' => 0,
            'reported' => [],
            'sheets' => $register['sheets'] ?? [],
            'ignored_sign_in' => (bool) ($register['ignored_sign_in'] ?? false),
            'skipped' => $register['skipped'] ?? [],
        ];
        $personState = [];
        $partnerState = [];
        $rows = $register['rows'] ?? [];
        foreach ($rows as $index => $row) {
            $rows[$index]['_order'] = $index;
        }
        usort($rows, function (array $left, array $right) {
            $rank = $this->rank($left['kind'] ?? '') <=> $this->rank($right['kind'] ?? '');

            return $rank !== 0 ? $rank : ($left['_order'] <=> $right['_order']);
        });

        foreach ($rows as $row) {
            if (($row['kind'] ?? '') === 'helper') {
                $this->helper($school, $author, $row, $people, $partners, $partnersByName, $peopleByName, $personState, $partnerState, $dry, $result);

                continue;
            }
            $this->staff($school, $author, $row, $people, $peopleByName, $personState, $dry, $result);
        }

        return $result;
    }

    private function rank(string $kind): int
    {
        return match ($kind) {
            'staff' => 0,
            'pending' => 1,
            'helper' => 2,
            default => 9,
        };
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Person>  $people
     * @param  array<string, array<int, int>>  $peopleByName
     * @param  array<int, array<string, mixed>>  $state
     * @param  array{updated: int, unchanged: int, reported: array<int, array{name: string, sheet: string, why: string}>}  $result
     */
    private function staff(School $school, ?User $author, array $row, $people, array $peopleByName, array &$state, bool $dry, array &$result): void
    {
        $matches = $this->matches($row['name'], $peopleByName);
        if (count($matches) > 1) {
            $this->report($result, $row, 'More than one file has this name. Nothing was written.');

            return;
        }
        if ($matches === []) {
            $this->report($result, $row, 'No email on the register, so this name stays off the list.');

            return;
        }
        $person = $people->firstWhere('id', $matches[0]);
        $changed = $this->fillPerson($school, $author, $person, $row, $state, $dry);
        $result[$changed ? 'updated' : 'unchanged']++;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Partner>  $partners
     * @param  array<string, array<int, int>>  $partnersByName
     * @param  array<string, array<int, int>>  $peopleByName
     * @param  array<int, array<string, mixed>>  $state
     * @param  array{updated: int, unchanged: int, reported: array<int, array{name: string, sheet: string, why: string}>}  $result
     */
    private function helper(School $school, ?User $author, array $row, $people, $partners, array $partnersByName, array $peopleByName, array &$personState, array &$state, bool $dry, array &$result): void
    {
        $staff = $this->matches($row['name'], $peopleByName);
        if (count($staff) === 1) {
            $person = $people->firstWhere('id', $staff[0]);
            $changed = $this->fillPerson($school, $author, $person, $row, $personState, $dry);
            $result[$changed ? 'updated' : 'unchanged']++;
        } elseif (count($staff) > 1) {
            $this->report($result, $row, 'More than one file has this name. Nothing was written.');
        }

        $found = $this->matches($row['name'], $partnersByName);
        if (count($found) > 1) {
            $this->report($result, $row, 'More than one helper file has this name. Nothing was written.');

            return;
        }
        if ($found === []) {
            if ($staff === []) {
                $this->report($result, $row, 'A helper, not a casual teacher. No email, so they are not added.');
            }

            return;
        }
        $partner = $partners->firstWhere('id', $found[0]);
        $changed = $this->fillPartner($author, $partner, $row, $state, $dry);
        $result[$changed ? 'updated' : 'unchanged']++;
    }

    /**
     * @param  array<int, array<string, mixed>>  $state
     */
    private function fillPerson(School $school, ?User $author, Person $person, array $row, array &$state, bool $dry): bool
    {
        $record = $person->wwcc;
        $current = array_merge([
            'wwcc_number' => $record?->wwcc_number,
            'date_of_birth' => $record?->date_of_birth?->toDateString(),
            'name_on_check' => $record?->name_on_check,
            'expires_on' => $record?->expires_on?->toDateString(),
            'verified_at' => $record?->verified_at,
            'outcome' => $record?->outcome,
            'verified_by' => $record?->verified_by,
        ], $state[$person->id] ?? []);
        $changes = [];
        $number = $row['number'] ?? null;
        if (is_string($number) && $number !== '' && ! WwccCheck::looksLikeApplicationNumber($number) && blank($current['wwcc_number'])) {
            $changes['wwcc_number'] = $number;
        }
        if (filled($row['dob'] ?? null) && blank($current['date_of_birth'])) {
            $changes['date_of_birth'] = $row['dob'];
        }
        if ($this->laterDate($row['expiry'] ?? null, $current['expires_on'])) {
            $changes['expires_on'] = $row['expiry'];
        }
        $keptNumber = $changes['wwcc_number'] ?? $current['wwcc_number'];
        $keptExpiry = $changes['expires_on'] ?? $current['expires_on'];
        $today = now()->timezone('Australia/Sydney')->toDateString();
        if (blank($current['verified_at'])
            && ($row['kind'] ?? '') !== 'pending'
            && $this->affirmative($row['result'] ?? null)
            && is_string($number) && $number !== ''
            && is_string($keptNumber) && strcasecmp($keptNumber, $number) === 0
            && ! WwccCheck::looksLikeApplicationNumber($keptNumber)
            && is_string($keptExpiry) && $keptExpiry >= $today) {
            $when = filled($row['verified_on'] ?? null)
                ? Carbon::parse($row['verified_on'], 'Australia/Sydney')->startOfDay()
                : now();
            $changes['outcome'] = 'cleared';
            $changes['verified_at'] = $when;
            if (blank($current['verified_by'])) {
                $changes['verified_by'] = $author?->id;
            }
        }
        if ($changes === []) {
            return false;
        }
        if (blank($current['name_on_check'])) {
            $changes['name_on_check'] = $person->legal_name;
        }
        $state[$person->id] = array_merge($current, $changes);
        if ($dry) {
            return true;
        }
        $saved = $person->wwcc()->firstOrNew(['person_id' => $person->id]);
        $saved->school_id = $school->id;
        foreach ($changes as $field => $value) {
            $saved->{$field} = $value;
        }
        $saved->save();
        $person->setRelation('wwcc', $saved);

        return true;
    }

    /**
     * @param  array<int, array<string, mixed>>  $state
     */
    private function fillPartner(?User $author, Partner $partner, array $row, array &$state, bool $dry): bool
    {
        $current = array_merge([
            'volunteer_number' => $partner->volunteer_number,
            'volunteer_expires_on' => $partner->volunteer_expires_on?->toDateString(),
            'volunteer_verified_on' => $partner->volunteer_verified_on?->toDateString(),
            'volunteer_status' => $partner->volunteer_status,
            'volunteer_user_id' => $partner->volunteer_user_id,
        ], $state[$partner->id] ?? []);
        $changes = [];
        $number = $row['number'] ?? null;
        if (is_string($number) && $number !== '' && ! WwccCheck::looksLikeApplicationNumber($number) && blank($current['volunteer_number'])) {
            $changes['volunteer_number'] = $number;
        }
        if ($this->laterDate($row['expiry'] ?? null, $current['volunteer_expires_on'])) {
            $changes['volunteer_expires_on'] = $row['expiry'];
        }
        $keptNumber = $changes['volunteer_number'] ?? $current['volunteer_number'];
        $keptExpiry = $changes['volunteer_expires_on'] ?? $current['volunteer_expires_on'];
        $today = now()->timezone('Australia/Sydney')->toDateString();
        $open = blank($current['volunteer_status']) || $current['volunteer_status'] === 'pending';
        if ($open
            && blank($current['volunteer_verified_on'])
            && $this->affirmative($row['result'] ?? null)
            && is_string($number) && $number !== ''
            && is_string($keptNumber) && strcasecmp($keptNumber, $number) === 0
            && ! WwccCheck::looksLikeApplicationNumber($keptNumber)
            && is_string($keptExpiry) && $keptExpiry >= $today) {
            $changes['volunteer_status'] = 'cleared';
            $changes['volunteer_verified_on'] = $row['verified_on'] ?? $today;
            if (blank($current['volunteer_user_id'])) {
                $changes['volunteer_user_id'] = $author?->id;
            }
        }
        if ($changes === []) {
            return false;
        }
        $state[$partner->id] = array_merge($current, $changes);
        if ($dry) {
            return true;
        }
        $partner->fill($changes);
        $partner->save();

        return true;
    }

    private function laterDate(mixed $incoming, mixed $stored): bool
    {
        if (! is_string($incoming) || $incoming === '') {
            return false;
        }
        if (blank($stored)) {
            return true;
        }

        return $incoming > (string) $stored;
    }

    private function affirmative(?string $result): bool
    {
        $text = strtolower(trim((string) $result));
        if ($text === '') {
            return true;
        }
        foreach (['barred', 'interim', 'refus', 'declin', 'not clear', 'not verified', 'expired', 'application', 'pending', 'negative', 'suspended', 'unverified'] as $bad) {
            if (str_contains($text, $bad)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Person|Partner>  $records
     * @param  callable(Person|Partner): array<int, ?string>  $names
     * @return array<string, array<int, int>>
     */
    private function index($records, callable $names): array
    {
        $index = [];
        foreach ($records as $record) {
            foreach ($names($record) as $name) {
                foreach ($this->keys((string) $name) as $key) {
                    $index[$key][] = $record->id;
                }
            }
        }
        foreach ($index as $key => $ids) {
            $index[$key] = array_values(array_unique($ids));
        }

        return $index;
    }

    /**
     * @param  array<string, array<int, int>>  $index
     * @return array<int, int>
     */
    private function matches(string $name, array $index): array
    {
        $ids = [];
        foreach ($this->keys($name) as $key) {
            foreach ($index[$key] ?? [] as $id) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /**
     * @return array<int, string>
     */
    private function keys(string $name): array
    {
        $name = str_replace(["\u{00A0}", "\u{2007}", "\u{202F}", "\u{200B}"], ' ', $name);
        $name = trim((string) preg_replace('/\s+/', ' ', $name));
        $name = (string) preg_replace('/^(mr|mrs|ms|miss|dr)\.?\s+/i', '', $name);
        if ($name === '') {
            return [];
        }
        $keys = [strtolower($name)];
        if (str_contains($name, ',')) {
            [$last, $rest] = array_map('trim', explode(',', $name, 2));
            $flipped = trim($rest.' '.$last);
            if ($flipped !== '') {
                $keys[] = strtolower($flipped);
                $name = $flipped;
            }
        }
        $tokens = array_values(array_filter(preg_split('/\s+/', strtolower($name)) ?: []));
        if (count($tokens) >= 2) {
            sort($tokens);
            $keys[] = implode(' ', $tokens);
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param  array{reported: array<int, array{name: string, sheet: string, why: string}>}  $result
     */
    private function report(array &$result, array $row, string $why): void
    {
        $result['reported'][] = [
            'name' => (string) $row['name'],
            'sheet' => (string) $row['sheet'],
            'why' => $why,
        ];
    }
}
