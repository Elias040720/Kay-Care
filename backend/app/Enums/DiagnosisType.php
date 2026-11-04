<?php

namespace App\Enums;

enum DiagnosisType: string
{
    case Presuntivo = 'P';
    case Definitivo = 'D';
    case Repetido = 'R';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
