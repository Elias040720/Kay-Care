<?php

use Illuminate\Support\Facades\Route;

// El backend solo expone la API; la interfaz vive en el frontend Vue.
Route::get('/', fn () => response()->json([
    'service' => 'kay-care-farmacia-api',
    'notice' => 'PROYECTO ACADÉMICO — TIENDA DEMOSTRATIVA — NO SE REALIZAN VENTAS REALES',
    'api' => '/api/v1/store',
]));
