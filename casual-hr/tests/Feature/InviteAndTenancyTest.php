<?php

namespace Tests\Feature;

use App\Models\Invite;
use App\Models\Person;
use App\Models\School;
use App\Models\User;
use App\Tenancy\SchoolContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InviteAndTenancyTest extends TestCase
{
    public function test_an_invite_is_single_use_and_opens_only_that_shell(): void
    {
        $hr = $this->staff(User::HR);
        $person = Person::factory()->create([
            'preferred_name' => 'VISIBLE-PERSON',
            'legal_name' => 'VISIBLE-PERSON',
        ]);
        Person::factory()->create([
            'preferred_name' => 'HIDDEN-PERSON',
            'legal_name' => 'HIDDEN-PERSON',
        ]);
        $person->wwcc()->create([
            'school_id' => $person->school_id,
            'wwcc_number' => 'WWC8111111E',
            'name_used' => 'VISIBLE-PERSON',
            'date_of_birth' => '1991-01-01',
            'outcome' => 'pending',
        ]);

        $this->actingAs($hr)->post(route('hr.people.invites', $person))
            ->assertRedirect()
            ->assertSessionHas('invite_url');

        $url = session('invite_url');

        $this->get($url)->assertRedirect(route('induction.shell'));
        $this->get(route('induction.shell'))
            ->assertOk()
            ->assertSee('VISIBLE-PERSON')
            ->assertDontSee('HIDDEN-PERSON')
            ->assertDontSee('WWC8111111E');
        $this->get('/hr')->assertRedirect('/login');
        $this->get($url)->assertStatus(410);
    }

    public function test_an_expired_invite_does_not_open(): void
    {
        $person = Person::factory()->create();
        $raw = 'expiredtokenexpiredtokenexpiredtoken12';
        Invite::create([
            'school_id' => $person->school_id,
            'person_id' => $person->id,
            'token_hash' => Invite::hashToken($raw),
            'expires_at' => now()->subMinute(),
        ]);

        $this->get(route('induction.open', ['token' => $raw]))->assertStatus(410);
    }

    public function test_a_second_school_is_invisible_to_the_first(): void
    {
        $fig = $this->neighbourhood('Fig Neighbourhood');
        $director = $this->staff(User::DIRECTOR, $fig);
        $office = $this->staff(User::OFFICE);

        $other = School::factory()->create(['name' => 'Other School', 'timezone' => 'Australia/Sydney']);
        app(SchoolContext::class)->use($other);
        $hidden = Person::factory()->clear()->create([
            'preferred_name' => 'OTHER-SCHOOL-PERSON',
            'legal_name' => 'OTHER-SCHOOL-PERSON',
        ]);
        app(SchoolContext::class)->use($this->school);

        $this->actingAs($director)->get(route('director.index'))
            ->assertOk()
            ->assertDontSee('OTHER-SCHOOL-PERSON');

        $this->actingAs($office)->post(route('bookings.store', $hidden), [
            'on_date' => now()->toDateString(),
            'class_or_group' => 'Year 2',
        ])->assertNotFound();
    }

    public function test_every_school_record_table_has_school_id(): void
    {
        $exempt = [
            'migrations',
            'cache',
            'cache_locks',
            'jobs',
            'job_batches',
            'failed_jobs',
            'schools',
        ];

        $tables = collect(DB::select("select table_name from information_schema.tables where table_schema = 'public' and table_type = 'BASE TABLE'"))
            ->pluck('table_name');

        $this->assertTrue($tables->contains('people'));

        foreach ($tables as $table) {
            if (in_array($table, $exempt, true)) {
                continue;
            }

            $this->assertTrue(
                Schema::hasColumn($table, 'school_id'),
                $table.' is missing school_id',
            );
        }
    }
}
