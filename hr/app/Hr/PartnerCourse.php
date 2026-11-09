<?php

namespace App\Hr;

use App\Models\Partner;
use App\Models\PartnerCheck;
use App\Models\PartnerPage;
use Illuminate\Support\Collection;

class PartnerCourse
{
    /** @param Collection<int, PartnerPage> $pages */
    public static function status(Partner $partner, Collection $pages, PartnerCheck $check): string
    {
        $seen = self::pagesCurrent($partner, $pages);
        $quiz = (int) $partner->quiz_version_passed === (int) $check->version;
        if (! $seen || ! $quiz) {
            if ($partner->quiz_version_passed || $partner->finished_at) {
                return 'wording';
            }

            return $seen ? 'quiz' : 'reading';
        }
        if (! self::formDone($partner)) {
            return 'form';
        }
        if ($partner->volunteer_status === 'not_cleared') {
            return 'not_cleared';
        }
        if ($partner->volunteer_status === 'cleared') {
            return self::clearanceInDate($partner) ? 'ready' : 'lapsed';
        }

        return 'waiting';
    }

    public static function label(string $status): string
    {
        return [
            'reading' => 'Still reading',
            'quiz' => 'Ready for the check',
            'form' => 'Form still open',
            'waiting' => 'Finished, waiting on HR',
            'ready' => 'Ready',
            'not_cleared' => 'Not cleared',
            'lapsed' => 'Volunteer check lapsed',
            'wording' => 'Wording changed',
        ][$status] ?? $status;
    }

    /** @param Collection<int, PartnerPage> $pages */
    public static function pagesCurrent(Partner $partner, Collection $pages): bool
    {
        $seen = $partner->seen ?? [];
        foreach ($pages as $page) {
            if ((int) ($seen[$page->slug] ?? 0) !== (int) $page->version) {
                return false;
            }
        }

        return $pages->isNotEmpty();
    }

    public static function formDone(Partner $partner): bool
    {
        return is_array($partner->interests) && $partner->interests !== [];
    }

    public static function clearanceInDate(Partner $partner): bool
    {
        if ($partner->volunteer_status !== 'cleared' || ! $partner->volunteer_expires_on) {
            return false;
        }
        if (\App\Models\WwccCheck::looksLikeApplicationNumber($partner->volunteer_number)) {
            return false;
        }
        $today = now()->timezone('Australia/Sydney')->toDateString();

        return $partner->volunteer_expires_on->toDateString() >= $today;
    }

    /** @param Collection<int, PartnerPage> $pages */
    public static function firstUnread(Partner $partner, Collection $pages): ?PartnerPage
    {
        $seen = $partner->seen ?? [];
        foreach ($pages as $page) {
            if ((int) ($seen[$page->slug] ?? 0) !== (int) $page->version) {
                return $page;
            }
        }

        return null;
    }

    /** @param Collection<int, PartnerPage> $pages */
    public static function unlocked(Partner $partner, Collection $pages, PartnerPage $page): bool
    {
        foreach ($pages as $earlier) {
            if ((int) $earlier->sort > (int) $page->sort || (int) $earlier->id === (int) $page->id) {
                break;
            }
            $seen = $partner->seen ?? [];
            if ((int) ($seen[$earlier->slug] ?? 0) !== (int) $earlier->version) {
                return false;
            }
        }

        return true;
    }
}
