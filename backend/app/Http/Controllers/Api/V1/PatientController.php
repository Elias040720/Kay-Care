<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PatientIndexRequest;
use App\Http\Requests\Api\V1\StorePatientRequest;
use App\Http\Requests\Api\V1\UpdatePatientRequest;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use App\Services\Patients\PatientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PatientController extends Controller
{
    public function __construct(private readonly PatientService $patients) {}

    public function index(PatientIndexRequest $request): JsonResponse
    {
        Gate::authorize('viewAny', Patient::class);

        $page = $this->patients->search($request->term(), $request->page());

        return response()->json([
            'data' => PatientResource::collection($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(StorePatientRequest $request): JsonResponse
    {
        $patient = $this->patients->create($request->patientData(), $request->user());

        return response()->json(['data' => new PatientResource($patient)], 201);
    }

    public function show(Patient $patient): JsonResponse
    {
        Gate::authorize('view', $patient);

        return response()->json(['data' => new PatientResource($patient)]);
    }

    public function update(UpdatePatientRequest $request, Patient $patient): JsonResponse
    {
        $patient = $this->patients->update($patient, $request->patientData(), $request->user());

        return response()->json(['data' => new PatientResource($patient)]);
    }
}
