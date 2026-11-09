<?php

namespace App\Services;

use App\Models\ContractTemplate;
use App\Models\Person;

class ContractMerger
{
    public function merge(ContractTemplate $template, Person $person, array $extra = []): string
    {
        $director = \App\Models\User::query()
            ->where('school_id', $person->school_id)
            ->where('role', 'director')
            ->where('neighbourhood', $person->neighbourhood)
            ->first();

        $replacements = [
            '{{preferred_name}}' => $person->preferred_name,
            '{{legal_name}}' => $person->legal_name,
            '{{email}}' => $person->email,
            '{{mobile}}' => $person->mobile ?? '',
            '{{neighbourhood}}' => $person->neighbourhood ?? '',
            '{{nesa_number}}' => $person->accreditation?->nesa_number ?? '',
            '{{director_name}}' => $director?->name ?? 'Learning Neighbourhood Director',
            '{{director_mobile}}' => $director?->mobile ?? '',
            '{{today}}' => now()->timezone('Australia/Sydney')->toFormattedDateString(),
            '{{class_label}}' => $extra['class_label'] ?? 'to be confirmed',
            '{{booked_on}}' => $extra['booked_on'] ?? now()->timezone('Australia/Sydney')->toDateString(),
            '{{start_time}}' => $extra['start_time'] ?? '9:00',
            '{{finish_time}}' => $extra['finish_time'] ?? '3:15',
            '{{office_phone}}' => '(02) 5632 1218',
        ];

        return strtr($template->body, $replacements);
    }

    public function sample(string $body): string
    {
        return strtr($body, [
            '{{preferred_name}}' => 'Sam Green',
            '{{legal_name}}' => 'Samantha Green',
            '{{email}}' => 'sam.green@living.school',
            '{{mobile}}' => '0400 000 111',
            '{{neighbourhood}}' => 'Ngulingah',
            '{{nesa_number}}' => 'NESA000',
            '{{director_name}}' => 'Rob Roberts',
            '{{director_mobile}}' => '0400 000 100',
            '{{today}}' => now()->timezone('Australia/Sydney')->format('j F Y'),
            '{{class_label}}' => 'S3 sample',
            '{{booked_on}}' => now()->timezone('Australia/Sydney')->format('j F Y'),
            '{{start_time}}' => '9:00',
            '{{finish_time}}' => '3:15',
            '{{office_phone}}' => '(02) 5632 1218',
        ]);
    }
}
