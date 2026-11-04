<?php

use Illuminate\Support\Facades\Route;

// Kay Care backend serves no HTML UI: the Vue SPA lives in frontend/.
Route::get('/', fn () => response()->json([
    'name' => 'Kay Care API',
    'api' => url('/api/v1'),
    'health' => url('/api/v1/health'),
]));
