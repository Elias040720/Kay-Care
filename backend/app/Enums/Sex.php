<?php

namespace App\Enums;

enum Sex: string
{
    case Female = 'F';
    case Male = 'M';
    case Unspecified = 'X';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
