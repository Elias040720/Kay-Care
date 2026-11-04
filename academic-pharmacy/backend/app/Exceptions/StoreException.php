<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Error de dominio de la tienda. Se renderiza como JSON uniforme:
 * { "message": "...", "code": "...", "details": {...} }
 */
class StoreException extends RuntimeException
{
    /** @param array<string, mixed> $details */
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status = 422,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    /** @param list<array<string, mixed>> $issues */
    public static function insufficientStock(array $issues): self
    {
        return new self('Uno o más productos no tienen stock suficiente.', 'insufficient_stock', 409, ['issues' => $issues]);
    }

    /** @param list<array<string, mixed>> $issues */
    public static function productUnavailable(array $issues): self
    {
        return new self('Uno o más productos ya no están disponibles.', 'product_unavailable', 422, ['issues' => $issues]);
    }

    public static function idempotencyConflict(): self
    {
        return new self(
            'La clave de idempotencia ya se usó con un contenido distinto. Genera una nueva clave para un nuevo pedido.',
            'idempotency_conflict',
            409,
        );
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self("No se puede cambiar un pedido de «{$from}» a «{$to}».", 'invalid_status_transition', 422, [
            'from' => $from,
            'to' => $to,
        ]);
    }

    public static function negativeStock(int $current, int $delta): self
    {
        return new self('El ajuste dejaría el stock en negativo.', 'negative_stock', 422, [
            'current_stock' => $current,
            'delta' => $delta,
        ]);
    }

    public static function productHasOrders(): self
    {
        return new self(
            'El producto tiene pedidos asociados; desactívalo en lugar de eliminarlo.',
            'product_has_orders',
            409,
        );
    }
}
