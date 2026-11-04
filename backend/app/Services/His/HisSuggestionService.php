<?php

namespace App\Services\His;

use App\Enums\VerificationStatus;
use App\Models\Encounter;
use App\Models\HisLine;
use App\Models\HisRule;
use App\Services\Cred\AgeCalculationService;
use App\Support\AgeAtDate;

/**
 * Produces SUGGESTIONS of HIS activities for an encounter.
 *
 * Fundamental rule: age and recorded data only suggest activities that may
 * apply. Nothing is marked as performed, no LAB value is invented, no
 * diagnosis is inferred from measurements and no previous control is assumed
 * without recorded history.
 */
class HisSuggestionService
{
    public function __construct(
        private readonly AgeCalculationService $ages,
        private readonly HisCatalogResolver $catalogs,
        private readonly HisRuleMatcher $matcher,
    ) {}

    /**
     * @return list<HisSuggestion>
     */
    public function suggestFor(Encounter $encounter): array
    {
        $encounter->loadMissing('patient');
        $version = $this->catalogs->forEncounter($encounter);

        if ($version === null) {
            return [];
        }

        $age = $this->ages->forEncounter($encounter);

        $rules = $version->rules()
            ->with('item.version')
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('rule_code')
            ->get();

        $suggestions = [];

        foreach ($rules as $rule) {
            $item = $rule->item;

            if (! $item->is_active || isset($suggestions[$item->id])) {
                continue; // one suggestion per catalog item (highest priority rule wins)
            }

            if (! $this->matcher->matches($rule, $age, $encounter)) {
                continue;
            }

            [$lab, $labNote] = $this->suggestLab($rule, $age, $encounter);

            $verified = $rule->verification_status === VerificationStatus::Verified
                && $item->effectiveVerification() === VerificationStatus::Verified;

            $suggestions[$item->id] = new HisSuggestion(
                catalogItemId: $item->id,
                label: $item->label,
                code: $item->code,
                diagnosisTypeSuggested: $rule->diagnosis_type_suggested,
                labSuggested: $lab,
                verificationStatus: $verified ? VerificationStatus::Verified->value : VerificationStatus::Draft->value,
                source: $rule->source,
                rationale: trim($rule->description.' Edad en la atención: '.$age->label().'. '.$labNote),
                ruleCode: $rule->rule_code,
                catalogVersion: $version->code,
            );
        }

        return array_values($suggestions);
    }

    /**
     * @return array{0: ?string, 1: string} [lab, explanation]
     */
    private function suggestLab(HisRule $rule, AgeAtDate $age, Encounter $encounter): array
    {
        return match ($rule->lab_strategy) {
            HisRule::LAB_CONTROL_NUMBER => $this->suggestControlNumber($rule, $encounter),
            HisRule::LAB_MEASUREMENT_HB => $encounter->hb_g_dl !== null
                ? [$this->formatDecimal($encounter->hb_g_dl), 'LAB tomado del valor de hemoglobina registrado en la atención; verifíquelo.']
                : [null, 'Sin resultado de hemoglobina registrado: no se sugiere LAB.'],
            HisRule::LAB_FIXED => [$rule->lab_fixed, 'LAB fijo definido por la regla del catálogo.'],
            default => [null, ''],
        };
    }

    /**
     * The control number is suggested ONLY from controls confirmed in Kay Care
     * (HIS lines of the same item, in the same age band, before this
     * encounter). Without recorded history it is left empty on purpose.
     *
     * @return array{0: ?string, 1: string}
     */
    private function suggestControlNumber(HisRule $rule, Encounter $encounter): array
    {
        [$previous, $highestLab] = $this->previousControlsInBand($rule, $encounter);

        if ($previous === 0) {
            return [null, 'Sin controles previos registrados en Kay Care para este grupo de edad: no se asume ningún control anterior. Determine el número de control con el carné o la historia clínica.'];
        }

        // Prefer the numbering the nurse already confirmed (e.g. copied from
        // the CRED card); fall back to counting recorded controls.
        $next = $highestLab !== null ? $highestLab + 1 : $previous + 1;
        $basis = $highestLab !== null
            ? "del último número de control confirmado en Kay Care para este grupo de edad ({$highestLab})"
            : "de {$previous} control(es) confirmados sin número en Kay Care para este grupo de edad";

        if ($rule->max_sequence !== null && $next > $rule->max_sequence) {
            return [null, "A partir {$basis}, el siguiente control superaría el máximo esperado por la regla ({$rule->max_sequence}). Verifique antes de registrar."];
        }

        return [(string) $next, "Número de control calculado a partir {$basis}. Verifíquelo con el carné CRED."];
    }

    /**
     * @return array{0: int, 1: ?int} [number of prior controls in band, highest confirmed control number]
     */
    private function previousControlsInBand(HisRule $rule, Encounter $encounter): array
    {
        $itemCode = $rule->item->code;

        $previousLines = HisLine::query()
            ->whereHas('item', fn ($q) => $q->where('code', $itemCode))
            ->whereHas('encounter', fn ($q) => $q
                ->where('patient_id', $encounter->patient_id)
                ->where('id', '!=', $encounter->id)
                ->where('occurred_at', '<', $encounter->occurred_at))
            ->with('encounter.patient')
            ->get();

        $inBand = $previousLines->filter(
            fn (HisLine $line) => $this->matcher->matchesAge($rule, $this->ages->forEncounter($line->encounter))
        );

        $highest = $inBand
            ->map(fn (HisLine $line) => $line->lab)
            ->filter(fn (?string $lab) => $lab !== null && preg_match('/^[1-9]\d*$/', $lab) === 1)
            ->map(fn (string $lab) => (int) $lab)
            ->max();

        return [$inBand->unique('encounter_id')->count(), $highest];
    }

    private function formatDecimal(string|float $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 1, '.', ''), '0'), '.');
    }
}
