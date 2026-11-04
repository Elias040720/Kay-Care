<?php

namespace App\Services\His;

use App\Models\Encounter;
use App\Models\HisRule;
use App\Support\AgeAtDate;

/**
 * Pure evaluation of a rule against an age and the encounter's recorded data.
 * No database access: easy to unit test and to audit.
 */
class HisRuleMatcher
{
    public function matchesAge(HisRule $rule, AgeAtDate $age): bool
    {
        $bounds = [
            [$rule->min_age_days, $rule->max_age_days, $age->days],
            [$rule->min_age_months, $rule->max_age_months, $age->months],
            [$rule->min_age_years, $rule->max_age_years, $age->years],
        ];

        foreach ($bounds as [$min, $max, $value]) {
            if ($min !== null && $value < $min) {
                return false;
            }
            if ($max !== null && $value > $max) {
                return false;
            }
        }

        return true;
    }

    public function matchesDate(HisRule $rule, string $date): bool
    {
        if ($rule->valid_from !== null && $rule->valid_from->toDateString() > $date) {
            return false;
        }

        return ! ($rule->valid_to !== null && $rule->valid_to->toDateString() < $date);
    }

    public function matchesCondition(HisRule $rule, Encounter $encounter): bool
    {
        return match ($rule->condition) {
            null, '' => true,
            HisRule::CONDITION_HAS_ANTHROPOMETRY => $encounter->weight_kg !== null || $encounter->height_cm !== null,
            HisRule::CONDITION_HAS_HB => $encounter->hb_g_dl !== null,
            // Unknown conditions never match: fail closed.
            default => false,
        };
    }

    public function matches(HisRule $rule, AgeAtDate $age, Encounter $encounter): bool
    {
        if (! $rule->is_active) {
            return false;
        }

        if ($rule->sex !== null && $rule->sex !== $encounter->patient->sex->value) {
            return false;
        }

        return $this->matchesDate($rule, $age->referenceDate)
            && $this->matchesAge($rule, $age)
            && $this->matchesCondition($rule, $encounter);
    }
}
