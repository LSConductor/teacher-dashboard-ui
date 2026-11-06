<?php

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<School>
 */
class SchoolFactory extends Factory
{
    protected $model = School::class;

    public function definition(): array
    {
        return [
            'name' => 'Living School',
            'slug' => 'school-'.fake()->unique()->numerify('####'),
            'timezone' => 'Australia/Sydney',
        ];
    }
}
