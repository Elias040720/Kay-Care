<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    /** @param list<array<string, mixed>> $items */
    private function checkout(array $items, ?string $key = null, string $alias = 'Comprador Demo 01', array $extra = [])
    {
        $headers = $key === '' ? [] : ['Idempotency-Key' => $key ?? $this->idempotencyKey()];

        return $this->postJson(self::API.'/checkout', array_merge([
            'buyer_alias' => $alias,
            'accept_demo_terms' => true,
            'items' => $items,
        ], $extra), $headers);
    }

    public function test_cart_validation_prices_on_server_and_reports_stock_issues(): void
    {
        $a = Product::factory()->price(1250)->stock(10)->create();
        $b = Product::factory()->price(990)->stock(1)->create();

        $this->postJson(self::API.'/cart/validate', ['items' => [
            ['product_id' => $a->id, 'quantity' => 2, 'unit_price_cents' => 1],
            ['product_id' => $b->id, 'quantity' => 3],
        ]])->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.lines.0.unit_price_cents', 1250)
            ->assertJsonPath('data.lines.0.line_total_cents', 2500)
            ->assertJsonPath('data.issues.0.code', 'insufficient_stock')
            ->assertJsonPath('data.issues.0.available', 1);
    }

    public function test_rejects_zero_negative_fractional_and_excessive_quantities(): void
    {
        $p = Product::factory()->create();

        foreach ([0, -1, 1.5, 11, 'abc'] as $quantity) {
            $this->postJson(self::API.'/cart/validate', ['items' => [['product_id' => $p->id, 'quantity' => $quantity]]])
                ->assertStatus(422)->assertJsonValidationErrors('items.0.quantity');
        }
        $this->postJson(self::API.'/cart/validate', ['items' => []])->assertStatus(422);
    }

    public function test_places_order_decrements_stock_and_records_movements(): void
    {
        $a = Product::factory()->price(2490)->stock(10)->create();
        $b = Product::factory()->price(1000)->stock(5)->create();

        $response = $this->checkout([
            ['product_id' => $a->id, 'quantity' => 2],
            ['product_id' => $b->id, 'quantity' => 1],
        ])->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'false')
            ->assertJsonPath('replayed', false)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total_cents', 5980)
            ->assertJsonPath('data.items_count', 3)
            ->assertJsonPath('notice', 'PROYECTO ACADÉMICO — TIENDA DEMOSTRATIVA — NO SE REALIZAN VENTAS REALES');

        // IGV incluido: 5980 × 18 / 118 = 912,2 → 912
        $response->assertJsonPath('data.tax_cents', 912)->assertJsonPath('data.subtotal_cents', 5068);
        $this->assertMatchesRegularExpression('/^KCF-\d{4}-\d{6}$/', $response->json('data.number'));

        $this->assertSame(8, $a->fresh()->stock);
        $this->assertSame(4, $b->fresh()->stock);
        $this->assertSame(2, StockMovement::query()->where('reason', 'checkout')->count());
        $this->assertDatabaseHas('order_status_history', ['to_status' => 'pending']);
    }

    public function test_client_supplied_prices_and_totals_are_ignored(): void
    {
        $p = Product::factory()->price(5000)->create();

        $this->checkout([['product_id' => $p->id, 'quantity' => 1, 'unit_price_cents' => 1]], extra: ['total_cents' => 1])
            ->assertCreated()
            ->assertJsonPath('data.total_cents', 5000)
            ->assertJsonPath('data.items.0.unit_price_cents', 5000);
    }

    public function test_insufficient_stock_rolls_back_everything(): void
    {
        $ok = Product::factory()->stock(10)->create();
        $short = Product::factory()->stock(1)->create();

        $this->checkout([
            ['product_id' => $ok->id, 'quantity' => 2],
            ['product_id' => $short->id, 'quantity' => 2],
        ])->assertStatus(409)
            ->assertJsonPath('code', 'insufficient_stock')
            ->assertJsonPath('details.issues.0.product_id', $short->id);

        $this->assertSame(10, $ok->fresh()->stock);
        $this->assertSame(1, $short->fresh()->stock);
        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_inactive_or_missing_products_cannot_be_bought(): void
    {
        $inactive = Product::factory()->inactive()->create();

        $this->checkout([['product_id' => $inactive->id, 'quantity' => 1]])
            ->assertStatus(422)->assertJsonPath('code', 'product_unavailable');
        $this->checkout([['product_id' => 999999, 'quantity' => 1]])
            ->assertStatus(422)->assertJsonPath('code', 'product_unavailable');
    }

    public function test_duplicate_lines_are_merged_before_stock_check(): void
    {
        $p = Product::factory()->stock(3)->create();

        $this->checkout([
            ['product_id' => $p->id, 'quantity' => 2],
            ['product_id' => $p->id, 'quantity' => 2],
        ])->assertStatus(409);

        $this->checkout([
            ['product_id' => $p->id, 'quantity' => 1],
            ['product_id' => $p->id, 'quantity' => 2],
        ])->assertCreated()->assertJsonPath('data.items_count', 3)->assertJsonCount(1, 'data.items');

        $this->assertSame(0, $p->fresh()->stock);
    }

    public function test_same_idempotency_key_replays_the_original_order_once(): void
    {
        $p = Product::factory()->stock(10)->create();
        $key = $this->idempotencyKey();
        $items = [['product_id' => $p->id, 'quantity' => 3]];

        $first = $this->checkout($items, $key)->assertCreated();
        $second = $this->checkout($items, $key)->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('replayed', true);

        $this->assertSame($first->json('data.number'), $second->json('data.number'));
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(7, $p->fresh()->stock);
    }

    public function test_same_key_with_different_payload_is_a_conflict(): void
    {
        $p = Product::factory()->stock(10)->create();
        $key = $this->idempotencyKey();

        $this->checkout([['product_id' => $p->id, 'quantity' => 1]], $key)->assertCreated();
        $this->checkout([['product_id' => $p->id, 'quantity' => 2]], $key)
            ->assertStatus(409)->assertJsonPath('code', 'idempotency_conflict');

        $this->assertSame(9, $p->fresh()->stock);
    }

    public function test_idempotency_key_is_required_and_validated(): void
    {
        $p = Product::factory()->create();

        $this->checkout([['product_id' => $p->id, 'quantity' => 1]], '')
            ->assertStatus(422)->assertJsonValidationErrors('idempotency_key');
        $this->checkout([['product_id' => $p->id, 'quantity' => 1]], 'corta')
            ->assertStatus(422)->assertJsonValidationErrors('idempotency_key');
        $this->checkout([['product_id' => $p->id, 'quantity' => 1]], str_repeat('a', 20).'<script>')
            ->assertStatus(422)->assertJsonValidationErrors('idempotency_key');
    }

    public function test_buyer_alias_must_be_fictitious_and_terms_accepted(): void
    {
        $p = Product::factory()->create();
        $items = [['product_id' => $p->id, 'quantity' => 1]];

        $this->checkout($items, alias: '45879632')->assertStatus(422)->assertJsonValidationErrors('buyer_alias');
        $this->checkout($items, alias: 'Juan 987654321')->assertStatus(422)->assertJsonValidationErrors('buyer_alias');
        $this->checkout($items, alias: 'correo@real.com')->assertStatus(422)->assertJsonValidationErrors('buyer_alias');
        $this->checkout($items, alias: '<b>x</b>')->assertStatus(422)->assertJsonValidationErrors('buyer_alias');
        $this->checkout($items, extra: ['accept_demo_terms' => false])->assertStatus(422)->assertJsonValidationErrors('accept_demo_terms');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_confirmation_requires_matching_lookup_token(): void
    {
        $p = Product::factory()->create();
        $response = $this->checkout([['product_id' => $p->id, 'quantity' => 1]])->assertCreated();
        $number = $response->json('data.number');
        $token = $response->json('lookup_token');

        $this->getJson(self::API."/orders/{$number}?token={$token}")->assertOk()
            ->assertJsonPath('data.number', $number)
            ->assertJsonMissingPath('data.idempotency_key');

        $this->getJson(self::API."/orders/{$number}?token=incorrecto")->assertNotFound();
        $this->getJson(self::API."/orders/{$number}")->assertNotFound();
    }

    public function test_checkout_is_rate_limited(): void
    {
        config(['store.rate_limits.checkout' => 2]);
        $p = Product::factory()->stock(50)->create();
        $items = [['product_id' => $p->id, 'quantity' => 1]];

        $this->checkout($items)->assertCreated();
        $this->checkout($items)->assertCreated();
        $this->checkout($items)->assertStatus(429)->assertJsonPath('code', 'too_many_requests');
    }
}
