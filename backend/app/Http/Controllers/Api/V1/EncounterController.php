<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEncounterRequest;
use App\Http\Requests\Api\V1\UpdateEncounterRequest;
use App\Http\Resources\EncounterResource;
use App\Models\Encounter;
use App\Services\Encounters\EncounterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class EncounterController extends Controller
{
    public function __construct(private readonly EncounterService $encounters) {}

    public function store(StoreEncounterRequest $request): JsonResponse
    {
        $encounter = $this->encounters->create($request->encounterData(), $request->user());

        return response()->json(['data' => new EncounterResource($encounter)], 201);
    }

    public function show(Encounter $encounter): JsonResponse
    {
        Gate::authorize('view', $encounter);

        return response()->json(['data' => new EncounterResource($encounter->load('patient'))]);
    }

    public function update(UpdateEncounterRequest $request, Encounter $encounter): JsonResponse
    {
        $encounter = $this->encounters->update($encounter, $request->encounterData(), $request->user());

        return response()->json(['data' => new EncounterResource($encounter)]);
    }
}
