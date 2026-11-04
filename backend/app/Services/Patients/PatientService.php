<?php

namespace App\Services\Patients;

use App\Models\Encounter;
use App\Models\Patient;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PatientService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function search(?string $term, int $page): LengthAwarePaginator
    {
        return Patient::query()
            ->search($term)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate(perPage: (int) config('kaycare.patients.per_page', 15), page: $page);
    }

    /**
     * @param  array<string, mixed>  $data  validated PatientWrite
     */
    public function create(array $data, User $user): Patient
    {
        return DB::transaction(function () use ($data, $user) {
            $patient = new Patient($data);
            $patient->created_by = $user->id;
            $patient->updated_by = $user->id;
            $patient->save();

            $this->audit->log(AuditLogger::PATIENT_CREATED, $patient, [
                'chart_number' => $patient->chart_number,
            ], $user);

            return $patient;
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated partial PatientWrite
     */
    public function update(Patient $patient, array $data, User $user): Patient
    {
        return DB::transaction(function () use ($patient, $data, $user) {
            $patient->fill($data);

            if (! $patient->isDirty()) {
                return $patient;
            }

            if ($patient->isDirty('birth_date')) {
                $this->assertBirthDateBeforeEncounters($patient);
            }

            $changed = array_keys($patient->getDirty());
            $this->audit->snapshot($patient, $changed, $user);
            $patient->updated_by = $user->id;
            $patient->save();

            $this->audit->log(AuditLogger::PATIENT_UPDATED, $patient, [
                'changed_fields' => $changed,
                'revision' => $patient->revision,
            ], $user);

            return $patient;
        });
    }

    /**
     * Referential/clinical integrity: the birth date cannot be moved after an
     * already recorded encounter.
     */
    private function assertBirthDateBeforeEncounters(Patient $patient): void
    {
        $birth = $patient->birth_date->toDateString();

        $conflict = $patient->encounters()->get()
            ->contains(fn (Encounter $e) => $e->localOccurredAt()->toDateString() < $birth);

        if ($conflict) {
            throw ValidationException::withMessages([
                'birth_date' => ['La fecha de nacimiento no puede ser posterior a atenciones ya registradas.'],
            ]);
        }
    }
}
