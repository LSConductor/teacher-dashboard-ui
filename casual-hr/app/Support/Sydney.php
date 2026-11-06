<?php

namespace App\Support;

use App\Tenancy\SchoolContext;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class Sydney
{
    public static function timezone(): string
    {
        return app(SchoolContext::class)->timezone();
    }

    public static function now(): Carbon
    {
        return now()->timezone(self::timezone());
    }

    public static function today(): Carbon
    {
        return self::now()->startOfDay();
    }

    public static function date(CarbonInterface|string|null $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value, self::timezone())->startOfDay();
    }
}
