<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_only_active_products_with_stock_status(): void
    {
        Product::factory()->stock(0)->create(['name' => 'Agotado Demo', 'slug' => 'agotado-demo']);
        Product::factory()->stock(3)->create(['name' => 'Poco Stock Demo', 'slug' => 'poco-stock-demo']);
        Product::factory()->inactive()->create(['name' => 'Inactivo Demo', 'slug' => 'inactivo-demo']);

        $response = $this->getJson(self::API.'/products?sort=name_asc')->assertOk();

        $response->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Agotado Demo')
            ->assertJsonPath('data.0.stock_status', 'out_of_stock')
            ->assertJsonPath('data.1.stock_status', 'low_stock')
            ->assertJsonStructure(['data' => [['id', 'sku', 'name', 'slug', 'price_cents', 'stock', 'category']], 'meta' => ['total', 'per_page']]);
    }

    public function test_search_matches_name_brand_and_sku_case_insensitively(): void
    {
        Product::factory()->create(['name' => 'Protector Solar Demo', 'slug' => 'protector-solar-demo', 'brand' => 'Solárea']);
        Product::factory()->create(['name' => 'Gel Aloe Demo', 'slug' => 'gel-aloe-demo', 'sku' => 'KCF-ALOE-9']);

        $this->getJson(self::API.'/products?q=PROTECTOR')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'protector-solar-demo');
        $this->getJson(self::API.'/products?q=aloe-9')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_search_treats_wildcards_and_sql_as_literal_text(): void
    {
        Product::factory()->count(3)->create();

        $this->getJson(self::API.'/products?q=%25')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(self::API.'/products?q=_')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(self::API.'/products?q='.urlencode("' OR 1=1 --"))->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame(3, Product::query()->count());
    }

    public function test_filters_by_category_price_range_and_stock(): void
    {
        $skin = Category::factory()->create(['slug' => 'piel']);
        Product::factory()->for($skin)->price(1500)->create();
        Product::factory()->for($skin)->price(4500)->stock(0)->create();
        Product::factory()->price(2000)->create();

        $this->getJson(self::API.'/products?category=piel')->assertJsonCount(2, 'data');
        $this->getJson(self::API.'/products?category=piel&in_stock=1')->assertJsonCount(1, 'data');
        $this->getJson(self::API.'/products?min_price=16&max_price=50')->assertJsonCount(2, 'data');
        $this->getJson(self::API.'/products?max_price=15')->assertJsonCount(1, 'data')->assertJsonPath('data.0.price_cents', 1500);
    }

    public function test_sorts_by_price(): void
    {
        Product::factory()->price(3000)->create();
        Product::factory()->price(1000)->create();
        Product::factory()->price(2000)->create();

        $asc = $this->getJson(self::API.'/products?sort=price_asc')->json('data.*.price_cents');
        $desc = $this->getJson(self::API.'/products?sort=price_desc')->json('data.*.price_cents');

        $this->assertSame([1000, 2000, 3000], $asc);
        $this->assertSame([3000, 2000, 1000], $desc);
    }

    public function test_rejects_invalid_filters(): void
    {
        $this->getJson(self::API.'/products?sort=drop_table')->assertStatus(422)->assertJsonPath('code', 'validation_failed');
        $this->getJson(self::API.'/products?min_price=50&max_price=10')->assertStatus(422)->assertJsonValidationErrors('max_price');
        $this->getJson(self::API.'/products?per_page=500')->assertStatus(422);
    }

    public function test_shows_product_detail_and_hides_inactive(): void
    {
        $product = Product::factory()->create();
        $inactive = Product::factory()->inactive()->create();

        $this->getJson(self::API.'/products/'.$product->slug)->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.category.slug', $product->category->slug);

        $this->getJson(self::API.'/products/'.$inactive->slug)->assertNotFound()->assertJsonPath('code', 'not_found');
        $this->getJson(self::API.'/products/no-existe')->assertNotFound();
    }

    public function test_related_products_share_category(): void
    {
        $category = Category::factory()->create();
        $main = Product::factory()->for($category)->create();
        Product::factory()->for($category)->count(2)->create();
        Product::factory()->create();

        $this->getJson(self::API.'/products/'.$main->slug.'/related')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_lists_categories_with_active_product_counts(): void
    {
        $category = Category::factory()->create();
        Product::factory()->for($category)->count(2)->create();
        Product::factory()->for($category)->inactive()->create();

        $this->getJson(self::API.'/categories')->assertOk()
            ->assertJsonPath('data.0.products_count', 2);
    }
}
