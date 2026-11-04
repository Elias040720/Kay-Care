<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Support\Like;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Búsqueda, filtros y ordenamiento del catálogo. Toda entrada llega ya
 * validada por el Form Request y se aplica con bindings parametrizados
 * (sin concatenar SQL con datos del usuario).
 */
class ProductSearch
{
    public const SORTS = ['featured', 'price_asc', 'price_desc', 'name_asc', 'newest', 'stock_desc'];

    /** @param array<string, mixed> $filters */
    public function paginate(array $filters, bool $includeInactive = false): LengthAwarePaginator
    {
        $query = Product::query()->with('category');

        if (! $includeInactive) {
            $query->active();
        } elseif (array_key_exists('active', $filters) && $filters['active'] !== null) {
            $query->where('is_active', (bool) $filters['active']);
        }

        $this->applySearch($query, $filters['q'] ?? null);

        if (! empty($filters['category'])) {
            $query->whereHas('category', fn (Builder $c) => $c->where('slug', $filters['category']));
        }
        if (isset($filters['min_price'])) {
            $query->where('price_cents', '>=', (int) round(((float) $filters['min_price']) * 100));
        }
        if (isset($filters['max_price'])) {
            $query->where('price_cents', '<=', (int) round(((float) $filters['max_price']) * 100));
        }
        if (! empty($filters['in_stock'])) {
            $query->where('stock', '>', 0);
        }
        if (! empty($filters['low_stock'])) {
            $query->whereColumn('stock', '<=', 'low_stock_threshold');
        }
        if (! empty($filters['featured'])) {
            $query->where('is_featured', true);
        }

        match ($filters['sort'] ?? 'featured') {
            'price_asc' => $query->orderBy('price_cents')->orderBy('id'),
            'price_desc' => $query->orderByDesc('price_cents')->orderBy('id'),
            'name_asc' => $query->orderBy('name')->orderBy('id'),
            'newest' => $query->orderByDesc('created_at')->orderByDesc('id'),
            'stock_desc' => $query->orderByDesc('stock')->orderBy('id'),
            default => $query->orderByDesc('is_featured')->orderBy('name')->orderBy('id'),
        };

        $perPage = min(max((int) ($filters['per_page'] ?? 12), 1), 48);

        return $query->paginate($perPage)->withQueryString();
    }

    private function applySearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term === '') {
            return;
        }

        Like::anyColumn($query, ['name', 'brand', 'short_description', 'sku'], $term);
    }
}
