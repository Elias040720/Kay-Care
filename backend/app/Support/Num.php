<?php

namespace App\Support;

final class Num
{
    /** Float or null from a DB decimal (string/int/float). */
    public static function toFloat(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }

    /** Human formatting without trailing zeros: 12.500 -> "12.5", 100.0 -> "100". */
    public static function format(mixed $value, int $decimals = 3): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $formatted = number_format((float) $value, $decimals, '.', '');

        return str_contains($formatted, '.') ? rtrim(rtrim($formatted, '0'), '.') : $formatted;
    }
}
