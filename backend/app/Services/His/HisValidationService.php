<?php

namespace App\Services\His;

use App\Enums\VerificationStatus;
use App\Models\Encounter;
use App\Models\HisCatalogItem;
use App\Models\HisCatalogVersion;
use App\Models\HisLine;
use App\Services\Cred\AgeCalculationService;
use App\Support\AgeAtDate;
use Illuminate\Support\Collection;

/**
 * Structural validation of HIS lines against the catalog in force.
 *
 * "Valid" here means internally consistent with the catalog (types, LAB
 * formats, validity, age band). It is NOT an official validation: unverified
 * (draft/demo) catalogs always produce a warning.
 */
class HisValidationService
{
    public function __construct(
        private readonly AgeCalculationService $ages,
        private readonly HisCatalogResolver $catalogs,
        private readonly HisRuleMatcher $matcher,
    ) {}

    /**
     * Validates line inputs before persisting them (PUT his-lines).
     * Missing-but-required values are tolerated while the record is a
     * draft; they are reported as blocking issues by evaluate().
     *
     * @param  list<array{catalog_item_id: string, diagnosis_type: ?string, lab: ?string}>  $lines
     * @return array<string, list<string>> errors keyed as "lines.N.field"
     */
    public function lineErrors(Encounter $encounter, array $lines): array
    {
        $version = $this->catalogs->forEncounter($encounter);
        $errors = [];

        if ($version === null) {
            return ['lines' => ['No existe un catálogo HIS vigente para la fecha de la atención.']];
        }

        $items = HisCatalogItem::query()
            ->whereIn('id', array_column($lines, 'catalog_item_id'))
            ->get()
            ->keyBy('id');

        foreach ($lines as $i => $line) {
            /** @var HisCatalogItem|null $item */
            $item = $items->get($line['catalog_item_id']);

            if ($item === null || $item->catalog_version_id !== $version->id || ! $item->is_active) {
                $errors["lines.{$i}.catalog_item_id"][] = 'La actividad no pertenece al catálogo HIS vigente para la fecha de la atención.';

                continue;
            }

            $type = $line['diagnosis_type'] ?? null;
            if ($type !== null && ! in_array($type, $item->allowed_diagnosis_types, true)) {
                $errors["lines.{$i}.diagnosis_type"][] = sprintf(
                    'Tipo de diagnóstico no permitido para %s. Permitidos: %s.',
                    $item->code,
                    $item->allowed_diagnosis_types === [] ? 'ninguno' : implode(', ', $item->allowed_diagnosis_types),
                );
            }

            $lab = $line['lab'] ?? null;
            if ($lab !== null && ($labError = $this->labError($item, $lab)) !== null) {
                $errors["lines.{$i}.lab"][] = $labError;
            }
        }

        return $errors;
    }

