<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'number', 'buyer_alias', 'status', 'items_count', 'subtotal_cents', 'tax_cents',
        'total_cents', 'idempotency_key', 'request_hash', 'lookup_token', 'status_changed_at',
    ];

    protected $hidden = ['idempotency_key', 'request_hash', 'lookup_token'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'items_count' => 'integer',
            'subtotal_cents' => 'integer',
            'tax_cents' => 'integer',
            'total_cents' => 'integer',
            'status_changed_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('id');
    }
}
