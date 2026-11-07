<?php

namespace Tests\Feature;

use App\Hr\PartnerPack;
use App\Hr\Roles;
use App\Models\Partner;
use App\Models\PartnerPage;
use App\Models\Person;
use App\Models\User;
use Database\Seeders\HrSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerCourseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Roles::forgetAll();
        $this->seed(HrSeeder::class);
    }

    public function test_a_parent_finishes_and_a_director_sees_them_only_when_cleared(): void
    {
        $this->get(route('partners.join'))
            ->assertOk()
            ->assertSee('Help at Living School')
            ->assertDontSee('Complete Lesson');

        $this->post(route('partners.join.store'), [
            'preferred_name' => 'Ada River',
            'email' => 'ada.river@example.com',
            'relationship' => 'grandparent',
            'child_name' => 'Jo River',
            'neighbourhood' => 'Ngulingah',
        ])->assertRedirect();

        $ada = Partner::where('email', 'ada.river@example.com')->first();
        $this->assertNotNull($ada);
        $this->get(route('partners.page', [$ada, 'confidence']))->assertRedirect();

        foreach (PartnerPage::orderBy('sort')->get() as $page) {
            $this->post(route('partners.page.next', [$ada, $page->slug]))->assertRedirect();
        }

        $wrong = [];
        foreach (PartnerPack::quiz() as $index => $question) {
            $wrong[$index] = 1;
        }
        $this->post(route('partners.check.save', $ada), ['answers' => $wrong])
            ->assertSessionHasErrors('answers');
        $this->assertNull($ada->fresh()->quiz_version_passed);

        $answers = [];
        foreach (PartnerPack::quiz() as $index => $question) {
            $answers[$index] = $question['answer'];
        }
        $this->post(route('partners.check.save', $ada), ['answers' => $answers])->assertRedirect(route('partners.about', $ada));
        $this->post(route('partners.about.save', $ada), [
            'mobile' => '0400 222 333',
            'interests' => ['reading', 'canteen'],
            'strengths' => 'I like reading with beginning readers.',
            'work_style' => 'set_path',
            'with_children' => 'room',
            'availability' => 'Tuesday mornings',
        ])->assertRedirect(route('partners.done', $ada));

        $this->get(route('partners.done', $ada))->assertSee('HR has this');

        $hr = User::where('email', 'hr@living.school')->first();
        $director = User::where('email', 'director@living.school')->first();
        $office = User::where('email', 'office@living.school')->first();

        $this->actingAs($hr)->get(route('partners.index'))
            ->assertOk()
            ->assertSee('Ada River')
            ->assertSee('Finished, waiting on HR');

        $this->actingAs($director)->get(route('partners.index'))
            ->assertOk()
            ->assertDontSee('Ada River');

        $this->actingAs($office)->get(route('partners.index'))->assertForbidden();

        $this->actingAs($hr)->post(route('partners.volunteer', $ada), [
            'volunteer_status' => 'cleared',
            'volunteer_number' => 'APP1234567',
            'volunteer_verified_on' => now()->toDateString(),
            'volunteer_expires_on' => now()->addYear()->toDateString(),
        ])->assertSessionHasErrors('volunteer_number');

        $this->actingAs($hr)->post(route('partners.volunteer', $ada), [
            'volunteer_status' => 'cleared',
            'volunteer_number' => 'WWC100E',
            'volunteer_verified_on' => now()->toDateString(),
            'volunteer_expires_on' => now()->addYear()->toDateString(),
        ])->assertRedirect();

        $this->actingAs($director)->get(route('partners.index', ['interest' => 'reading']))
            ->assertOk()
            ->assertSee('Ada River')
            ->assertSee('Hearing readers')
            ->assertSee('Showing Ngulingah only.')
            ->assertDontSee('WWC100E');

        $this->actingAs($director)->get(route('partners.index', ['interest' => 'excursion']))
            ->assertOk()
            ->assertDontSee('Ada River');

        $this->actingAs($director)->get(route('partners.show', $ada))
            ->assertOk()
            ->assertDontSee('WWC100E')
            ->assertDontSee('name="volunteer_number"', false);

        Person::create([
            'school_id' => $director->school_id,
            'preferred_name' => 'Yirran Child',
            'legal_name' => 'Yirran Child',
            'email' => 'yirran.child@example.com',
            'neighbourhood' => 'Yirran',
            'status' => 'casual_pool',
        ]);
        $this->post(route('partners.join.store'), [
            'preferred_name' => 'Bo Yirran',
            'email' => 'bo.yirran@example.com',
            'relationship' => 'parent',
            'child_name' => 'Sam Yirran',
            'neighbourhood' => 'Yirran',
        ]);
        $bo = Partner::where('email', 'bo.yirran@example.com')->first();
        $bo->update([
            'interests' => ['reading'],
            'quiz_version_passed' => 1,
            'seen' => PartnerPage::pluck('version', 'slug')->all(),
            'volunteer_status' => 'cleared',
            'volunteer_number' => 'WWC200E',
            'volunteer_verified_on' => now()->toDateString(),
            'volunteer_expires_on' => now()->addYear()->toDateString(),
            'finished_at' => now(),
        ]);

        $this->actingAs($director)->get(route('partners.index'))
            ->assertOk()
            ->assertSee('Ada River')
            ->assertDontSee('Bo Yirran');

        $welcome = PartnerPage::where('slug', 'welcome')->first();
        $this->actingAs($hr)->put(route('setup.partners.update', $welcome), [
            'title' => $welcome->title,
            'body' => $welcome->body."\n\nA new sentence for the welcome.",
            'video_url' => 'https://example.com/thanks',
        ])->assertRedirect(route('setup.partners'));

        $this->actingAs($director)->get(route('partners.index'))->assertDontSee('Ada River');
        $ada->refresh();
        $seen = $ada->seen;
        $seen['welcome'] = $welcome->fresh()->version;
        $ada->seen = $seen;
        $ada->save();
        $this->actingAs($director)->get(route('partners.index'))->assertSee('Ada River')->assertDontSee('WWC100E');
    }
}
