<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'product_id', 'product_name', 'sku', 'unit_price_cents', 'quantity', 'line_total_cents'];

    protected function casts(): array
    {
        return ['unit_price_cents' => 'integer', 'quantity' => 'integer', 'line_total_cents' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
