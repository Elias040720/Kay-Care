<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 24)->unique();
            // Identificador ficticio elegido por el comprador (sin datos personales).
            $table->string('buyer_alias', 40);
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('items_count');
            $table->unsignedInteger('subtotal_cents');
            $table->unsignedInteger('tax_cents');
            $table->unsignedInteger('total_cents');
            $table->string('idempotency_key', 80)->unique();
            $table->char('request_hash', 64);
            $table->char('lookup_token', 40);
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            // Instantánea del producto al momento de la compra.
            $table->string('product_name', 120);
            $table->string('sku', 32);
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('line_total_cents');
            $table->timestamps();
        });

        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->string('note', 200)->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_quantity_positive CHECK (quantity > 0)');
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_valid CHECK (status IN ('pending','confirmed','preparing','ready','delivered','cancelled'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
