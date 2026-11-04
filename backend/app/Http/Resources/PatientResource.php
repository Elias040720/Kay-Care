<?php

namespace App\Http\Resources;

use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Patient */
class PatientResource extends JsonResource
{
    /**
     * Patient = {id, chart_number, first_name, last_name, birth_date, sex,
     * document_type, document_number, locality}. Extra (documented, additive):
     * created_at, updated_at.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'chart_number' => $this->chart_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'birth_date' => $this->birth_date->format('Y-m-d'),
            'sex' => $this->sex->value,
            'document_type' => $this->document_type,
            'document_number' => $this->document_number,
            'locality' => $this->locality,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
