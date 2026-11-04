<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An activity the nurse explicitly CONFIRMED as performed in an encounter.
 * Lines are never created automatically from suggestions.
 *
 * @property string $id
 * @property string $encounter_id
 * @property string $catalog_item_id
 * @property string|null $diagnosis_type
 * @property string|null $lab
 * @property int $position
 * @property-read HisCatalogItem $item
 */
class HisLine extends Model
{
    use HasUuids;

    protected $fillable = [
        'catalog_item_id',
        'diagnosis_type',
        'lab',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'confirmed_at' => 'immutable_datetime',
        ];
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(HisCatalogItem::class, 'catalog_item_id');
    }
}
