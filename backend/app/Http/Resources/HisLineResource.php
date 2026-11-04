<?php

namespace App\Http\Resources;

use App\Models\HisLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HisLine */
class HisLineResource extends JsonResource
{
    /** HisLine = {id, catalog_item_id, label, code, diagnosis_type, lab, verification_status} */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'catalog_item_id' => (string) $this->catalog_item_id,
            'label' => $this->item->label,
            'code' => $this->item->code,
            'diagnosis_type' => $this->diagnosis_type,
            'lab' => $this->lab,
            'verification_status' => $this->item->effectiveVerification()->value,
        ];
    }
}
