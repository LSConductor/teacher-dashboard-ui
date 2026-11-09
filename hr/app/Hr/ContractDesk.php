<?php

namespace App\Hr;

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\Person;
use App\Services\Audit;
use App\Services\ContractMerger;

class ContractDesk
{
    public static function published(Person $person, string $key): ?ContractTemplate
    {
        return ContractTemplate::query()
            ->where('school_id', $person->school_id)
            ->where('key', $key)
            ->where('status', 'published')
            ->orderByDesc('version')
            ->first();
    }

    public static function alreadySent(Person $person, string $key): bool
    {
        return Contract::query()
            ->where('person_id', $person->id)
            ->whereIn('status', ['sent', 'signed'])
            ->whereHas('template', fn ($query) => $query->where('key', $key))
            ->exists();
    }

    public static function issue(Person $person, string $key, array $extra = []): ?Contract
    {
        ContractPack::refreshUntouched($person->school_id);
        $template = self::published($person, $key);
        if (! $template) {
            return null;
        }

        $contract = Contract::create([
            'school_id' => $person->school_id,
            'person_id' => $person->id,
            'template_id' => $template->id,
            'version' => $template->version,
            'merged_body' => app(ContractMerger::class)->merge($template, $person, $extra),
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        Audit::record('contract.sent', $contract, ['template' => $template->key, 'version' => $template->version]);

        return $contract;
    }
}
