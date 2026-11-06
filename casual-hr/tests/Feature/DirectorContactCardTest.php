<?php

namespace Tests\Feature;

use App\Hr\ClearToTeach;
use App\Hr\DirectorContactCard;
use App\Models\Audit;
use App\Models\Mentorship;
use App\Models\Person;
use App\Models\User;
use Tests\TestCase;

class DirectorContactCardTest extends TestCase
{
    public function test_a_director_can_call_a_green_casual_and_cannot_see_the_hr_file(): void
    {
        $fig = $this->neighbourhood('Fig Neighbourhood');
        $river = $this->neighbourhood('River Neighbourhood');
        $director = $this->staff(User::DIRECTOR, $fig);
        $office = $this->staff(User::OFFICE);
        $hr = $this->staff(User::HR);

        $green = Person::factory()->clear()->create([
            'neighbourhood_id' => $fig->id,
            'preferred_name' => 'Avery Lang',
            'legal_name' => 'Avery Legalname',
            'email' => 'avery.lang@example.test',
            'mobile' => '0400000001',
            'postal_address' => '9 Fictional Lane, Example Town NSW 2999',
            'payroll_reference' => 'PAY-FICTION-001',
            'stages' => ['S2', 'S3'],
        ]);
        $green->wwcc->update([
            'wwcc_number' => 'WWC8000001E',
            'date_of_birth' => '1984-02-29',
        ]);
        $mentor = Person::factory()->clear()->create([
            'neighbourhood_id' => $fig->id,
            'preferred_name' => 'Nia Cole',
        ]);
        Mentorship::create([
            'school_id' => $this->school->id,
            'person_id' => $green->id,
            'mentor_person_id' => $mentor->id,
            'is_accreditation_supervisor' => false,
            'started_on' => '2026-08-01',
        ]);

        Person::factory()->clear()->create([
            'neighbourhood_id' => $river->id,
            'preferred_name' => 'RIVER-ONLY',
        ]);

        $red = Person::factory()->clear()->create([
            'neighbourhood_id' => $fig->id,
            'preferred_name' => 'Casey Red',
        ]);
        $red->wwcc->update([
            'outcome' => 'barred',
            'bar_blocked' => true,
            'wwcc_number' => 'WWC8000099E',
        ]);

        $green->load(['bookings', 'mentorship.mentor', 'accreditation', 'wwcc', 'neighbourhood']);
        $visible = DirectorContactCard::for($green, app(ClearToTeach::class))->visible();
        $this->assertSame([
            'name', 'mobile', 'email', 'stages', 'todays_booking', 'light', 'mentor_name',
        ], array_keys($visible));
        $this->assertSame('Avery Lang', $visible['name']);
        $this->assertSame('green', $visible['light']);
        $this->assertSame('Nia Cole', $visible['mentor_name']);

        $this->actingAs($director)->get(route('director.index'))
            ->assertOk()
            ->assertSee('Avery Lang')
            ->assertSee('tel:+61400000001', false)
            ->assertSee('mailto:avery.lang@example.test', false)
            ->assertSee('S3')
            ->assertSee('Nia Cole')
            ->assertSee('Book this casual')
            ->assertSee('Casey Red')
            ->assertSee('Red cannot be booked')
            ->assertDontSee('RIVER-ONLY')
            ->assertDontSee('Avery Legalname')
            ->assertDontSee('WWC8000001E')
            ->assertDontSee('WWC8000099E')
            ->assertDontSee('1984-02-29')
            ->assertDontSee('9 Fictional Lane')
            ->assertDontSee('PAY-FICTION-001');

        $this->actingAs($office)->get(route('office.index'))
            ->assertOk()
            ->assertSee('RIVER-ONLY')
            ->assertSee('Avery Lang')
            ->assertDontSee('WWC8000001E')
            ->assertDontSee('1984-02-29')
            ->assertDontSee('9 Fictional Lane');

        $this->actingAs($director)->get(route('hr.people.wwcc', $green))->assertForbidden();
        $denied = Audit::query()->where('action', 'wwcc.open_denied')->latest('id')->first();
        $this->assertNotNull($denied);
        $this->assertStringNotContainsString('WWC8000001E', json_encode($denied->metadata));

        $this->actingAs($hr)->get(route('hr.people.wwcc', $green))
            ->assertOk()
            ->assertSee('WWC8000001E')
            ->assertSee('1984-02-29');
    }
}
