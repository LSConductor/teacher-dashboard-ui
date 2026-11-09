<?php

namespace Tests\Feature;

use App\Hr\Roles;
use App\Hr\StaffPhoto;
use App\Models\Contract;
use App\Models\InductionAcknowledgement;
use App\Models\StaffRole;
use App\Models\ContractTemplate;
use App\Models\InductionModule;
use App\Models\Invite;
use App\Models\Person;
use App\Models\School;
use App\Models\User;
use App\Models\WwccCheck;
use App\Services\ClearToTeach;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Database\Seeders\HrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class CasualHrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Roles::forgetAll();
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
        $response->assertSee('Clear to teach');
        $response->assertSee('Needs a note');
        $response->assertSee('Do not book');
        $response->assertSee('Day-one');
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

        $this->assertDatabaseMissing('bookings', ['person_id' => $pat->id]);
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
            'invites', 'audit_logs', 'performance_records',
            'partner_pages', 'partner_checks', 'partners',
            'day_codes', 'day_rolls', 'conduct_flags', 'standards_notices',
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

    public function test_seeder_can_run_again_after_the_school_exists(): void
    {
        Contract::query()->delete();
        ContractTemplate::query()->delete();

        $this->seed(HrSeeder::class);
        $this->seed(HrSeeder::class);

        $this->assertSame(1, School::where('slug', 'living-school')->count());
        $this->assertSame(1, User::where('email', 'hr@living.school')->count());
        $this->assertSame(1, User::where('email', 'conductor@living.school')->count());
        $this->assertSame(11, InductionModule::count());
        $this->assertSame(1, ContractTemplate::where('key', 'casual-pool')->where('version', 1)->count());
        $this->assertSame(1, ContractTemplate::where('key', 'casual-pool')->where('version', 2)->count());

        $this->post('/login', [
            'email' => 'hr@living.school',
            'password' => 'living-demo',
        ])->assertRedirect(route('today'));
    }

    public function test_director_list_has_contact_actions_and_hides_wwcc(): void
    {
        $director = User::where('email', 'director@living.school')->first();
        $number = Person::where('email', 'sam.green@example.com')->first()->wwcc->wwcc_number;

        $this->actingAs($director)
            ->get(route('casuals.index'))
            ->assertOk()
            ->assertSee('Call')
            ->assertSee('Email')
            ->assertSee('Open the plan')
            ->assertDontSee($number)
            ->assertDontSee('date of birth');
    }

    public function test_day_one_page_names_the_acknowledgement(): void
    {
        $hr = User::where('email', 'hr@living.school')->first();
        $pat = Person::where('email', 'pat.red@example.com')->first();
        $module = InductionModule::where('title', 'Child protection and mandatory reporting')->first();

        $this->actingAs($hr)
            ->get(route('casuals.induction', [$pat, $module]))
            ->assertOk()
            ->assertSee('mandatory reporter')
            ->assertSee('A child asks you to keep a disclosure secret')
            ->assertSee('I, Pat Red, acknowledge this page');
    }

    public function test_a_casual_must_pass_the_quest_check(): void
    {
        $samUser = User::where('email', 'sam.green@example.com')->first();
        $sam = Person::where('email', 'sam.green@example.com')->first();
        $module = InductionModule::where('title', 'Child protection and mandatory reporting')->first();
        $answers = collect($module->quiz)->map(fn ($question) => $question['answer'])->all();

        $this->actingAs($samUser)
            ->from(route('casuals.induction', [$sam, $module]))
            ->post(route('casuals.acknowledge', [$sam, $module]), ['answers' => [1, 1]])
            ->assertSessionHasErrors('answers');

        $this->actingAs($samUser)
            ->post(route('casuals.acknowledge', [$sam, $module]), ['answers' => $answers])
            ->assertRedirect();
    }

    public function test_public_application_creates_an_applicant(): void
    {
        $this->get('/apply')
            ->assertOk()
            ->assertSee('Apply to teach')
            ->assertDontSee('name="wwcc_number"', false)
            ->assertDontSee('name="date_of_birth"', false);

        $this->post('/apply', [
            'preferred_name' => 'Zara River',
            'legal_name' => 'Zara Alexandra River',
            'email' => 'zara.river@example.com',
            'mobile' => '0400 111 222',
            'neighbourhood' => 'Ngulingah',
            'stages' => ['S3'],
            'right_to_work' => 'Australian citizen. This is a fictional application.',
            'heard_about' => 'A teacher at the school',
        ])->assertRedirect(route('apply'));

        $this->assertSame('applicant', Person::where('email', 'zara.river@example.com')->first()->status);
        $this->post('/apply', [
            'preferred_name' => 'Zara River',
            'legal_name' => 'Zara Alexandra River',
            'email' => 'zara.river@example.com',
            'mobile' => '0400 111 222',
            'neighbourhood' => 'Ngulingah',
            'stages' => ['S3'],
            'right_to_work' => 'Australian citizen. This is a fictional application.',
        ])->assertSessionHasErrors('email');
        $this->assertSame(1, Person::where('email', 'zara.river@example.com')->count());

        $hr = User::where('email', 'hr@living.school')->first();
        $director = User::where('email', 'director@living.school')->first();
        $alex = Person::where('email', 'zara.river@example.com')->first();

        $this->actingAs($hr)->get(route('casuals.index'))
            ->assertOk()
            ->assertSeeInOrder(['Zara River', 'Pat Red'])
            ->assertSee('New candidate')
            ->assertSee('Australian citizen. This is a fictional application.')
            ->assertSee('Heard about the school from A teacher at the school.')
            ->assertSee('Application received')
            ->assertDontSee('See what Zara River sees');

        $this->actingAs($hr)->get(route('casuals.show', $alex))
            ->assertOk()
            ->assertSee('New candidate')
            ->assertSee('Send the invite on Person')
            ->assertDontSee('eTAMS does not show an active level');

        $this->actingAs($director)->get(route('casuals.index'))
            ->assertOk()
            ->assertSee('Zara River')
            ->assertSee('New candidate')
            ->assertDontSee('Australian citizen. This is a fictional application.');

        $this->actingAs($hr)->get(route('casuals.index', ['focus' => 'candidates']))
            ->assertSee('Zara River')
            ->assertDontSee('Pat Red');
        $this->actingAs($hr)->get(route('casuals.index', ['light' => 'red']))
            ->assertSee('Pat Red')
            ->assertDontSee('Zara River');
    }

    public function test_director_cannot_open_the_wwcc_form(): void
    {
        $director = User::where('email', 'director@living.school')->first();
        $sam = Person::where('email', 'sam.green@example.com')->first();

        $this->actingAs($director)
            ->get(route('casuals.forms.wwcc', $sam))
            ->assertForbidden();
    }

    public function test_completed_day_booking_does_not_merge_wwcc(): void
    {
        $hr = User::where('email', 'hr@living.school')->first();
        $sam = Person::where('email', 'sam.green@example.com')->first();
        $template = ContractTemplate::where('key', 'day-booking')->where('status', 'published')->first();

        $this->actingAs($hr)
            ->post(route('templates.complete.send'), [
                'person_id' => $sam->id,
                'template_id' => $template->id,
                'class_label' => 'S3 sample',
                'booked_on' => now()->toDateString(),
            ])
            ->assertRedirect();

        $body = Contract::query()->where('person_id', $sam->id)->where('template_id', $template->id)->first()->merged_body;
        $this->assertStringContainsString('S3 sample', $body);
        $this->assertStringNotContainsString($sam->wwcc->wwcc_number, $body);
        $this->assertStringNotContainsString('1990-04-12', $body);
    }

    public function test_start_gate_keeps_an_invite_on_the_link(): void
    {
        $this->get('/start')
            ->assertOk()
            ->assertSee('Casual Induction')
            ->assertSee('invite codes stay on the link')
            ->assertSee('Log in to portal');

        $hr = User::where('email', 'hr@living.school')->first();
        $pat = Person::where('email', 'pat.red@example.com')->first();
        $this->actingAs($hr)
            ->post(route('casuals.invite', $pat))
            ->assertSessionHas('invite_url');

        $url = session('invite_url');
        $this->assertStringContainsString('/start?code=', $url);

        Auth::logout();
        $this->flushSession();

        $this->get($url)
            ->assertOk()
            ->assertSee('Pat Red')
            ->assertSee('Open my induction')
            ->assertSee('Pay details stay in payroll.')
            ->assertSee('invite codes stay on the link')
            ->assertDontSee($pat->wwcc->wwcc_number);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->post('/invites/'.$query['code'], [
            'password' => 'day-one-pass',
            'password_confirmation' => 'day-one-pass',
        ])->assertRedirect(route('start'));

        $this->get(route('start'))
            ->assertOk()
            ->assertSee('Do not book')
            ->assertSee('Open day-one')
            ->assertSee('(02) 5632 1218')
            ->assertSee('Day-one')
            ->assertDontSee($pat->wwcc->wwcc_number);
    }

    public function test_casual_start_desk_is_the_return_home(): void
    {
        $samUser = User::where('email', 'sam.green@example.com')->first();
        $sam = Person::where('email', 'sam.green@example.com')->first();

        $this->actingAs($samUser)
            ->get('/start')
            ->assertOk()
            ->assertSee('Clear to teach')
            ->assertSee('Open day-one')
            ->assertSee('Open the plan')
            ->assertSee('Call Ngulingah Director')
            ->assertSee('Later induction')
            ->assertSee('class="place"', false)
            ->assertSee('class="mark"', false)
            ->assertSee('Ngulingah</strong>', false)
            ->assertDontSee($sam->wwcc->wwcc_number)
            ->assertDontSee('1990-04-12');

        $this->actingAs($samUser)
            ->get('/casual-induction')
            ->assertRedirect('/start');

        $director = User::where('email', 'director@living.school')->first();
        $this->actingAs($director)
            ->get('/start')
            ->assertOk()
            ->assertSee('Your work for the day is Today')
            ->assertSee('Casual teachers')
            ->assertDontSee('class="place"', false)
            ->assertDontSee($sam->wwcc->wwcc_number);
    }

    public function test_a_used_invite_does_not_open_the_password_form(): void
    {
        $pat = Person::where('email', 'pat.red@example.com')->first();
        $token = Str::random(40);
        Invite::create([
            'school_id' => $pat->school_id,
            'person_id' => $pat->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDay(),
            'used_at' => now(),
        ]);

        $this->get(route('start', ['code' => $token]))
            ->assertOk()
            ->assertSee('already been used')
            ->assertDontSee('Open my induction');

        $this->get('/invites/old-link')
            ->assertRedirect('/start?code=old-link');
    }

    public function test_login_returns_to_the_start_link(): void
    {
        $this->get('/start?code=come-back');

        $this->post('/login', [
            'email' => 'hr@living.school',
            'password' => 'living-demo',
        ])->assertRedirect('/start?code=come-back');
    }

    public function test_hr_setup_manages_quests_staff_and_contracts(): void
    {
        $hr = User::where('email', 'hr@living.school')->first();
        $director = User::where('email', 'director@living.school')->first();

        $this->actingAs($director)->get(route('setup'))->assertForbidden();
        $this->actingAs($hr)->get(route('setup'))->assertOk()->assertSee('Induction quests');

        $this->actingAs($hr)->post(route('setup.induction.store'), [
            'title' => 'Flood wardens',
            'body' => 'Walk the class to the high ground named for that neighbourhood.',
            'phase' => 'later',
        ])->assertRedirect(route('setup.induction'));
        $quest = InductionModule::where('title', 'Flood wardens')->first();
        $this->assertSame('later', $quest->phase);

        $this->actingAs($hr)->post(route('setup.induction.move', $quest), ['phase' => 'day_one']);
        $this->assertSame('day_one', $quest->fresh()->phase);

        $child = InductionModule::where('title', 'Child protection and mandatory reporting')->first();
        $this->actingAs($hr)->put(route('setup.induction.update', $child), [
            'title' => $child->title,
            'body' => $child->body."\n\nA new sentence for this version.",
        ])->assertRedirect(route('setup.induction'));
        $this->assertSame(2, $child->fresh()->version);

        $this->actingAs($hr)->post(route('setup.staff.store'), [
            'name' => 'Yirran Director',
            'email' => 'yirran.director@living.school',
            'role' => 'director',
            'neighbourhood' => 'Yirran',
            'mobile' => '0400 000 300',
            'password' => 'living-demo',
            'password_confirmation' => 'living-demo',
        ])->assertRedirect(route('setup.staff'));
        $this->assertNotNull(User::where('email', 'yirran.director@living.school')->first());

        $this->actingAs($hr)->from(route('setup.staff.edit', $hr))->put(route('setup.staff.update', $hr), [
            'name' => $hr->name,
            'email' => $hr->email,
            'role' => 'office',
        ])->assertSessionHasErrors('role');
        $this->assertSame('hr', $hr->fresh()->role);

        $this->actingAs($hr)->post(route('templates.store'), [
            'name' => 'Visitor note',
            'body' => 'Hello {{preferred_name}}.',
        ])->assertRedirect();
        $this->assertSame('draft', ContractTemplate::where('key', 'visitor-note')->first()->status);

        $pool = ContractTemplate::where('key', 'casual-pool')->where('status', 'published')->first();
        $before = (int) $pool->sort;
        $this->actingAs($hr)->post(route('templates.move', $pool), ['direction' => 'down']);
        $this->assertNotSame($before, (int) $pool->fresh()->sort);

        $sam = Person::where('email', 'sam.green@example.com')->first();
        $pat = Person::where('email', 'pat.red@example.com')->first();
        $day = ContractTemplate::where('key', 'day-booking')->where('status', 'published')->first();
        $this->actingAs($hr)->post(route('templates.complete.send'), [
            'person_id' => $sam->id,
            'template_id' => $day->id,
            'class_label' => 'S2 cover',
            'booked_on' => now()->toDateString(),
        ]);
        $sent = Contract::where('person_id', $sam->id)->where('status', 'sent')->first();
        $this->actingAs($hr)->post(route('contracts.move', $sent), ['person_id' => $pat->id])
            ->assertRedirect(route('casuals.show', $pat));
        $this->assertSame($pat->id, $sent->fresh()->person_id);
        $this->assertStringContainsString('Pat Red', $sent->fresh()->merged_body);

        $signed = Contract::where('person_id', $sam->id)->where('status', 'signed')->first();
        $this->actingAs($hr)->post(route('contracts.move', $signed), ['person_id' => $pat->id])->assertStatus(422);
        $this->assertSame($sam->id, $signed->fresh()->person_id);

        $this->actingAs($hr)->get(route('setup.induction'))->assertOk()->assertSee('Flood wardens')->assertSee('Move up');
        $this->actingAs($hr)->get(route('setup.staff'))->assertOk()->assertSee('Yirran Director');
        $this->actingAs($hr)->get(route('templates.index'))->assertOk()->assertSee('Visitor note')->assertSee('Move up');
    }

    public function test_a_casual_sees_the_people_on_site(): void
    {
        $hr = User::where('email', 'hr@living.school')->first();
        $sam = User::where('email', 'sam.green@example.com')->first();
        $director = User::where('email', 'director@living.school')->first();

        $this->actingAs($hr)->post(route('setup.staff.store'), [
            'name' => 'Yirran Director',
            'email' => 'yirran.director@living.school',
            'role' => 'director',
            'neighbourhood' => 'Yirran',
            'mobile' => '0400 000 300',
            'on_people_page' => '1',
            'people_title' => 'Yirran Director',
            'days_on_site' => 'Tuesday and Wednesday',
            'people_note' => 'Yirran only.',
            'password' => 'living-demo',
            'password_confirmation' => 'living-demo',
        ])->assertRedirect(route('setup.staff'));

        $office = User::where('email', 'office@living.school')->first();
        $this->actingAs($hr)->put(route('setup.staff.update', $office), [
            'name' => 'Office',
            'email' => $office->email,
            'role' => 'office',
            'on_people_page' => '1',
            'people_title' => 'Front office',
            'days_on_site' => 'Monday to Friday',
            'people_note' => 'Collect your lanyard here.',
            'mobile' => '0256321218',
        ])->assertRedirect(route('setup.staff'));

        Auth::logout();
        $this->get(route('people'))->assertRedirect(route('login'));

        $samPerson = Person::where('email', 'sam.green@example.com')->first();
        $samPerson->bookings()->create([
            'school_id' => $samPerson->school_id,
            'booked_on' => now()->toDateString(),
            'class_label' => 'S3 sample',
            'teacher_name' => null,
            'plan_url' => 'https://portal.living.school/',
            'program_source' => 'wordpress',
            'light_at_booking' => 'green',
        ]);

        $this->actingAs($sam)->get(route('people'))
            ->assertOk()
            ->assertSee('Alex Sample')
            ->assertSee('S3 sample')
            ->assertDontSee('Teacher not named yet')
            ->assertSee('Ngulingah Director')
            ->assertSee('Front office')
            ->assertSee('Collect your lanyard here.')
            ->assertSee('Mentor Teacher')
            ->assertSee('Call Ngulingah Director')
            ->assertSee('mailto:director@living.school', false)
            ->assertDontSee('Yirran Director')
            ->assertDontSee('HR Officer')
            ->assertDontSee('John Stewart')
            ->assertDontSee('Pat Red')
            ->assertDontSee('WWCC');

        $this->actingAs($director)->get(route('people'))
            ->assertOk()
            ->assertSee('Ngulingah Director')
            ->assertDontSee('Yirran Director');

        $this->actingAs($hr)->get(route('people'))
            ->assertOk()
            ->assertSee('Not on the people page')
            ->assertSee('HR Officer')
            ->assertSee('Yirran Director');
    }

    public function test_saving_a_video_link_does_not_error(): void
    {
        $hr = User::where('email', 'hr@living.school')->first();
        $module = InductionModule::where('title', 'Child protection and mandatory reporting')->first();
        $version = $module->version;
        $long = 'https://videos.example.com/'.str_repeat('a', 380);

        $this->actingAs($hr)->put(route('setup.induction.update', $module), [
            'title' => $module->title,
            'body' => $module->body,
            'video_url' => 'youtu.be/abcdefghijk',
        ])->assertRedirect(route('setup.induction'));

        $module->refresh();
        $this->assertSame('https://youtu.be/abcdefghijk', $module->video_url);
        $this->assertSame($version, $module->version);

        $this->actingAs($hr)->from(route('setup.induction.edit', $module))->put(route('setup.induction.update', $module), [
            'title' => $module->title,
            'body' => $module->body,
            'video_url' => 'not a link',
        ])->assertRedirect(route('setup.induction.edit', $module))
            ->assertSessionHasErrors('video_url');
        $this->assertSame('https://youtu.be/abcdefghijk', $module->fresh()->video_url);

        $this->actingAs($hr)->put(route('setup.induction.update', $module), [
            'title' => $module->title,
            'body' => $module->body,
            'video_url' => $long,
        ])->assertRedirect(route('setup.induction'));
        $this->assertSame($long, $module->fresh()->video_url);

        Schema::table('induction_modules', function (Blueprint $table) {
            $table->dropColumn('video_url');
        });

        $this->actingAs($hr)->from(route('setup.induction.edit', $module))->put(route('setup.induction.update', $module), [
            'title' => $module->title,
            'body' => $module->body,
            'video_url' => 'https://youtu.be/abcdefghijk',
        ])->assertRedirect(route('setup.induction.edit', $module))
            ->assertSessionHasErrors('video_url');
    }

    public function test_a_people_photo_stays_with_the_school_and_whole_school_is_visible(): void
    {
        Storage::fake('local');
        $hr = User::where('email', 'hr@living.school')->first();
        $sam = User::where('email', 'sam.green@example.com')->first();
        $conductor = User::where('email', 'conductor@living.school')->first();

        $this->actingAs($hr)->get(route('setup.staff.edit', $conductor))
            ->assertOk()
            ->assertSee('Work mobile')
            ->assertSee('Optional head and shoulders photo');

        $this->actingAs($hr)->put(route('setup.staff.update', $conductor), [
            'name' => $conductor->name,
            'email' => $conductor->email,
            'role' => 'conductor',
            'neighbourhood' => 'Whole School',
            'mobile' => '0400 111 222',
            'on_people_page' => '1',
            'people_title' => 'Conductor',
            'days_on_site' => 'Monday to Friday',
            'people_note' => 'For matters that affect safety.',
            'photo' => UploadedFile::fake()->createWithContent('head.png', $this->tinyPng()),
        ])->assertRedirect(route('setup.staff'));

        $conductor->refresh();
        Storage::disk('local')->assertExists($conductor->photo_path);
        $sized = getimagesize(Storage::disk('local')->path($conductor->photo_path));
        $this->assertSame(StaffPhoto::SIZE, $sized[0]);
        $this->assertSame(StaffPhoto::SIZE, $sized[1]);

        $this->actingAs($sam)->get(route('people'))
            ->assertOk()
            ->assertSee('Conductor')
            ->assertSee('Work mobile 0400 111 222')
            ->assertSee('call 000')
            ->assertSee('/people/'.$conductor->id.'/photo', false)
            ->assertDontSee('http://localhost/people/'.$conductor->id.'/photo');

        $this->actingAs($sam)->get(route('people.photo', $conductor))
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg')
            ->assertHeader('cache-control', 'private');

        $yirran = User::create([
            'name' => 'Yirran Only',
            'email' => 'yirran.only@living.school',
            'password' => 'living-demo',
            'school_id' => $hr->school_id,
            'role' => 'director',
            'neighbourhood' => 'Yirran',
            'on_people_page' => true,
        ]);
        $yirran->photo_path = UploadedFile::fake()->createWithContent('yirran.png', $this->tinyPng())->store('people', 'local');
        $yirran->save();

        $this->actingAs($sam)->get(route('people'))->assertDontSee('Yirran Only');
        $this->actingAs($sam)->get(route('people.photo', $yirran))->assertNotFound();

        Auth::logout();
        $this->get(route('people.photo', $conductor))->assertRedirect(route('login'));
    }

    public function test_an_admin_can_save_the_people_card_order(): void
    {
        $hr = User::where('email', 'hr@living.school')->first();
        $sam = User::where('email', 'sam.green@example.com')->first();
        $director = User::where('email', 'director@living.school')->first();
        $office = User::where('email', 'office@living.school')->first();
        $mentor = User::where('email', 'mentor@living.school')->first();

        $this->actingAs($director)->post(route('people.order'), [
            'order' => [$mentor->id, $office->id, $director->id],
        ])->assertForbidden();

        $this->actingAs($hr)->get(route('people'))
            ->assertOk()
            ->assertSee('Save order')
            ->assertSee('Move up');

        $this->actingAs($hr)->post(route('people.order'), [
            'order' => [$mentor->id, $office->id, $director->id],
        ])->assertRedirect(route('people'));

        $this->assertSame(1, $mentor->fresh()->people_sort);
        $this->assertSame(2, $office->fresh()->people_sort);
        $this->assertSame(3, $director->fresh()->people_sort);

        $this->actingAs($sam)->get(route('people'))
            ->assertOk()
            ->assertSeeInOrder(['Mentor Teacher', 'Office', 'Ngulingah Director'])
            ->assertDontSee('Save order');
    }

    public function test_a_photo_over_15_megabytes_is_resized_and_the_card_is_kept(): void
    {
        Storage::fake('local');
        $hr = User::where('email', 'hr@living.school')->first();
        $bytes = $this->tinyJpeg().str_repeat('A', 16000 * 1024);
        $this->assertGreaterThan(15360 * 1024, strlen($bytes));

        $this->actingAs($hr)->post(route('setup.staff.store'), [
            'name' => 'Cloudia Darvill',
            'email' => 'cloudia.darvill@living.school',
            'role' => 'mentor',
            'password' => 'living-demo',
            'password_confirmation' => 'living-demo',
            'photo' => UploadedFile::fake()->createWithContent('cloudia.jpg', $bytes),
        ])->assertRedirect(route('setup.staff'))
            ->assertSessionHas('status', 'Cloudia Darvill can sign in.');

        $member = User::where('email', 'cloudia.darvill@living.school')->first();
        $this->assertNotNull($member);
        Storage::disk('local')->assertExists($member->photo_path);
        $this->assertLessThan(15360 * 1024, Storage::disk('local')->size($member->photo_path));
        $sized = getimagesize(Storage::disk('local')->path($member->photo_path));
        $this->assertSame(StaffPhoto::SIZE, $sized[0]);
        $this->assertSame(StaffPhoto::SIZE, $sized[1]);
        $this->assertSame('image/jpeg', $sized['mime']);
        $this->actingAs($hr)->get(route('setup.staff'))->assertOk()->assertSee('Cloudia Darvill');
    }

    public function test_a_staff_card_is_kept_when_the_photo_cannot_be_used(): void
    {
        Storage::fake('local');
        $hr = User::where('email', 'hr@living.school')->first();

        $this->actingAs($hr)->post(route('setup.staff.store'), [
            'name' => 'Cloudia Darvill',
            'email' => 'cloudia.darvill@living.school',
            'role' => 'mentor',
            'password' => 'living-demo',
            'password_confirmation' => 'living-demo',
            'photo' => UploadedFile::fake()->createWithContent('notes.jpg', str_repeat('not-a-photo', 100)),
        ])->assertRedirect(route('setup.staff'));

        $member = User::where('email', 'cloudia.darvill@living.school')->first();
        $this->assertNotNull($member);
        $this->assertNull($member->photo_path);
        $status = session('status');
        $this->assertStringContainsString('Cloudia Darvill can sign in.', $status);
        $this->assertStringContainsString('card was saved', $status);
        $this->actingAs($hr)->get(route('setup.staff'))->assertOk()->assertSee('Cloudia Darvill');
    }

    public function test_a_wide_photo_is_cropped_to_a_square(): void
    {
        Storage::fake('local');
        $wide = imagecreatetruecolor(900, 300);
        imagefilledrectangle($wide, 0, 0, 899, 299, imagecolorallocate($wide, 10, 20, 30));
        ob_start();
        imagejpeg($wide, null, 90);
        $bytes = ob_get_clean();
        imagedestroy($wide);

        $path = StaffPhoto::store(UploadedFile::fake()->createWithContent('wide.jpg', $bytes));
        $info = getimagesize(Storage::disk('local')->path($path));
        $this->assertSame(StaffPhoto::SIZE, $info[0]);
        $this->assertSame(StaffPhoto::SIZE, $info[1]);
        $this->assertSame('image/jpeg', $info['mime']);
        $this->assertLessThan(120000, Storage::disk('local')->size($path));

        $conductor = User::where('email', 'conductor@living.school')->first();
        Storage::disk('local')->put('people/raw.jpg', $bytes);
        $conductor->photo_path = 'people/raw.jpg';
        $conductor->save();
        StaffPhoto::ensureSized($conductor->fresh());
        $conductor->refresh();
        $this->assertNotSame('people/raw.jpg', $conductor->photo_path);
        $again = getimagesize(Storage::disk('local')->path($conductor->photo_path));
        $this->assertSame(StaffPhoto::SIZE, $again[0]);
        Storage::disk('local')->assertMissing('people/raw.jpg');
    }

    public function test_an_admin_can_add_and_rename_a_role(): void
    {
        $hr = User::where('email', 'hr@living.school')->first();
        $director = User::where('email', 'director@living.school')->first();

        $this->actingAs($director)->get(route('setup.roles'))->assertForbidden();

        $this->actingAs($hr)->post(route('setup.roles.store'), [
            'name' => 'Learning support',
            'kind' => 'support',
        ])->assertRedirect(route('setup.roles'));

        $this->actingAs($hr)->get(route('setup.staff'))
            ->assertOk()
            ->assertSee('Learning support')
            ->assertSee('Add or edit roles');

        $this->actingAs($hr)->post(route('setup.staff.store'), [
            'name' => 'Avery Lane',
            'email' => 'avery.lane@living.school',
            'role' => 'learning-support',
            'password' => 'living-demo',
            'password_confirmation' => 'living-demo',
        ])->assertRedirect(route('setup.staff'));
        $this->assertSame('learning-support', User::where('email', 'avery.lane@living.school')->first()->role);

        $role = StaffRole::where('slug', 'learning-support')->first();
        $this->actingAs($hr)->put(route('setup.roles.update', $role), [
            'name' => 'Learning team',
            'kind' => 'support',
        ])->assertRedirect(route('setup.roles'));
        $this->assertSame('learning-support', $role->fresh()->slug);
        $this->assertSame('Learning team', $role->fresh()->name);

        $this->actingAs($hr)->delete(route('setup.roles.destroy', $role))
            ->assertSessionHasErrors('role');
        $this->assertNotNull($role->fresh());

        $hrRole = StaffRole::where('slug', 'hr')->where('school_id', $hr->school_id)->first();
        $this->actingAs($hr)->put(route('setup.roles.update', $hrRole), [
            'name' => 'People team',
            'kind' => 'manage',
        ])->assertRedirect(route('setup.roles'));
        $this->actingAs($hr)->get(route('casuals.index'))
            ->assertOk()
            ->assertSee('People team');

        $this->actingAs($hr)->post(route('setup.roles.store'), [
            'name' => 'Business manager',
            'kind' => 'manage',
        ])->assertRedirect(route('setup.roles'));
        $manager = User::create([
            'name' => 'Casey Brooks',
            'email' => 'casey.brooks@living.school',
            'password' => 'living-demo',
            'school_id' => $hr->school_id,
            'role' => 'business-manager',
        ]);
        $this->actingAs($manager)->get(route('setup'))->assertOk()->assertSee('Open roles');
        $this->actingAs($director)->get(route('setup'))->assertForbidden();

        $casual = StaffRole::where('slug', 'casual')->where('school_id', $hr->school_id)->first();
        $this->actingAs($hr)->delete(route('setup.roles.destroy', $casual))
            ->assertSessionHasErrors('role');
    }

    public function test_the_light_stays_red_until_clearance_level_day_one_and_alignment(): void
    {
        $gate = app(ClearToTeach::class);
        $sam = Person::where('email', 'sam.green@example.com')->first();
        $this->assertSame('green', $gate->assess($sam)['light']);

        $sam->wwcc->update(['wwcc_number' => 'APP999']);
        $app = $gate->assess($sam->fresh());
        $this->assertSame('red', $app['light']);
        $this->assertTrue(collect($app['reasons'])->contains(
            fn ($reason) => $reason['text'] === 'An APP number is not a clearance'
        ));

        $sam->wwcc->update([
            'wwcc_number' => 'WWC100E',
            'outcome' => 'cleared',
            'verified_at' => null,
        ]);
        $this->assertSame('red', $gate->assess($sam->fresh())['light']);

        $sam->wwcc->update(['verified_at' => now(), 'expires_on' => now()->addYear()]);
        $sam->accreditation->update(['level' => null]);
        $this->assertSame('red', $gate->assess($sam->fresh())['light']);

        $sam->accreditation->update(['level' => 'proficient', 'alignment' => 'pending']);
        $aligned = $gate->assess($sam->fresh());
        $this->assertSame('red', $aligned['light']);
        $this->assertTrue(collect($aligned['reasons'])->contains(
            fn ($reason) => $reason['text'] === 'The Conductor has not recorded eTAMS alignment'
        ));

        $sam->accreditation->update(['alignment' => 'approved']);
        InductionAcknowledgement::where('person_id', $sam->id)->delete();
        $day = $gate->assess($sam->fresh());
        $this->assertSame('red', $day['light']);
        $this->assertTrue(collect($day['reasons'])->contains(
            fn ($reason) => $reason['text'] === 'Day-one induction is incomplete'
        ));
    }

    public function test_a_director_can_call_green_and_cannot_book_red(): void
    {
        $director = User::where('email', 'director@living.school')->first();
        $sam = Person::where('email', 'sam.green@example.com')->first();
        $pat = Person::where('email', 'pat.red@example.com')->first();

        $this->actingAs($director)->get(route('casuals.index'))
            ->assertOk()
            ->assertSee('Call Sam Green')
            ->assertDontSee('Call Pat Red');

        $this->actingAs($director)->get(route('casuals.show', $sam))
            ->assertOk()
            ->assertSee('Call Sam Green');

        $this->actingAs($director)->get(route('casuals.show', $pat))
            ->assertOk()
            ->assertDontSee('Call Pat Red')
            ->assertSee('There is no override.');

        $this->actingAs($director)
            ->post(route('casuals.bookings', $pat), [
                'booked_on' => now()->toDateString(),
                'class_label' => 'Director try',
                'conductor_note' => 'Please book Pat',
            ])
            ->assertSessionHasErrors('booking');
        $this->assertDatabaseMissing('bookings', ['person_id' => $pat->id]);

        $this->actingAs($director)
            ->post(route('casuals.bookings', $sam), [
                'booked_on' => now()->addDay()->toDateString(),
                'class_label' => 'Director day',
                'start_time' => '9:00',
                'finish_time' => '3:15',
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('bookings', [
            'person_id' => $sam->id,
            'class_label' => 'Director day',
            'light_at_booking' => 'green',
        ]);
    }

    public function test_the_conductor_cannot_override_a_red_light(): void
    {
        $conductor = User::where('email', 'conductor@living.school')->first();
        $pat = Person::where('email', 'pat.red@example.com')->first();

        $this->actingAs($conductor)
            ->from(route('casuals.show', $pat))
            ->post(route('casuals.bookings', $pat), [
                'booked_on' => now()->toDateString(),
                'class_label' => 'Override day',
                'conductor_note' => 'I know this person.',
            ])
            ->assertSessionHasErrors('booking');

        $this->assertDatabaseMissing('bookings', ['person_id' => $pat->id]);
        $this->actingAs($conductor)
            ->get(route('casuals.show', $pat))
            ->assertSee('There is no override.');
    }

    public function test_hr_can_produce_a_casual_status_letter(): void
    {
        $hr = User::where('email', 'hr@living.school')->first();
        $director = User::where('email', 'director@living.school')->first();
        $sam = Person::where('email', 'sam.green@example.com')->first();
        $sam->bookings()->create([
            'school_id' => $sam->school_id,
            'booked_on' => now()->subYears(8)->toDateString(),
            'class_label' => 'Ancient class',
            'program_source' => 'wordpress',
            'light_at_booking' => 'green',
            'created_by' => $hr->id,
        ]);

        $this->actingAs($director)->get(route('casuals.letter', $sam))->assertForbidden();

        $this->actingAs($hr)
            ->get(route('casuals.letter', $sam))
            ->assertOk()
            ->assertSee('Casual teacher status')
            ->assertSee('seven years')
            ->assertSee('Samantha Green')
            ->assertSee('S3 sample')
            ->assertDontSee('Ancient class')
            ->assertDontSee($sam->wwcc->wwcc_number)
            ->assertDontSee('1990-04-12');
    }

    public function test_a_booking_files_the_day_confirmation_and_the_ceis_receipt(): void
    {
        $hr = User::where('email', 'hr@living.school')->first();
        $sam = Person::where('email', 'sam.green@example.com')->first();
        $ceis = ContractTemplate::where('key', 'ceis-receipt')->where('status', 'published')->first();
        $day = ContractTemplate::where('key', 'day-booking')->where('status', 'published')->first();
        $pool = ContractTemplate::where('key', 'casual-pool')->where('status', 'published')->first();

        $this->actingAs($hr)
            ->get(route('casuals.show', $sam))
            ->assertSee('Record the Casual Employment Information Statement receipt');

        $this->actingAs($hr)
            ->post(route('casuals.contracts.issue', $sam), ['template_id' => $ceis->id])
            ->assertRedirect();

        $receipt = Contract::where('person_id', $sam->id)->where('template_id', $ceis->id)->first();
        $this->assertNotNull($receipt);
        $this->assertSame('sent', $receipt->status);
        $this->assertStringContainsString('Casual Employment Information Statement', $receipt->merged_body);
        $this->assertStringNotContainsString($sam->wwcc->wwcc_number, $receipt->merged_body);

        $this->actingAs($hr)
            ->post(route('casuals.bookings', $sam), [
                'booked_on' => now()->addDays(2)->toDateString(),
                'class_label' => 'Confirmation class',
                'start_time' => '9:00',
                'finish_time' => '3:15',
            ])
            ->assertRedirect();

        $body = Contract::where('person_id', $sam->id)->where('template_id', $day->id)->latest('id')->first()->merged_body;
        $this->assertStringContainsString('Confirmation class', $body);
        $this->assertStringContainsString('9:00', $body);
        $this->assertStringContainsString('3:15', $body);
        $this->assertStringNotContainsString($sam->wwcc->wwcc_number, $body);

        $this->actingAs($hr)
            ->post(route('casuals.contracts.issue', $sam), ['template_id' => $pool->id])
            ->assertRedirect();
        $this->assertSame(1, Contract::where('person_id', $sam->id)->where('template_id', $ceis->id)->count());
    }

    public function test_google_sign_in_uses_the_school_account_and_the_password_remains(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Continue with Google')
            ->assertSee('Password');

        $this->followingRedirects()
            ->get(route('auth.google'))
            ->assertSee('GOOGLE_CLIENT_ID')
            ->assertSee('GOOGLE_CLIENT_SECRET');

        $this->post('/login', [
            'email' => 'hr@living.school',
            'password' => 'living-demo',
        ])->assertRedirect(route('today'));
        Auth::logout();
        $this->flushSession();

        config([
            'services.google.client_id' => 'school-client-id',
            'services.google.client_secret' => 'school-client-secret',
            'services.google.hosted_domain' => 'living.school',
        ]);

        $schoolId = User::where('email', 'hr@living.school')->first()->school_id;
        $casual = User::create([
            'name' => 'Sam School',
            'email' => 'sam.school@living.school',
            'password' => 'living-demo',
            'school_id' => $schoolId,
            'role' => 'casual',
        ]);

        $googleEmail = 'sam.school@living.school';
        $googleSub = 'google-sub-sam';
        Http::fake(function ($request) use (&$googleEmail, &$googleSub) {
            if (str_contains($request->url(), 'oauth2.googleapis.com/token')) {
                return Http::response(['access_token' => 'token-from-google']);
            }

            return Http::response([
                'sub' => $googleSub,
                'email' => $googleEmail,
                'email_verified' => true,
            ]);
        });

        $this->withSession(['google_oauth_state' => 'state-token'])
            ->get(route('auth.google.callback', ['state' => 'state-token', 'code' => 'auth-code']))
            ->assertRedirect(route('start'));
        $this->assertAuthenticatedAs($casual);
        $this->assertSame('google-sub-sam', $casual->fresh()->google_sub);

        $this->post(route('logout'))->assertRedirect();
        $this->assertGuest();

        $googleEmail = 'outsider@gmail.com';
        $googleSub = 'google-sub-gmail';
        $this->withSession(['google_oauth_state' => 'state-token'])
            ->get(route('auth.google.callback', ['state' => 'state-token', 'code' => 'auth-code']))
            ->assertRedirect(route('login'));
        $this->assertGuest();

        $googleEmail = 'new.teacher@living.school';
        $googleSub = 'google-sub-new';
        $this->withSession(['google_oauth_state' => 'state-token'])
            ->get(route('auth.google.callback', ['state' => 'state-token', 'code' => 'auth-code']))
            ->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNull(User::where('email', 'new.teacher@living.school')->first());

        $this->post('/login', [
            'email' => 'hr@living.school',
            'password' => 'living-demo',
        ])->assertRedirect(route('today'));
    }

    public function test_google_sign_in_accepts_a_saved_nsw_school_email(): void
    {
        config([
            'services.google.client_id' => 'school-client-id',
            'services.google.client_secret' => 'school-client-secret',
        ]);

        $schoolId = User::where('email', 'hr@living.school')->first()->school_id;
        $conductor = User::where('email', 'conductor@living.school')->first();
        $conductor->email = 'john@livingschool.nsw.edu.au';
        $conductor->save();

        $googleEmail = 'john@livingschool.nsw.edu.au';
        $googleSub = 'google-sub-john';
        Http::fake(function ($request) use (&$googleEmail, &$googleSub) {
            if (str_contains($request->url(), 'oauth2.googleapis.com/token')) {
                return Http::response(['access_token' => 'token-from-google']);
            }

            return Http::response([
                'sub' => $googleSub,
                'email' => $googleEmail,
                'email_verified' => true,
            ]);
        });

        $this->withSession(['google_oauth_state' => 'state-token'])
            ->get(route('auth.google.callback', ['state' => 'state-token', 'code' => 'auth-code']))
            ->assertRedirect(route('today'));
        $this->assertAuthenticatedAs($conductor);
        $this->assertSame(1, User::where('email', 'john@livingschool.nsw.edu.au')->count());

        $this->post(route('logout'))->assertRedirect();
        $this->assertGuest();

        $googleEmail = 'someone.else@livingschool.nsw.edu.au';
        $googleSub = 'google-sub-unknown';

        $this->followingRedirects()
            ->withSession(['google_oauth_state' => 'state-token'])
            ->get(route('auth.google.callback', ['state' => 'state-token', 'code' => 'auth-code']))
            ->assertSee('not on the casual list');
        $this->assertGuest();
        $this->assertNull(User::where('email', 'someone.else@livingschool.nsw.edu.au')->first());
        $this->assertSame($schoolId, $conductor->fresh()->school_id);
    }

    public function test_home_and_logout_work_without_the_google_column(): void
    {
        config([
            'services.google.client_id' => 'school-client-id',
            'services.google.client_secret' => 'school-client-secret',
            'services.google.hosted_domain' => 'living.school',
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_sub']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('google_sub');
        });
        $this->assertFalse(Schema::hasColumn('users', 'google_sub'));

        $this->get('/')->assertOk()->assertSee('Casual Induction');
        $this->get('/login')->assertOk()->assertSee('Continue with Google')->assertSee('Password');

        $this->post('/login', [
            'email' => 'hr@living.school',
            'password' => 'living-demo',
        ])->assertRedirect('/');

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->get('/login')->assertOk()->assertSee('Staff sign in');

        $schoolId = User::where('email', 'hr@living.school')->first()->school_id;
        User::create([
            'name' => 'Sam School',
            'email' => 'sam.school@living.school',
            'password' => 'living-demo',
            'school_id' => $schoolId,
            'role' => 'casual',
        ]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'oauth2.googleapis.com/token')) {
                return Http::response(['access_token' => 'token-from-google']);
            }

            return Http::response([
                'sub' => 'google-sub-sam',
                'email' => 'sam.school@living.school',
                'email_verified' => true,
            ]);
        });

        $this->withSession(['google_oauth_state' => 'state-token'])
            ->get(route('auth.google.callback', ['state' => 'state-token', 'code' => 'auth-code']))
            ->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_a_missing_app_key_tells_john_the_server_command(): void
    {
        config(['app.key' => '', 'app.debug' => false]);
        $this->app->forgetInstance('encrypter');

        $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
        $response = $kernel->handle(\Illuminate\Http\Request::create('/login', 'GET'));

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString('APP_KEY is missing', $response->getContent());
        $this->assertStringContainsString('php8.4 artisan key:generate --force', $response->getContent());
    }

    public function test_a_lapsed_or_missing_date_stays_red_and_warns_before_it_lapses(): void
    {
        $gate = app(ClearToTeach::class);
        $director = User::where('email', 'director@living.school')->first();
        $hr = User::where('email', 'hr@living.school')->first();
        $sam = Person::where('email', 'sam.green@example.com')->first();

        $sam->accreditation->update(['maintenance_end' => now()->subDay()->toDateString()]);
        $lapsed = $gate->assess($sam->fresh());
        $this->assertSame('red', $lapsed['light']);
        $this->assertTrue(collect($lapsed['reasons'])->contains(
            fn ($reason) => $reason['text'] === 'Accreditation maintenance has lapsed'
        ));

        $this->actingAs($director)
            ->get(route('casuals.show', $sam))
            ->assertOk()
            ->assertSee('Expiry watch')
            ->assertSee('Accreditation maintenance has lapsed')
            ->assertSee('There is no override.')
            ->assertDontSee($sam->wwcc->wwcc_number);

        $this->actingAs($director)
            ->from(route('casuals.show', $sam))
            ->post(route('casuals.bookings', $sam), [
                'booked_on' => now()->addDays(2)->toDateString(),
                'class_label' => 'After lapse',
            ])
            ->assertSessionHasErrors('booking');
        $this->assertDatabaseMissing('bookings', ['class_label' => 'After lapse']);

        $sam->accreditation->update(['maintenance_end' => null]);
        $missing = $gate->assess($sam->fresh());
        $this->assertSame('red', $missing['light']);
        $this->assertTrue(collect($missing['reasons'])->contains(
            fn ($reason) => $reason['text'] === 'Accreditation maintenance date is missing'
        ));

        $sam->accreditation->update(['maintenance_end' => now()->addYears(2)->toDateString()]);
        $sam->wwcc->update(['expires_on' => null]);
        $noExpiry = $gate->assess($sam->fresh());
        $this->assertSame('red', $noExpiry['light']);
        $this->assertTrue(collect($noExpiry['reasons'])->contains(
            fn ($reason) => $reason['text'] === 'WWCC expiry date is missing'
        ));

        $sam->wwcc->update(['expires_on' => now()->subDay()->toDateString()]);
        $wwccLapsed = $gate->assess($sam->fresh());
        $this->assertSame('red', $wwccLapsed['light']);
        $this->assertTrue(collect($wwccLapsed['reasons'])->contains(
            fn ($reason) => $reason['text'] === 'WWCC clearance has lapsed'
        ));

        $sam->wwcc->update(['expires_on' => now()->addDays(10)->toDateString()]);
        $sam->accreditation->update(['maintenance_end' => now()->addDays(12)->toDateString()]);
        $soon = $gate->assess($sam->fresh());
        $this->assertSame('amber', $soon['light']);
        $this->assertTrue(collect($soon['reasons'])->contains(
            fn ($reason) => $reason['text'] === 'WWCC expires within 30 days'
        ));
        $this->assertTrue(collect($soon['reasons'])->contains(
            fn ($reason) => $reason['text'] === 'Accreditation maintenance ends within 30 days'
        ));

        $this->actingAs($hr)
            ->get(route('casuals.index', ['focus' => 'expiring']))
            ->assertOk()
            ->assertSee('Sam Green')
            ->assertSee('due within 30 days');

        $this->actingAs($director)
            ->get(route('casuals.show', $sam))
            ->assertOk()
            ->assertSee('due within 30 days')
            ->assertDontSee($sam->fresh()->wwcc->wwcc_number);
    }

    public function test_the_casual_file_holds_a_goal_a_day_note_an_observation_and_an_annual_page(): void
    {
        $hr = User::where('email', 'hr@living.school')->first();
        $mentor = User::where('email', 'mentor@living.school')->first();
        $sam = Person::where('email', 'sam.green@example.com')->first();

        $this->actingAs($hr)
            ->post(route('casuals.mentor', $sam), [
                'mentor_user_id' => $mentor->id,
                'supervisor_user_id' => '',
            ])
            ->assertRedirect();
        $this->assertSame($mentor->id, (int) $sam->fresh()->mentor_user_id);

        $this->actingAs($hr)
            ->post(route('casuals.goal', $sam), [
                'body' => 'Lead a calm morning meeting',
                'descriptors' => '4.1',
            ])
            ->assertRedirect();

        $this->actingAs($mentor)
            ->post(route('casuals.notes', $sam), [
                'body' => 'The class settled after recess.',
                'descriptors' => '4.1',
            ])
            ->assertRedirect();

        $this->actingAs($mentor)
            ->post(route('casuals.observation', $sam), [
                'body' => 'Students knew where to sit.',
                'next_step' => 'Name the quiet students next.',
                'descriptors' => '4.1, 5.2',
            ])
            ->assertRedirect();

        $this->actingAs($hr)
            ->post(route('casuals.annual', $sam), [
                'body' => 'The morning meeting held.',
                'students_did' => 'They read the plan aloud.',
                'keep_doing' => 'Keep the three-line note.',
                'change_next' => 'Ask one student to lead.',
            ])
            ->assertRedirect(route('casuals.annual', $sam));

        $this->actingAs($hr)
            ->get(route('casuals.show', $sam))
            ->assertOk()
            ->assertSee('Lead a calm morning meeting')
            ->assertSee('The class settled after recess.')
            ->assertSee('Students knew where to sit.')
            ->assertSee('Name the quiet students next.')
            ->assertSee($mentor->name);

        $this->actingAs($hr)
            ->get(route('casuals.annual', $sam))
            ->assertOk()
            ->assertSee('They read the plan aloud.')
            ->assertSee('Ask one student to lead.')
            ->assertSee('What to keep');
    }

    public function test_professional_learning_records_hours_against_the_standards(): void
    {
        $hr = User::where('email', 'hr@living.school')->first();
        $sam = Person::where('email', 'sam.green@example.com')->first();

        $this->actingAs($hr)
            ->from(route('casuals.show', $sam))
            ->post(route('casuals.pd', $sam), [
                'occurred_on' => now()->toDateString(),
                'name' => 'Morning meeting practice',
                'provider' => 'Living School',
                'hours' => 2,
                'activity_type' => 'school',
            ])
            ->assertSessionHasErrors('standards');

        $this->actingAs($hr)
            ->post(route('casuals.pd', $sam), [
                'occurred_on' => now()->toDateString(),
                'name' => 'Morning meeting practice',
                'provider' => 'Living School',
                'hours' => 2,
                'standards' => ['4', '6'],
                'activity_type' => 'school',
            ])
            ->assertRedirect();

        $this->actingAs($hr)
            ->get(route('casuals.show', $sam))
            ->assertOk()
            ->assertSee('Morning meeting practice')
            ->assertSee('Standards 4, 6')
            ->assertSee('2 hours recorded against the teacher standards.');

        $this->actingAs($hr)
            ->get(route('casuals.letter', $sam))
            ->assertOk()
            ->assertSee('S3 sample')
            ->assertSee('bookings already stored')
            ->assertDontSee('Morning meeting practice')
            ->assertDontSee($sam->wwcc->wwcc_number);
    }

    public function test_a_conditional_or_provisional_casual_stays_red_until_a_supervisor_is_named(): void
    {
        $gate = app(ClearToTeach::class);
        $hr = User::where('email', 'hr@living.school')->first();
        $director = User::where('email', 'director@living.school')->first();
        $mentor = User::where('email', 'mentor@living.school')->first();
        $sam = Person::where('email', 'sam.green@example.com')->first();

        $this->assertNull($sam->supervisor_user_id);
        $this->assertSame('green', $gate->assess($sam)['light']);

        $sam->accreditation->update(['level' => 'provisional']);
        $provisional = $gate->assess($sam->fresh());
        $this->assertSame('red', $provisional['light']);
        $this->assertTrue(collect($provisional['reasons'])->contains(
            fn ($reason) => $reason['text'] === 'An Accreditation Supervisor is not named'
        ));

        $this->actingAs($director)
            ->from(route('casuals.show', $sam))
            ->post(route('casuals.bookings', $sam), [
                'booked_on' => now()->addDays(3)->toDateString(),
                'class_label' => 'No supervisor',
            ])
            ->assertSessionHasErrors('booking');
        $this->assertDatabaseMissing('bookings', ['class_label' => 'No supervisor']);

        $sam->accreditation->update(['level' => 'conditional']);
        $this->assertSame('red', $gate->assess($sam->fresh())['light']);

        $this->actingAs($hr)
            ->post(route('casuals.mentor', $sam), [
                'mentor_user_id' => $mentor->id,
                'supervisor_user_id' => $mentor->id,
            ])
            ->assertRedirect();

        $named = $gate->assess($sam->fresh());
        $this->assertSame('green', $named['light']);
        $this->assertFalse(collect($named['reasons'])->contains(
            fn ($reason) => str_contains($reason['text'], 'Accreditation Supervisor')
        ));

        $this->actingAs($hr)
            ->get(route('casuals.show', $sam))
            ->assertOk()
            ->assertSee('Accreditation Supervisor')
            ->assertSee($mentor->name);

        $this->actingAs($director)
            ->post(route('casuals.bookings', $sam), [
                'booked_on' => now()->addDays(4)->toDateString(),
                'class_label' => 'Supervisor named',
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('bookings', [
            'person_id' => $sam->id,
            'class_label' => 'Supervisor named',
            'light_at_booking' => 'green',
        ]);
    }

    public function test_a_director_searches_only_their_learning_neighbourhood(): void
    {
        $director = User::where('email', 'director@living.school')->first();
        $hr = User::where('email', 'hr@living.school')->first();
        $office = User::where('email', 'office@living.school')->first();
        $mentor = User::where('email', 'mentor@living.school')->first();
        $sam = Person::where('email', 'sam.green@example.com')->first();
        $this->assertSame('Ngulingah', $director->neighbourhood);

        $yirran = Person::create([
            'school_id' => $director->school_id,
            'preferred_name' => 'Yirran Only',
            'legal_name' => 'Yirran Only',
            'email' => 'yirran.only@example.com',
            'neighbourhood' => 'Yirran',
            'status' => 'casual_pool',
        ]);
        $covers = Person::create([
            'school_id' => $director->school_id,
            'preferred_name' => 'Covers Ngulingah',
            'legal_name' => 'Covers Ngulingah',
            'email' => 'covers.ngulingah@example.com',
            'neighbourhood' => 'Yirran',
            'neighbourhoods' => ['Ngulingah'],
            'status' => 'casual_pool',
        ]);

        $this->actingAs($director)
            ->get(route('casuals.index', ['q' => 'Yirran', 'neighbourhood' => 'Yirran']))
            ->assertOk()
            ->assertSee('Learning neighbourhood')
            ->assertSee('Showing Ngulingah only.')
            ->assertDontSee('Yirran Only');

        $this->actingAs($director)
            ->get(route('casuals.index', ['q' => 'Covers']))
            ->assertOk()
            ->assertSee('Covers Ngulingah');

        $this->actingAs($director)->get(route('casuals.show', $yirran))->assertForbidden();
        $this->actingAs($director)->get(route('casuals.show', $covers))->assertOk();
        $this->actingAs($director)
            ->get(route('casuals.show', $sam))
            ->assertOk()
            ->assertDontSee('Signed sample engagement')
            ->assertDontSee($sam->wwcc->wwcc_number);

        $this->actingAs($hr)
            ->get(route('casuals.index', ['neighbourhood' => 'Yirran']))
            ->assertOk()
            ->assertSee('Yirran Only')
            ->assertSee('Learning neighbourhood')
            ->assertDontSee('Sam Green');

        $this->actingAs($office)
            ->get(route('casuals.show', $sam))
            ->assertOk()
            ->assertDontSee($sam->wwcc->wwcc_number)
            ->assertDontSee('Signed sample engagement');

        $this->actingAs($mentor)->get(route('casuals.show', $yirran))->assertForbidden();
        $this->actingAs($mentor)->get(route('casuals.show', $covers))->assertForbidden();

        $this->actingAs($director)
            ->get(route('access'))
            ->assertOk()
            ->assertSee('Casuals in Ngulingah')
            ->assertSee('Another neighbourhood')
            ->assertDontSee('Every role');

        $this->actingAs($hr)
            ->get(route('access'))
            ->assertOk()
            ->assertSee('Every role')
            ->assertSee('WWCC number, date of birth, and contract text');

        $this->actingAs($director)->get(route('setup'))->assertForbidden();
        $this->actingAs($office)->get(route('audit.index'))->assertForbidden();
    }

    public function test_an_untouched_booking_note_opens_as_a_letter(): void
    {
        $hr = User::where('email', 'hr@living.school')->first();
        $template = ContractTemplate::where('key', 'day-booking')->where('status', 'published')->first();
        $template->update([
            'body' => "{{preferred_name}} ({{mobile}}) is booked on {{booked_on}} for {{class_label}} in {{neighbourhood}}.\nThe day runs {{start_time}} to {{finish_time}}. Director {{director_name}}, {{director_mobile}}. Office {{office_phone}}.\nThis note does not include a WWCC number or a date of birth.",
        ]);

        $this->actingAs($hr)
            ->get(route('templates.edit', $template))
            ->assertOk()
            ->assertSee('How a sent copy looks')
            ->assertSee('Living School confirms one day of casual teaching.')
            ->assertSee('Sam Green')
            ->assertSee('class="sheet"', false);

        $this->assertStringContainsString(
            'Living School confirms one day of casual teaching.',
            $template->fresh()->body
        );
    }

    public function test_hr_turns_one_card_and_a_director_does_not(): void
    {
        $hr = User::where('email', 'hr@living.school')->first();
        $director = User::where('email', 'director@living.school')->first();
        $riley = Person::where('email', 'riley.amber@example.com')->first();

        $this->actingAs($hr)
            ->get(route('casuals.show', $riley))
            ->assertOk()
            ->assertSee('name="file-tab"', false)
            ->assertSee('Person')
            ->assertSee('Clearances')
            ->assertSee('The day')
            ->assertSee('Learning')
            ->assertSee('Records')
            ->assertSee('Amber')
            ->assertSee('Later induction is still open')
            ->assertSee('Call Riley Amber');

        $this->actingAs($director)
            ->get(route('casuals.show', $riley))
            ->assertOk()
            ->assertSee('Book a day')
            ->assertSee('Later induction is still open')
            ->assertDontSee('name="file-tab"', false);
    }

    public function test_the_office_code_records_the_day(): void
    {
        $office = User::where('email', 'office@living.school')->first();
        $director = User::where('email', 'director@living.school')->first();
        $samUser = User::where('email', 'sam.green@example.com')->first();
        $sam = Person::where('email', 'sam.green@example.com')->first();
        $pat = Person::where('email', 'pat.red@example.com')->first();

        $this->actingAs($director)->get(route('today'))
            ->assertOk()
            ->assertSee('S3 sample')
            ->assertDontSee('Office code');
        $this->assertDatabaseCount('day_codes', 0);

        $this->actingAs($samUser)->from(route('today'))
            ->post(route('today.on', $sam), ['code' => 'ABCDEF'])
            ->assertSessionHasErrors('code');
        $this->assertDatabaseCount('day_rolls', 0);

        $this->actingAs($office)->get(route('today'))->assertOk()->assertSee('Office code');
        $code = \App\Models\DayCode::query()->value('code');
        $this->assertNotSame('', $code);

        $this->actingAs($director)->get(route('today'))->assertDontSee($code);
        $this->actingAs($samUser)->post(route('today.on', $pat), ['code' => $code])->assertForbidden();

        $this->actingAs($samUser)->post(route('today.on', $sam), ['code' => strtolower($code)])->assertRedirect();
        $roll = \App\Models\DayRoll::query()->where('person_id', $sam->id)->first();
        $this->assertNotNull($roll->signed_on_at);
        $this->assertSame($samUser->id, (int) $roll->signed_on_by);

        $this->actingAs($samUser)->from(route('today'))
            ->post(route('today.on', $sam), ['at_desk' => '1'])
            ->assertForbidden();
        $this->actingAs($samUser)->from(route('today'))
            ->post(route('today.off', $sam), ['code' => $code])
            ->assertRedirect();
        $this->assertNotNull($roll->fresh()->signed_off_at);

        \App\Models\DayRoll::query()->delete();
        $this->actingAs($office)->post(route('today.on', $sam), ['at_desk' => '1'])->assertRedirect();
        $this->assertSame($office->id, (int) \App\Models\DayRoll::query()->where('person_id', $sam->id)->value('signed_on_by'));
        $this->actingAs($director)->post(route('today.close', $sam), ['close_note' => 'Left at the end of the day'])->assertForbidden();
        $this->actingAs($office)->post(route('today.close', $sam), ['close_note' => 'Left at the end of the day'])->assertRedirect();
        $closed = \App\Models\DayRoll::query()->where('person_id', $sam->id)->first();
        $this->assertNull($closed->signed_off_at);
        $this->assertSame($office->id, (int) $closed->closed_by);
        $this->assertSame('Left at the end of the day', $closed->close_note);

        $this->actingAs($office)->from(route('today'))
            ->post(route('today.on', $pat), ['at_desk' => '1'])
            ->assertSessionHasErrors('roll');
        $this->assertFalse(str_contains(
            (string) DB::table('audit_logs')->where('action', 'day.rejected')->value('metadata'),
            'ABCDEF'
        ));
    }

    public function test_hr_can_edit_a_quest_check(): void
    {
        $hr = User::where('email', 'hr@living.school')->first();
        $director = User::where('email', 'director@living.school')->first();
        $module = InductionModule::where('title', 'How pay works and who to call')->first();

        $this->actingAs($hr)->get(route('setup.induction.edit', $module))
            ->assertOk()
            ->assertSee('Where does a tax file number belong?')
            ->assertSee('The check');

        $payload = ['prompt' => [], 'choice' => [], 'answer' => []];
        foreach ($module->quiz as $index => $question) {
            $payload['prompt'][$index] = $index === 0 ? 'Where does a tax file number belong now?' : $question['prompt'];
            foreach ($question['choices'] as $choice => $label) {
                $payload['choice'][$index][$choice] = $label;
            }
            $payload['answer'][$index] = $question['answer'];
        }

        $this->actingAs($director)->put(route('setup.induction.check', $module), $payload)->assertForbidden();
        $this->actingAs($hr)->put(route('setup.induction.check', $module), $payload)->assertRedirect();
        $module->refresh();
        $this->assertSame(2, $module->version);
        $this->assertSame('Where does a tax file number belong now?', $module->quiz[0]['prompt']);

        $again = ['prompt' => [], 'choice' => [], 'answer' => []];
        foreach ($module->quiz as $index => $question) {
            $again['prompt'][$index] = $question['prompt'];
            foreach ($question['choices'] as $choice => $label) {
                $again['choice'][$index][$choice] = $label;
            }
            $again['answer'][$index] = $question['answer'];
        }
        $this->actingAs($hr)->put(route('setup.induction.check', $module), $again)->assertRedirect();
        $this->assertSame(2, $module->fresh()->version);
    }

    public function test_the_morning_watch_and_the_day_stay_in_their_places(): void
    {
        $hr = User::where('email', 'hr@living.school')->first();
        $director = User::where('email', 'director@living.school')->first();
        $samUser = User::where('email', 'sam.green@example.com')->first();
        $sam = Person::where('email', 'sam.green@example.com')->first();
        $number = $sam->wwcc->wwcc_number;
        $booking = $sam->bookings()->first();

        $this->actingAs($hr)->from(route('setup'))
            ->put(route('setup.desk'), ['incident_desk_url' => 'http://portal.living.school/incidents'])
            ->assertSessionHasErrors('incident_desk_url');
        $this->actingAs($hr)->put(route('setup.desk'), [
            'incident_desk_url' => 'https://portal.living.school/create-student-incident/',
        ])->assertRedirect();

        $this->actingAs($samUser)->post(route('casuals.bookings.note', [$sam, $booking]), [
            'covering_note' => 'Finished the fractions page. Start decimals tomorrow.',
        ])->assertRedirect();

        $this->actingAs($hr)->post(route('casuals.conduct', $sam), [
            'kind' => 'child_protection',
            'noted_on' => now()->toDateString(),
            'location' => 'Classter staff file 14',
        ])->assertRedirect();
        $this->actingAs($director)->post(route('casuals.conduct', $sam), [
            'kind' => 'complaint',
            'noted_on' => now()->toDateString(),
            'location' => 'Somewhere else',
        ])->assertForbidden();

        $told = now()->subDays(24)->toDateString();
        $this->actingAs($hr)->post(route('casuals.standards', $sam), ['told_on' => $told])->assertRedirect();
        $notice = \App\Models\StandardsNotice::query()->where('person_id', $sam->id)->first();
        $this->assertSame(
            \Carbon\Carbon::parse($told, 'Australia/Sydney')->addDays(28)->toDateString(),
            $notice->due_on->toDateString()
        );

        $sam->wwcc->update(['expires_on' => now()->addDays(5)->toDateString()]);

        $this->actingAs($hr)->get(route('watch'))
            ->assertOk()
            ->assertSee('Sam Green')
            ->assertSee('due this week')
            ->assertSee('28-day reply is due')
            ->assertDontSee($number);
        $this->actingAs($director)->get(route('watch'))
            ->assertOk()
            ->assertSee('Sam Green')
            ->assertDontSee('28-day reply')
            ->assertDontSee($number)
            ->assertDontSee('Classter staff file 14');
        $this->actingAs($samUser)->get(route('watch'))->assertForbidden();

        $this->actingAs($hr)->post(route('setup.staff.store'), [
            'name' => 'Yirran Director',
            'email' => 'yirran.watch@living.school',
            'role' => 'director',
            'neighbourhood' => 'Yirran',
            'password' => 'living-demo',
            'password_confirmation' => 'living-demo',
        ]);
        $this->actingAs(User::where('email', 'yirran.watch@living.school')->first())
            ->get(route('watch'))
            ->assertOk()
            ->assertDontSee('Sam Green');

        $this->actingAs($director)->get(route('casuals.show', $sam))
            ->assertOk()
            ->assertSee('Finished the fractions page')
            ->assertSee('https://portal.living.school/create-student-incident/', false)
            ->assertSee('Do not write a student')
            ->assertDontSee('Classter staff file 14')
            ->assertDontSee('28-day reply')
            ->assertDontSee('Where the full file is kept');
        $this->actingAs($samUser)->get(route('casuals.show', $sam))
            ->assertOk()
            ->assertDontSee('Classter staff file 14')
            ->assertDontSee('Count the 28 days')
            ->assertSee('Your answer')
            ->assertSee('Reply due');
        $this->actingAs($hr)->get(route('casuals.show', $sam))
            ->assertSee('Classter staff file 14')
            ->assertSee('Child protection')
            ->assertSee('Where the full file is kept')
            ->assertSee('Count the 28 days')
            ->assertSee('Written by HR Officer');

        $this->actingAs($samUser)->post(route('casuals.standards.reply', [$sam, $notice]), [
            'reply' => 'I have read the note and this is my answer.',
        ])->assertRedirect();
        $this->actingAs($hr)->get(route('watch'))->assertDontSee('28-day reply is due');
    }

    public function test_an_administrator_can_look_through_a_casuals_screens(): void
    {
        $hr = User::where('email', 'hr@living.school')->first();
        $director = User::where('email', 'director@living.school')->first();
        $sam = Person::where('email', 'sam.green@example.com')->first();
        $samUser = User::where('email', 'sam.green@example.com')->first();
        $pat = Person::where('email', 'pat.red@example.com')->first();

        $this->actingAs($director)->post(route('view-as.start', $sam))->assertForbidden();
        $this->actingAs($samUser)->post(route('view-as.start', $sam))->assertForbidden();
        $this->actingAs($director)->get(route('casuals.show', $sam))->assertDontSee('See what Sam Green sees');

        $this->actingAs($hr)->get(route('casuals.show', $sam))
            ->assertOk()
            ->assertSee('See what Sam Green sees')
            ->assertSee('Opens their day, sign-on, and people page');
        $this->actingAs($hr)->get(route('casuals.index'))->assertOk()->assertSee('See what Sam Green sees');

        $this->actingAs($hr)->from(route('casuals.show', $pat))
            ->post(route('view-as.start', $pat))
            ->assertRedirect(route('casuals.show', $pat))
            ->assertSessionHas('status', 'Pat Red has no sign-in yet. Send an invite, then you can open their screens.');
        $this->assertAuthenticatedAs($hr);

        $this->actingAs($hr)->post(route('view-as.start', $sam))->assertRedirect(route('start'));
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $hr->id,
            'action' => 'view.opened',
            'subject_id' => $sam->id,
        ]);

        $this->app['auth']->guard()->forgetUser();

        $this->get(route('start'))
            ->assertOk()
            ->assertSee('S3 sample')
            ->assertSee('You are seeing Sam Green')
            ->assertSee('Sign on for today')
            ->assertSee('Today')
            ->assertDontSee('Setup');
        $this->get(route('today'))
            ->assertOk()
            ->assertSee('Ask the office to read')
            ->assertDontSee('Show this at the office')
            ->assertDontSee('Record arrival');
        $this->get(route('people'))->assertOk()->assertSee('People');
        $this->get(route('setup'))->assertForbidden();
        $this->get(route('casuals.show', $pat))->assertForbidden();

        $this->from(route('today'))
            ->post(route('today.on', $sam), ['code' => 'ABCDEF'])
            ->assertRedirect(route('today'))
            ->assertSessionHasErrors('view');
        $this->assertDatabaseMissing('day_rolls', ['person_id' => $sam->id]);

        $this->post(route('logout'))->assertRedirect(route('casuals.show', $sam));
        $this->app['auth']->guard()->forgetUser();
        $this->get(route('casuals.show', $sam))
            ->assertOk()
            ->assertSee('You are back on your own desk.')
            ->assertSee('Setup')
            ->assertDontSee('You are seeing Sam Green');
        $this->assertAuthenticatedAs($hr);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $hr->id,
            'action' => 'view.closed',
            'subject_id' => $sam->id,
        ]);

        $this->actingAs($hr)->post(route('view-as.start', $sam))->assertRedirect(route('start'));
        $this->app['auth']->guard()->forgetUser();
        $this->post(route('view-as.stop'))->assertRedirect(route('casuals.show', $sam));
        $this->app['auth']->guard()->forgetUser();
        $this->get(route('setup'))->assertOk();
        $this->assertAuthenticatedAs($hr);
    }

    public function test_help_starts_with_the_job_in_front_of_the_person(): void
    {
        $hr = User::where('email', 'hr@living.school')->first();
        $director = User::where('email', 'director@living.school')->first();
        $office = User::where('email', 'office@living.school')->first();
        $mentor = User::where('email', 'mentor@living.school')->first();
        $sam = User::where('email', 'sam.green@example.com')->first();

        $this->get(route('help'))->assertRedirect(route('login'));

        $this->actingAs($hr)->get(route('casuals.index'))
            ->assertSee('How to use this desk')
            ->assertSee(route('help'), false);

        $this->actingAs($hr)->get(route('help'))
            ->assertOk()
            ->assertSee('Pay details stay in payroll.')
            ->assertSee('How this desk works')
            ->assertSee('A teacher wants to join')
            ->assertSee('Someone is teaching today')
            ->assertSee('A parent or grandparent wants to help')
            ->assertSee('Create invite link')
            ->assertSee('28-day reply')
            ->assertSee(route('apply'), false)
            ->assertSee(route('partners.join'), false);

        $this->actingAs($director)->get(route('help'))
            ->assertOk()
            ->assertSee('Your part of the desk')
            ->assertSee('your neighbourhood')
            ->assertDontSee('Create invite link')
            ->assertDontSee('volunteer check');

        $this->actingAs($office)->get(route('help'))
            ->assertOk()
            ->assertSee('office code')
            ->assertSee('Record arrival')
            ->assertDontSee('Create invite link');

        $this->actingAs($mentor)->get(route('help'))
            ->assertOk()
            ->assertSee('name you as mentor')
            ->assertDontSee('Create invite link');

        $this->actingAs($sam)->get(route('help'))
            ->assertOk()
            ->assertSee('Sign on')
            ->assertSee('portal.living.school')
            ->assertDontSee('Create invite link')
            ->assertDontSee('volunteer check');
    }

    private function tinyJpeg(): string
    {
        $image = imagecreatetruecolor(2, 2);
        ob_start();
        imagejpeg($image);
        imagedestroy($image);

        return ob_get_clean();
    }

    private function tinyPng(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    }
}
