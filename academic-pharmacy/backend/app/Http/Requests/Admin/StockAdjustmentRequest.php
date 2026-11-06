<?php

namespace App\Http\Requests\Admin;

use App\Models\StockMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'delta' => ['required', 'integer', 'between:-1000,1000', 'not_in:0'],
            'reason' => ['required', Rule::in(StockMovement::ADMIN_REASONS)],
            'note' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'delta.not_in' => 'El ajuste no puede ser cero.',
            'delta.between' => 'El ajuste debe estar entre -1000 y 1000 unidades.',
        ];
    }
}
