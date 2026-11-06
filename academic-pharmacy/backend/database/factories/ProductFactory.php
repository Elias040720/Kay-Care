<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $n = $this->faker->unique()->numberBetween(1, 999999);
        $name = 'Producto Demo '.$n;

        return [
            'category_id' => Category::factory(),
            'sku' => 'TEST-'.$n,
            'name' => $name,
            'slug' => Str::slug($name),
            'short_description' => 'Producto ficticio de prueba.',
            'description' => 'Descripción ficticia para pruebas automatizadas.',
            'presentation' => 'Caja x 10',
            'brand' => 'Marca Demo',
            'price_cents' => $this->faker->numberBetween(500, 9000),
            'stock' => 20,
            'low_stock_threshold' => 5,
            'illustration' => 'capsule',
            'is_active' => true,
            'is_featured' => false,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function stock(int $stock): static
    {
        return $this->state(fn () => ['stock' => $stock]);
    }

    public function price(int $cents): static
    {
        return $this->state(fn () => ['price_cents' => $cents]);
    }
}
