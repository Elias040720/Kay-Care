<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\Sex;
use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * PatientWrite (POST: full, PATCH: partial).
 */
abstract class PatientWriteRequest extends FormRequest
{
    private const NAME_PATTERN = "/^[\pL\pM' .\-]+$/u";

    abstract protected function isPartial(): bool;

    abstract protected function currentPatient(): ?Patient;

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['first_name', 'last_name', 'locality'] as $field) {
            if (is_string($this->input($field))) {
                $value = preg_replace('/\s+/u', ' ', trim($this->input($field)));
                $normalized[$field] = $value === '' && $field === 'locality' ? null : $value;
            }
        }

        if (is_string($this->input('chart_number'))) {
            $normalized['chart_number'] = mb_strtoupper(preg_replace('/\s+/u', '', $this->input('chart_number')));
        }

        if (is_string($this->input('document_type'))) {
            $value = mb_strtoupper(trim($this->input('document_type')));
            $normalized['document_type'] = $value === '' ? null : $value;
        }

        if (is_string($this->input('document_number'))) {
            $value = mb_strtoupper(preg_replace('/[\s\-]+/u', '', $this->input('document_number')));
            $normalized['document_number'] = $value === '' ? null : $value;
        }

        if (is_string($this->input('sex'))) {
            $normalized['sex'] = mb_strtoupper(trim($this->input('sex')));
        }

        $this->merge($normalized);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $required = $this->isPartial() ? 'sometimes' : 'required';
        // Optional fields may be omitted or sent as null.
        $optional = $this->isPartial() ? 'sometimes' : 'nullable';
        $patient = $this->currentPatient();
        $today = now((string) config('kaycare.timezone'))->toDateString();

        $documentType = $this->has('document_type')
            ? $this->input('document_type')
            : $patient?->document_type;

        return [
            'chart_number' => [$required, 'string', 'min:1', 'max:30', 'regex:/^[A-Z0-9\-\/]+$/',
                Rule::unique('patients', 'chart_number')->ignore($patient?->id)],
            'first_name' => [$required, 'string', 'min:1', 'max:100', 'regex:'.self::NAME_PATTERN],
            'last_name' => [$required, 'string', 'min:1', 'max:150', 'regex:'.self::NAME_PATTERN],
            'birth_date' => [$required, 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:'.$today],
            'sex' => [$required, Rule::enum(Sex::class)],
            'document_type' => [$optional, 'nullable', 'string', Rule::in(config('kaycare.patients.document_types'))],
            'document_number' => [$optional, 'nullable', 'string', 'min:4', 'max:20', 'regex:/^[A-Z0-9]+$/',
                Rule::unique('patients', 'document_number')
                    ->where(fn ($q) => $q->where('document_type', $documentType))
                    ->ignore($patient?->id)],
            'locality' => [$optional, 'nullable', 'string', 'max:150'],
            'id' => ['prohibited'],
        ];
    }

    /** Cross-field rules: document type and number go together; DNI format. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['document_type', 'document_number'])) {
                    return;
                }

                $patient = $this->currentPatient();
                $type = $this->has('document_type') ? $this->input('document_type') : $patient?->document_type;
                $number = $this->has('document_number') ? $this->input('document_number') : $patient?->document_number;

                if ($type !== null && $number === null) {
                    $validator->errors()->add('document_number', 'El número de documento es obligatorio cuando se indica el tipo.');
                } elseif ($type === null && $number !== null) {
                    $validator->errors()->add('document_type', 'El tipo de documento es obligatorio cuando se indica el número.');
                } elseif ($type === 'DNI' && preg_match('/^\d{8}$/', (string) $number) !== 1) {
                    $validator->errors()->add('document_number', 'El DNI debe tener 8 dígitos.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'chart_number.unique' => 'Ya existe un paciente con ese número de historia clínica.',
            'document_number.unique' => 'Ya existe un paciente con ese tipo y número de documento.',
            'first_name.regex' => 'El nombre contiene caracteres no permitidos.',
            'last_name.regex' => 'Los apellidos contienen caracteres no permitidos.',
            'chart_number.regex' => 'El número de historia clínica solo admite letras, números, "-" y "/".',
            'birth_date.before_or_equal' => 'La fecha de nacimiento no puede ser futura.',
        ];
    }

    /** @return array<string, mixed> */
    public function patientData(): array
    {
        return collect($this->validated())->only(Patient::WRITABLE)->all();
    }
}
