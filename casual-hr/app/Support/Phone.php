<?php

namespace App\Support;

class Phone
{
    public static function digits(string $mobile): string
    {
        $digits = preg_replace('/\D+/', '', $mobile) ?? '';

        if (str_starts_with($digits, '61') && strlen($digits) === 11) {
            $digits = '0'.substr($digits, 2);
        }

        return $digits;
    }

    public static function display(string $mobile): string
    {
        $digits = self::digits($mobile);

        if (strlen($digits) === 10) {
            return substr($digits, 0, 4).' '.substr($digits, 4, 3).' '.substr($digits, 7);
        }

        return $mobile;
    }

    public static function tel(string $mobile): string
    {
        $digits = self::digits($mobile);

        if (str_starts_with($digits, '0')) {
            $digits = '61'.substr($digits, 1);
        }

        return '+'.$digits;
    }
}
