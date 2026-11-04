<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class PatientIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorization is enforced by PatientPolicy::viewAny
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:'.(int) config('kaycare.patients.search_max_length', 100)],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }

    public function term(): ?string
    {
        $term = $this->validated('search');

        return is_string($term) ? preg_replace('/\s+/u', ' ', trim($term)) : null;
    }

    public function page(): int
    {
        return (int) ($this->validated('page') ?? 1);
    }
}
