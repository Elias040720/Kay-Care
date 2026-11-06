<?php

namespace App\Services\Inventory;

use App\Exceptions\StoreException;
use App\Models\AdminUser;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

/**
 * Único punto por el que el administrador modifica stock. Cada cambio
 * queda auditado en `stock_movements`.
 */
class StockService
{
    public function adjust(Product $product, int $delta, string $reason, ?string $note, ?AdminUser $admin): Product
    {
        return DB::transaction(function () use ($product, $delta, $reason, $note, $admin) {
            /** @var Product $locked */
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            if ($locked->stock + $delta < 0) {
                throw StoreException::negativeStock($locked->stock, $delta);
            }

            $locked->stock += $delta;
            $locked->save();

            StockMovement::query()->create([
                'product_id' => $locked->id,
                'delta' => $delta,
                'stock_after' => $locked->stock,
                'reason' => $reason,
                'note' => $note,
                'admin_user_id' => $admin?->id,
            ]);

            return $locked;
        });
    }
}
