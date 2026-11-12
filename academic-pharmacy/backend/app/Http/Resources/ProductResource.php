<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'slug' => $this->slug,
            'brand' => $this->brand,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'presentation' => $this->presentation,
            'price_cents' => $this->price_cents,
            // Precio «antes» solo si realmente es mayor (promoción ficticia).
            'compare_at_price_cents' => $this->hasDiscount() ? $this->compare_at_price_cents : null,
            'discount_percent' => $this->discountPercent(),
            'stock' => $this->stock,
            'stock_status' => $this->stockStatus(),
            'image_url' => $this->imageUrl(),
            'illustration' => $this->illustration,
            'is_featured' => $this->is_featured,
            'category' => new CategoryResource($this->whenLoaded('category')),
        ];
    }
}
