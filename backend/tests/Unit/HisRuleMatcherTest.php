<?php

namespace Tests\Unit;

use App\Enums\Sex;
use App\Models\Encounter;
use App\Models\HisRule;
use App\Models\Patient;
use App\Services\Cred\AgeCalculationService;
use App\Services\His\HisRuleMatcher;
use PHPUnit\Framework\TestCase;

class HisRuleMatcherTest extends TestCase
{
    private HisRuleMatcher $matcher;

    private AgeCalculationService $ages;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matcher = new HisRuleMatcher;
        $this->ages = new AgeCalculationService;
    }

    private function rule(array $attributes): HisRule
    {
        $rule = new HisRule;
        $rule->forceFill(array_merge(['is_active' => true, 'lab_strategy' => 'none', 'priority' => 1], $attributes));

        return $rule;
    }

    private function encounter(array $attributes = []): Encounter
    {
        $patient = new Patient;
        $patient->forceFill(['sex' => Sex::Female, 'birth_date' => '2026-01-01']);
        $encounter = new Encounter;
        $encounter->forceFill(array_merge(['weight_kg' => null, 'height_cm' => null, 'hb_g_dl' => null], $attributes));
        $encounter->setRelation('patient', $patient);

        return $encounter;
    }

    public function test_bounds_are_inclusive(): void
    {
        $neonatal = $this->rule(['min_age_days' => 0, 'max_age_days' => 28]);

        $this->assertTrue($this->matcher->matchesAge($neonatal, $this->ages->between('2026-01-01', '2026-01-01')));
        $this->assertTrue($this->matcher->matchesAge($neonatal, $this->ages->between('2026-01-01', '2026-01-29')));
        $this->assertFalse($this->matcher->matchesAge($neonatal, $this->ages->between('2026-01-01', '2026-01-30')));
    }

    public function test_combined_day_and_year_bounds(): void
    {
        $underOne = $this->rule(['min_age_days' => 29, 'max_age_years' => 0]);

        $this->assertFalse($this->matcher->matchesAge($underOne, $this->ages->between('2025-01-01', '2025-01-29')));
        $this->assertTrue($this->matcher->matchesAge($underOne, $this->ages->between('2025-01-01', '2025-01-30')));
        $this->assertTrue($this->matcher->matchesAge($underOne, $this->ages->between('2025-01-01', '2025-12-31')));
        $this->assertFalse($this->matcher->matchesAge($underOne, $this->ages->between('2025-01-01', '2026-01-01')));
    }

    public function test_conditions_require_recorded_data_and_unknown_conditions_fail_closed(): void
    {
        $hb = $this->rule(['condition' => HisRule::CONDITION_HAS_HB]);
        $anthro = $this->rule(['condition' => HisRule::CONDITION_HAS_ANTHROPOMETRY]);
        $unknown = $this->rule(['condition' => 'something_else']);

        $this->assertFalse($this->matcher->matchesCondition($hb, $this->encounter()));
        $this->assertTrue($this->matcher->matchesCondition($hb, $this->encounter(['hb_g_dl' => '11.2'])));
        $this->assertFalse($this->matcher->matchesCondition($anthro, $this->encounter()));
        $this->assertTrue($this->matcher->matchesCondition($anthro, $this->encounter(['height_cm' => '70.0'])));
        $this->assertFalse($this->matcher->matchesCondition($unknown, $this->encounter(['hb_g_dl' => '11.2'])));
    }

    public function test_inactive_rules_and_sex_restrictions(): void
    {
        $age = $this->ages->between('2026-01-01', '2026-01-05');

        $this->assertFalse($this->matcher->matches($this->rule(['is_active' => false]), $age, $this->encounter()));
        $this->assertFalse($this->matcher->matches($this->rule(['sex' => 'M']), $age, $this->encounter()));
        $this->assertTrue($this->matcher->matches($this->rule(['sex' => 'F']), $age, $this->encounter()));
    }

    public function test_rule_validity_window(): void
    {
        $rule = $this->rule(['valid_from' => '2026-02-01', 'valid_to' => '2026-12-31']);

        $this->assertFalse($this->matcher->matchesDate($rule, '2026-01-31'));
        $this->assertTrue($this->matcher->matchesDate($rule, '2026-02-01'));
        $this->assertFalse($this->matcher->matchesDate($rule, '2027-01-01'));
    }
}
