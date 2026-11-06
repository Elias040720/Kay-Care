<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Producto ficticio de venta libre. Los precios se guardan en céntimos (PEN).
 */
class Product extends Model
{
    use HasFactory;

    public const ILLUSTRATIONS = ['capsule', 'bottle', 'drops', 'tube', 'box', 'spray', 'sachet', 'jar'];

    protected $fillable = [
        'category_id', 'sku', 'name', 'slug', 'short_description', 'description',
        'presentation', 'brand', 'price_cents', 'stock', 'low_stock_threshold',
        'image_path', 'illustration', 'is_active', 'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'stock' => 'integer',
            'low_stock_threshold' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function stockStatus(): string
    {
        if ($this->stock <= 0) {
            return 'out_of_stock';
        }

        return $this->stock <= $this->low_stock_threshold ? 'low_stock' : 'in_stock';
    }

    public function imageUrl(): ?string
    {
        // Ruta relativa: el frontend (nginx) la sirve vía proxy, sin depender del host interno.
        return $this->image_path ? '/storage/'.ltrim($this->image_path, '/') : null;
    }
}
