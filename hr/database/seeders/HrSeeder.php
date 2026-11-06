<?php

namespace Database\Seeders;

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
        $mentor = $this->user('mentor@living.school', [
            'name' => 'Mentor Teacher',
            'password' => $password,
            'school_id' => $school->id,
            'role' => 'mentor',
            'mobile' => '0400 000 200',
        ]);
        $this->user('office@living.school', [
            'name' => 'Office',
            'password' => $password,
            'school_id' => $school->id,
            'role' => 'office',
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
            $modules->push(InductionModule::firstOrCreate(
                ['school_id' => $school->id, 'title' => $title],
                ['body' => $title, 'phase' => 'day_one', 'version' => 1, 'sort' => $sort]
            ));
            $sort++;
        }
        foreach ($later as $title) {
            $modules->push(InductionModule::firstOrCreate(
                ['school_id' => $school->id, 'title' => $title],
                ['body' => $title, 'phase' => 'later', 'version' => 1, 'sort' => $sort]
            ));
            $sort++;
        }

        $body = "Living School engages {{legal_name}} ({{preferred_name}}) as a casual teacher in {{neighbourhood}}.\nEach day is offered and accepted. NESA number {{nesa_number}}. Director {{director_name}}, {{director_mobile}}.\nDated {{today}}.";
        $template = ContractTemplate::firstOrCreate(
            ['school_id' => $school->id, 'key' => 'casual-pool', 'version' => 1],
            [
                'name' => 'Casual pool engagement',
                'body' => $body,
                'status' => 'published',
                'published_at' => now(),
            ]
        );
        ContractTemplate::firstOrCreate(
            ['school_id' => $school->id, 'key' => 'casual-pool', 'version' => 2],
            [
                'name' => 'Casual pool engagement',
                'body' => $template->body,
                'status' => 'draft',
            ]
        );

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

    private function user(string $email, array $attributes): User
    {
        return User::firstOrCreate(['email' => $email], $attributes);
    }
}
