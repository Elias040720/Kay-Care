<?php

namespace App\Http\Resources;

use App\Models\Encounter;
use App\Services\Cred\AgeCalculationService;
use App\Support\IsoDateTime;
use App\Support\Num;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Encounter */
class EncounterResource extends JsonResource
{
    /**
     * Encounter = {id, patient_id, type, occurred_at, clinical_note, weight_kg,
     * height_cm, hb_g_dl, status}. Extra (documented, additive): age_at_encounter,
     * revision, created_at, updated_at.
     */
    public function toArray(Request $request): array
    {
        $age = $this->relationLoaded('patient')
            ? app(AgeCalculationService::class)->forEncounter($this->resource)->toArray()
            : null;

        return [
            'id' => (string) $this->id,
            'patient_id' => (string) $this->patient_id,
            'type' => $this->type,
            'occurred_at' => IsoDateTime::format($this->occurred_at, $this->occurred_at_offset),
            'clinical_note' => $this->clinical_note,
            'weight_kg' => Num::toFloat($this->weight_kg),
            'height_cm' => Num::toFloat($this->height_cm),
            'hb_g_dl' => Num::toFloat($this->hb_g_dl),
            'status' => $this->status->value,
            'age_at_encounter' => $age,
            'revision' => $this->revision,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
