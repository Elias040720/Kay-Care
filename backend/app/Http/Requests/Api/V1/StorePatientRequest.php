<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Patient;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

class StorePatientRequest extends PatientWriteRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('create', Patient::class);
    }

    protected function isPartial(): bool
    {
        return false;
    }

    protected function currentPatient(): ?Patient
    {
        return null;
    }
}
