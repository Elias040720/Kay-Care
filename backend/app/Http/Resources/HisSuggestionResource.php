<?php

namespace App\Http\Resources;

use App\Services\His\HisSuggestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property HisSuggestion $resource */
class HisSuggestionResource extends JsonResource
{
    /**
     * HisSuggestion = {catalog_item_id, label, code, diagnosis_type_suggested,
     * lab_suggested, verification_status, source}. Extra (documented, additive):
     * rationale, rule_code, catalog_version. There is intentionally NO
     * "selected"/"performed" flag: suggestions are never pre-selected.
     */
    public function toArray(Request $request): array
    {
        $s = $this->resource;

        return [
            'catalog_item_id' => $s->catalogItemId,
            'label' => $s->label,
            'code' => $s->code,
            'diagnosis_type_suggested' => $s->diagnosisTypeSuggested,
            'lab_suggested' => $s->labSuggested,
            'verification_status' => $s->verificationStatus,
            'source' => $s->source,
            'rationale' => $s->rationale,
            'rule_code' => $s->ruleCode,
            'catalog_version' => $s->catalogVersion,
        ];
    }
}
