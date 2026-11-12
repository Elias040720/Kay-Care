<?php

namespace App\Http\Requests\Store;

use App\Services\Catalog\ProductSearch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:80'],
            'category' => ['nullable', 'string', 'max:90', 'regex:/^[a-z0-9-]+$/'],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:100000', Rule::when($this->filled('min_price'), 'gte:min_price')],
            'in_stock' => ['nullable', 'boolean'],
            'featured' => ['nullable', 'boolean'],
            'on_sale' => ['nullable', 'boolean'],
            'low_stock' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(ProductSearch::SORTS)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'max_price.gte' => 'El precio máximo debe ser mayor o igual al mínimo.',
            'category.regex' => 'La categoría no es válida.',
        ];
    }
}
