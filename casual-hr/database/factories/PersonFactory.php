<?php

namespace Database\Factories;

use App\Models\Accreditation;
use App\Models\Neighbourhood;
use App\Models\Person;
use App\Models\Wwcc;
use App\Support\Sydney;
use App\Tenancy\SchoolContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    protected $model = Person::class;

    public function definition(): array
    {
        return [
            'school_id' => app(SchoolContext::class)->id(),
            'legal_name' => fake()->name(),
            'preferred_name' => fake()->firstName(),
            'email' => fake()->unique()->safeEmail(),
            'mobile' => '04'.fake()->numerify('########'),
            'postal_address' => null,
            'emergency_contact_name' => null,
            'emergency_contact_mobile' => null,
            'neighbourhood_id' => Neighbourhood::factory(),
            'stages' => ['S3'],
            'employment_status' => Person::CASUAL_POOL,
            'google_subject' => null,
            'payroll_reference' => null,
            'engagement_signed_at' => null,
            'day_one_induction_completed_at' => null,
            'later_induction_window_ends_on' => null,
            'pd_conversation_due_on' => null,
        ];
    }

    public function clear(): static
    {
        return $this->afterCreating(function (Person $person) {
            Accreditation::factory()->for($person)->active()->create([
                'school_id' => $person->school_id,
            ]);
            Wwcc::factory()->for($person)->verified()->create([
                'school_id' => $person->school_id,
            ]);
            $person->forceFill([
                'employment_status' => Person::CASUAL_POOL,
                'engagement_signed_at' => now(),
                'day_one_induction_completed_at' => now(),
                'later_induction_window_ends_on' => null,
                'pd_conversation_due_on' => null,
            ])->save();
        });
    }

    public function amberPd(): static
    {
        return $this->clear()->afterCreating(function (Person $person) {
            $person->forceFill([
                'pd_conversation_due_on' => Sydney::today()->toDateString(),
            ])->save();
        });
    }
}
