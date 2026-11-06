<?php

namespace App\Services;

use App\Models\ContractTemplate;
use App\Models\Person;

class ContractMerger
{
    public function merge(ContractTemplate $template, Person $person): string
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
        ];

        return strtr($template->body, $replacements);
    }
}
