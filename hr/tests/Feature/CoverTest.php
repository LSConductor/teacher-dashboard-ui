<?php

namespace Tests\Feature;

use App\Hr\Roles;
use App\Mail\CoverMail;
use App\Models\Booking;
use App\Models\Person;
use App\Models\User;
use Database\Seeders\HrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CoverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Roles::forgetAll();
        $this->seed(HrSeeder::class);
        Mail::fake();
    }

    public function test_a_director_asks_the_free_people_in_order_and_the_first_yes_books(): void
    {
        $director = User::where('email', 'director@living.school')->first();
        $sam = Person::where('email', 'sam.green@example.com')->first();
        $second = $this->twin($sam, 'Casey Ngul', 'casey.ngul@example.com', 'Ngulingah');
        $middle = $this->twin($sam, 'Morgan Middle', 'morgan.middle@example.com', 'Middle');
        $across = $this->twin($sam, 'Avery All', 'avery.all@example.com', 'K-12');
        $on = $this->schoolDay();

        $this->actingAs($director)
            ->get(route('cover', ['on' => $on]))
            ->assertOk()
            ->assertSee('Who can take a class')
            ->assertSee('Sam Green')
            ->assertSee('Casey Ngul')
            ->assertSee('Avery All')
            ->assertSee('Riley Amber')
            ->assertDontSee('Pat Red')
            ->assertDontSee('Morgan Middle')
            ->assertDontSee($sam->wwcc->wwcc_number);

        $this->actingAs($director)
            ->post(route('cover.store'), [
                'on' => $on,
                'class_label' => 'S3 Ngul',
                'teacher_name' => 'Alex Sample',
                'neighbourhood' => 'Middle',
                'mode' => 'ordered',
                'person_ids' => [$middle->id],
                'order' => [$middle->id => 1],
            ])
            ->assertSessionHasErrors('person_ids');

        $this->actingAs($director)
            ->post(route('cover.store'), [
                'on' => $on,
                'class_label' => 'S3 Ngul',
                'teacher_name' => 'Alex Sample',
                'mode' => 'ordered',
                'person_ids' => [$sam->id, $second->id],
                'order' => [$sam->id => 1, $second->id => 2],
            ])
            ->assertRedirect();

        Mail::assertSent(CoverMail::class, 1);
        $url = null;
        Mail::assertSent(CoverMail::class, function (CoverMail $mail) use (&$url) {
            if ($mail->hasTo('sam.green@example.com') && $mail->kind === 'ask') {
                $url = $mail->url;

                return true;
            }

            return false;
        });
        $token = basename((string) parse_url((string) $url, PHP_URL_PATH));

        $this->get(route('cover.reply', $token))
            ->assertOk()
            ->assertSee('S3 Ngul')
            ->assertSee('Accept')
            ->assertDontSee($sam->wwcc->wwcc_number)
            ->assertDontSee('Casey Ngul');

        $this->post(route('cover.decline', $token))->assertRedirect();
        Mail::assertSent(CoverMail::class, 2);

        $secondUrl = null;
        Mail::assertSent(CoverMail::class, function (CoverMail $mail) use (&$secondUrl) {
            if ($mail->hasTo('casey.ngul@example.com') && $mail->kind === 'ask') {
                $secondUrl = $mail->url;

                return true;
            }

            return false;
        });
        $secondToken = basename((string) parse_url((string) $secondUrl, PHP_URL_PATH));
        $this->post(route('cover.accept', $secondToken))->assertRedirect();

        $this->assertTrue(
            Booking::query()->where('person_id', $second->id)->where('class_label', 'S3 Ngul')->whereDate('booked_on', $on)->exists()
        );
        $this->assertFalse(
            Booking::query()->where('person_id', $sam->id)->where('class_label', 'S3 Ngul')->exists()
        );
        $this->get(route('cover.reply', $secondToken))->assertSee('You have this day');
    }

    public function test_asking_everyone_books_the_first_yes_and_closes_the_rest(): void
    {
        $director = User::where('email', 'director@living.school')->first();
        $sam = Person::where('email', 'sam.green@example.com')->first();
        $second = $this->twin($sam, 'Casey Ngul', 'casey.ngul@example.com', 'Ngulingah');
        $on = $this->schoolDay();

        $this->actingAs($director)->post(route('cover.store'), [
            'on' => $on,
            'class_label' => 'S2 together',
            'mode' => 'all',
            'person_ids' => [$sam->id, $second->id],
        ])->assertRedirect();

        Mail::assertSent(CoverMail::class, 2);
        $tokens = [];
        Mail::assertSent(CoverMail::class, function (CoverMail $mail) use (&$tokens) {
            if ($mail->kind === 'ask') {
                $tokens[$mail->ask->person_id] = basename((string) parse_url((string) $mail->url, PHP_URL_PATH));
            }

            return $mail->kind === 'ask';
        });

        $this->post(route('cover.accept', $tokens[$sam->id]))->assertRedirect();
        $this->post(route('cover.accept', $tokens[$second->id]))->assertRedirect();
        $this->get(route('cover.reply', $tokens[$second->id]))->assertSee('This cover is filled');
        $this->assertSame(1, Booking::query()->where('class_label', 'S2 together')->count());
    }

    public function test_red_and_amber_stay_off_a_directors_ask_unless_the_conductor_writes_the_note(): void
    {
        $director = User::where('email', 'director@living.school')->first();
        $conductor = User::where('email', 'conductor@living.school')->first();
        $mentor = User::where('email', 'mentor@living.school')->first();
        $amber = Person::where('email', 'riley.amber@example.com')->first();
        $red = Person::where('email', 'pat.red@example.com')->first();
        $on = $this->schoolDay();

        $this->actingAs($mentor)->get(route('cover', ['on' => $on]))->assertForbidden();

        $this->actingAs($director)->post(route('cover.store'), [
            'on' => $on,
            'class_label' => 'S3',
            'mode' => 'ordered',
            'person_ids' => [$red->id],
        ])->assertSessionHasErrors('person_ids');

        $this->actingAs($director)->post(route('cover.store'), [
            'on' => $on,
            'class_label' => 'S3',
            'mode' => 'ordered',
            'person_ids' => [$amber->id],
            'conductor_note' => 'Trying to sneak a note',
        ])->assertSessionHasErrors('person_ids');

        $this->actingAs($conductor)->post(route('cover.store'), [
            'on' => $on,
            'class_label' => 'S3 amber',
            'neighbourhood' => 'Ngulingah',
            'mode' => 'ordered',
            'person_ids' => [$amber->id],
            'conductor_note' => 'Known to the class, and the gap is later induction only.',
        ])->assertRedirect();

        Mail::assertSent(CoverMail::class, fn (CoverMail $mail) => $mail->hasTo('riley.amber@example.com'));
    }

    public function test_a_casual_can_accept_from_their_own_day(): void
    {
        $director = User::where('email', 'director@living.school')->first();
        $samUser = User::where('email', 'sam.green@example.com')->first();
        $sam = Person::where('email', 'sam.green@example.com')->first();
        $on = $this->schoolDay();

        $this->actingAs($director)->post(route('cover.store'), [
            'on' => $on,
            'class_label' => 'S4 music',
            'mode' => 'ordered',
            'person_ids' => [$sam->id],
        ])->assertRedirect();

        $this->actingAs($samUser)
            ->get(route('start'))
            ->assertOk()
            ->assertSee('S4 music')
            ->assertSee('Accept');

        $ask = $sam->fresh()->bookings()->count();
        $this->actingAs($samUser)
            ->post(route('cover.mine.accept', $sam->coverAsks()->first()))
            ->assertRedirect();

        $this->assertSame($ask + 1, $sam->bookings()->count());
    }

    public function test_someone_already_booked_is_not_asked(): void
    {
        $director = User::where('email', 'director@living.school')->first();
        $sam = Person::where('email', 'sam.green@example.com')->first();
        $on = $this->schoolDay();
        Booking::create([
            'school_id' => $sam->school_id,
            'person_id' => $sam->id,
            'booked_on' => $on,
            'class_label' => 'Already in',
            'light_at_booking' => 'green',
            'created_by' => $director->id,
        ]);

        $this->actingAs($director)
            ->get(route('cover', ['on' => $on]))
            ->assertOk()
            ->assertSee('Already booked for Already in')
            ->assertDontSee('name="person_ids[]" value="'.$sam->id.'"', false);

        $this->actingAs($director)->post(route('cover.store'), [
            'on' => $on,
            'class_label' => 'Another class',
            'mode' => 'ordered',
            'person_ids' => [$sam->id],
        ])->assertSessionHasErrors('person_ids');
    }

    private function schoolDay(): string
    {
        $day = now()->timezone('Australia/Sydney')->addDays(9)->startOfDay();
        while ($day->isWeekend()) {
            $day->addDay();
        }

        return $day->toDateString();
    }

    private function twin(Person $sam, string $name, string $email, string $neighbourhood): Person
    {
        $copy = $sam->replicate();
        $copy->preferred_name = $name;
        $copy->legal_name = $name;
        $copy->email = $email;
        $copy->neighbourhood = $neighbourhood;
        $copy->neighbourhoods = [$neighbourhood];
        $copy->available_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $copy->save();

        foreach (['accreditation', 'wwcc'] as $relation) {
            $row = $sam->{$relation}->replicate();
            $row->person_id = $copy->id;
            if ($relation === 'wwcc') {
                $row->wwcc_number = 'WWC'.$copy->id.'E';
                $row->name_on_check = $name;
            }
            $row->save();
        }
        foreach ($sam->contracts as $contract) {
            $row = $contract->replicate();
            $row->person_id = $copy->id;
            $row->save();
        }
        foreach ($sam->acknowledgements as $ack) {
            $row = $ack->replicate();
            $row->person_id = $copy->id;
            $row->save();
        }

        return $copy;
    }
}
