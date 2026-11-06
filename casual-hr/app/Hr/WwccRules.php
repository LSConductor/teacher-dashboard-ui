<?php

namespace App\Hr;

use App\Support\Sydney;

class WwccRules
{
    public static function looksLikeAppNumber(?string $number): bool
    {
        return (bool) preg_match('/^\s*APP[\s-]?\d+/i', (string) $number);
    }

    public static function normaliseNumber(string $number): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($number)) ?? '');
    }

    /**
     * @return array{outcome: string, app_number_blocked: bool, bar_blocked: bool, interim_bar_blocked: bool, expired_blocked: bool, error: ?string}
     */
    public static function normalise(string $number, string $outcome, ?string $expiresOn): array
    {
        $number = self::normaliseNumber($number);
        $app = self::looksLikeAppNumber($number);
        $today = Sydney::today()->toDateString();
        $expiredByDate = filled($expiresOn) && $expiresOn < $today;
        $error = null;

        if ($app && $outcome === 'verified') {
            $error = 'An APP number is not a Working with Children Check clearance.';
        }

        if ($expiredByDate && $outcome === 'verified') {
            $error = 'An expired clearance cannot be recorded as verified.';
        }

        if ($app) {
            $outcome = 'app_number';
        }

        return [
            'outcome' => $outcome,
            'app_number_blocked' => $app || $outcome === 'app_number',
            'bar_blocked' => $outcome === 'barred',
            'interim_bar_blocked' => $outcome === 'interim_bar',
            'expired_blocked' => $outcome === 'expired' || $expiredByDate,
            'error' => $error,
        ];
    }
}
