<?php

namespace App\Hr;

use App\Models\Person;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Carbon;

class StaffImport
{
    /**
     * @param  array{people?: array<int, array<string, mixed>>, needs_email?: array<int, array<string, mixed>>}  $payload
     * @return array{created: int, updated: int, unchanged: int, needs_email: array<int, array<string, mixed>>}
     */
    public function load(School $school, array $payload, bool $dry = false): array
    {
        $author = User::query()
            ->where('school_id', $school->id)
            ->whereIn('role', Roles::slugsOfKind($school->id, 'manage'))
            ->orderBy('id')
            ->first();
        $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'needs_email' => $payload['needs_email'] ?? []];

        foreach ($payload['people'] ?? [] as $row) {
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            if ($email === '') {
                continue;
            }
            $person = Person::query()
                ->where('school_id', $school->id)
                ->whereRaw('lower(email) = ?', [$email])
                ->first();
            if (! $person) {
                $result['created']++;
                if (! $dry) {
                    $this->create($school, $author, $row, $email);
                }
                continue;
            }
            $changed = $dry ? false : $this->fillBlanks($school, $author, $person, $row);
            $result[$changed ? 'updated' : 'unchanged']++;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function create(School $school, ?User $author, array $row, string $email): void
    {
        $person = Person::create([
            'school_id' => $school->id,
            'legal_name' => $row['legal_name'],
            'preferred_name' => $row['preferred_name'] ?: $row['legal_name'],
            'email' => $email,
            'mobile' => $this->blank($row['mobile'] ?? null),
            'neighbourhood' => $this->blank($row['neighbourhood'] ?? null),
            'neighbourhoods' => $row['neighbourhoods'] ?? [],
            'stages' => $row['stages'] ?? [],
            'status' => ($row['status'] ?? '') === 'applicant' ? 'applicant' : 'casual_pool',
        ]);
        $this->accreditation($school, $person, $row);
        $this->wwcc($school, $author, $person, $row);
        $this->note($school, $author, $person, $row);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function fillBlanks(School $school, ?User $author, Person $person, array $row): bool
    {
        $changed = false;
        foreach ([
            'mobile' => $this->blank($row['mobile'] ?? null),
            'neighbourhood' => $this->blank($row['neighbourhood'] ?? null),
        ] as $field => $value) {
            if ($value && blank($person->{$field})) {
                $person->{$field} = $value;
                $changed = true;
            }
        }
        $places = array_values(array_unique(array_merge($person->neighbourhoods ?? [], $row['neighbourhoods'] ?? [])));
        $stages = array_values(array_unique(array_merge($person->stages ?? [], $row['stages'] ?? [])));
        if ($places !== ($person->neighbourhoods ?? [])) {
            $person->neighbourhoods = $places;
            $changed = true;
        }
        if ($stages !== ($person->stages ?? [])) {
            $person->stages = $stages;
            $changed = true;
        }
        if ($person->status === 'applicant' && ($row['status'] ?? '') === 'casual_pool') {
            $person->status = 'casual_pool';
            $changed = true;
        }
        if ($changed) {
            $person->save();
        }
        $changed = $this->accreditation($school, $person, $row) || $changed;
        $changed = $this->wwcc($school, $author, $person, $row) || $changed;
        $changed = $this->note($school, $author, $person, $row) || $changed;

        return $changed;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function accreditation(School $school, Person $person, array $row): bool
    {
        $level = $this->blank($row['accreditation_level'] ?? null);
        $number = $this->blank($row['nesa_number'] ?? null);
        if (! $level && ! $number) {
            return false;
        }
        $record = $person->accreditation()->firstOrNew(['person_id' => $person->id]);
        $changed = ! $record->exists;
        $record->school_id = $school->id;
        if ($number && blank($record->nesa_number)) {
            $record->nesa_number = $number;
            $changed = true;
        }
        if ($level && blank($record->level)) {
            $record->level = $level;
            $record->status = $record->status ?: 'active';
            $changed = true;
        }
        if ($changed) {
            $record->save();
        }

        return $changed;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function wwcc(School $school, ?User $author, Person $person, array $row): bool
    {
        $expires = $this->blank($row['wwcc_expires_on'] ?? null);
        if (! $expires) {
            return false;
        }
        $record = $person->wwcc()->firstOrNew(['person_id' => $person->id]);
        if ($record->verified_at || filled($record->wwcc_number)) {
            return false;
        }
        $record->school_id = $school->id;
        $record->name_on_check = $record->name_on_check ?: $person->legal_name;
        if (blank($record->expires_on)) {
            $record->expires_on = $expires;
        }
        $record->outcome = 'cleared';
        $record->verified_at = now();
        $record->verified_by = $author?->id;
        $record->save();

        return true;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function note(School $school, ?User $author, Person $person, array $row): bool
    {
        if (! $author) {
            return false;
        }
        $body = $this->noteBody($row);
        $exists = $person->dayNotes()->where('body', 'like', 'Casual list 30 July 2026.%')->exists();
        if ($exists) {
            return false;
        }
        $person->dayNotes()->create([
            'school_id' => $school->id,
            'author_id' => $author->id,
            'body' => $body,
            'descriptors' => 'import',
        ]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function noteBody(array $row): string
    {
        $lines = ['Casual list 30 July 2026.'];
        $kinds = $row['kinds'] ?? [];
        if (in_array('teacher', $kinds, true)) {
            $lines[] = 'On the casual teacher list.';
        }
        if (in_array('ta', $kinds, true)) {
            $lines[] = 'On the casual teaching-assistant list.';
        }
        if (in_array('pipeline', $kinds, true)) {
            $lines[] = 'Held from the pipeline. WWCC was not verified on the sheet.';
        }
        if ($level = $this->blank($row['casual_level'] ?? null)) {
            $lines[] = 'Casual level on the sheet: '.$level.'.';
        }
        if ($ta = $this->blank($row['ta_level'] ?? null)) {
            $lines[] = 'Teaching-assistant level on the sheet: '.$ta.'.';
        }
        if ($subjects = $this->blank($row['subjects'] ?? null)) {
            $lines[] = 'Subjects: '.$subjects.'.';
        }
        if ($worked = $this->blank($row['previously'] ?? null)) {
            $lines[] = 'Previously worked at Living School: '.$worked.'.';
        }
        if ($safety = $this->blank($row['child_safety'] ?? null)) {
            $lines[] = 'Child safety training on the sheet: '.$safety.'.';
        } elseif (! in_array('pipeline', $kinds, true)) {
            $lines[] = 'Child safety training was not recorded on the sheet.';
        }
        if ($due = $this->blank($row['child_safety_due'] ?? null)) {
            $lines[] = 'Next child-safety date on the sheet: '.$this->shown($due).'.';
        }
        if ($proficient = $this->blank($row['proficient_on'] ?? null)) {
            $lines[] = 'Proficient teacher date on the sheet: '.$this->shown($proficient).'.';
        }
        foreach ($row['sheet_notes'] ?? [] as $note) {
            $note = trim((string) $note);
            if ($note !== '') {
                $lines[] = 'Sheet note: '.$note;
            }
        }

        return implode("\n", $lines);
    }

    private function shown(string $day): string
    {
        return Carbon::parse($day, 'Australia/Sydney')->format('j F Y');
    }

    private function blank(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
