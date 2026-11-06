<?php

namespace App\Services\Admin;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Indicadores calculados exclusivamente a partir de los datos demo
 * almacenados en la base de datos de la farmacia.
 */
class DashboardService
{
    /** @return array<string, mixed> */
    public function summary(int $days = 14): array
    {
        // Consulta sin modelo: evita que el cast a enum convierta las claves.
        $byStatus = DB::table('orders')
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($v) => (int) $v)
            ->all();

        $valid = Order::query()->where('status', '!=', OrderStatus::Cancelled->value);
        $revenue = (int) (clone $valid)->sum('total_cents');
        $validCount = (int) (clone $valid)->count();

        $since = CarbonImmutable::now()->startOfDay()->subDays($days - 1);
        $recent = Order::query()
            ->where('created_at', '>=', $since)
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->get(['created_at', 'total_cents']);

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $since->addDays($i)->toDateString();
            $series[$day] = ['date' => $day, 'orders' => 0, 'revenue_cents' => 0];
        }
        foreach ($recent as $order) {
            $day = $order->created_at->toDateString();
            if (isset($series[$day])) {
                $series[$day]['orders']++;
                $series[$day]['revenue_cents'] += $order->total_cents;
            }
        }

        $topProducts = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->select('order_items.product_name', DB::raw('SUM(order_items.quantity) as units'), DB::raw('SUM(order_items.line_total_cents) as revenue_cents'))
            ->groupBy('order_items.product_name')
            ->orderByDesc('units')
            ->orderBy('order_items.product_name')
            ->limit(5)
            ->get()
            ->map(fn ($r) => ['name' => $r->product_name, 'units' => (int) $r->units, 'revenue_cents' => (int) $r->revenue_cents]);

        $lowStock = Product::query()
            ->whereColumn('stock', '<=', 'low_stock_threshold')
            ->orderBy('stock')
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'sku', 'stock', 'low_stock_threshold', 'is_active']);

        return [
            'orders_total' => array_sum($byStatus),
            'orders_by_status' => collect(OrderStatus::cases())
                ->mapWithKeys(fn (OrderStatus $s) => [$s->value => $byStatus[$s->value] ?? 0])
                ->all(),
            'revenue_cents' => $revenue,
            'average_ticket_cents' => $validCount > 0 ? intdiv($revenue, $validCount) : 0,
            'products_total' => Product::query()->count(),
            'products_active' => Product::query()->where('is_active', true)->count(),
            'units_in_stock' => (int) Product::query()->sum('stock'),
            'low_stock' => $lowStock,
            'top_products' => $topProducts,
            'daily' => array_values($series),
            'generated_at' => now()->toIso8601String(),
            'disclaimer' => 'Cifras calculadas sobre pedidos ficticios de demostración.',
        ];
    }
}
