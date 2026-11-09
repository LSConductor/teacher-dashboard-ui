<?php

namespace Tests\Feature;

use App\Hr\Roles;
use App\Hr\StaffImport;
use App\Models\Person;
use App\Models\School;
use App\Models\User;
use App\Services\ClearToTeach;
use Database\Seeders\HrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Roles::forgetAll();
        $this->seed(HrSeeder::class);
    }

    public function test_the_july_list_writes_an_expiry_without_turning_the_light(): void
    {
        $payload = json_decode((string) file_get_contents(database_path('data/casual-staff-2026-07-30.json')), true);
        $school = School::query()->where('slug', 'living-school')->first();
        $import = new StaffImport;
        $gate = app(ClearToTeach::class);
        $today = now()->timezone('Australia/Sydney')->toDateString();
        $clearAfter = now()->timezone('Australia/Sydney')->addDays(31)->toDateString();

        $first = $import->load($school, $payload);
        $this->assertSame(count($payload['people']), $first['created']);
        $this->assertSame(8, count($first['needs_email']));

        $sample = collect($payload['people'])->first(fn ($row) => filled($row['nesa_number'] ?? null));
        $named = Person::query()->where('email', $sample['email'])->first();
        $this->assertSame($sample['nesa_number'], $named->accreditation->nesa_number);
        $this->assertStringNotContainsString('.', (string) $named->accreditation->nesa_number);
        $this->assertStringNotContainsString('$', $named->dayNotes()->first()->body);

        $clear = collect($payload['people'])->first(fn ($row) => ($row['status'] ?? 'casual_pool') === 'casual_pool'
            && filled($row['wwcc_expires_on'] ?? null)
            && $row['wwcc_expires_on'] > $clearAfter);
        $person = Person::query()->where('email', $clear['email'])->first();
        $this->assertSame($clear['wwcc_expires_on'], $person->wwcc->expires_on->toDateString());
        $this->assertNotSame('cleared', $person->wwcc->outcome);
        $this->assertNull($person->wwcc->verified_at);
        $this->assertNull($person->wwcc->wwcc_number);
        $clearGate = $gate->assess($person);
        $this->assertSame('red', $clearGate['light']);
        $this->assertTrue(collect($clearGate['reasons'])->contains(
            fn ($reason) => $reason['text'] === 'WWCC is not a verified clearance'
        ));

        $lapsed = collect($payload['people'])->first(fn ($row) => filled($row['wwcc_expires_on'] ?? null) && $row['wwcc_expires_on'] < $today);
        $past = Person::query()->where('email', $lapsed['email'])->first();
        $this->assertSame($lapsed['wwcc_expires_on'], $past->wwcc->expires_on->toDateString());
        $this->assertNull($past->wwcc->verified_at);
        $pastGate = $gate->assess($past);
        $this->assertSame('red', $pastGate['light']);

        $candidate = collect($payload['people'])->first(fn ($row) => ($row['status'] ?? '') === 'applicant');
        $this->assertSame('applicant', Person::query()->where('email', $candidate['email'])->first()->status);

        $held = collect($payload['people'])->first(fn ($row) => ($row['status'] ?? 'casual_pool') === 'casual_pool'
            && filled($row['wwcc_expires_on'] ?? null)
            && $row['wwcc_expires_on'] > $clearAfter
            && $row['email'] !== $clear['email']);
        $owned = Person::query()->where('email', $held['email'])->first();
        $owned->wwcc->update(['wwcc_number' => 'WWC1234567E', 'verified_at' => null, 'outcome' => 'pending']);

        $again = $import->load($school, $payload);
        $this->assertSame(0, $again['created']);
        $this->assertSame(1, $person->dayNotes()->count());
        $owned = $owned->fresh('wwcc');
        $this->assertSame('WWC1234567E', $owned->wwcc->wwcc_number);
        $this->assertNull($owned->wwcc->verified_at);
    }

    public function test_hr_archives_a_casual_and_each_card_opens_the_file(): void
    {
        $hr = User::query()->where('email', 'hr@living.school')->first();
        $director = User::query()->where('email', 'director@living.school')->first();
        $sam = Person::query()->where('email', 'sam.green@example.com')->first();

        $this->actingAs($hr)->get(route('casuals.index'))
            ->assertOk()
            ->assertSee('Open the file');

        $this->actingAs($hr)->get(route('casuals.show', $sam))
            ->assertOk()
            ->assertSee('Leave the list')
            ->assertSee('Archive '.$sam->preferred_name);

        $this->actingAs($director)->post(route('casuals.archive', $sam))->assertForbidden();

        $this->actingAs($hr)->post(route('casuals.archive', $sam))
            ->assertRedirect(route('casuals.index'));
        $this->assertNotNull($sam->fresh()->archived_at);

        $this->actingAs($hr)->get(route('casuals.index'))
            ->assertDontSee('>'.$sam->preferred_name.'</a>', false);

        $this->actingAs($hr)->get(route('casuals.index', ['focus' => 'archived']))
            ->assertSee($sam->preferred_name)
            ->assertSee('Bring back')
            ->assertSee('Open the file');

        $this->actingAs($hr)->post(route('casuals.restore', $sam))
            ->assertRedirect(route('casuals.show', $sam));
        $this->assertNull($sam->fresh()->archived_at);
    }

    public function test_ellie_and_sam_come_on_from_the_papers_without_pay(): void
    {
        $payload = json_decode((string) file_get_contents(database_path('data/casual-staff-2026-07-30.json')), true);
        $school = School::query()->where('slug', 'living-school')->first();
        (new StaffImport)->load($school, $payload);
        $gate = app(ClearToTeach::class);

        $ellie = Person::query()->where('email', 'ellie.r@living.school')->first();
        $this->assertNotNull($ellie);
        $this->assertSame('Middle', $ellie->neighbourhood);
        $this->assertSame(['Wednesday', 'Thursday', 'Friday'], $ellie->available_days);
        $this->assertSame('2030-01-29', $ellie->wwcc->expires_on->toDateString());
        $this->assertNotSame('cleared', $ellie->wwcc->outcome);
        $this->assertNull($ellie->wwcc->verified_at);
        $this->assertNull($ellie->wwcc->wwcc_number);
        $this->assertSame('red', $gate->assess($ellie)['light']);
        $note = $ellie->dayNotes()->first()->body;
        $this->assertStringContainsString('11 December 2026', $note);
        $this->assertStringContainsString('28 October 2026', $note);
        $this->assertStringNotContainsString('$', $note);

        $sam = Person::query()->where('email', 'sam@living.school')->first();
        $this->assertSame('Sam Buckley', $sam->preferred_name);
        $this->assertSame('Samuel Buckley', $sam->legal_name);
        $this->assertSame('256030', $sam->accreditation->nesa_number);
        $this->assertSame('0407 906 389', $sam->mobile);
        $this->assertSame('1980-08-18', $sam->wwcc->date_of_birth->toDateString());
        $this->assertSame('pending', $sam->wwcc->outcome);
        $this->assertNull($sam->wwcc->verified_at);
        $this->assertSame('red', $gate->assess($sam)['light']);
        $this->assertStringNotContainsString('$', $sam->dayNotes()->first()->body);
    }
}
