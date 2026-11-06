<?php

namespace App\Hr;

use App\Models\Accreditation;
use App\Models\Person;
use App\Models\Wwcc;
use App\Support\Sydney;
use Carbon\CarbonInterface;

/**
 * Clear-to-teach is a query. There is no override argument.
 * Red is final for WWCC and accreditation, and for every other red reason.
 */
class ClearToTeach
{
    public function assess(Person $person, ?CarbonInterface $today = null): TeachingLight
    {
        $today = $today
            ? Sydney::date($today) ?? Sydney::today()
            : Sydney::today();

        $person->loadMissing(['accreditation', 'wwcc']);

        $red = array_values(array_unique([
            ...$this->wwccReasons($person->wwcc, $today->toDateString()),
            ...$this->accreditationReasons($person->accreditation),
            ...$this->gateReasons($person),
        ]));

        if ($red !== []) {
            return new TeachingLight(TeachingLight::RED, $red);
        }

        $amber = $this->amberReasons($person, $today->toDateString());

        if ($amber !== []) {
            return new TeachingLight(TeachingLight::AMBER, $amber);
        }

        return new TeachingLight(TeachingLight::GREEN, []);
    }

    /**
     * @return list<string>
     */
    private function wwccReasons(?Wwcc $wwcc, string $today): array
    {
        if (! $wwcc) {
            return ['wwcc_missing'];
        }

        $reasons = [];

        if ($wwcc->app_number_blocked || $wwcc->outcome === 'app_number' || WwccRules::looksLikeAppNumber($wwcc->wwcc_number)) {
            $reasons[] = 'wwcc_app_number';
        }

        if ($wwcc->bar_blocked || $wwcc->outcome === 'barred') {
            $reasons[] = 'wwcc_bar';
        }

        if ($wwcc->interim_bar_blocked || $wwcc->outcome === 'interim_bar') {
            $reasons[] = 'wwcc_interim_bar';
        }

        $expiry = $wwcc->expires_on?->toDateString();
        $expired = $wwcc->expired_blocked
            || $wwcc->outcome === 'expired'
            || ($expiry !== null && $expiry < $today);

        if ($expired) {
            $reasons[] = 'wwcc_expired';
        }

        $verified = $wwcc->outcome === 'verified'
            && $wwcc->verified_at !== null
            && $expiry !== null
            && ! $expired
            && $reasons === [];

        if (! $verified && $reasons === []) {
            $reasons[] = 'wwcc_unverified';
        }

        return $reasons;
    }

    /**
     * @return list<string>
     */
    private function accreditationReasons(?Accreditation $accreditation): array
    {
        if (! $accreditation) {
            return ['accreditation_missing'];
        }

        $reasons = [];

        if (! $accreditation->level || ! in_array($accreditation->level, Accreditation::LEVELS, true)) {
            $reasons[] = 'accreditation_missing';
        }

        if ($accreditation->status !== 'active') {
            $reasons[] = 'accreditation_not_active';
        }

        if (! $accreditation->etams_checked_at || ! $accreditation->checked_by) {
            $reasons[] = 'accreditation_not_checked';
        }

        $alignmentOk = $accreditation->alignment_decision === 'approved'
            || ($accreditation->alignment_decision === 'declined' && filled($accreditation->other_employer_arrangement));

        if (! $alignmentOk) {
            $reasons[] = 'alignment_not_approved';
        }

        return $reasons;
    }

    /**
     * @return list<string>
     */
    private function gateReasons(Person $person): array
    {
        $reasons = [];

        if ($person->employment_status !== Person::CASUAL_POOL) {
            $reasons[] = 'not_in_pool';
        }

        if (! $person->engagement_signed_at) {
            $reasons[] = 'engagement_unsigned';
        }

        if (! $person->day_one_induction_completed_at) {
            $reasons[] = 'day_one_incomplete';
        }

        return $reasons;
    }

    /**
     * @return list<string>
     */
    private function amberReasons(Person $person, string $today): array
    {
        $reasons = [];

        $window = $person->later_induction_window_ends_on?->toDateString();
        if ($window !== null && $window >= $today) {
            $reasons[] = 'later_induction_open';
        }

        $due = $person->pd_conversation_due_on?->toDateString();
        if ($due !== null && $due <= $today) {
            $reasons[] = 'pd_conversation_due';
        }

        return $reasons;
    }
}
