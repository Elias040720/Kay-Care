<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * MVP scope: a single health facility. Every ACTIVE nurse or admin of the
 * facility may read and maintain patient demographics. Inactive accounts
 * and unknown roles are denied. Clinical records are never deleted.
 */
class PatientPolicy
{
    public function viewAny(User $user): Response
    {
        return $this->staff($user);
    }

    public function view(User $user, Patient $patient): Response
    {
        return $this->staff($user);
    }

    public function create(User $user): Response
    {
        return $this->staff($user);
    }

    public function update(User $user, Patient $patient): Response
    {
        return $this->staff($user);
    }

    public function delete(User $user, Patient $patient): Response
    {
        return Response::deny('La información clínica no se elimina en Kay Care.');
    }

    private function staff(User $user): Response
    {
        return $user->isClinicalStaff()
            ? Response::allow()
            : Response::deny('No tiene permisos para acceder a expedientes.');
    }
}
