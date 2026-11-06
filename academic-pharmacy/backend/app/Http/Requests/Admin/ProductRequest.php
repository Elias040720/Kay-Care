<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de productos. El stock inicial solo se acepta al crear;
 * después se modifica únicamente por /stock para dejar auditoría.
 */
class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $data = [];
        if ($this->filled('name') && ! $this->filled('slug')) {
            $data['slug'] = Str::slug((string) $this->input('name'));
        }
        if ($this->filled('price')) {
            $data['price_cents'] = (int) round(((float) $this->input('price')) * 100);
        }
        if ($this->filled('sku')) {
            $data['sku'] = Str::upper(trim((string) $this->input('sku')));
        }
        $this->merge($data);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Product|null $product */
        $product = $this->route('product');
        $isUpdate = $product !== null;
        $required = $isUpdate ? 'sometimes' : 'required';

        return [
            'category_id' => [$required, 'integer', Rule::exists('categories', 'id')],
            'sku' => [$required, 'string', 'max:32', 'regex:/^[A-Z0-9-]+$/', Rule::unique('products', 'sku')->ignore($product?->id)],
            'name' => [$required, 'string', 'min:3', 'max:120'],
            'slug' => [$required, 'string', 'max:140', 'regex:/^[a-z0-9-]+$/', Rule::unique('products', 'slug')->ignore($product?->id)],
            'short_description' => [$required, 'string', 'max:200'],
            'description' => [$required, 'string', 'max:3000'],
            'presentation' => [$required, 'string', 'max:120'],
            'brand' => [$required, 'string', 'max:80'],
            'price' => [$required, 'numeric', 'gt:0', 'max:9999.99', 'decimal:0,2'],
            'price_cents' => ['sometimes', 'integer', 'min:1', 'max:999999'],
            'stock' => [$isUpdate ? 'prohibited' : 'sometimes', 'integer', 'min:0', 'max:100000'],
            'low_stock_threshold' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'illustration' => ['sometimes', Rule::in(Product::ILLUSTRATIONS)],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'stock.prohibited' => 'El stock se modifica desde «Gestión de stock» para dejar trazabilidad.',
            'price.gt' => 'El precio debe ser mayor que cero.',
            'sku.regex' => 'El SKU solo admite mayúsculas, números y guiones.',
        ];
    }

    /** @return array<string, mixed> */
    public function productData(): array
    {
        return collect($this->validated())->except(['price', 'stock'])->all();
    }
}
