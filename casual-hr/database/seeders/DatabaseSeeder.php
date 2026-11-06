<?php

namespace Database\Seeders;

use App\Models\Accreditation;
use App\Models\Booking;
use App\Models\Mentorship;
use App\Models\Neighbourhood;
use App\Models\Person;
use App\Models\School;
use App\Models\User;
use App\Models\Wwcc;
use App\Support\Sydney;
use App\Tenancy\SchoolContext;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::create([
            'name' => 'Living School',
            'slug' => 'living-school',
            'timezone' => 'Australia/Sydney',
        ]);
        app(SchoolContext::class)->use($school);

        $fig = Neighbourhood::create(['name' => 'Fig Neighbourhood']);
        $river = Neighbourhood::create(['name' => 'River Neighbourhood']);

        User::create([
            'name' => 'Morgan Hale',
            'email' => 'morgan.hale@example.test',
            'password' => 'local-staff',
            'role' => User::CONDUCTOR,
        ]);
        $hr = User::create([
            'name' => 'Rowan Ellis',
            'email' => 'rowan.ellis@example.test',
            'password' => 'local-staff',
            'role' => User::HR,
        ]);
        $quinn = User::create([
            'name' => 'Quinn Adler',
            'email' => 'quinn.adler@example.test',
            'password' => 'local-staff',
            'role' => User::DIRECTOR,
            'neighbourhood_id' => $fig->id,
        ]);
        User::create([
            'name' => 'Sage Bennett',
            'email' => 'sage.bennett@example.test',
            'password' => 'local-staff',
            'role' => User::DIRECTOR,
            'neighbourhood_id' => $river->id,
        ]);
        $office = User::create([
            'name' => 'Taylor Brooks',
            'email' => 'taylor.brooks@example.test',
            'password' => 'local-staff',
            'role' => User::OFFICE,
        ]);
        $conductor = User::where('email', 'morgan.hale@example.test')->firstOrFail();

        $nia = $this->person($fig, 'Nia Cole', 'Nia Cole', 'nia.cole@example.test', '0400000010', ['S3', 'S4']);
        $this->clear($nia, $hr, $conductor, 'WWC8000010E', '1981-03-14');

        $avery = $this->person($fig, 'Avery Legalname', 'Avery Lang', 'avery.lang@example.test', '0400000001', ['S2', 'S3'], [
            'postal_address' => '9 Fictional Lane, Example Town NSW 2999',
            'google_subject' => 'fictional-google-sub-avery-lang',
            'payroll_reference' => 'PAY-FICTION-001',
        ]);
        $this->clear($avery, $hr, $conductor, 'WWC8000001E', '1984-02-29');
        $this->mentor($avery, $nia, false);

        Booking::create([
            'school_id' => $school->id,
            'person_id' => $avery->id,
            'neighbourhood_id' => $fig->id,
            'on_date' => Sydney::today()->subDay()->toDateString(),
            'class_or_group' => 'Year 4',
            'director_user_id' => $quinn->id,
            'status' => 'booked',
            'light_at_booking' => 'green',
            'booked_by' => $office->id,
            'booked_at' => Sydney::now()->subDay(),
        ]);

        $blake = $this->person($river, 'Blake Okonkwo', 'Blake Okonkwo', 'blake.okonkwo@example.test', '0400000002', ['S1']);
        $this->clear($blake, $hr, $conductor, 'WWC8000002E', '1992-06-02');
        $blake->update(['pd_conversation_due_on' => Sydney::today()->toDateString()]);
        $this->mentor($blake, $nia, false);

        $casey = $this->person($river, 'Casey Nguyen', 'Casey Nguyen', 'casey.nguyen@example.test', '0400000003', ['S2']);
        $this->clear($casey, $hr, $conductor, 'WWC8000003E', '1988-11-09', [
            'expires_on' => Sydney::today()->subDay()->toDateString(),
            'outcome' => 'expired',
            'expired_blocked' => true,
        ]);

        $drew = $this->person($fig, 'Drew Patel', 'Drew Patel', 'drew.patel@example.test', '0400000004', ['S4']);
        $this->clear($drew, $hr, $conductor, 'APP8000004', '1995-01-20', [
            'outcome' => 'app_number',
            'app_number_blocked' => true,
            'verified_at' => null,
            'verified_by' => null,
        ]);

        $ellis = $this->person($river, 'Ellis Mora', 'Ellis Mora', 'ellis.mora@example.test', '0400000005', ['ES1']);
        $this->clear($ellis, $hr, $conductor, 'WWC8000005E', '1991-08-08', [
            'outcome' => 'interim_bar',
            'interim_bar_blocked' => true,
        ]);

        $frankie = $this->person($fig, 'Frankie Berg', 'Frankie Berg', 'frankie.berg@example.test', '0400000006', ['S5']);
        $this->clear($frankie, $hr, $conductor, 'WWC8000006E', '1986-12-01', accreditation: [
            'status' => 'suspended',
        ]);

        $gray = $this->person($river, 'Gray Ito', 'Gray Ito', 'gray.ito@example.test', '0400000007', ['S3']);
        $this->clear($gray, $hr, $conductor, 'WWC8000007E', '1998-04-18', accreditation: [
            'level' => 'provisional',
            'alignment_decision' => 'pending',
            'alignment_decided_at' => null,
            'alignment_decided_by' => null,
        ]);
        $this->mentor($gray, $nia, true);

        $harper = $this->person($fig, 'Harper Quinn', 'Harper Quinn', 'harper.quinn@example.test', '0400000008', ['S1', 'S2']);
        $this->clear($harper, $hr, $conductor, 'WWC8000008E', '1993-07-22');
        $harper->update(['day_one_induction_completed_at' => null]);

        $jules = $this->person($river, 'Jules Okada', 'Jules Okada', 'jules.okada@example.test', '0400000009', ['S6']);
        $this->clear($jules, $hr, $conductor, 'WWC8000009E', '1989-05-05');
        $jules->update(['engagement_signed_at' => null]);

        $lane = $this->person($fig, 'Lane Foster', 'Lane Foster', 'lane.foster@example.test', '0400000011', ['S3']);
        $this->clear($lane, $hr, $conductor, 'WWC8000011E', '1990-10-10', [
            'expires_on' => Sydney::today()->addDays(20)->toDateString(),
        ]);
        $this->mentor($lane, $nia, false);

        $remy = $this->person($fig, 'Remy Santos', 'Remy Santos', 'remy.santos@example.test', '0400000012', ['S2']);
        $this->clear($remy, $hr, $conductor, 'WWC8000012E', '1987-09-09', [
            'outcome' => 'barred',
            'bar_blocked' => true,
        ]);
    }

    /**
     * @param  list<string>  $stages
     * @param  array<string, mixed>  $extra
     */
    private function person(Neighbourhood $neighbourhood, string $legal, string $preferred, string $email, string $mobile, array $stages, array $extra = []): Person
    {
        return Person::create(array_merge([
            'legal_name' => $legal,
            'preferred_name' => $preferred,
            'email' => $email,
            'mobile' => $mobile,
            'neighbourhood_id' => $neighbourhood->id,
            'stages' => $stages,
            'employment_status' => Person::CASUAL_POOL,
        ], $extra));
    }

    /**
     * @param  array<string, mixed>  $wwcc
     * @param  array<string, mixed>  $accreditation
     */
    private function clear(Person $person, User $hr, User $conductor, string $number, string $dob, array $wwcc = [], array $accreditation = []): void
    {
        Accreditation::create(array_merge([
            'school_id' => $person->school_id,
            'person_id' => $person->id,
            'nesa_number' => 'NESA'.substr($number, -4),
            'level' => 'proficient',
            'status' => 'active',
            'maintenance_start' => '2024-01-01',
            'maintenance_end' => '2031-01-01',
            'casual_evidence_given' => false,
            'fee_year' => 2026,
            'etams_checked_at' => Sydney::now(),
            'checked_by' => $hr->id,
            'alignment_decision' => 'approved',
            'alignment_decided_at' => Sydney::now(),
            'alignment_decided_by' => $conductor->id,
            'evidence_reference' => 'Fictional eTAMS note. Not a real teacher.',
        ], $accreditation));

        Wwcc::create(array_merge([
            'school_id' => $person->school_id,
            'person_id' => $person->id,
            'wwcc_number' => $number,
            'name_used' => $person->legal_name,
            'date_of_birth' => $dob,
            'outcome' => 'verified',
            'verified_at' => Sydney::now(),
            'expires_on' => Sydney::today()->addYear()->toDateString(),
            'verified_by' => $hr->id,
            'app_number_blocked' => false,
            'bar_blocked' => false,
            'interim_bar_blocked' => false,
            'expired_blocked' => false,
        ], $wwcc));

        $person->update([
            'engagement_signed_at' => Sydney::now(),
            'day_one_induction_completed_at' => Sydney::now(),
        ]);
    }

    private function mentor(Person $casual, Person $mentor, bool $supervisor): void
    {
        Mentorship::create([
            'school_id' => $casual->school_id,
            'person_id' => $casual->id,
            'mentor_person_id' => $mentor->id,
            'is_accreditation_supervisor' => $supervisor,
            'started_on' => Sydney::today()->subMonths(2)->toDateString(),
        ]);
    }
}
