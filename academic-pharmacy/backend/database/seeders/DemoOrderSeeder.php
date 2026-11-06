<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Services\Checkout\CheckoutService;
use App\Services\Orders\OrderStatusService;
use Illuminate\Database\Seeder;

/**
 * Pedidos ficticios creados a través del mismo servicio de checkout que usa
 * la tienda (stock, movimientos e historial consistentes). Determinista.
 */
class DemoOrderSeeder extends Seeder
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly OrderStatusService $statuses,
    ) {}

    public function run(): void
    {
        if (Order::query()->exists()) {
            $this->command?->info('Pedidos demo ya presentes; se omite.');

            return;
        }

        mt_srand(2026);
        $products = Product::query()->where('stock', '>', 6)->orderBy('id')->get();
        if ($products->isEmpty()) {
            return;
        }

        // Ruta de estados final para cada pedido demo.
        $plans = [
            OrderStatus::Delivered, OrderStatus::Delivered, OrderStatus::Delivered, OrderStatus::Delivered,
            OrderStatus::Delivered, OrderStatus::Ready, OrderStatus::Ready, OrderStatus::Preparing,
            OrderStatus::Preparing, OrderStatus::Confirmed, OrderStatus::Confirmed, OrderStatus::Pending,
            OrderStatus::Pending, OrderStatus::Pending, OrderStatus::Cancelled, OrderStatus::Delivered,
            OrderStatus::Delivered, OrderStatus::Cancelled,
        ];
        $path = [OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::Delivered];

        foreach ($plans as $index => $target) {
            $lines = [];
            $picked = $products->random(mt_rand(1, 3));
            foreach ($picked as $product) {
                $lines[] = ['product_id' => $product->id, 'quantity' => mt_rand(1, 2)];
            }

            $result = $this->checkout->place(
                sprintf('seed-demo-order-%04d-kaycare', $index + 1),
                sprintf('Comprador Demo %02d', $index + 1),
                $lines,
            );
            $order = $result->order;

            if ($target === OrderStatus::Cancelled) {
                $this->statuses->transition($order, OrderStatus::Cancelled, null, 'Cancelación de ejemplo (datos demo).');
            } else {
                foreach ($path as $step) {
                    if ($order->status === $target) {
                        break;
                    }
                    $order = $this->statuses->transition($order, $step, null, 'Avance automático de datos demo.');
                }
            }

            // Distribuye los pedidos en los últimos 13 días para el dashboard.
            $createdAt = now()->subDays(13 - ($index % 14))->setTime(9 + ($index % 9), (7 * $index) % 60);
            Order::query()->whereKey($order->id)->update(['created_at' => $createdAt]);
        }
    }
}
