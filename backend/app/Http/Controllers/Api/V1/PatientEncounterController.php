<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EncounterResource;
use App\Models\Patient;
use App\Services\Encounters\EncounterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PatientEncounterController extends Controller
{
    public function __construct(private readonly EncounterService $encounters) {}

    /** Encounter history, most recent first. */
    public function index(Patient $patient): JsonResponse
    {
        Gate::authorize('view', $patient);

        return response()->json([
            'data' => EncounterResource::collection($this->encounters->historyFor($patient)),
        ]);
    }
}
