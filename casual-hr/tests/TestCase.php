<?php

namespace Tests;

use App\Models\Neighbourhood;
use App\Models\School;
use App\Models\User;
use App\Tenancy\SchoolContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create([
            'name' => 'Living School',
            'timezone' => 'Australia/Sydney',
        ]);
        app(SchoolContext::class)->use($this->school);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        app(SchoolContext::class)->use(null);

        parent::tearDown();
    }

    protected function neighbourhood(string $name): Neighbourhood
    {
        return Neighbourhood::factory()->create(['name' => $name]);
    }

    protected function staff(string $role, ?Neighbourhood $neighbourhood = null): User
    {
        return User::factory()->create([
            'role' => $role,
            'neighbourhood_id' => $neighbourhood?->id,
            'email' => $role.'-'.fake()->unique()->numerify('###').'@example.test',
        ]);
    }
}
