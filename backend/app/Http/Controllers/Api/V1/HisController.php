<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReplaceHisLinesRequest;
use App\Http\Resources\EncounterResource;
use App\Http\Resources\HisLineResource;
use App\Http\Resources\HisSuggestionResource;
use App\Http\Resources\PatientResource;
use App\Models\Encounter;
use App\Services\His\HisLineService;
use App\Services\His\HisSuggestionService;
use App\Services\His\HisValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class HisController extends Controller
{
    public function suggestions(Encounter $encounter, HisSuggestionService $service): JsonResponse
    {
        Gate::authorize('view', $encounter);

        return response()->json([
            'data' => HisSuggestionResource::collection($service->suggestFor($encounter)),
        ]);
    }

    public function replaceLines(ReplaceHisLinesRequest $request, Encounter $encounter, HisLineService $service): JsonResponse
    {
        $lines = $service->replace($encounter, $request->lines(), $request->user());

        return response()->json(['data' => HisLineResource::collection($lines)]);
    }

    public function preview(Encounter $encounter, HisValidationService $validator): JsonResponse
    {
        Gate::authorize('view', $encounter);

        $encounter->load(['patient', 'hisLines.item.version', 'hisLines.item.rules']);
        $result = $validator->evaluate($encounter);

        return response()->json([
            'data' => [
                'patient' => new PatientResource($encounter->patient),
                'encounter' => new EncounterResource($encounter),
                'lines' => HisLineResource::collection($encounter->hisLines),
                'warnings' => $result->messages(),
                'validated' => $result->isValid(),
                // Additive, documented: "validated" is structural, never official.
                'validation_scope' => 'structural',
            ],
        ]);
    }
}
