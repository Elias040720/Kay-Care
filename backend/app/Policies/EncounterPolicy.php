<?php

namespace App\Policies;

use App\Models\Encounter;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * - Read: active clinical staff of the facility.
 * - Modify (fields and HIS lines): only the responsible professional or an
 *   admin, and only while the encounter is a draft. Reviewed encounters are
 *   immutable.
 */
class EncounterPolicy
{
    public function view(User $user, Encounter $encounter): Response
    {
        return $user->isClinicalStaff()
            ? Response::allow()
            : Response::deny('No tiene permisos para acceder a esta atención.');
    }

    public function create(User $user): Response
    {
        return $user->isClinicalStaff()
            ? Response::allow()
            : Response::deny('No tiene permisos para registrar atenciones.');
    }

    public function update(User $user, Encounter $encounter): Response
    {
        if (! $user->isClinicalStaff()) {
            return Response::deny('No tiene permisos para modificar atenciones.');
        }

        if (! $encounter->isDraft()) {
            return Response::deny('La atención ya fue revisada y no puede modificarse.');
        }

        return $user->isAdmin() || $encounter->responsible_user_id === $user->id
            ? Response::allow()
            : Response::deny('Solo el profesional responsable o un administrador puede modificar esta atención.');
    }

    public function manageHisLines(User $user, Encounter $encounter): Response
    {
        return $this->update($user, $encounter);
    }

    public function review(User $user, Encounter $encounter): Response
    {
        return $user->isClinicalStaff() && $encounter->isDraft()
            ? Response::allow()
            : Response::deny('No es posible revisar esta atención.');
    }

    public function downloadDocuments(User $user, Encounter $encounter): Response
    {
        return $this->view($user, $encounter);
    }

    public function delete(User $user, Encounter $encounter): Response
    {
        return Response::deny('La información clínica no se elimina en Kay Care.');
    }
}
