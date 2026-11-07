<?php

namespace App\Services;

use App\Models\InductionModule;
use App\Models\Person;
use App\Models\WwccCheck;

class ClearToTeach
{
    /**
     * @return array{light: string, reasons: array<int, array{level: string, text: string}>}
     */
    public function assess(Person $person): array
    {
        $person->loadMissing(['accreditation', 'wwcc', 'contracts.template', 'acknowledgements']);
        $reasons = [];

        if ($person->blocked) {
            $reasons[] = ['level' => 'red', 'text' => $person->block_reason ?: 'Blocked from teaching'];
        }

        $accreditation = $person->accreditation;
        $activeLevel = $accreditation && in_array($accreditation->level, [
            'conditional', 'provisional', 'proficient', 'highly_accomplished', 'lead',
        ], true);
        if (! $accreditation || $accreditation->status !== 'active' || ! $activeLevel) {
            $reasons[] = ['level' => 'red', 'text' => 'eTAMS does not show an active level'];
        } elseif ($accreditation->alignment !== 'approved') {
            $reasons[] = ['level' => 'red', 'text' => 'The Conductor has not recorded eTAMS alignment'];
        }

        $wwcc = $person->wwcc;
        if ($wwcc && WwccCheck::looksLikeApplicationNumber($wwcc->wwcc_number)) {
            $reasons[] = ['level' => 'red', 'text' => 'An APP number is not a clearance'];
        } elseif (! $wwcc || $wwcc->outcome !== 'cleared' || ! $wwcc->verified_at || ! $wwcc->expires_on) {
            $reasons[] = ['level' => 'red', 'text' => 'WWCC is not a verified clearance'];
        } elseif ($wwcc->expires_on->isPast()) {
            $reasons[] = ['level' => 'red', 'text' => 'WWCC clearance has expired'];
        }

        $signedPool = $person->contracts->contains(function ($contract) {
            return $contract->status === 'signed'
                && $contract->template
                && $contract->template->key === 'casual-pool';
        });
        if (! $signedPool) {
            $reasons[] = ['level' => 'red', 'text' => 'Casual engagement is not signed'];
        }

        $modules = InductionModule::query()
            ->where('school_id', $person->school_id)
            ->get(['id', 'phase', 'version']);

        $acked = $person->acknowledgements->mapWithKeys(
            fn ($row) => [$row->module_id => $row->module_version]
        );

        $dayOneOpen = $modules->where('phase', 'day_one')->contains(
            fn ($module) => (int) ($acked[$module->id] ?? 0) < $module->version
        );
        if ($dayOneOpen) {
            $reasons[] = ['level' => 'red', 'text' => 'Day-one induction is incomplete'];
        }

        $laterOpen = $modules->where('phase', 'later')->contains(
            fn ($module) => (int) ($acked[$module->id] ?? 0) < $module->version
        );

        $red = collect($reasons)->contains(fn ($reason) => $reason['level'] === 'red');
        if (! $red && $laterOpen) {
            $reasons[] = ['level' => 'amber', 'text' => 'Later induction is still open'];
        }

        $light = $red ? 'red' : ($laterOpen ? 'amber' : 'green');

        return ['light' => $light, 'reasons' => $reasons];
    }
}
