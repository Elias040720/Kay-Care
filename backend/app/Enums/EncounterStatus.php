<?php

namespace App\Enums;

enum EncounterStatus: string
{
    case Draft = 'draft';
    case Reviewed = 'reviewed';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
