<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Store\CatalogController;
use App\Http\Controllers\Store\CheckoutController;
use App\Http\Controllers\Store\HealthController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Kay Care Farmacia — API académica /api/v1/store
|--------------------------------------------------------------------------
| Independiente del sistema clínico: base de datos, sesiones y credenciales
| propias. Ninguna ruta acepta datos personales reales ni pagos.
*/

Route::prefix('v1/store')->group(function () {
    Route::get('health', HealthController::class)->name('store.health');

    Route::middleware('throttle:store-api')->group(function () {
        Route::get('categories', [CatalogController::class, 'categories']);
        Route::get('products', [CatalogController::class, 'index']);
        Route::get('products/{slug}', [CatalogController::class, 'show'])->where('slug', '[a-z0-9-]+');
        Route::get('products/{slug}/related', [CatalogController::class, 'related'])->where('slug', '[a-z0-9-]+');
        Route::post('cart/validate', [CheckoutController::class, 'validateCart']);
        Route::get('orders/{number}', [CheckoutController::class, 'showOrder'])->where('number', 'KCF-[0-9]{4}-[0-9]{6}');
    });

    Route::post('checkout', [CheckoutController::class, 'store'])->middleware('throttle:checkout');

    Route::prefix('admin')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:admin-login');

        Route::middleware(['auth:sanctum', EnsureAdmin::class, 'throttle:admin-api'])->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('dashboard', DashboardController::class);

            Route::get('products', [ProductController::class, 'index']);
            Route::post('products', [ProductController::class, 'store']);
            Route::get('products/{product}', [ProductController::class, 'show'])->whereNumber('product');
            Route::put('products/{product}', [ProductController::class, 'update'])->whereNumber('product');
            Route::delete('products/{product}', [ProductController::class, 'destroy'])->whereNumber('product');
            Route::post('products/{product}/image', [ProductController::class, 'uploadImage'])->whereNumber('product');
            Route::post('products/{product}/stock', [ProductController::class, 'adjustStock'])->whereNumber('product');
            Route::get('stock-movements', [ProductController::class, 'movements']);

            Route::get('orders', [OrderController::class, 'index']);
            Route::get('orders/{order}', [OrderController::class, 'show'])->whereNumber('order');
            Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->whereNumber('order');
        });
    });
});
