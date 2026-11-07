<?php

namespace Database\Seeders;

use App\Hr\ContractPack;
use App\Hr\InductionPack;
use App\Models\Booking;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\InductionAcknowledgement;
use App\Models\InductionModule;
use App\Models\Person;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;

class HrSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::firstOrCreate(
            ['slug' => 'living-school'],
            ['name' => 'Living School', 'timezone' => 'Australia/Sydney']
        );

        $password = 'living-demo';

        $conductor = $this->user('conductor@living.school', [
            'name' => 'John Stewart',
            'password' => $password,
            'school_id' => $school->id,
            'role' => 'conductor',
        ]);
        $this->user('hr@living.school', [
            'name' => 'HR Officer',
            'password' => $password,
            'school_id' => $school->id,
            'role' => 'hr',
        ]);
        $director = $this->user('director@living.school', [
            'name' => 'Ngulingah Director',
            'password' => $password,
            'school_id' => $school->id,
            'role' => 'director',
            'neighbourhood' => 'Ngulingah',
            'mobile' => '0400 000 100',
        ]);
        $this->card($director, [
            'on_people_page' => true,
            'people_title' => 'Ngulingah Director',
            'days_on_site' => 'Monday to Friday',
            'people_note' => 'Call if the plan is unclear or a student needs a director.',
        ]);
        $mentor = $this->user('mentor@living.school', [
            'name' => 'Mentor Teacher',
            'password' => $password,
            'school_id' => $school->id,
            'role' => 'mentor',
            'mobile' => '0400 000 200',
        ]);
        $this->card($mentor, [
            'on_people_page' => true,
            'people_title' => 'Mentor',
            'days_on_site' => 'Monday to Thursday',
            'people_note' => 'Your mentor for the casual pool.',
        ]);
        $office = $this->user('office@living.school', [
            'name' => 'Office',
            'password' => $password,
            'school_id' => $school->id,
            'role' => 'office',
        ]);
        $this->card($office, [
            'on_people_page' => true,
            'people_title' => 'Office',
            'days_on_site' => 'Monday to Friday',
            'people_note' => 'Sign in here when you arrive. School day 9:00–3:15.',
        ]);

        $dayOne = [
            'Child protection and mandatory reporting',
            'Reportable conduct',
            'Professional boundaries',
            'Code of conduct',
            'Emergency, evacuation, and flood',
            'How pay works and who to call',
            'How to raise a student incident',
        ];
        $later = [
            'Attendance and the class admin you will use',
            'Adjustments and disability obligations',
            'Meeting your mentor',
            'NESA path for Conditional and Provisional teachers',
        ];
        $sort = 1;
        $modules = collect();
        foreach ($dayOne as $title) {
            $modules->push($this->module($school->id, $title, 'day_one', $sort++));
        }
        foreach ($later as $title) {
            $modules->push($this->module($school->id, $title, 'later', $sort++));
        }

        ContractPack::ensure($school);
        $template = ContractTemplate::query()
            ->where('school_id', $school->id)
            ->where('key', 'casual-pool')
            ->where('version', 1)
            ->first();

        $green = $this->person($school->id, 'Sam Green', 'Samantha Green', 'sam.green@example.com', 'Ngulingah', $mentor->id);
        $amber = $this->person($school->id, 'Riley Amber', 'Riley Amber', 'riley.amber@example.com', 'Ngulingah', $mentor->id);
        $red = $this->person($school->id, 'Pat Red', 'Patrick Red', 'pat.red@example.com', 'Ngulingah', null);

        foreach ([$green, $amber] as $person) {
            $person->accreditation()->update([
                'nesa_number' => 'NESA'.$person->id,
                'level' => 'proficient',
                'status' => 'active',
                'maintenance_end' => now()->addYears(6)->toDateString(),
                'fee_year' => (string) now()->year,
                'etams_checked_at' => now(),
                'checked_by' => $conductor->id,
                'alignment' => 'approved',
                'alignment_decided_at' => now(),
                'alignment_decided_by' => $conductor->id,
                'casual_evidence_on_file' => true,
            ]);
            $person->wwcc()->update([
                'wwcc_number' => 'WWC'.$person->id.'E',
                'name_on_check' => $person->legal_name,
                'date_of_birth' => '1990-04-12',
                'outcome' => 'cleared',
                'verified_at' => now(),
                'expires_on' => now()->addYear()->toDateString(),
                'verified_by' => $conductor->id,
            ]);
            Contract::firstOrCreate(
                ['person_id' => $person->id, 'template_id' => $template->id],
                [
                    'school_id' => $school->id,
                    'version' => 1,
                    'merged_body' => "Signed sample engagement for {$person->legal_name}.",
                    'status' => 'signed',
                    'sent_at' => now(),
                    'signed_at' => now(),
                ]
            );
        }

        $dayOneIds = $modules->where('phase', 'day_one')->pluck('id');
        foreach ([$green, $amber] as $person) {
            foreach ($dayOneIds as $moduleId) {
                InductionAcknowledgement::firstOrCreate(
                    ['person_id' => $person->id, 'module_id' => $moduleId, 'module_version' => 1],
                    ['school_id' => $school->id, 'acknowledged_at' => now()]
                );
            }
        }
        foreach ($modules->where('phase', 'later') as $module) {
            InductionAcknowledgement::firstOrCreate(
                ['person_id' => $green->id, 'module_id' => $module->id, 'module_version' => 1],
                ['school_id' => $school->id, 'acknowledged_at' => now()]
            );
        }

        if ($green->available_days === null) {
            $green->update([
                'available_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                'earliest_start' => '8:30',
                'neighbourhoods' => ['Ngulingah'],
            ]);
        }
        $booking = Booking::firstOrCreate(
            ['person_id' => $green->id, 'booked_on' => now()->toDateString()],
            [
                'school_id' => $school->id,
                'class_label' => 'S3 sample',
                'teacher_name' => 'Alex Sample',
                'plan_url' => 'https://portal.living.school/',
                'program_source' => 'wordpress',
                'external_program_id' => 'sample-s3',
                'light_at_booking' => 'green',
                'created_by' => $conductor->id,
            ]
        );
        if (blank($booking->teacher_name)) {
            $booking->teacher_name = 'Alex Sample';
            $booking->save();
        }

        $this->user('sam.green@example.com', [
            'name' => 'Sam Green',
            'password' => $password,
            'school_id' => $school->id,
            'role' => 'casual',
            'person_id' => $green->id,
            'neighbourhood' => 'Ngulingah',
        ]);

        $director->save();
    }

    private function person(int $schoolId, string $preferred, string $legal, string $email, string $neighbourhood, ?int $mentorId): Person
    {
        $person = Person::firstOrCreate(
            ['school_id' => $schoolId, 'email' => $email],
            [
                'preferred_name' => $preferred,
                'legal_name' => $legal,
                'mobile' => '0400 000 010',
                'neighbourhood' => $neighbourhood,
                'stages' => ['S2', 'S3'],
                'status' => 'casual_pool',
                'mentor_user_id' => $mentorId,
            ]
        );
        $person->accreditation()->firstOrCreate(
            [],
            ['school_id' => $schoolId, 'alignment' => 'pending', 'status' => 'active']
        );
        $person->wwcc()->firstOrCreate(
            [],
            ['school_id' => $schoolId, 'outcome' => 'pending']
        );

        return $person;
    }

    private function module(int $schoolId, string $title, string $phase, int $sort): InductionModule
    {
        $module = InductionModule::firstOrCreate(
            ['school_id' => $schoolId, 'title' => $title],
            [
                'body' => InductionPack::body($title),
                'phase' => $phase,
                'version' => 1,
                'sort' => $sort,
                'quiz' => InductionPack::quiz($title),
            ]
        );
        if ($module->body === $title || strlen((string) $module->body) < 80) {
            $module->body = InductionPack::body($title);
        }
        if (empty($module->quiz)) {
            $module->quiz = InductionPack::quiz($title);
        }
        $module->save();

        return $module;
    }

    private function user(string $email, array $attributes): User
    {
        return User::firstOrCreate(['email' => $email], $attributes);
    }

    private function card(User $user, array $fields): void
    {
        if ($user->people_title === null) {
            $user->fill($fields)->save();
        }
    }
}
