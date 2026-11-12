<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogPromotionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_exposes_discount_only_when_compare_at_price_is_higher(): void
    {
        Product::factory()->price(4000)->create(['slug' => 'con-promo', 'compare_at_price_cents' => 5000]);
        Product::factory()->price(4000)->create(['slug' => 'precio-invalido', 'compare_at_price_cents' => 3000]);

        $this->getJson(self::API.'/products/con-promo')->assertOk()
            ->assertJsonPath('data.compare_at_price_cents', 5000)
            ->assertJsonPath('data.discount_percent', 20);

        $this->getJson(self::API.'/products/precio-invalido')->assertOk()
            ->assertJsonPath('data.compare_at_price_cents', null)
            ->assertJsonPath('data.discount_percent', null);
    }

    public function test_on_sale_filter_returns_only_real_promotions(): void
    {
        Product::factory()->price(1000)->create(['slug' => 'oferta-a', 'compare_at_price_cents' => 1500]);
        Product::factory()->price(1000)->create(['slug' => 'sin-oferta']);
        Product::factory()->price(1000)->create(['slug' => 'oferta-mal-cargada', 'compare_at_price_cents' => 900]);

        $this->getJson(self::API.'/products?on_sale=1')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'oferta-a');
    }

    public function test_checkout_charges_current_price_not_compare_at_price(): void
    {
        $product = Product::factory()->price(1990)->stock(5)->create(['compare_at_price_cents' => 2990]);

        $this->withHeader('Idempotency-Key', 'promo-test-key-0001')
            ->postJson(self::API.'/checkout', [
                'buyer_alias' => 'Cliente Demo',
                'accept_demo_terms' => true,
                'items' => [['product_id' => $product->id, 'quantity' => 2]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.total_cents', 3980);
    }

    public function test_catalog_seeder_is_idempotent_and_creates_the_six_store_categories(): void
    {
        $this->seed(CatalogSeeder::class);
        $this->seed(CatalogSeeder::class);

        $this->assertSame(
            ['medicamentos-venta-libre', 'vitaminas-y-bienestar', 'cuidado-personal', 'higiene', 'primeros-auxilios', 'cuidado-infantil'],
            Category::query()->orderBy('sort_order')->pluck('slug')->all(),
        );
        $this->assertSame(24, Product::query()->count());
        $this->assertSame(count(CatalogSeeder::PROMOTIONS), Product::query()->whereNotNull('compare_at_price_cents')->count());
    }

    public function test_reorganize_migration_renames_legacy_categories_without_overwriting_admin_changes(): void
    {
        // Simula una base creada con la versión anterior del catálogo.
        $legacy = Category::factory()->create(['name' => 'Garganta y resfrío', 'slug' => 'garganta-y-resfrio']);
        $baby = Category::factory()->create(['name' => 'Bebé y familia', 'slug' => 'bebe-y-familia']);
        Category::factory()->create(['name' => 'Bienestar diario', 'slug' => 'bienestar-diario']);
        $moved = Product::factory()->create(['sku' => 'KCF-GR-002', 'category_id' => $legacy->id]);
        $promo = Product::factory()->price(4990)->create(['sku' => 'KCF-PIEL-001']);
        $adminPromo = Product::factory()->price(2490)->create(['sku' => 'KCF-VIT-001', 'compare_at_price_cents' => 2700]);
        $babyItem = Product::factory()->create(['sku' => 'KCF-BB-001', 'category_id' => $baby->id]);

        $migration = require database_path('migrations/2026_11_12_000200_reorganize_demo_catalog.php');
        $migration->up();
        $migration->up(); // idempotente

        $this->assertSame('Medicamentos de venta libre', $legacy->fresh()->name);
        $this->assertSame('medicamentos-venta-libre', $legacy->fresh()->slug);
        $this->assertSame('cuidado-infantil', $baby->fresh()->slug);
        $this->assertSame('vitaminas-y-bienestar', $moved->fresh()->category->slug);
        $this->assertSame($baby->id, $babyItem->fresh()->category_id);
        $this->assertSame(5890, $promo->fresh()->compare_at_price_cents);
        $this->assertSame(2700, $adminPromo->fresh()->compare_at_price_cents, 'No pisa un precio «antes» definido por la administración.');
        $this->assertSame(0, DB::table('categories')->where('slug', 'garganta-y-resfrio')->count());
    }
}
