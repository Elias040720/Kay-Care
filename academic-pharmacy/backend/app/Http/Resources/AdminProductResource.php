<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;

/** @mixin Product */
class AdminProductResource extends ProductResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'category_id' => $this->category_id,
            'low_stock_threshold' => $this->low_stock_threshold,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ]);
    }
}
