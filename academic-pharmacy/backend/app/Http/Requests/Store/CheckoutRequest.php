<?php

namespace App\Http\Requests\Store;

use Closure;

/**
 * Checkout simulado: solo se acepta un identificador ficticio de comprador.
 * No se piden DNI, dirección, teléfono, correo ni datos bancarios, y se
 * rechazan valores que parezcan datos personales reales.
 */
class CheckoutRequest extends CartValidateRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotency_key' => $this->header('Idempotency-Key'),
            'buyer_alias' => is_string($this->input('buyer_alias')) ? trim($this->input('buyer_alias')) : $this->input('buyer_alias'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'idempotency_key' => ['required', 'string', 'min:16', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/'],
            'buyer_alias' => [
                'required', 'string', 'min:3', 'max:40',
                'regex:/^[\pL0-9 _-]+$/u',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (preg_match('/\d{7,}/', (string) $value)) {
                        $fail('No ingreses documentos ni teléfonos: usa un alias ficticio (p. ej. «Comprador Demo 07»).');
                    }
                },
            ],
            'accept_demo_terms' => ['accepted'],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'idempotency_key.required' => 'Falta la cabecera Idempotency-Key.',
            'idempotency_key.min' => 'La cabecera Idempotency-Key no es válida.',
            'idempotency_key.regex' => 'La cabecera Idempotency-Key no es válida.',
            'buyer_alias.required' => 'Ingresa un identificador ficticio de comprador.',
            'buyer_alias.regex' => 'El identificador solo admite letras, números, espacios, guiones y guion bajo.',
            'accept_demo_terms.accepted' => 'Debes confirmar que entiendes que es una tienda demostrativa sin ventas reales.',
        ]);
    }
}
