<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /** Public liveness probe. Exposes no configuration or data. */
    public function __invoke(): JsonResponse
    {
        return response()->json(['status' => 'ok', 'version' => 'v1']);
    }
}
