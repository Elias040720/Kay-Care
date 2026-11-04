<?php

namespace App\Services\Checkout;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Calcula precios en el servidor. El cliente solo envía product_id y
 * cantidad; cualquier precio que envíe el navegador se ignora.
 */
class CartPricer
{
    /** Tasa del IGV peruano, incluida en el precio de lista. */
    public const TAX_RATE_PERCENT = 18;

    /**
     * Fusiona líneas repetidas y ordena por product_id (orden estable para
     * el hash de idempotencia y para tomar bloqueos sin interbloqueos).
     *
     * @param  array<int, array{product_id:int|string, quantity:int|string}>  $items
     * @return array<int, int> product_id => cantidad
     */
    public function normalize(array $items): array
    {
        $merged = [];
        foreach ($items as $item) {
            $id = (int) $item['product_id'];
            $merged[$id] = ($merged[$id] ?? 0) + (int) $item['quantity'];
        }
        ksort($merged);

        return $merged;
    }

    /**
     * Revisa disponibilidad sin bloquear (vista previa del carrito).
     *
     * @param  array<int, int>  $normalized
     * @param  Collection<int, Product>  $products  indexados por id
     * @return array{lines: list<array<string, mixed>>, issues: list<array<string, mixed>>, totals: array<string, int>}
     */
    public function price(array $normalized, Collection $products): array
    {
        $lines = [];
        $issues = [];

        foreach ($normalized as $productId => $quantity) {
            /** @var Product|null $product */
            $product = $products->get($productId);

            if (! $product || ! $product->is_active) {
                $issues[] = ['product_id' => $productId, 'code' => 'unavailable', 'available' => 0, 'requested' => $quantity];

                continue;
            }

            if ($product->stock < $quantity) {
                $issues[] = [
                    'product_id' => $productId,
                    'code' => 'insufficient_stock',
                    'available' => $product->stock,
                    'requested' => $quantity,
                    'name' => $product->name,
                ];
            }

            $lines[] = [
                'product_id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'unit_price_cents' => $product->price_cents,
                'quantity' => $quantity,
                'line_total_cents' => $product->price_cents * $quantity,
                'available_stock' => $product->stock,
            ];
        }

        return ['lines' => $lines, 'issues' => $issues, 'totals' => $this->totals($lines)];
    }

    /**
     * @param  list<array{line_total_cents:int, quantity:int}>  $lines
     * @return array{items_count:int, subtotal_cents:int, tax_cents:int, total_cents:int}
     */
    public function totals(array $lines): array
    {
        $total = array_sum(array_column($lines, 'line_total_cents'));
        // Precio con IGV incluido: IGV = total × 18 / 118, redondeado al céntimo.
        $tax = (int) round($total * self::TAX_RATE_PERCENT / (100 + self::TAX_RATE_PERCENT));

        return [
            'items_count' => (int) array_sum(array_column($lines, 'quantity')),
            'subtotal_cents' => $total - $tax,
            'tax_cents' => $tax,
            'total_cents' => $total,
        ];
    }
}
