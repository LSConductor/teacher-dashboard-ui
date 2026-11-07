<?php

namespace Tests\Feature;

use App\Hr\Roles;
use App\Hr\StaffImport;
use App\Models\Person;
use App\Models\School;
use App\Models\WwccCheck;
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

    public function test_the_july_list_fills_the_file_and_does_not_verify_a_clearance(): void
    {
        $payload = json_decode((string) file_get_contents(database_path('data/casual-staff-2026-07-30.json')), true);
        $school = School::query()->where('slug', 'living-school')->first();
        $import = new StaffImport;

        $first = $import->load($school, $payload);
        $this->assertSame(count($payload['people']), $first['created']);
        $this->assertSame(10, count($first['needs_email']));
        $this->assertSame(count($payload['people']), Person::query()->whereIn('email', array_column($payload['people'], 'email'))->count());

        $sample = collect($payload['people'])->first(fn ($row) => filled($row['nesa_number'] ?? null));
        $person = Person::query()->where('email', $sample['email'])->first();
        $this->assertNotNull($person);
        $this->assertSame($sample['nesa_number'], $person->accreditation->nesa_number);
        $this->assertStringNotContainsString('.', (string) $person->accreditation->nesa_number);
        $this->assertNull($person->wwcc->verified_at);
        $this->assertSame('pending', $person->wwcc->outcome);
        $this->assertNull($person->wwcc->wwcc_number);
        $note = $person->dayNotes()->first()->body;
        $this->assertStringStartsWith('Casual list 30 July 2026.', $note);
        $this->assertStringNotContainsString('$', $note);

        $candidate = collect($payload['people'])->first(fn ($row) => ($row['status'] ?? '') === 'applicant');
        $this->assertSame('applicant', Person::query()->where('email', $candidate['email'])->first()->status);

        $again = $import->load($school, $payload);
        $this->assertSame(0, $again['created']);
        $this->assertSame(1, $person->dayNotes()->count());
        $imported = array_column($payload['people'], 'email');
        $this->assertSame(0, WwccCheck::query()->whereHas('person', function ($query) use ($imported) {
            $query->whereIn('email', $imported);
        })->whereNotNull('verified_at')->count());
    }
}
