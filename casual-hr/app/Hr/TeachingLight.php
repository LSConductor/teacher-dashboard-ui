<?php

namespace App\Hr;

class TeachingLight
{
    public const GREEN = 'green';

    public const AMBER = 'amber';

    public const RED = 'red';

    public const LABELS = [
        'wwcc_missing' => 'WWCC not recorded',
        'wwcc_app_number' => 'APP number is not a clearance',
        'wwcc_bar' => 'WWCC bar',
        'wwcc_interim_bar' => 'WWCC interim bar',
        'wwcc_expired' => 'WWCC expired',
        'wwcc_unverified' => 'WWCC not verified',
        'accreditation_missing' => 'Accreditation missing',
        'accreditation_not_active' => 'Accreditation is not active',
        'accreditation_not_checked' => 'eTAMS not checked',
        'alignment_not_approved' => 'Conductor has not approved eTAMS alignment',
        'engagement_unsigned' => 'Engagement is not signed',
        'day_one_incomplete' => 'Day-one induction is not complete',
        'not_in_pool' => 'Not in the casual pool',
        'later_induction_open' => 'A later induction module is still inside its window',
        'pd_conversation_due' => 'A professional development conversation is due',
    ];

    /**
     * @param  list<string>  $reasons
     */
    public function __construct(
        public readonly string $colour,
        public readonly array $reasons,
    ) {}

    public function isRed(): bool
    {
        return $this->colour === self::RED;
    }

    public function isAmber(): bool
    {
        return $this->colour === self::AMBER;
    }

    public function isGreen(): bool
    {
        return $this->colour === self::GREEN;
    }

    public function word(): string
    {
        return match ($this->colour) {
            self::GREEN => 'Green',
            self::AMBER => 'Amber',
            default => 'Red',
        };
    }

    /**
     * @return list<string>
     */
    public function reasonLabels(): array
    {
        return array_map(
            fn (string $reason) => self::LABELS[$reason] ?? $reason,
            $this->reasons,
        );
    }
}
