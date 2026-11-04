<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

/**
 * Validación de imágenes: solo JPEG/PNG/WebP (sin SVG, que puede contener
 * scripts), máximo 2 MB, dimensiones razonables. El tipo se comprueba por
 * contenido (finfo), no por la extensión enviada.
 */
class ProductImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'image' => [
                'required',
                File::types(['jpg', 'jpeg', 'png', 'webp'])->max(2048),
                'mimetypes:image/jpeg,image/png,image/webp',
                'dimensions:min_width=64,min_height=64,max_width=4000,max_height=4000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'image.required' => 'Selecciona una imagen.',
            'image.mimetypes' => 'Formato no permitido: usa JPG, PNG o WebP.',
            'image.dimensions' => 'La imagen debe medir entre 64 y 4000 px por lado.',
        ];
    }
}
