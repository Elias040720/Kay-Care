<?php

namespace App\Enums;

enum UserRole: string
{
    case Nurse = 'nurse';
    case Admin = 'admin';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
