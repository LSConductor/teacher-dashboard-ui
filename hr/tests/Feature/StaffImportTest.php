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

    public function test_the_july_list_treats_an_expiry_as_a_checked_clearance(): void
    {
        $payload = json_decode((string) file_get_contents(database_path('data/casual-staff-2026-07-30.json')), true);
        $school = School::query()->where('slug', 'living-school')->first();
        $import = new StaffImport;
        $gate = app(ClearToTeach::class);
        $today = now()->timezone('Australia/Sydney')->toDateString();
        $clearAfter = now()->timezone('Australia/Sydney')->addDays(31)->toDateString();

        $first = $import->load($school, $payload);
        $this->assertSame(count($payload['people']), $first['created']);
        $this->assertSame(10, count($first['needs_email']));

        $sample = collect($payload['people'])->first(fn ($row) => filled($row['nesa_number'] ?? null));
        $named = Person::query()->where('email', $sample['email'])->first();
        $this->assertSame($sample['nesa_number'], $named->accreditation->nesa_number);
        $this->assertStringNotContainsString('.', (string) $named->accreditation->nesa_number);
        $this->assertStringNotContainsString('$', $named->dayNotes()->first()->body);

        $clear = collect($payload['people'])->first(fn ($row) => ($row['status'] ?? 'casual_pool') === 'casual_pool'
            && filled($row['wwcc_expires_on'] ?? null)
            && $row['wwcc_expires_on'] > $clearAfter);
        $person = Person::query()->where('email', $clear['email'])->first();
        $this->assertSame('cleared', $person->wwcc->outcome);
        $this->assertNotNull($person->wwcc->verified_at);
        $this->assertNull($person->wwcc->wwcc_number);
        $this->assertSame('green', $gate->assess($person)['light']);

        $lapsed = collect($payload['people'])->first(fn ($row) => filled($row['wwcc_expires_on'] ?? null) && $row['wwcc_expires_on'] < $today);
        $past = Person::query()->where('email', $lapsed['email'])->first();
        $pastGate = $gate->assess($past);
        $this->assertSame('red', $pastGate['light']);
        $this->assertTrue(collect($pastGate['reasons'])->contains(
            fn ($reason) => str_contains($reason['text'], 'lapsed')
        ));

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
}
