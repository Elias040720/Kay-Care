<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * PUT his-lines: { lines: HisLineWrite[] }. Replaces the full set of
 * activities confirmed by the professional (an empty list clears it).
 */
class ReplaceHisLinesRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('manageHisLines', $this->route('encounter'));
    }

    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines');

        if (! is_array($lines)) {
            return;
        }

        foreach ($lines as $i => $line) {
            if (is_array($line) && isset($line['lab']) && is_string($line['lab'])) {
                $lab = trim($line['lab']);
                $lines[$i]['lab'] = $lab === '' ? null : $lab;
            }
        }

        $this->merge(['lines' => $lines]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'lines' => ['present', 'array', 'list', 'max:'.(int) config('kaycare.his.max_lines', 20)],
            'lines.*' => ['array:catalog_item_id,diagnosis_type,lab'],
            'lines.*.catalog_item_id' => ['required', 'uuid', 'distinct', Rule::exists('his_catalog_items', 'id')],
            'lines.*.diagnosis_type' => ['nullable', 'string', Rule::in(config('kaycare.his.diagnosis_types'))],
            'lines.*.lab' => ['nullable', 'string', 'max:'.(int) config('kaycare.his.lab_max_length', 10)],
        ];
    }

    public function messages(): array
    {
        return [
            'lines.*.catalog_item_id.distinct' => 'La actividad está duplicada en la lista.',
            'lines.*.catalog_item_id.exists' => 'La actividad indicada no existe en el catálogo HIS.',
            'lines.*.catalog_item_id.uuid' => 'El identificador de catálogo no es válido.',
        ];
    }

    /** @return list<array{catalog_item_id: string, diagnosis_type: ?string, lab: ?string}> */
    public function lines(): array
    {
        return array_map(fn (array $line) => [
            'catalog_item_id' => $line['catalog_item_id'],
            'diagnosis_type' => $line['diagnosis_type'] ?? null,
            'lab' => $line['lab'] ?? null,
        ], array_values($this->validated('lines')));
    }
}
