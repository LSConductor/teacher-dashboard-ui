<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\User;
use App\Models\WwccCheck;
use Database\Seeders\HrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CasualHrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(HrSeeder::class);
    }

    public function test_green_amber_and_red_are_distinct(): void
    {
        $hr = User::where('email', 'hr@living.school')->first();
        $response = $this->actingAs($hr)->get('/casuals');
        $response->assertOk();
        $response->assertSee('Sam Green');
        $response->assertSee('Riley Amber');
        $response->assertSee('Pat Red');
        $response->assertSee('>green<', false);
        $response->assertSee('>amber<', false);
        $response->assertSee('>red<', false);
    }

    public function test_director_cannot_see_wwcc_number(): void
    {
        $director = User::where('email', 'director@living.school')->first();
        $sam = Person::where('email', 'sam.green@example.com')->first();
        $number = $sam->wwcc->wwcc_number;

        $this->actingAs($director)
            ->get(route('casuals.show', $sam))
            ->assertOk()
            ->assertDontSee($number)
            ->assertDontSee('1990-04-12')
            ->assertSee('Call');
    }

    public function test_red_casual_cannot_be_booked(): void
    {
        $office = User::where('email', 'office@living.school')->first();
        $pat = Person::where('email', 'pat.red@example.com')->first();

        $this->actingAs($office)
            ->post(route('casuals.bookings', $pat), [
                'booked_on' => now()->toDateString(),
                'class_label' => 'S3',
            ])
            ->assertSessionHasErrors('booking');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_amber_needs_a_conductor_note(): void
    {
        $office = User::where('email', 'office@living.school')->first();
        $conductor = User::where('email', 'conductor@living.school')->first();
        $riley = Person::where('email', 'riley.amber@example.com')->first();

        $this->actingAs($office)
            ->post(route('casuals.bookings', $riley), [
                'booked_on' => now()->toDateString(),
                'class_label' => 'S2',
                'conductor_note' => 'Cover anyway',
            ])
            ->assertSessionHasErrors('booking');

        $this->actingAs($conductor)
            ->post(route('casuals.bookings', $riley), [
                'booked_on' => now()->toDateString(),
                'class_label' => 'S2',
                'conductor_note' => 'Later induction is booked for Friday.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('bookings', ['person_id' => $riley->id, 'light_at_booking' => 'amber']);
    }

    public function test_app_number_is_rejected(): void
    {
        $hr = User::where('email', 'hr@living.school')->first();
        $pat = Person::where('email', 'pat.red@example.com')->first();

        $this->actingAs($hr)
            ->from(route('casuals.show', $pat))
            ->post(route('casuals.wwcc', $pat), [
                'wwcc_number' => 'APP1234567',
                'name_on_check' => 'Patrick Red',
                'date_of_birth' => '1991-01-01',
                'outcome' => 'cleared',
                'expires_on' => now()->addYear()->toDateString(),
            ])
            ->assertSessionHasErrors('wwcc_number');

        $this->assertTrue(WwccCheck::looksLikeApplicationNumber('APP1234567'));
    }

    public function test_casual_cannot_open_another_record(): void
    {
        $samUser = User::where('email', 'sam.green@example.com')->first();
        $pat = Person::where('email', 'pat.red@example.com')->first();

        $this->actingAs($samUser)
            ->get(route('casuals.show', $pat))
            ->assertForbidden();
    }

    public function test_index_names_fit_mysql_and_a_partial_run_can_resume(): void
    {
        $tables = [
            'schools', 'users', 'people', 'accreditations', 'wwcc_checks',
            'induction_modules', 'induction_acknowledgements', 'contract_templates',
            'contracts', 'bookings', 'unavailabilities', 'day_notes', 'pd_entries',
            'invites', 'audit_logs',
        ];

        foreach ($tables as $table) {
            foreach (Schema::getIndexes($table) as $index) {
                $this->assertLessThanOrEqual(64, strlen((string) $index['name']), $table.' '.$index['name']);
            }
            foreach (Schema::getForeignKeys($table) as $foreign) {
                $this->assertLessThanOrEqual(64, strlen((string) $foreign['name']), $table.' '.$foreign['name']);
            }
        }

        $this->assertTrue(Schema::hasIndex(
            'induction_acknowledgements',
            'ack_person_module_version_unique'
        ));

        foreach ([
            'audit_logs', 'invites', 'pd_entries', 'day_notes', 'unavailabilities',
            'bookings', 'contracts', 'contract_templates', 'induction_acknowledgements',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('induction_acknowledgements', function ($table) {
            $table->id();
            $table->foreignId('school_id')->constrained();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('induction_modules')->cascadeOnDelete();
            $table->unsignedInteger('module_version');
            $table->timestamp('acknowledged_at');
            $table->timestamps();
        });

        DB::table('migrations')->where('migration', 'like', '%create_hr_tables')->delete();

        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        $this->assertTrue(Schema::hasTable('audit_logs'));
        $this->assertTrue(Schema::hasTable('schools'));
        $this->assertTrue(Schema::hasIndex(
            'induction_acknowledgements',
            'ack_person_module_version_unique'
        ));
    }

    public function test_nesa_export_omits_wwcc(): void
    {
        $hr = User::where('email', 'hr@living.school')->first();
        $number = Person::where('email', 'sam.green@example.com')->first()->wwcc->wwcc_number;

        $this->actingAs($hr)
            ->get(route('export.nesa'))
            ->assertOk()
            ->assertDontSee($number)
            ->assertSee('Samantha Green');
    }
}
