<?php

namespace Tests\Unit;

use App\Services\Cred\AgeCalculationService;
use App\Support\AgeAtDate;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AgeCalculationServiceTest extends TestCase
{
    private AgeCalculationService $ages;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ages = new AgeCalculationService;
    }

    public static function ageCases(): array
    {
        return [
            'day of birth' => ['2026-03-10', '2026-03-10', 0, 0, 0],
            'one day' => ['2026-03-10', '2026-03-11', 1, 0, 0],
            'end of neonatal period' => ['2026-01-01', '2026-01-29', 28, 0, 0],
            'first day after neonatal' => ['2026-01-01', '2026-01-30', 29, 0, 0],
            'month not yet completed' => ['2026-01-15', '2026-02-14', 30, 0, 0],
            'month completed on same day' => ['2026-01-15', '2026-02-15', 31, 1, 0],
            'born 31st, completes on last day of shorter month' => ['2025-01-31', '2025-02-28', 28, 1, 0],
            'born 31st, April 30 completes month 3' => ['2025-01-31', '2025-04-30', 89, 3, 0],
            'born 30th, 29th does not complete' => ['2025-03-30', '2025-04-29', 30, 0, 0],
            'eleven months' => ['2025-05-20', '2026-05-19', 364, 11, 0],
            'first birthday' => ['2025-05-20', '2026-05-20', 365, 12, 1],
            'leap day birth, Feb 28 non-leap year' => ['2024-02-29', '2025-02-28', 365, 12, 1],
            'leap day birth, day before' => ['2024-02-29', '2025-02-27', 364, 11, 0],
            'four years eleven months' => ['2020-06-10', '2025-06-09', 1825, 59, 4],
            'eleven years' => ['2014-01-01', '2025-12-31', 4382, 143, 11],
        ];
    }

    #[DataProvider('ageCases')]
    public function test_computes_completed_days_months_and_years(string $birth, string $reference, int $days, int $months, int $years): void
    {
        $age = $this->ages->between($birth, $reference);

        $this->assertSame($days, $age->days, 'days');
        $this->assertSame($months, $age->months, 'months');
        $this->assertSame($years, $age->years, 'years');
    }

    public function test_classifies_cred_age_groups(): void
    {
        $this->assertSame(AgeAtDate::GROUP_NEONATAL, $this->ages->between('2026-01-01', '2026-01-29')->group());
        $this->assertSame(AgeAtDate::GROUP_INFANT, $this->ages->between('2026-01-01', '2026-01-30')->group());
        $this->assertSame(AgeAtDate::GROUP_INFANT, $this->ages->between('2025-01-01', '2025-12-31')->group());
        $this->assertSame(AgeAtDate::GROUP_1_4, $this->ages->between('2025-01-01', '2026-01-01')->group());
        $this->assertSame(AgeAtDate::GROUP_5_11, $this->ages->between('2018-01-01', '2023-01-01')->group());
        $this->assertSame(AgeAtDate::GROUP_OUT_OF_RANGE, $this->ages->between('2010-01-01', '2022-01-01')->group());
    }

    public function test_age_depends_on_reference_date_not_today(): void
    {
        // A historical encounter keeps the age it had on that date.
        $this->assertSame(12, $this->ages->between('2020-01-10', '2020-01-22')->days);
        $this->assertSame('12 días', $this->ages->between('2020-01-10', '2020-01-22')->label());
    }

    public function test_rejects_reference_before_birth(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->ages->between('2026-03-10', '2026-03-09');
    }

    public function test_rejects_invalid_dates(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->ages->between('2026-02-30', '2026-03-09');
    }

    public function test_labels(): void
    {
        $this->assertSame('1 día', $this->ages->between('2026-01-01', '2026-01-02')->label());
        $this->assertSame('7 meses', $this->ages->between('2025-01-01', '2025-08-01')->label());
        $this->assertSame('3 años 2 meses', $this->ages->between('2020-01-01', '2023-03-05')->label());
        $this->assertSame('2 años', $this->ages->between('2020-01-01', '2022-01-01')->label());
    }
}
