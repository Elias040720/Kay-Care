<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Strict ISO 8601 date-time with explicit timezone offset handling.
 */
final class IsoDateTime
{
    /** Requires a date, a time and an explicit offset ("Z" or "+hh:mm"). */
    public const PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d{1,6})?)?(Z|[+-]\d{2}:\d{2})$/';

    public static function isValid(mixed $value): bool
    {
        if (! is_string($value) || preg_match(self::PATTERN, $value) !== 1) {
            return false;
        }

        try {
            $parsed = CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return false;
        }

        // Reject overflowed dates such as 2026-02-30.
        return $parsed->format('Y-m-d') === substr($value, 0, 10);
    }

    /**
     * @return array{utc: CarbonImmutable, offset: string, local: CarbonImmutable}
     */
    public static function parse(string $value): array
    {
        if (! self::isValid($value)) {
            throw new InvalidArgumentException('Fecha/hora ISO 8601 con zona horaria inválida.');
        }

        $local = CarbonImmutable::parse($value);
        $offset = str_ends_with($value, 'Z') ? '+00:00' : substr($value, -6);

        return [
            'utc' => $local->utc(),
            'offset' => $offset,
            'local' => $local->setTimezone($offset),
        ];
    }

    /** ISO 8601 output with the given offset, e.g. 2026-11-04T09:30:00-05:00. */
    public static function format(CarbonImmutable $utc, string $offset): string
    {
        return $utc->setTimezone($offset)->format('Y-m-d\TH:i:sP');
    }
}
