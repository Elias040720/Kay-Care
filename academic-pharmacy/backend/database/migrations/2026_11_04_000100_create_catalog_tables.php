<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 90)->unique();
            $table->string('description', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('sku', 32)->unique();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('short_description', 200);
            $table->text('description');
            $table->string('presentation', 120);
            $table->string('brand', 80);
            // Precio en céntimos de sol (PEN), IGV incluido. Nunca float.
            $table->unsignedInteger('price_cents');
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(5);
            $table->string('image_path', 255)->nullable();
            $table->string('illustration', 40)->default('capsule');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();

            $table->index(['is_active', 'category_id']);
            $table->index('price_cents');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('delta');
            $table->unsignedInteger('stock_after');
            $table->string('reason', 30);
            $table->string('reference', 60)->nullable();
            $table->string('note', 200)->nullable();
            $table->foreignId('admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
        });

        // Defensa en profundidad: el motor rechaza stock/precios negativos
        // aunque fallara la validación de la aplicación.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE products ADD CONSTRAINT products_stock_non_negative CHECK (stock >= 0)');
            DB::statement('ALTER TABLE products ADD CONSTRAINT products_price_positive CHECK (price_cents > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
