<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Services\Checkout\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderTest extends TestCase
{
    use RefreshDatabase;

    private function placeOrder(Product $product, int $quantity = 2, string $alias = 'Comprador Demo 01'): Order
    {
        return app(CheckoutService::class)
            ->place($this->idempotencyKey(), $alias, [['product_id' => $product->id, 'quantity' => $quantity]])
            ->order;
    }

    public function test_admin_lists_and_filters_orders(): void
    {
        $this->actingAsAdmin();
        $product = Product::factory()->stock(50)->create();
        $this->placeOrder($product, alias: 'Alias Uno');
        $second = $this->placeOrder($product, alias: 'Alias Dos');
        $second->update(['status' => 'confirmed']);

        $this->getJson(self::API.'/admin/orders')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson(self::API.'/admin/orders?status=confirmed')->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.buyer_alias', 'Alias Dos');
        $this->getJson(self::API.'/admin/orders?q=uno')->assertJsonCount(1, 'data');
        $this->getJson(self::API.'/admin/orders?status=pagado')->assertStatus(422);
    }

    public function test_admin_sees_order_detail_with_items_and_history(): void
    {
        $this->actingAsAdmin();
        $order = $this->placeOrder(Product::factory()->create());

        $this->getJson(self::API."/admin/orders/{$order->id}")->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.history.0.to_status', 'pending')
            ->assertJsonPath('data.allowed_transitions', ['confirmed', 'cancelled']);
    }

    public function test_status_moves_forward_along_the_state_machine(): void
    {
        $admin = $this->actingAsAdmin();
        $order = $this->placeOrder(Product::factory()->create());

        foreach (['confirmed', 'preparing', 'ready', 'delivered'] as $status) {
            $this->patchJson(self::API."/admin/orders/{$order->id}/status", ['status' => $status])
                ->assertOk()->assertJsonPath('data.status', $status);
        }

        $this->assertDatabaseHas('order_status_history', ['order_id' => $order->id, 'to_status' => 'delivered', 'admin_user_id' => $admin->id]);
        $this->patchJson(self::API."/admin/orders/{$order->id}/status", ['status' => 'cancelled'])
            ->assertStatus(422)->assertJsonPath('code', 'invalid_status_transition');
    }

    public function test_invalid_jumps_and_unknown_statuses_are_rejected(): void
    {
        $this->actingAsAdmin();
        $order = $this->placeOrder(Product::factory()->create());

        $this->patchJson(self::API."/admin/orders/{$order->id}/status", ['status' => 'delivered'])
            ->assertStatus(422)->assertJsonPath('code', 'invalid_status_transition');
        $this->patchJson(self::API."/admin/orders/{$order->id}/status", ['status' => 'pagado'])
            ->assertStatus(422)->assertJsonValidationErrors('status');
        $this->assertSame('pending', $order->fresh()->status->value);
    }

    public function test_cancelling_returns_stock_exactly_once(): void
    {
        $this->actingAsAdmin();
        $product = Product::factory()->stock(10)->create();
        $order = $this->placeOrder($product, 4);
        $this->assertSame(6, $product->fresh()->stock);

        $this->patchJson(self::API."/admin/orders/{$order->id}/status", ['status' => 'cancelled', 'note' => 'Prueba'])
            ->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertSame(10, $product->fresh()->stock);

        $this->patchJson(self::API."/admin/orders/{$order->id}/status", ['status' => 'cancelled'])->assertStatus(422);
        $this->assertSame(10, $product->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'reason' => 'cancellation', 'delta' => 4]);
    }

    public function test_dashboard_is_computed_from_stored_demo_data(): void
    {
        $this->actingAsAdmin();
        $a = Product::factory()->price(1000)->stock(20)->create(['name' => 'Producto A']);
        $b = Product::factory()->price(500)->stock(3)->create(['name' => 'Producto B']);

        $this->placeOrder($a, 2);                       // 2000
        $this->placeOrder($b, 1);                       // 500
        $cancelled = $this->placeOrder($a, 1);          // 1000 (se cancela)
        $this->patchJson(self::API."/admin/orders/{$cancelled->id}/status", ['status' => 'cancelled'])->assertOk();

        $this->getJson(self::API.'/admin/dashboard')->assertOk()
            ->assertJsonPath('data.orders_total', 3)
            ->assertJsonPath('data.orders_by_status.pending', 2)
            ->assertJsonPath('data.orders_by_status.cancelled', 1)
            ->assertJsonPath('data.revenue_cents', 2500)
            ->assertJsonPath('data.average_ticket_cents', 1250)
            ->assertJsonPath('data.top_products.0.name', 'Producto A')
            ->assertJsonPath('data.top_products.0.units', 2)
            ->assertJsonPath('data.low_stock.0.name', 'Producto B')
            ->assertJsonCount(14, 'data.daily');
    }
}
