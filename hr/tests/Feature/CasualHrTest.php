<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\InductionModule;
use App\Models\Invite;
use App\Models\Person;
use App\Models\School;
use App\Models\User;
use App\Models\WwccCheck;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
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
        ])->assertRedirect(route('casuals.index'));
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
            ->assertSee('I, Pat Red, acknowledge this page');
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
            ->assertDontSee($sam->wwcc->wwcc_number)
            ->assertDontSee('1990-04-12');

        $this->actingAs($samUser)
            ->get('/casual-induction')
            ->assertRedirect('/start');

        $director = User::where('email', 'director@living.school')->first();
        $this->actingAs($director)
            ->get('/start')
            ->assertOk()
            ->assertSee('Casual list')
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
}
