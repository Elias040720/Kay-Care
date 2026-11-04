<?php

namespace App\Support;

/**
 * Immutable age of a person at a reference date (never "today" unless the
 * reference date is today). All values are COMPLETED units.
 */
final class AgeAtDate
{
    public const GROUP_NEONATAL = 'neonatal';      // 0-28 days

    public const GROUP_INFANT = 'under_1_year';    // 29 days - 11 completed months

    public const GROUP_1_4 = 'years_1_4';

    public const GROUP_5_11 = 'years_5_11';

    public const GROUP_OUT_OF_RANGE = 'out_of_cred_range';

    public const NEONATAL_MAX_DAYS = 28;

    public function __construct(
        public readonly string $birthDate,
        public readonly string $referenceDate,
        public readonly int $days,
        public readonly int $months,
        public readonly int $years,
    ) {}

    public function isNeonatal(): bool
    {
        return $this->days <= self::NEONATAL_MAX_DAYS;
    }

    public function group(): string
    {
        return match (true) {
            $this->days <= self::NEONATAL_MAX_DAYS => self::GROUP_NEONATAL,
            $this->years < 1 => self::GROUP_INFANT,
            $this->years <= 4 => self::GROUP_1_4,
            $this->years <= 11 => self::GROUP_5_11,
            default => self::GROUP_OUT_OF_RANGE,
        };
    }

    /** Human readable label in Spanish, e.g. "12 días", "7 meses", "3 años 2 meses". */
    public function label(): string
    {
        if ($this->isNeonatal()) {
            return $this->days.' '.($this->days === 1 ? 'día' : 'días');
        }

        if ($this->years < 1) {
            return $this->months.' '.($this->months === 1 ? 'mes' : 'meses');
        }

        $rest = $this->months - $this->years * 12;
        $label = $this->years.' '.($this->years === 1 ? 'año' : 'años');

        return $rest > 0 ? $label.' '.$rest.' '.($rest === 1 ? 'mes' : 'meses') : $label;
    }

    /** @return array{days: int, months: int, years: int, group: string, label: string} */
    public function toArray(): array
    {
        return [
            'days' => $this->days,
            'months' => $this->months,
            'years' => $this->years,
            'group' => $this->group(),
            'label' => $this->label(),
        ];
    }
}
