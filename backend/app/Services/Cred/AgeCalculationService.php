<?php

namespace App\Services\Cred;

use App\Models\Encounter;
use App\Support\AgeAtDate;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Computes the age AT THE ENCOUNTER DATE (never the current age).
 *
 * Conventions (documented in docs/his-rules.md):
 * - Calendar dates only (no time of day); the encounter date is the local
 *   date in the offset in which the encounter was recorded.
 * - Days: number of calendar days elapsed since birth (birth day = 0).
 * - Completed months: a month is completed when the day-of-month of the
 *   birth is reached. When the birth day does not exist in the reference
 *   month (e.g. born on the 31st), the last day of that month completes it.
 * - Completed years = floor(completed months / 12).
 */
class AgeCalculationService
{
    public function between(DateTimeInterface|string $birthDate, DateTimeInterface|string $referenceDate): AgeAtDate
    {
        $birth = $this->toDate($birthDate);
        $reference = $this->toDate($referenceDate);

        if ($reference->lessThan($birth)) {
            throw new InvalidArgumentException('La fecha de referencia es anterior a la fecha de nacimiento.');
        }

        $days = (int) $birth->diffInDays($reference);
        $months = $this->completedMonths($birth, $reference);

        return new AgeAtDate(
            birthDate: $birth->toDateString(),
            referenceDate: $reference->toDateString(),
            days: $days,
            months: $months,
            years: intdiv($months, 12),
        );
    }

    public function forEncounter(Encounter $encounter): AgeAtDate
    {
        return $this->between(
            $encounter->patient->birth_date->toDateString(),
            $encounter->localOccurredAt()->toDateString(),
        );
    }

    private function completedMonths(CarbonImmutable $birth, CarbonImmutable $reference): int
    {
        $months = ($reference->year - $birth->year) * 12 + ($reference->month - $birth->month);

        if ($reference->day < $birth->day) {
            $isLastDayOfMonth = $reference->day === $reference->daysInMonth;
            if (! $isLastDayOfMonth) {
                $months--;
            }
        }

        return max(0, $months);
    }

    private function toDate(DateTimeInterface|string $value): CarbonImmutable
    {
        $date = $value instanceof DateTimeInterface
            ? $value->format('Y-m-d')
            : substr(trim($value), 0, 10);

        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'UTC');

        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException("Fecha inválida: {$date}");
        }

        return $parsed;
    }
}
