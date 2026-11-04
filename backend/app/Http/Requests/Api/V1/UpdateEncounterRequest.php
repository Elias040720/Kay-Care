<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Encounter;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

class UpdateEncounterRequest extends EncounterWriteRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('encounter'));
    }

    protected function isPartial(): bool
    {
        return true;
    }

    protected function currentEncounter(): ?Encounter
    {
        $encounter = $this->route('encounter');

        return $encounter instanceof Encounter ? $encounter->loadMissing('patient') : null;
    }
}
