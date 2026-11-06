<?php

namespace Tests\Feature;

use App\Hr\ClearToTeach;
use App\Models\Person;
use Carbon\Carbon;
use ReflectionMethod;
use Tests\TestCase;

class ClearToTeachTest extends TestCase
{
    public function test_the_clock_is_australia_sydney(): void
    {
        $this->assertSame('Australia/Sydney', config('app.timezone'));
    }

    public function test_assess_has_no_override_argument(): void
    {
        $names = array_map(
            fn ($parameter) => $parameter->getName(),
            (new ReflectionMethod(ClearToTeach::class, 'assess'))->getParameters(),
        );

        $this->assertNotContains('override', $names);
    }

    public function test_red_reasons_stay_red(): void
    {
        $lights = app(ClearToTeach::class);

        $missingWwcc = Person::factory()->clear()->create();
        $missingWwcc->wwcc->delete();
        $missingWwcc->unsetRelation('wwcc');
        $this->assertRed($lights, $missingWwcc, 'wwcc_missing');

        $app = Person::factory()->clear()->create();
        $app->wwcc->update([
            'wwcc_number' => 'APP1234567',
            'outcome' => 'verified',
            'app_number_blocked' => false,
        ]);
        $this->assertRed($lights, $app, 'wwcc_app_number');

        $bar = Person::factory()->clear()->create();
        $bar->wwcc->update(['bar_blocked' => true, 'outcome' => 'verified']);
        $this->assertRed($lights, $bar, 'wwcc_bar');

        $interim = Person::factory()->clear()->create();
        $interim->wwcc->update(['outcome' => 'interim_bar', 'interim_bar_blocked' => true]);
        $this->assertRed($lights, $interim, 'wwcc_interim_bar');

        $unverified = Person::factory()->clear()->create();
        $unverified->wwcc->update(['outcome' => 'pending', 'verified_at' => null]);
        $this->assertRed($lights, $unverified, 'wwcc_unverified');

        $missingAccreditation = Person::factory()->clear()->create();
        $missingAccreditation->accreditation->delete();
        $missingAccreditation->unsetRelation('accreditation');
        $this->assertRed($lights, $missingAccreditation, 'accreditation_missing');

        $suspended = Person::factory()->clear()->create();
        $suspended->accreditation->update(['status' => 'suspended']);
        $this->assertRed($lights, $suspended, 'accreditation_not_active');

        $notChecked = Person::factory()->clear()->create();
        $notChecked->accreditation->update(['etams_checked_at' => null, 'checked_by' => null]);
        $this->assertRed($lights, $notChecked, 'accreditation_not_checked');

        $pending = Person::factory()->clear()->create();
        $pending->accreditation->update([
            'alignment_decision' => 'pending',
            'other_employer_arrangement' => 'Some other school',
        ]);
        $this->assertRed($lights, $pending, 'alignment_not_approved');

        $declined = Person::factory()->clear()->create();
        $declined->accreditation->update([
            'alignment_decision' => 'declined',
            'other_employer_arrangement' => null,
        ]);
        $this->assertRed($lights, $declined, 'alignment_not_approved');

        $unsigned = Person::factory()->clear()->create();
        $unsigned->update(['engagement_signed_at' => null]);
        $this->assertRed($lights, $unsigned, 'engagement_unsigned');

        $dayOne = Person::factory()->clear()->create();
        $dayOne->update(['day_one_induction_completed_at' => null]);
        $this->assertRed($lights, $dayOne, 'day_one_incomplete');

        $applicant = Person::factory()->clear()->create();
        $applicant->update(['employment_status' => Person::APPLICANT]);
        $this->assertRed($lights, $applicant, 'not_in_pool');
    }

    public function test_sydney_date_decides_wwcc_expiry(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-07 00:30:00', 'Australia/Sydney'));
        $lights = app(ClearToTeach::class);

        $expired = Person::factory()->clear()->create();
        $expired->wwcc->update([
            'expires_on' => '2026-11-06',
            'outcome' => 'verified',
            'expired_blocked' => false,
            'pd_conversation_due_on' => null,
        ]);
        $expired->update(['pd_conversation_due_on' => '2026-11-01']);
        $light = $lights->assess($expired->fresh(['wwcc', 'accreditation']));

        $this->assertTrue($light->isRed());
        $this->assertContains('wwcc_expired', $light->reasons);
        $this->assertNotSame('amber', $light->colour);

        $inside = Person::factory()->clear()->create();
        $inside->wwcc->update([
            'expires_on' => '2026-11-07',
            'outcome' => 'verified',
            'expired_blocked' => false,
        ]);

        $this->assertTrue($lights->assess($inside->fresh(['wwcc', 'accreditation']))->isGreen());
    }

    public function test_declined_alignment_with_another_employer_arrangement_is_not_red_for_alignment(): void
    {
        $person = Person::factory()->clear()->create();
        $person->accreditation->update([
            'alignment_decision' => 'declined',
            'other_employer_arrangement' => 'Accredited through another NSW school.',
        ]);

        $light = app(ClearToTeach::class)->assess($person->fresh(['wwcc', 'accreditation']));

        $this->assertTrue($light->isGreen(), implode(',', $light->reasons));
    }

    public function test_amber_is_only_a_later_module_or_a_due_pd_conversation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-06 09:00:00', 'Australia/Sydney'));
        $lights = app(ClearToTeach::class);

        $pd = Person::factory()->clear()->create();
        $pd->update(['pd_conversation_due_on' => '2026-11-06']);
        $amber = $lights->assess($pd->fresh(['wwcc', 'accreditation']));
        $this->assertTrue($amber->isAmber());
        $this->assertSame(['pd_conversation_due'], $amber->reasons);

        $later = Person::factory()->clear()->create();
        $later->update(['later_induction_window_ends_on' => '2026-11-06']);
        $this->assertTrue($lights->assess($later->fresh(['wwcc', 'accreditation']))->isAmber());

        $closed = Person::factory()->clear()->create();
        $closed->update(['later_induction_window_ends_on' => '2026-11-05']);
        $this->assertTrue($lights->assess($closed->fresh(['wwcc', 'accreditation']))->isGreen());

        $notYet = Person::factory()->clear()->create();
        $notYet->update(['pd_conversation_due_on' => '2026-11-07']);
        $this->assertTrue($lights->assess($notYet->fresh(['wwcc', 'accreditation']))->isGreen());
    }

    private function assertRed(ClearToTeach $lights, Person $person, string $reason): void
    {
        $light = $lights->assess($person->fresh(['wwcc', 'accreditation']));
        $this->assertTrue($light->isRed(), $reason.' got '.$light->colour.' '.implode(',', $light->reasons));
        $this->assertContains($reason, $light->reasons);
    }
}
