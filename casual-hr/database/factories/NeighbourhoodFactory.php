<?php

namespace Database\Factories;

use App\Models\Neighbourhood;
use App\Tenancy\SchoolContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Neighbourhood>
 */
class NeighbourhoodFactory extends Factory
{
    protected $model = Neighbourhood::class;

    public function definition(): array
    {
        return [
            'school_id' => app(SchoolContext::class)->id(),
            'name' => 'Neighbourhood '.fake()->unique()->numerify('###'),
        ];
    }
}
