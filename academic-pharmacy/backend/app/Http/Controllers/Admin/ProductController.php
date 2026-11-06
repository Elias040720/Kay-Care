<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\StoreException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductImageRequest;
use App\Http\Requests\Admin\ProductRequest;
use App\Http\Requests\Admin\StockAdjustmentRequest;
use App\Http\Requests\Store\ProductIndexRequest;
use App\Http\Resources\AdminProductResource;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\Catalog\ProductSearch;
use App\Services\Inventory\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(ProductIndexRequest $request, ProductSearch $search): AnonymousResourceCollection
    {
        $filters = $request->validated() + ['sort' => 'name_asc'];

        return AdminProductResource::collection($search->paginate($filters, includeInactive: true));
    }

    public function show(Product $product): AdminProductResource
    {
        return new AdminProductResource($product->load('category'));
    }

    public function store(ProductRequest $request): JsonResponse
    {
        $product = DB::transaction(function () use ($request) {
            $initialStock = (int) $request->validated('stock', 0);
            $product = Product::query()->create($request->productData() + ['stock' => $initialStock]);

            if ($initialStock > 0) {
                StockMovement::query()->create([
                    'product_id' => $product->id,
                    'delta' => $initialStock,
                    'stock_after' => $initialStock,
                    'reason' => StockMovement::REASON_RESTOCK,
                    'note' => 'Stock inicial',
                    'admin_user_id' => $request->user()->id,
                ]);
            }

            return $product;
        });

        return (new AdminProductResource($product->load('category')))->response()->setStatusCode(201);
    }

    public function update(ProductRequest $request, Product $product): AdminProductResource
    {
        $product->fill($request->productData())->save();

        return new AdminProductResource($product->load('category'));
    }

    public function destroy(Product $product): JsonResponse
    {
        if (OrderItem::query()->where('product_id', $product->id)->exists()) {
            throw StoreException::productHasOrders();
        }

        $path = $product->image_path;
        $product->delete();
        if ($path) {
            Storage::disk('public')->delete($path);
        }

        return response()->json(['message' => 'Producto eliminado.']);
    }

    public function uploadImage(ProductImageRequest $request, Product $product): AdminProductResource
    {
        $file = $request->file('image');
        // Nombre aleatorio + extensión derivada del contenido (no del nombre original).
        $extension = match ($file->getMimeType()) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $path = $file->storeAs('products', Str::uuid()->toString().'.'.$extension, 'public');

        $previous = $product->image_path;
        $product->forceFill(['image_path' => $path])->save();
        if ($previous) {
            Storage::disk('public')->delete($previous);
        }

        return new AdminProductResource($product->load('category'));
    }

    public function adjustStock(StockAdjustmentRequest $request, Product $product, StockService $stock): AdminProductResource
    {
        $updated = $stock->adjust(
            $product,
            (int) $request->validated('delta'),
            $request->validated('reason'),
            $request->validated('note'),
            $request->user(),
        );

        return new AdminProductResource($updated->load('category'));
    }

    public function movements(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $movements = StockMovement::query()
            ->with(['product:id,name,sku', 'admin:id,name'])
            ->when($request->integer('product_id'), fn ($q, $id) => $q->where('product_id', $id))
            ->latest('id')
            ->paginate($request->integer('per_page', 25));

        return response()->json([
            'data' => $movements->getCollection()->map(fn (StockMovement $m) => [
                'id' => $m->id,
                'product' => $m->product ? ['id' => $m->product->id, 'name' => $m->product->name, 'sku' => $m->product->sku] : null,
                'delta' => $m->delta,
                'stock_after' => $m->stock_after,
                'reason' => $m->reason,
                'reference' => $m->reference,
                'note' => $m->note,
                'admin' => $m->admin?->name,
                'created_at' => $m->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $movements->currentPage(),
                'last_page' => $movements->lastPage(),
                'total' => $movements->total(),
            ],
        ]);
    }
}
