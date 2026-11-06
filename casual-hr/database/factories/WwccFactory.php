<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Wwcc;
use App\Tenancy\SchoolContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wwcc>
 */
class WwccFactory extends Factory
{
    protected $model = Wwcc::class;

    public function definition(): array
    {
        return [
            'school_id' => app(SchoolContext::class)->id(),
            'person_id' => null,
            'wwcc_number' => 'WWC9'.fake()->unique()->numerify('######').'E',
            'name_used' => 'Fictional Person',
            'date_of_birth' => '1990-01-15',
            'outcome' => 'pending',
            'verified_at' => null,
            'expires_on' => null,
            'verified_by' => null,
            'app_number_blocked' => false,
            'bar_blocked' => false,
            'interim_bar_blocked' => false,
            'expired_blocked' => false,
            'superseded_at' => null,
        ];
    }

    public function verified(): static
    {
        return $this->state(function () {
            $hr = User::factory()->create(['role' => User::HR]);

            return [
                'outcome' => 'verified',
                'verified_at' => now(),
                'verified_by' => $hr->id,
                'expires_on' => now()->addYear()->toDateString(),
                'app_number_blocked' => false,
                'bar_blocked' => false,
                'interim_bar_blocked' => false,
                'expired_blocked' => false,
            ];
        });
    }
}
