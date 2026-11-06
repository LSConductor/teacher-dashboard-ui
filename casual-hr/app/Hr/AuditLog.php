<?php

namespace App\Hr;

use App\Models\Audit;
use App\Models\Person;
use App\Models\User;
use App\Support\Sydney;
use App\Tenancy\SchoolContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLog
{
    private const SENSITIVE = [
        'wwcc_number',
        'date_of_birth',
        'postal_address',
        'payroll_reference',
        'password',
        'contract_text',
        'pay',
        'pay_rate',
        'token',
        'token_hash',
        'google_subject',
    ];

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        ?User $actor,
        string $action,
        ?Model $record = null,
        ?Person $person = null,
        array $metadata = [],
        ?Request $request = null,
    ): Audit {
        $schoolId = $person?->school_id
            ?? $actor?->school_id
            ?? app(SchoolContext::class)->id();

        return Audit::create([
            'school_id' => $schoolId,
            'occurred_at' => Sydney::now(),
            'actor_user_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'action' => $action,
            'record_type' => $record ? $record::class : null,
            'record_id' => $record?->getKey(),
            'person_id' => $person?->id,
            'metadata' => $this->strip($metadata),
            'ip_address' => $request?->ip(),
        ]);
    }

    public function recordLightChange(Person $person, TeachingLight $before, TeachingLight $after, ?User $actor): void
    {
        if ($before->colour === $after->colour) {
            return;
        }

        $this->record($actor, 'light.changed', $person, $person, [
            'from' => $before->colour,
            'to' => $after->colour,
            'reasons' => $after->reasons,
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function strip(array $metadata): array
    {
        $clean = [];

        foreach ($metadata as $key => $value) {
            if (in_array((string) $key, self::SENSITIVE, true)) {
                continue;
            }

            $clean[$key] = is_array($value) ? $this->strip($value) : $value;
        }

        return $clean;
    }
}
