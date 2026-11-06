<?php

namespace Database\Factories;

use App\Models\Accreditation;
use App\Models\User;
use App\Tenancy\SchoolContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Accreditation>
 */
class AccreditationFactory extends Factory
{
    protected $model = Accreditation::class;

    public function definition(): array
    {
        return [
            'school_id' => app(SchoolContext::class)->id(),
            'person_id' => null,
            'nesa_number' => 'NESA'.fake()->numerify('######'),
            'level' => null,
            'status' => null,
            'maintenance_start' => null,
            'maintenance_end' => null,
            'casual_evidence_given' => false,
            'fee_year' => null,
            'etams_checked_at' => null,
            'checked_by' => null,
            'alignment_decision' => 'pending',
            'alignment_decided_at' => null,
            'alignment_decided_by' => null,
            'delegate_user_id' => null,
            'other_employer_arrangement' => null,
            'evidence_reference' => null,
            'superseded_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(function () {
            $hr = User::factory()->create(['role' => User::HR]);

            return [
                'level' => 'proficient',
                'status' => 'active',
                'maintenance_start' => '2024-01-01',
                'maintenance_end' => '2031-01-01',
                'fee_year' => 2026,
                'etams_checked_at' => now(),
                'checked_by' => $hr->id,
                'alignment_decision' => 'approved',
                'alignment_decided_at' => now(),
                'alignment_decided_by' => $hr->id,
            ];
        });
    }
}
