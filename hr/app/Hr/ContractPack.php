<?php

namespace App\Hr;

use App\Models\ContractTemplate;
use App\Models\School;
use Illuminate\Support\Facades\Schema;

class ContractPack
{
    /**
     * @return list<array{key: string, name: string, body: string}>
     */
    public static function templates(): array
    {
        return [
            [
                'key' => 'casual-pool',
                'name' => 'Casual pool engagement',
                'body' => "Living School engages {{legal_name}} ({{preferred_name}}) as a casual teacher in {{neighbourhood}}.\nThe instrument is the Educational Services (Teachers) Award 2020, unless the school has an enterprise agreement that applies instead. The rate basis is the casual daily rate in that instrument. Each day is offered and accepted. It is not an ongoing appointment.\nNESA number {{nesa_number}}. Director {{director_name}}, {{director_mobile}}.\nDated {{today}}.",
            ],
            [
                'key' => 'day-booking',
                'name' => 'Single-day booking confirmation',
                'body' => "{{preferred_name}} ({{mobile}}) is booked on {{booked_on}} for {{class_label}} in {{neighbourhood}}.\nThe day runs {{start_time}} to {{finish_time}}. Director {{director_name}}, {{director_mobile}}. Office {{office_phone}}.\nThis note does not include a WWCC number or a date of birth.",
            ],
            [
                'key' => 'code-of-conduct',
                'name' => 'Code of conduct acknowledgement',
                'body' => "I, {{legal_name}}, have read the code of conduct in day-one induction and will follow it on every day I teach at Living School.\nDated {{today}}.",
            ],
            [
                'key' => 'child-protection',
                'name' => 'Child protection and professional boundaries acknowledgement',
                'body' => "I, {{legal_name}}, have read the child protection, reportable conduct, and professional boundaries pages in day-one induction.\nDated {{today}}.",
            ],
            [
                'key' => 'wwcc-consent',
                'name' => 'WWCC details and consent to verify',
                'body' => "I, {{legal_name}}, authorise Living School to verify my paid NSW Working With Children Check with the Office of the Children's Guardian. An application number is not a clearance.\nThe number and date of birth stay on the HR file. They are not printed on this page.\nDated {{today}}.",
            ],
            [
                'key' => 'privacy-notice',
                'name' => 'Privacy collection notice',
                'body' => "Living School collects {{preferred_name}}'s contact, accreditation, WWCC outcome, induction, and engagement records so it can decide whether they may teach and so it can contact them for a day of work.\nDirectors see the phone number and the clear-to-teach light. They do not see the WWCC number or date of birth.\nDated {{today}}.",
            ],
            [
                'key' => 'ceis-receipt',
                'name' => 'Casual Employment Information Statement receipt',
                'body' => "I, {{legal_name}}, have been given the Casual Employment Information Statement. This page is the receipt, not the statement itself.\nDated {{today}}.",
            ],
            [
                'key' => 'etams-alignment',
                'name' => 'eTAMS alignment request',
                'body' => "{{legal_name}}, NESA number {{nesa_number}}, asks the Conductor to approve eTAMS alignment with Living School.\nThe Conductor's decision is stored on the person. This request does not write to eTAMS.\nDated {{today}}.",
            ],
            [
                'key' => 'mentor-appointment',
                'name' => 'Mentor and Accreditation Supervisor appointment',
                'body' => "{{preferred_name}} is appointed a mentor in {{neighbourhood}}. Where {{preferred_name}} is Conditional or Provisional, that mentor is the Accreditation Supervisor if they are Proficient or above.\nDated {{today}}.",
            ],
            [
                'key' => 'end-of-pool',
                'name' => 'End of pool or variation',
                'body' => "The casual pool engagement of {{legal_name}} in {{neighbourhood}} is ended or varied from {{today}}. Earlier signed versions stay on the file.\nDirector {{director_name}}.",
            ],
        ];
    }

    public static function ensure(School $school): void
    {
        $hasSort = Schema::hasColumn('contract_templates', 'sort');
        foreach (self::templates() as $index => $template) {
            $sort = $hasSort ? ['sort' => $index + 1] : [];
            $published = ContractTemplate::firstOrCreate(
                ['school_id' => $school->id, 'key' => $template['key'], 'version' => 1],
                [
                    'name' => $template['name'],
                    'body' => $template['body'],
                    'status' => 'published',
                    'published_at' => now(),
                ] + $sort
            );
            ContractTemplate::firstOrCreate(
                ['school_id' => $school->id, 'key' => $template['key'], 'version' => 2],
                [
                    'name' => $template['name'],
                    'body' => $published->body,
                    'status' => 'draft',
                ] + $sort
            );
        }
    }
}
