<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProductTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'category_id' => Category::factory()->create()->id,
            'sku' => 'kcf-new-01',
            'name' => 'Crema Demo Nueva',
            'short_description' => 'Crema ficticia.',
            'description' => 'Descripción ficticia.',
            'presentation' => 'Tubo x 50 g',
            'brand' => 'Marca Demo',
            'price' => 19.9,
            'stock' => 12,
            'illustration' => 'tube',
        ], $overrides);
    }

    public function test_admin_lists_products_including_inactive(): void
    {
        $this->actingAsAdmin();
        Product::factory()->count(2)->create();
        Product::factory()->inactive()->create();

        $this->getJson(self::API.'/admin/products')->assertOk()->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data' => [['is_active', 'low_stock_threshold', 'category_id']]]);
        $this->getJson(self::API.'/admin/products?active=0')->assertJsonCount(1, 'data');
    }

    public function test_admin_creates_product_with_initial_stock_movement(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson(self::API.'/admin/products', $this->payload())->assertCreated()
            ->assertJsonPath('data.sku', 'KCF-NEW-01')
            ->assertJsonPath('data.slug', 'crema-demo-nueva')
            ->assertJsonPath('data.price_cents', 1990)
            ->assertJsonPath('data.stock', 12);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $response->json('data.id'), 'delta' => 12, 'reason' => 'restock',
        ]);
    }

    public function test_product_validation_rejects_bad_data(): void
    {
        $this->actingAsAdmin();
        Product::factory()->create(['sku' => 'DUP-1']);

        $this->postJson(self::API.'/admin/products', $this->payload(['price' => -5]))->assertStatus(422)->assertJsonValidationErrors('price');
        $this->postJson(self::API.'/admin/products', $this->payload(['price' => 0]))->assertStatus(422)->assertJsonValidationErrors('price');
        $this->postJson(self::API.'/admin/products', $this->payload(['stock' => -1]))->assertStatus(422)->assertJsonValidationErrors('stock');
        $this->postJson(self::API.'/admin/products', $this->payload(['sku' => 'DUP-1']))->assertStatus(422)->assertJsonValidationErrors('sku');
        $this->postJson(self::API.'/admin/products', $this->payload(['category_id' => 9999]))->assertStatus(422)->assertJsonValidationErrors('category_id');
        $this->postJson(self::API.'/admin/products', $this->payload(['illustration' => 'javascript:alert(1)']))->assertStatus(422);
    }

    public function test_html_is_stored_as_plain_text_not_interpreted(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson(self::API.'/admin/products', $this->payload([
            'description' => '<img src=x onerror=alert(1)>',
        ]))->assertCreated();

        // La API devuelve JSON (Content-Type application/json) y el frontend lo
        // renderiza como texto: el valor llega literal, sin ejecutarse.
        $this->assertSame('<img src=x onerror=alert(1)>', $response->json('data.description'));
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
    }

    public function test_admin_updates_product_but_not_stock_directly(): void
    {
        $this->actingAsAdmin();
        $product = Product::factory()->stock(5)->create();

        $this->putJson(self::API."/admin/products/{$product->id}", ['name' => 'Nombre Editado', 'price' => 12.5, 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nombre Editado')
            ->assertJsonPath('data.price_cents', 1250)
            ->assertJsonPath('data.is_active', false);

        $this->putJson(self::API."/admin/products/{$product->id}", ['stock' => 999])
            ->assertStatus(422)->assertJsonValidationErrors('stock');
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_stock_adjustments_are_audited_and_never_negative(): void
    {
        $admin = $this->actingAsAdmin();
        $product = Product::factory()->stock(5)->create();

        $this->postJson(self::API."/admin/products/{$product->id}/stock", ['delta' => 10, 'reason' => 'restock', 'note' => 'Reposición'])
            ->assertOk()->assertJsonPath('data.stock', 15);
        $this->postJson(self::API."/admin/products/{$product->id}/stock", ['delta' => -3, 'reason' => 'adjustment'])
            ->assertOk()->assertJsonPath('data.stock', 12);

        $this->postJson(self::API."/admin/products/{$product->id}/stock", ['delta' => -13, 'reason' => 'adjustment'])
            ->assertStatus(422)->assertJsonPath('code', 'negative_stock');
        $this->postJson(self::API."/admin/products/{$product->id}/stock", ['delta' => 0, 'reason' => 'adjustment'])
            ->assertStatus(422)->assertJsonValidationErrors('delta');
        $this->postJson(self::API."/admin/products/{$product->id}/stock", ['delta' => 5, 'reason' => 'checkout'])
            ->assertStatus(422)->assertJsonValidationErrors('reason');

        $this->assertSame(12, $product->fresh()->stock);
        $this->assertSame(2, StockMovement::query()->where('admin_user_id', $admin->id)->count());

        $this->getJson(self::API.'/admin/stock-movements?product_id='.$product->id)->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.delta', -3);
    }

    public function test_product_with_orders_cannot_be_deleted_but_others_can(): void
    {
        $this->actingAsAdmin();
        $sold = Product::factory()->create();
        $unsold = Product::factory()->create();
        $order = Order::query()->create([
            'number' => 'KCF-2026-999999', 'buyer_alias' => 'Demo', 'status' => 'pending', 'items_count' => 1,
            'subtotal_cents' => 100, 'tax_cents' => 18, 'total_cents' => 118, 'idempotency_key' => 'k-'.str_repeat('a', 20),
            'request_hash' => str_repeat('a', 64), 'lookup_token' => str_repeat('b', 40),
        ]);
        $order->items()->create([
            'product_id' => $sold->id, 'product_name' => $sold->name, 'sku' => $sold->sku,
            'unit_price_cents' => 118, 'quantity' => 1, 'line_total_cents' => 118,
        ]);

        $this->deleteJson(self::API."/admin/products/{$sold->id}")->assertStatus(409)->assertJsonPath('code', 'product_has_orders');
        $this->deleteJson(self::API."/admin/products/{$unsold->id}")->assertOk();
        $this->assertModelMissing($unsold);
        $this->assertModelExists($sold);
    }

    public function test_valid_png_image_is_stored_with_random_name(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $product = Product::factory()->create();

        $file = UploadedFile::fake()->createWithContent('foto original.png', $this->pngBytes(120, 120));

        $response = $this->post(self::API."/admin/products/{$product->id}/image", ['image' => $file], ['Accept' => 'application/json'])
            ->assertOk();

        $path = $product->fresh()->image_path;
        $this->assertMatchesRegularExpression('#^products/[0-9a-f-]{36}\.png$#', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertSame('/storage/'.$path, $response->json('data.image_url'));
    }

    public function test_svg_fake_and_tiny_images_are_rejected(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $product = Product::factory()->create();
        $upload = fn (UploadedFile $f) => $this->post(self::API."/admin/products/{$product->id}/image", ['image' => $f], ['Accept' => 'application/json']);

        $svg = UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $upload($svg)->assertStatus(422)->assertJsonValidationErrors('image');

        $disguised = UploadedFile::fake()->createWithContent('malicioso.png', '<?php echo "hola"; ?>');
        $upload($disguised)->assertStatus(422)->assertJsonValidationErrors('image');

        $tiny = UploadedFile::fake()->createWithContent('mini.png', $this->pngBytes(10, 10));
        $upload($tiny)->assertStatus(422)->assertJsonValidationErrors('image');

        $huge = UploadedFile::fake()->create('grande.png', 3000, 'image/png');
        $upload($huge)->assertStatus(422)->assertJsonValidationErrors('image');

        $this->assertNull($product->fresh()->image_path);
    }
}
