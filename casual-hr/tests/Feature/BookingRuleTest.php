<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Person;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class BookingRuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-06 09:00:00', 'Australia/Sydney'));
    }

    public function test_office_and_director_can_book_green_and_store_a_wordpress_program_id(): void
    {
        $fig = $this->neighbourhood('Fig Neighbourhood');
        $office = $this->staff(User::OFFICE);
        $director = $this->staff(User::DIRECTOR, $fig);
        $person = Person::factory()->clear()->create([
            'neighbourhood_id' => $fig->id,
            'preferred_name' => 'Avery Lang',
        ]);

        $this->actingAs($office)->post(route('bookings.store', $person), [
            'on_date' => '2026-11-06',
            'class_or_group' => 'Year 4',
            'wordpress_program_id' => '4821',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('bookings', [
            'person_id' => $person->id,
            'school_id' => $this->school->id,
            'status' => 'booked',
            'light_at_booking' => 'green',
            'wordpress_program_id' => '4821',
            'on_date' => '2026-11-06',
        ]);

        Booking::query()->delete();

        $this->actingAs($director)->post(route('bookings.store', $person), [
            'on_date' => '2026-11-06',
            'class_or_group' => 'Year 5',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('bookings', [
            'person_id' => $person->id,
            'status' => 'booked',
            'class_or_group' => 'Year 5',
        ]);
    }

    public function test_nobody_can_book_red_even_with_an_override_or_a_conductor_note(): void
    {
        $fig = $this->neighbourhood('Fig Neighbourhood');
        $office = $this->staff(User::OFFICE);
        $director = $this->staff(User::DIRECTOR, $fig);
        $conductor = $this->staff(User::CONDUCTOR);
        $person = Person::factory()->clear()->create(['neighbourhood_id' => $fig->id]);
        $person->wwcc->update([
            'outcome' => 'expired',
            'expired_blocked' => true,
            'expires_on' => '2026-11-05',
        ]);

        foreach ([$office, $director, $conductor] as $actor) {
            $this->actingAs($actor)->post(route('bookings.store', $person), [
                'on_date' => '2026-11-06',
                'class_or_group' => 'Year 4',
                'override' => '1',
                'conductor_note' => 'Please let them teach',
            ])->assertSessionHasErrors('booking');
        }

        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseHas('audits', [
            'action' => 'booking.override_refused',
            'school_id' => $this->school->id,
        ]);

        $held = Booking::create([
            'school_id' => $person->school_id,
            'person_id' => $person->id,
            'on_date' => '2026-11-06',
            'class_or_group' => 'Year 4',
            'status' => 'held',
            'conductor_note' => 'Already noted',
            'conductor_note_by' => $conductor->id,
            'conductor_note_at' => now(),
        ]);

        $this->actingAs($office)->post(route('bookings.confirm', $held), [
            'override' => '1',
        ])->assertSessionHasErrors('booking');

        $this->assertSame('held', $held->fresh()->status);

        $this->actingAs($conductor)->post(route('bookings.note', $held), [
            'conductor_note' => 'Still no',
        ])->assertSessionHasErrors('booking');
    }

    public function test_office_books_amber_only_when_a_conductor_note_is_already_on_the_booking(): void
    {
        $fig = $this->neighbourhood('Fig Neighbourhood');
        $office = $this->staff(User::OFFICE);
        $conductor = $this->staff(User::CONDUCTOR);
        $person = Person::factory()->amberPd()->create([
            'neighbourhood_id' => $fig->id,
            'preferred_name' => 'Blake Okonkwo',
        ]);

        $this->actingAs($office)->post(route('bookings.store', $person), [
            'on_date' => '2026-11-06',
            'class_or_group' => 'Year 1',
            'conductor_note' => 'Typed on the same request',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $booking = Booking::query()->firstOrFail();
        $this->assertSame('held', $booking->status);
        $this->assertNull($booking->conductor_note);

        $this->actingAs($office)->post(route('bookings.confirm', $booking))
            ->assertSessionHasErrors('booking');
        $this->assertSame('held', $booking->fresh()->status);

        $this->actingAs($conductor)->post(route('bookings.note', $booking), [
            'conductor_note' => 'PD conversation is booked with the mentor after school.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($office)->post(route('bookings.confirm', $booking))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'booked',
            'light_at_booking' => 'amber',
        ]);
    }

    public function test_a_director_cannot_book_another_neighbourhood_and_hr_cannot_book(): void
    {
        $fig = $this->neighbourhood('Fig Neighbourhood');
        $river = $this->neighbourhood('River Neighbourhood');
        $director = $this->staff(User::DIRECTOR, $fig);
        $hr = $this->staff(User::HR);
        $person = Person::factory()->clear()->create(['neighbourhood_id' => $river->id]);

        $this->actingAs($director)->post(route('bookings.store', $person), [
            'on_date' => '2026-11-06',
            'class_or_group' => 'Year 3',
        ])->assertForbidden();

        $this->actingAs($hr)->post(route('bookings.store', $person), [
            'on_date' => '2026-11-06',
            'class_or_group' => 'Year 3',
        ])->assertForbidden();
    }

    public function test_a_program_id_must_be_a_wordpress_post_id(): void
    {
        $office = $this->staff(User::OFFICE);
        $person = Person::factory()->clear()->create();

        $this->actingAs($office)->post(route('bookings.store', $person), [
            'on_date' => '2026-11-06',
            'class_or_group' => 'Year 4',
            'wordpress_program_id' => 'ckxyz',
        ])->assertSessionHasErrors('wordpress_program_id');

        $this->assertDatabaseCount('bookings', 0);
    }
}
