<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Patient;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

class UpdatePatientRequest extends PatientWriteRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('patient'));
    }

    protected function isPartial(): bool
    {
        return true;
    }

    protected function currentPatient(): ?Patient
    {
        $patient = $this->route('patient');

        return $patient instanceof Patient ? $patient : null;
    }
}
