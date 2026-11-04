<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Encounter;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

class StoreEncounterRequest extends EncounterWriteRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('create', Encounter::class);
    }

    protected function isPartial(): bool
    {
        return false;
    }

    protected function currentEncounter(): ?Encounter
    {
        return null;
    }
}
