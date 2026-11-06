<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'buyer_alias' => $this->buyer_alias,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'allowed_transitions' => array_map(fn ($s) => $s->value, $this->status->allowedTransitions()),
            'items_count' => $this->items_count,
            'subtotal_cents' => $this->subtotal_cents,
            'tax_cents' => $this->tax_cents,
            'total_cents' => $this->total_cents,
            'created_at' => $this->created_at?->toIso8601String(),
            'status_changed_at' => $this->status_changed_at?->toIso8601String(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'sku' => $item->sku,
                'unit_price_cents' => $item->unit_price_cents,
                'quantity' => $item->quantity,
                'line_total_cents' => $item->line_total_cents,
            ])),
            'history' => $this->whenLoaded('history', fn () => $this->history->map(fn ($h) => [
                'from_status' => $h->from_status,
                'to_status' => $h->to_status,
                'note' => $h->note,
                'admin' => $h->admin_user_id ? optional($h->admin)->name : null,
                'created_at' => $h->created_at?->toIso8601String(),
            ])),
            'simulated' => true,
        ];
    }
}