    /**
     * Full evaluation used by the HIS preview and the documents.
     */
    public function evaluate(Encounter $encounter): HisValidationResult
    {
        $encounter->loadMissing(['patient', 'hisLines.item.version', 'hisLines.item.rules']);
        $errors = [];
        $warnings = [];

        $version = $this->catalogs->forEncounter($encounter);
        $age = $this->ages->forEncounter($encounter);

        if ($version === null) {
            $errors[] = 'No existe un catálogo HIS vigente para la fecha de la atención.';
        } else {
            $warnings = array_merge($warnings, $this->catalogWarnings($version));
        }

        if ($age->group() === AgeAtDate::GROUP_OUT_OF_RANGE) {
            $errors[] = 'La edad del paciente en la fecha de la atención está fuera del rango CRED (0 a 11 años).';
        }

        if ($encounter->isDraft()) {
            $warnings[] = 'La atención está en estado borrador (no revisada por un profesional).';
        }

        if ($encounter->patient->document_number === null) {
            $warnings[] = 'El paciente no tiene documento de identidad registrado.';
        }

        /** @var Collection<int, HisLine> $lines */
        $lines = $encounter->hisLines;

        if ($lines->isEmpty()) {
            $errors[] = 'No hay actividades HIS confirmadas por el profesional para esta atención.';
        }

        foreach ($lines as $line) {
            $item = $line->item;
            $code = $item->code;

            if ($version !== null && $item->catalog_version_id !== $version->id) {
                $errors[] = "{$code}: la actividad no pertenece al catálogo vigente en la fecha de la atención.";
            }

            if ($item->diagnosis_type_required && $line->diagnosis_type === null) {
                $errors[] = "{$code}: requiere tipo de diagnóstico (P, D o R).";
            }

            if ($line->diagnosis_type !== null && ! in_array($line->diagnosis_type, $item->allowed_diagnosis_types, true)) {
                $errors[] = "{$code}: tipo de diagnóstico {$line->diagnosis_type} no permitido.";
            }

            if ($line->lab === null && in_array($item->lab_mode, [HisCatalogItem::LAB_CONTROL_NUMBER, HisCatalogItem::LAB_NUMERIC], true)) {
                $errors[] = "{$code}: requiere valor LAB (".($item->lab_mode === HisCatalogItem::LAB_CONTROL_NUMBER ? 'número de control' : 'resultado').').';
            }

            if ($line->lab !== null && ($labError = $this->labError($item, $line->lab)) !== null) {
                $errors[] = "{$code}: {$labError}";
            }

            if (! $this->appliesToAge($item, $age)) {
                $warnings[] = "{$code}: según el catálogo, la actividad no corresponde a la edad en la atención ({$age->label()}). Verifique.";
            }

            if ($item->effectiveVerification() !== VerificationStatus::Verified) {
                $warnings[] = "{$code}: código no verificado (fuente: {$item->source}).";
            }
        }

        return new HisValidationResult(array_values(array_unique($errors)), array_values(array_unique($warnings)));
    }

    public function labError(HisCatalogItem $item, string $lab): ?string
    {
        $options = $item->lab_options ?? [];
        $maxLength = (int) config('kaycare.his.lab_max_length', 10);

        if (mb_strlen($lab) > $maxLength) {
            return "El valor LAB no puede superar {$maxLength} caracteres.";
        }

        switch ($item->lab_mode) {
            case HisCatalogItem::LAB_NONE:
                return 'Esta actividad no admite valor LAB.';

            case HisCatalogItem::LAB_CONTROL_NUMBER:
                $max = (int) ($options['max'] ?? 99);
                if (preg_match('/^[1-9]\d*$/', $lab) !== 1 || (int) $lab > $max) {
                    return "El número de control debe ser un entero entre 1 y {$max}.";
                }

                return null;

            case HisCatalogItem::LAB_NUMERIC:
                $min = (float) ($options['min'] ?? 0);
                $max = (float) ($options['max'] ?? PHP_INT_MAX);
                if (preg_match('/^\d{1,3}(\.\d)?$/', $lab) !== 1 || (float) $lab < $min || (float) $lab > $max) {
                    return "El valor LAB debe ser numérico (máx. 1 decimal) entre {$min} y {$max}.";
                }

                return null;

            case HisCatalogItem::LAB_ENUM:
                $values = $options['values'] ?? [];
                if (! in_array($lab, $values, true)) {
                    return 'Valor LAB no permitido. Permitidos: '.implode(', ', $values).'.';
                }

                return null;

            case HisCatalogItem::LAB_FREE:
                return preg_match('/^[A-Za-z0-9.\-]+$/', $lab) === 1 ? null : 'El valor LAB contiene caracteres no permitidos.';

            default:
                return 'Configuración LAB desconocida en el catálogo.';
        }
    }

    private function appliesToAge(HisCatalogItem $item, AgeAtDate $age): bool
    {
        $rules = $item->rules->where('is_active', true);

        // Items without rules are not age-restricted by the catalog.
        if ($rules->isEmpty()) {
            return true;
        }

        return $rules->contains(fn ($rule) => $this->matcher->matchesAge($rule, $age));
    }

    /** @return list<string> */
    private function catalogWarnings(HisCatalogVersion $version): array
    {
        if ($version->verification_status === VerificationStatus::Verified) {
            return [];
        }

        return [sprintf(
            'Catálogo %s (fuente: %s) en estado borrador: los códigos son DEMOSTRATIVOS, no oficiales y NO han sido validados por MINSA ni por un profesional revisor.',
            $version->code,
            $version->source,
        )];
    }
}
