<?php

namespace Tests\Feature;

use App\Hr\Roles;
use App\Hr\WwccRegister;
use App\Models\DayNote;
use App\Models\Partner;
use App\Models\Person;
use App\Models\School;
use App\Models\User;
use App\Models\WwccCheck;
use App\Services\ClearToTeach;
use Database\Seeders\HrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class WwccImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Roles::forgetAll();
        $this->seed(HrSeeder::class);
    }

    public function test_excel_serials_round_trip_as_calendar_dates(): void
    {
        $date = '2027-06-15';
        $this->assertSame($date, WwccRegister::dateFromSerial(WwccRegister::serialFromDate($date)));
    }

    public function test_the_register_fills_clearances_and_keeps_secrets_off_the_file(): void
    {
        $school = School::query()->where('slug', 'living-school')->first();
        $sam = Person::query()->where('email', 'sam.green@example.com')->first();
        $riley = Person::query()->where('email', 'riley.amber@example.com')->first();
        $pat = Person::query()->where('email', 'pat.red@example.com')->first();
        $sam->wwcc->update([
            'wwcc_number' => null,
            'date_of_birth' => null,
            'name_on_check' => null,
            'outcome' => 'pending',
            'verified_at' => null,
            'expires_on' => null,
            'verified_by' => null,
        ]);
        $rileyNumber = $riley->wwcc->wwcc_number;
        $rileyDob = $riley->wwcc->date_of_birth->toDateString();
        $casey = Person::create([
            'school_id' => $school->id,
            'legal_name' => 'Casey Contract',
            'preferred_name' => 'Casey Contract',
            'email' => 'casey.contract@example.com',
            'neighbourhood' => 'Ngulingah',
            'status' => 'casual_pool',
        ]);
        $casey->wwcc()->create(['school_id' => $school->id, 'outcome' => 'pending']);
        $pip = Person::create([
            'school_id' => $school->id,
            'legal_name' => 'Pip Pending',
            'preferred_name' => 'Pip Pending',
            'email' => 'pip.pending@example.com',
            'neighbourhood' => 'Ngulingah',
            'status' => 'casual_pool',
        ]);
        $pip->wwcc()->create(['school_id' => $school->id, 'outcome' => 'pending']);
        Partner::create([
            'school_id' => $school->id,
            'token' => 'ada-parent',
            'preferred_name' => 'Ada Parent',
            'email' => 'ada.parent@example.com',
            'relationship' => 'parent',
            'child_name' => 'Jo Parent',
            'neighbourhood' => 'Ngulingah',
        ]);
        $peopleBefore = Person::query()->count();
        $future = now()->timezone('Australia/Sydney')->addYear()->toDateString();
        $later = now()->timezone('Australia/Sydney')->addYears(2)->toDateString();
        $past = now()->timezone('Australia/Sydney')->subMonth()->toDateString();
        $dob = '1975-03-09';
        $headers = ['Full name', 'Position or role', 'Date of birth', 'WWCC number', 'Start date', 'Verification date', 'Result', 'Expiry', 'Paid or volunteer', 'New expiry', 'Latest check', 'Notes'];
        $file = $this->workbook([
            'Overview and PD' => [
                $headers,
                $this->row('Should Not Load', ['number' => 'WWC000000E', 'expiry' => $future, 'dob' => $dob]),
            ],
            'Previous Staff' => [
                $headers,
                $this->row('Old Teacher', ['number' => 'WWC000001E', 'expiry' => $future, 'dob' => $dob]),
            ],
            'Pending' => [
                $headers,
                $this->row('Pip Pending', ['number' => 'APP999111', 'expiry' => $future, 'result' => 'Application', 'dob' => $dob]),
                $this->row('Sam Green', ['number' => 'APP000111', 'expiry' => $past, 'result' => 'Pending', 'dob' => '1960-01-01']),
            ],
            'Current Staff' => [
                ['Office of the Children\'s Guardian'],
                ['Username', 'ocg.login@example.com'],
                ['Password', 'SecretLogin99'],
                $headers,
                $this->row('Sam Green', ['number' => 'WWC111222E', 'expiry' => $future, 'dob' => $dob, 'verified' => $future, 'notes' => 'Checked with the Office of the Children\'s Guardian']),
                $this->row('Green, Samantha', ['number' => 'WWCCHANGED', 'expiry' => $later, 'dob' => '1980-01-01']),
                $this->row('Brand New Person', ['number' => 'WWC333444E', 'expiry' => $future, 'dob' => $dob]),
                $this->row('Pat Red', ['number' => 'WWC444555E', 'expiry' => $past, 'dob' => $dob, 'result' => 'Cleared']),
                $this->row('Riley Amber', ['number' => 'WWCNEWRILEY', 'expiry' => $later, 'dob' => '1981-02-02']),
            ],
            'Current External Contractors & Tutors' => [
                $headers,
                $this->row('Casey Contract', ['number' => 'WWC555666E', 'expiry' => $future, 'new' => $later, 'paid' => 'Paid']),
            ],
            'ParentsVolunteers' => [
                $headers,
                $this->row('Ada Parent', ['number' => 'WWC777888E', 'expiry' => $future, 'dob' => $dob, 'paid' => 'Volunteer', 'role' => 'Parent']),
                $this->row('No Email Parent', ['number' => 'WWC101010E', 'expiry' => $future, 'paid' => 'Volunteer']),
                $this->row('Sam Green', ['number' => 'WWCPARENT1E', 'expiry' => $future, 'paid' => 'Volunteer']),
            ],
            'Guardians (Board)' => [
                $headers,
                $this->row('Ned Guardian', ['number' => 'WWC202020E', 'expiry' => $future, 'paid' => 'Volunteer', 'role' => 'Guardian']),
            ],
            'PreviousParentsVolunteers' => [
                $headers,
                $this->row('Former Parent', ['number' => 'WWC303030E', 'expiry' => $future]),
            ],
            'Previous 3rd parties' => [
                $headers,
                $this->row('Former Tutor', ['number' => 'WWC404040E', 'expiry' => $future]),
            ],
        ]);

        $this->artisan('hr:import-wwcc', ['path' => $file, '--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Would update')
            ->expectsOutputToContain('The Guardian sign-in on Current Staff was ignored.')
            ->expectsOutputToContain('Brand New Person · Current Staff · No email on the register, so this name stays off the list.')
            ->expectsOutputToContain('No Email Parent · ParentsVolunteers · A helper, not a casual teacher. No email, so they are not added.')
            ->expectsOutputToContain('Ned Guardian · Guardians (Board) · A helper, not a casual teacher. No email, so they are not added.')
            ->expectsOutputToContain('Current Staff:')
            ->expectsOutputToContain('Skipped')
            ->doesntExpectOutputToContain('SecretLogin99')
            ->doesntExpectOutputToContain('ocg.login@example.com')
            ->doesntExpectOutputToContain('WWC111222E');

        $this->assertNull($sam->fresh()->wwcc->wwcc_number);
        $this->assertSame($peopleBefore, Person::query()->count());

        $this->artisan('hr:import-wwcc', ['path' => $file])
            ->assertSuccessful()
            ->expectsOutputToContain('Updated')
            ->doesntExpectOutputToContain('SecretLogin99')
            ->doesntExpectOutputToContain('ocg.login@example.com');

        $sam = $sam->fresh('wwcc');
        $this->assertSame('WWC111222E', $sam->wwcc->wwcc_number);
        $this->assertSame($dob, $sam->wwcc->date_of_birth->toDateString());
        $this->assertSame($later, $sam->wwcc->expires_on->toDateString());
        $this->assertSame('cleared', $sam->wwcc->outcome);
        $this->assertNotNull($sam->wwcc->verified_at);
        $this->assertSame('green', app(ClearToTeach::class)->assess($sam)['light']);

        $pat = $pat->fresh('wwcc');
        $this->assertSame('WWC444555E', $pat->wwcc->wwcc_number);
        $this->assertSame($past, $pat->wwcc->expires_on->toDateString());
        $this->assertNull($pat->wwcc->verified_at);
        $pastGate = app(ClearToTeach::class)->assess($pat);
        $this->assertSame('red', $pastGate['light']);

        $riley = $riley->fresh('wwcc');
        $this->assertSame($rileyNumber, $riley->wwcc->wwcc_number);
        $this->assertSame($rileyDob, $riley->wwcc->date_of_birth->toDateString());
        $this->assertSame($later, $riley->wwcc->expires_on->toDateString());

        $casey = $casey->fresh('wwcc');
        $this->assertSame('WWC555666E', $casey->wwcc->wwcc_number);
        $this->assertSame($later, $casey->wwcc->expires_on->toDateString());
        $this->assertNull($casey->wwcc->date_of_birth);
        $this->assertSame('cleared', $casey->wwcc->outcome);

        $pip = $pip->fresh('wwcc');
        $this->assertNull($pip->wwcc->wwcc_number);
        $this->assertNull($pip->wwcc->verified_at);

        $ada = Partner::query()->where('email', 'ada.parent@example.com')->first();
        $this->assertSame('WWC777888E', $ada->volunteer_number);
        $this->assertSame('cleared', $ada->volunteer_status);
        $this->assertSame($future, $ada->volunteer_expires_on->toDateString());
        $this->assertNull(Person::query()->where('preferred_name', 'Ada Parent')->first());
        $this->assertNull(Person::query()->where('preferred_name', 'Ned Guardian')->first());
        $this->assertNull(Person::query()->where('preferred_name', 'Should Not Load')->first());
        $this->assertNull(Person::query()->where('preferred_name', 'Old Teacher')->first());
        $this->assertNull(Person::query()->where('preferred_name', 'Brand New Person')->first());
        $this->assertSame($peopleBefore, Person::query()->count());
        $this->assertSame(1, Partner::query()->count());

        $stored = json_encode([
            Person::query()->get()->toArray(),
            WwccCheck::query()->get()->toArray(),
            Partner::query()->get()->toArray(),
            DayNote::query()->pluck('body'),
            User::query()->pluck('email'),
        ]);
        $this->assertStringNotContainsString('SecretLogin99', $stored);
        $this->assertStringNotContainsString('ocg.login@example.com', $stored);

        $director = User::query()->where('email', 'director@living.school')->first();
        $this->actingAs($director)
            ->get(route('casuals.show', $sam))
            ->assertOk()
            ->assertDontSee('WWC111222E')
            ->assertDontSee($dob);

        $second = $this->workbook([
            'Current Staff' => [
                $headers,
                $this->row('Sam Green', ['number' => 'WWCCHANGED', 'expiry' => $later, 'dob' => '1980-01-01']),
                $this->row('Casey Contract', ['number' => 'WWCOTHER01', 'expiry' => $past, 'dob' => '1972-11-04']),
            ],
        ]);
        $this->artisan('hr:import-wwcc', ['path' => $second])->assertSuccessful();
        $sam = $sam->fresh('wwcc');
        $casey = $casey->fresh('wwcc');
        $this->assertSame('WWC111222E', $sam->wwcc->wwcc_number);
        $this->assertSame($dob, $sam->wwcc->date_of_birth->toDateString());
        $this->assertSame($later, $sam->wwcc->expires_on->toDateString());
        $this->assertSame('WWC555666E', $casey->wwcc->wwcc_number);
        $this->assertSame($later, $casey->wwcc->expires_on->toDateString());
        $this->assertSame('1972-11-04', $casey->wwcc->date_of_birth->toDateString());
        $this->assertNotNull($casey->wwcc->verified_at);
    }

    public function test_a_split_name_and_a_helper_row_fill_the_existing_file(): void
    {
        $school = School::query()->where('slug', 'living-school')->first();
        $cindy = Person::create([
            'school_id' => $school->id,
            'legal_name' => 'Cindy Hill',
            'preferred_name' => 'Cindy Hill',
            'email' => 'cindy.hill@example.com',
            'neighbourhood' => 'Primary',
            'status' => 'casual_pool',
        ]);
        $cindy->wwcc()->create(['school_id' => $school->id, 'outcome' => 'pending']);
        $pat = Person::query()->where('email', 'pat.red@example.com')->first();
        $pat->wwcc->update([
            'wwcc_number' => null,
            'date_of_birth' => null,
            'verified_at' => null,
            'expires_on' => null,
            'outcome' => 'pending',
        ]);
        $future = now()->timezone('Australia/Sydney')->addYear()->toDateString();
        $dob = WwccRegister::serialFromDate('1975-03-09');
        $expiry = WwccRegister::serialFromDate($future);
        $file = $this->workbook([
            'Current Staff' => [
                ['Office of the Children\'s Guardian'],
                ['Username', 'ocg.login@example.com'],
                ['Password', 'SecretLogin99'],
                ['Surname', 'Given name', 'Date of birth', 'WWCC number', 'Expiry', 'Notes'],
                ['Hill', 'Cindy', $dob, 'WWC5551212E', $expiry, 'Checked with the Office of the Children\'s Guardian'],
            ],
            'ParentsVolunteers' => [
                ['Full name', 'Position or role', 'WWCC number', 'Expiry'],
                ['Pat Red', 'Parent', 'WWC888999E', $expiry],
                ['No Match Parent', 'Parent', 'WWC121212E', $expiry],
            ],
        ]);

        $this->artisan('hr:import-wwcc', ['path' => $file])
            ->assertSuccessful()
            ->expectsOutputToContain('Current Staff: 1 name.')
            ->expectsOutputToContain('ParentsVolunteers: 2 names.')
            ->expectsOutputToContain('No Match Parent · ParentsVolunteers · A helper, not a casual teacher. No email, so they are not added.')
            ->doesntExpectOutputToContain('SecretLogin99');

        $cindy = $cindy->fresh('wwcc');
        $this->assertSame('WWC5551212E', $cindy->wwcc->wwcc_number);
        $this->assertSame('1975-03-09', $cindy->wwcc->date_of_birth->toDateString());
        $this->assertSame('cleared', $cindy->wwcc->outcome);
        $this->assertSame('WWC888999E', $pat->fresh()->wwcc->wwcc_number);
        $this->assertNull(Person::query()->where('preferred_name', 'No Match Parent')->first());
        $this->assertStringNotContainsString('SecretLogin99', (string) json_encode($cindy->wwcc->toArray()));
    }

    public function test_a_short_label_row_does_not_hide_the_staff_header(): void
    {
        $school = School::query()->where('slug', 'living-school')->first();
        $cindy = Person::create([
            'school_id' => $school->id,
            'legal_name' => 'Cindy Hill',
            'preferred_name' => 'Cindy Hill',
            'email' => 'cindy.header@example.com',
            'neighbourhood' => 'Primary',
            'status' => 'casual_pool',
        ]);
        $cindy->wwcc()->create(['school_id' => $school->id, 'outcome' => 'pending']);
        $future = now()->timezone('Australia/Sydney')->addYear()->toDateString();
        $file = $this->workbook([
            'Current Staff' => [
                ['Office of the Children\'s Guardian'],
                ['Name', 'Expiry'],
                ['Staff member', 'Working with Children Check Number', 'Date of birth', 'Expiry date', 'Result', 'Notes'],
                ['Cindy Hill', 'WWC424242E', WwccRegister::serialFromDate('1975-03-09'), WwccRegister::serialFromDate($future), 'Cleared', 'Checked with the Office of the Children\'s Guardian'],
            ],
        ]);

        $this->artisan('hr:import-wwcc', ['path' => $file])
            ->assertSuccessful()
            ->expectsOutputToContain('Current Staff: 1 name.');

        $this->assertSame('WWC424242E', $cindy->fresh()->wwcc->wwcc_number);
        $this->assertSame('cleared', $cindy->fresh()->wwcc->outcome);
    }

    public function test_a_later_date_on_the_register_replaces_the_date_on_the_file(): void
    {
        $school = School::query()->where('slug', 'living-school')->first();
        $sam = Person::query()->where('email', 'sam.green@example.com')->first();
        $past = now()->timezone('Australia/Sydney')->subMonth()->toDateString();
        $sooner = now()->timezone('Australia/Sydney')->addMonths(2)->toDateString();
        $later = now()->timezone('Australia/Sydney')->addYears(2)->toDateString();
        $number = $sam->wwcc->wwcc_number;
        $dob = $sam->wwcc->date_of_birth->toDateString();
        $sam->wwcc->update([
            'expires_on' => $past,
            'outcome' => 'cleared',
            'verified_at' => now()->subYear(),
        ]);
        $ada = Partner::create([
            'school_id' => $school->id,
            'token' => 'ada-parent',
            'preferred_name' => 'Ada Parent',
            'email' => 'ada.parent@example.com',
            'relationship' => 'parent',
            'child_name' => 'Jo Parent',
            'neighbourhood' => 'Ngulingah',
            'volunteer_number' => 'WWC777888E',
            'volunteer_status' => 'cleared',
            'volunteer_expires_on' => $past,
            'volunteer_verified_on' => $past,
        ]);
        $headers = ['Full name', 'Position or role', 'Date of birth', 'WWCC number', 'Start date', 'Verification date', 'Result', 'Expiry', 'Paid or volunteer', 'New expiry', 'Latest check', 'Notes'];
        $file = $this->workbook([
            'Current Staff' => [
                $headers,
                $this->row('Sam Green', ['number' => 'WWCCHANGED', 'expiry' => $past, 'new' => $later, 'dob' => '1980-01-01', 'result' => 'Cleared']),
            ],
            'ParentsVolunteers' => [
                $headers,
                $this->row('Ada Parent', ['number' => 'WWCOTHER01', 'expiry' => $later, 'paid' => 'Volunteer']),
            ],
        ]);

        $this->artisan('hr:import-wwcc', ['path' => $file])
            ->assertSuccessful()
            ->expectsOutputToContain('A later date on the register replaces the date on the file.');

        $sam = $sam->fresh('wwcc');
        $this->assertSame($later, $sam->wwcc->expires_on->toDateString());
        $this->assertSame($number, $sam->wwcc->wwcc_number);
        $this->assertSame($dob, $sam->wwcc->date_of_birth->toDateString());
        $this->assertSame('cleared', $sam->wwcc->outcome);
        $this->assertSame('green', app(ClearToTeach::class)->assess($sam)['light']);
        $this->assertSame($later, $ada->fresh()->volunteer_expires_on->toDateString());
        $this->assertSame('WWC777888E', $ada->fresh()->volunteer_number);

        $shorter = $this->workbook([
            'Current Staff' => [
                $headers,
                $this->row('Sam Green', ['number' => $number, 'expiry' => $sooner, 'dob' => $dob]),
            ],
            'ParentsVolunteers' => [
                $headers,
                $this->row('Ada Parent', ['number' => 'WWC777888E', 'expiry' => $sooner, 'paid' => 'Volunteer']),
            ],
        ]);
        $this->artisan('hr:import-wwcc', ['path' => $shorter])->assertSuccessful();
        $this->assertSame($later, $sam->fresh()->wwcc->expires_on->toDateString());
        $this->assertSame($later, $ada->fresh()->volunteer_expires_on->toDateString());
    }

    /**
     * @param  array<string, array<int, array<int, string|int|null>>>  $sheets
     */
    private function workbook(array $sheets): string
    {
        $path = tempnam(sys_get_temp_dir(), 'wwcc');
        unlink($path);
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $shared = [];
        $lookup = [];
        $remember = function (string $text) use (&$shared, &$lookup): int {
            if (! array_key_exists($text, $lookup)) {
                $lookup[$text] = count($shared);
                $shared[] = $text;
            }

            return $lookup[$text];
        };
        $sheetXml = [];
        $index = 1;
        foreach ($sheets as $rows) {
            $sheetXml[$index] = $this->sheetXml($rows, $remember);
            $index++;
        }
        $names = array_keys($sheets);
        $sheetTags = '';
        $rels = '';
        foreach ($names as $offset => $name) {
            $id = $offset + 1;
            $sheetTags .= '<sheet name="'.htmlspecialchars($name, ENT_XML1).'" sheetId="'.$id.'" r:id="rId'.$id.'"/>';
            $rels .= '<Relationship Id="rId'.$id.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$id.'.xml"/>';
            $zip->addFromString('xl/worksheets/sheet'.$id.'.xml', $sheetXml[$id]);
        }
        $strings = '';
        foreach ($shared as $text) {
            $strings .= '<si><t xml:space="preserve">'.htmlspecialchars($text, ENT_XML1).'</t></si>';
        }
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.$strings.'</sst>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$sheetTags.'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>');
        $zip->close();

        return $path;
    }

    /**
     * @param  array<int, array<int, string|int|null>>  $rows
     */
    private function sheetXml(array $rows, callable $remember): string
    {
        $body = '';
        foreach ($rows as $rowIndex => $row) {
            $number = $rowIndex + 1;
            $cells = '';
            foreach ($row as $column => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $ref = $this->column($column).$number;
                if (is_int($value)) {
                    $cells .= '<c r="'.$ref.'"><v>'.$value.'</v></c>';
                } else {
                    $cells .= '<c r="'.$ref.'" t="s"><v>'.$remember((string) $value).'</v></c>';
                }
            }
            $body .= '<row r="'.$number.'">'.$cells.'</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$body.'</sheetData></worksheet>';
    }

    /**
     * @param  array<string, string|null>  $extra
     * @return array<int, string|int|null>
     */
    private function row(string $name, array $extra = []): array
    {
        return [
            $name,
            $extra['role'] ?? 'Teacher',
            isset($extra['dob']) ? WwccRegister::serialFromDate($extra['dob']) : null,
            $extra['number'] ?? '',
            isset($extra['start']) ? WwccRegister::serialFromDate($extra['start']) : null,
            isset($extra['verified']) ? WwccRegister::serialFromDate($extra['verified']) : null,
            $extra['result'] ?? 'Cleared',
            isset($extra['expiry']) ? WwccRegister::serialFromDate($extra['expiry']) : null,
            $extra['paid'] ?? 'Paid',
            isset($extra['new']) ? WwccRegister::serialFromDate($extra['new']) : null,
            isset($extra['latest']) ? WwccRegister::serialFromDate($extra['latest']) : null,
            $extra['notes'] ?? '',
        ];
    }

    private function column(int $index): string
    {
        $index++;
        $letters = '';
        while ($index > 0) {
            $index--;
            $letters = chr(65 + ($index % 26)).$letters;
            $index = intdiv($index, 26);
        }

        return $letters;
    }
}
