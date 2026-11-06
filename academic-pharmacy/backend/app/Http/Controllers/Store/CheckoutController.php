<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\CartValidateRequest;
use App\Http\Requests\Store\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Services\Checkout\CartPricer;
use App\Services\Checkout\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    /** Vista previa del carrito: precios y stock actuales, sin bloquear. */
    public function validateCart(CartValidateRequest $request, CartPricer $pricer): JsonResponse
    {
        $normalized = $pricer->normalize($request->validated('items'));
        $products = Product::query()->whereIn('id', array_keys($normalized))->get()->keyBy('id');

        $result = $pricer->price($normalized, $products);

        return response()->json([
            'data' => $result + ['valid' => $result['issues'] === []],
        ]);
    }

    public function store(CheckoutRequest $request, CheckoutService $checkout): JsonResponse
    {
        $result = $checkout->place(
            $request->validated('idempotency_key'),
            $request->validated('buyer_alias'),
            $request->validated('items'),
        );

        $order = $result->order;

        return response()->json([
            'data' => (new OrderResource($order))->resolve($request),
            'lookup_token' => $order->lookup_token,
            'replayed' => $result->replayed,
            'notice' => 'PROYECTO ACADÉMICO — TIENDA DEMOSTRATIVA — NO SE REALIZAN VENTAS REALES',
        ], $result->replayed ? 200 : 201)->header('Idempotency-Replayed', $result->replayed ? 'true' : 'false');
    }

    /** Confirmación: requiere número de pedido + token opaco (evita enumeración). */
    public function showOrder(Request $request, string $number): OrderResource
    {
        $token = (string) $request->query('token', '');

        $order = Order::query()->where('number', $number)->first();

        abort_if(! $order || $token === '' || ! hash_equals($order->lookup_token, $token), 404, 'Pedido no encontrado.');

        return new OrderResource($order->load('items'));
    }
}
