<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\ProductIndexRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\ProductSearch;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CatalogController extends Controller
{
    public function categories(): AnonymousResourceCollection
    {
        $categories = Category::query()
            ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories);
    }

    public function index(ProductIndexRequest $request, ProductSearch $search): AnonymousResourceCollection
    {
        $filters = collect($request->validated())->except(['low_stock', 'active'])->all();

        return ProductResource::collection($search->paginate($filters));
    }

    public function show(string $slug): ProductResource
    {
        $product = Product::query()
            ->active()
            ->with('category')
            ->where('slug', $slug)
            ->firstOrFail();

        return new ProductResource($product);
    }

    public function related(string $slug): AnonymousResourceCollection
    {
        $product = Product::query()->active()->where('slug', $slug)->firstOrFail();

        $related = Product::query()
            ->active()
            ->with('category')
            ->where('category_id', $product->category_id)
            ->whereKeyNot($product->id)
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->limit(4)
            ->get();

        return ProductResource::collection($related);
    }
}
