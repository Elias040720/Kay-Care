<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;

class CartValidateRequest extends FormRequest
{
    public const MAX_LINES = 20;

    public const MAX_QUANTITY = 10;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            // Sin cantidades negativas ni cero; tope demostrativo por línea.
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.self::MAX_QUANTITY],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'El carrito está vacío.',
            'items.max' => 'El carrito admite como máximo '.self::MAX_LINES.' productos distintos.',
            'items.*.quantity.min' => 'La cantidad debe ser al menos 1.',
            'items.*.quantity.max' => 'La cantidad máxima por producto es '.self::MAX_QUANTITY.'.',
            'items.*.quantity.integer' => 'La cantidad debe ser un número entero.',
        ];
    }
}
