<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderIndexRequest;
use App\Http\Requests\Admin\OrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Orders\OrderStatusService;
use App\Support\Like;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function index(OrderIndexRequest $request): AnonymousResourceCollection
    {
        $term = trim((string) $request->validated('q'));

        $orders = Order::query()
            ->when($request->validated('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($term !== '', fn ($q) => Like::anyColumn($q, ['number', 'buyer_alias'], $term))
            ->latest('id')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return OrderResource::collection($orders);
    }

    public function show(Order $order): OrderResource
    {
        return new OrderResource($order->load(['items', 'history.admin']));
    }

    public function updateStatus(OrderStatusRequest $request, Order $order, OrderStatusService $service): OrderResource
    {
        $updated = $service->transition(
            $order,
            OrderStatus::from($request->validated('status')),
            $request->user(),
            $request->validated('note'),
        );

        return new OrderResource($updated->load('history.admin'));
    }
}
