<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Encounter;
use App\Models\Patient;
use App\Services\Cred\AgeCalculationService;
use App\Support\IsoDateTime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * EncounterWrite (POST: full, PATCH: partial). "status" and "id" are not
 * writable: saving never marks an encounter as reviewed.
 */
abstract class EncounterWriteRequest extends FormRequest
{
    abstract protected function isPartial(): bool;

    abstract protected function currentEncounter(): ?Encounter;

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('clinical_note'))) {
            $note = trim($this->input('clinical_note'));
            $this->merge(['clinical_note' => $note === '' ? null : $note]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $required = $this->isPartial() ? 'sometimes' : 'required';
        $optional = $this->isPartial() ? 'sometimes' : 'nullable';
        $ranges = config('kaycare.encounters.ranges');

        return [
            'patient_id' => [$required, 'uuid', Rule::exists('patients', 'id')],
            'type' => [$required, 'string', Rule::in(config('kaycare.encounters.types'))],
            'occurred_at' => [$required, 'string', function (string $attribute, mixed $value, \Closure $fail) {
                if (! IsoDateTime::isValid($value)) {
                    $fail('La fecha/hora debe estar en formato ISO 8601 con zona horaria (p. ej. 2026-11-04T09:30:00-05:00).');
                }
            }],
            'clinical_note' => [$optional, 'nullable', 'string', 'max:'.(int) config('kaycare.encounters.clinical_note_max')],
            'weight_kg' => [$optional, 'nullable', 'numeric', 'decimal:0,3', 'between:'.$ranges['weight_kg']['min'].','.$ranges['weight_kg']['max']],
            'height_cm' => [$optional, 'nullable', 'numeric', 'decimal:0,1', 'between:'.$ranges['height_cm']['min'].','.$ranges['height_cm']['max']],
            'hb_g_dl' => [$optional, 'nullable', 'numeric', 'decimal:0,1', 'between:'.$ranges['hb_g_dl']['min'].','.$ranges['hb_g_dl']['max']],
            'id' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.prohibited' => 'El estado no puede modificarse al guardar; la revisión es una acción explícita.',
            'patient_id.exists' => 'El paciente indicado no existe.',
        ];
    }

    /** Clinical consistency of the date: not future, not before birth, CRED age range. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $encounter = $this->currentEncounter();

                if ($encounter !== null && $this->has('patient_id') && $this->input('patient_id') !== $encounter->patient_id) {
                    $validator->errors()->add('patient_id', 'Una atención no puede reasignarse a otro paciente.');

                    return;
                }

                if ($validator->errors()->hasAny(['patient_id', 'occurred_at'])) {
                    return;
                }

                $patient = $encounter?->patient ?? Patient::query()->find($this->input('patient_id'));
                $iso = $this->input('occurred_at');

                if ($patient === null || ($iso === null && $encounter === null)) {
                    return;
                }

                $local = $iso !== null ? IsoDateTime::parse($iso)['local'] : $encounter->localOccurredAt();
                $utc = $local->utc();

                $limit = now()->addMinutes((int) config('kaycare.future_tolerance_minutes', 5));
                if ($utc->greaterThan($limit)) {
                    $validator->errors()->add('occurred_at', 'La fecha/hora de la atención no puede ser futura.');

                    return;
                }

                $birth = $patient->birth_date->toDateString();
                if ($local->toDateString() < $birth) {
                    $validator->errors()->add('occurred_at', 'La atención no puede ser anterior a la fecha de nacimiento del paciente.');

                    return;
                }

                $age = app(AgeCalculationService::class)->between($birth, $local->toDateString());
                if ($age->years > (int) config('kaycare.encounters.cred_max_age_years', 11)) {
                    $validator->errors()->add('occurred_at', 'El control CRED aplica hasta los 11 años; el paciente tenía '.$age->label().' en la fecha indicada.');
                }
            },
        ];
    }

    /** @return array<string, mixed> */
    public function encounterData(): array
    {
        return collect($this->validated())
            ->only(['patient_id', 'type', 'occurred_at', 'clinical_note', 'weight_kg', 'height_cm', 'hb_g_dl'])
            ->all();
    }
}
