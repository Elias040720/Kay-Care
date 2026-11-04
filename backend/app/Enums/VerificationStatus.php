<?php

namespace App\Enums;

enum VerificationStatus: string
{
    case Draft = 'draft';
    case Verified = 'verified';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
