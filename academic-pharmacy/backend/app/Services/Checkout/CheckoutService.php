<?php

namespace App\Services\Checkout;

use App\Enums\OrderStatus;
use App\Exceptions\StoreException;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Checkout simulado (no hay cobro real).
 *
 * Garantías:
 *  1. Transacción única: pedido, líneas, descuento de stock y movimientos
 *     se confirman juntos o no se confirma nada.
 *  2. Bloqueo pesimista (SELECT … FOR UPDATE) de las filas de producto en
 *     orden ascendente de id → sin sobreventa y sin interbloqueos.
 *  3. Precios calculados en el servidor a partir de la fila bloqueada.
 *  4. Idempotencia: misma clave + mismo contenido ⇒ se devuelve el mismo
 *     pedido; misma clave + otro contenido ⇒ 409. La restricción UNIQUE de
 *     `orders.idempotency_key` resuelve la carrera entre dos peticiones
 *     simultáneas con la misma clave.
 */
class CheckoutService
{
    public function __construct(private readonly CartPricer $pricer) {}

    /**
     * @param  array<int, array{product_id:int|string, quantity:int|string}>  $items
     */
    public function place(string $idempotencyKey, string $buyerAlias, array $items): CheckoutResult
    {
        $normalized = $this->pricer->normalize($items);
        $requestHash = $this->requestHash($buyerAlias, $normalized);

        $existing = Order::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $this->replay($existing, $requestHash);
        }

        try {
            $order = DB::transaction(
                fn () => $this->createOrder($idempotencyKey, $requestHash, $buyerAlias, $normalized),
                attempts: 3,
            );
        } catch (UniqueConstraintViolationException $e) {
            // Otra petición con la misma clave ganó la carrera.
            $winner = Order::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($winner) {
                return $this->replay($winner, $requestHash);
            }
            throw $e;
        }

        return new CheckoutResult($order->load('items'), replayed: false);
    }

    /** @param array<int, int> $normalized */
    public function requestHash(string $buyerAlias, array $normalized): string
    {
        return hash('sha256', json_encode([
            'buyer_alias' => mb_strtolower(trim($buyerAlias)),
            'items' => $normalized,
        ], JSON_THROW_ON_ERROR));
    }

    /** @param array<int, int> $normalized */
    private function createOrder(string $key, string $hash, string $buyerAlias, array $normalized): Order
    {
        $products = Product::query()
            ->whereIn('id', array_keys($normalized))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $priced = $this->pricer->price($normalized, $products);

        $unavailable = array_values(array_filter($priced['issues'], fn ($i) => $i['code'] === 'unavailable'));
        if ($unavailable !== []) {
            throw StoreException::productUnavailable($unavailable);
        }
        if ($priced['issues'] !== []) {
            throw StoreException::insufficientStock($priced['issues']);
        }

        $totals = $priced['totals'];
        $now = now();

        $order = Order::query()->create([
            'number' => 'TMP-'.Str::random(20),
            'buyer_alias' => trim($buyerAlias),
            'status' => OrderStatus::Pending,
            'items_count' => $totals['items_count'],
            'subtotal_cents' => $totals['subtotal_cents'],
            'tax_cents' => $totals['tax_cents'],
            'total_cents' => $totals['total_cents'],
            'idempotency_key' => $key,
            'request_hash' => $hash,
            'lookup_token' => Str::random(40),
            'status_changed_at' => $now,
        ]);

        // Número legible y secuencial derivado de la clave primaria.
        $order->number = sprintf('KCF-%s-%06d', $now->format('Y'), $order->id);
        $order->save();

        foreach ($priced['lines'] as $line) {
            /** @var Product $product */
            $product = $products->get($line['product_id']);

            $order->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'unit_price_cents' => $line['unit_price_cents'],
                'quantity' => $line['quantity'],
                'line_total_cents' => $line['line_total_cents'],
            ]);

            $product->stock -= $line['quantity'];
            $product->save();

            StockMovement::query()->create([
                'product_id' => $product->id,
                'delta' => -$line['quantity'],
                'stock_after' => $product->stock,
                'reason' => StockMovement::REASON_CHECKOUT,
                'reference' => $order->number,
            ]);
        }

        $order->history()->create([
            'from_status' => null,
            'to_status' => OrderStatus::Pending->value,
            'note' => 'Pedido simulado creado desde el checkout académico.',
        ]);

        return $order;
    }

    private function replay(Order $order, string $requestHash): CheckoutResult
    {
        if (! hash_equals($order->request_hash, $requestHash)) {
            throw StoreException::idempotencyConflict();
        }

        return new CheckoutResult($order->load('items'), replayed: true);
    }
}
