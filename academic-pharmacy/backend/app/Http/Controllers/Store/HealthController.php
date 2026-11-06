<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sonda de salud usada por Docker (HEALTHCHECK) y por las probes de
 * Azure Container Apps. No expone versiones ni detalles internos.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('SELECT 1');
            $database = 'ok';
        } catch (Throwable) {
            $database = 'unavailable';
        }

        $healthy = $database === 'ok';

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'service' => 'kay-care-farmacia-api',
            'database' => $database,
            'time' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }
}
