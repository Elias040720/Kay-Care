<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Exceptions\StoreException;
use App\Models\AdminUser;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class OrderStatusService
{
    /**
     * Cambia el estado respetando la máquina de estados. Al cancelar,
     * devuelve el stock de cada línea dentro de la misma transacción.
     */
    public function transition(Order $order, OrderStatus $target, ?AdminUser $admin, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $target, $admin, $note) {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if (! $from->canTransitionTo($target)) {
                throw StoreException::invalidTransition($from->label(), $target->label());
            }

            if ($target === OrderStatus::Cancelled) {
                $this->restock($locked);
            }

            $locked->status = $target;
            $locked->status_changed_at = now();
            $locked->save();

            $locked->history()->create([
                'from_status' => $from->value,
                'to_status' => $target->value,
                'admin_user_id' => $admin?->id,
                'note' => $note,
            ]);

            return $locked->load(['items', 'history']);
        });
    }

    private function restock(Order $order): void
    {
        $items = $order->items()->whereNotNull('product_id')->orderBy('product_id')->get();
        $products = Product::query()
            ->whereIn('id', $items->pluck('product_id'))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($items as $item) {
            $product = $products->get($item->product_id);
            if (! $product) {
                continue;
            }
            $product->stock += $item->quantity;
            $product->save();

            StockMovement::query()->create([
                'product_id' => $product->id,
                'delta' => $item->quantity,
                'stock_after' => $product->stock,
                'reason' => StockMovement::REASON_CANCELLATION,
                'reference' => $order->number,
            ]);
        }
    }
}
