<?php

namespace App\Models;

use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Data-driven suggestion rule. All age bounds are inclusive and expressed in
 * completed units (days, months, years); NULL bounds are not evaluated.
 *
 * @property string $id
 * @property string $catalog_item_id
 * @property string $rule_code
 * @property string $description
 * @property int|null $min_age_days
 * @property int|null $max_age_days
 * @property int|null $min_age_months
 * @property int|null $max_age_months
 * @property int|null $min_age_years
 * @property int|null $max_age_years
 * @property string|null $sex
 * @property string|null $condition
 * @property string|null $diagnosis_type_suggested
 * @property string $lab_strategy none|control_number|measurement_hb|fixed
 * @property string|null $lab_fixed
 * @property int|null $max_sequence
 * @property int $priority
 * @property VerificationStatus $verification_status
 * @property string $source
 * @property bool $is_active
 * @property-read HisCatalogItem $item
 */
class HisRule extends Model
{
    use HasUuids;

    public const CONDITION_HAS_ANTHROPOMETRY = 'has_anthropometry';

    public const CONDITION_HAS_HB = 'has_hb';

    public const LAB_NONE = 'none';

    public const LAB_CONTROL_NUMBER = 'control_number';

    public const LAB_MEASUREMENT_HB = 'measurement_hb';

    public const LAB_FIXED = 'fixed';

    protected $fillable = [
        'catalog_version_id',
        'catalog_item_id',
        'rule_code',
        'description',
        'min_age_days',
        'max_age_days',
        'min_age_months',
        'max_age_months',
        'min_age_years',
        'max_age_years',
        'sex',
        'condition',
        'diagnosis_type_suggested',
        'lab_strategy',
        'lab_fixed',
        'max_sequence',
        'priority',
        'verification_status',
        'source',
        'source_reference',
        'valid_from',
        'valid_to',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_age_days' => 'integer',
            'max_age_days' => 'integer',
            'min_age_months' => 'integer',
            'max_age_months' => 'integer',
            'min_age_years' => 'integer',
            'max_age_years' => 'integer',
            'max_sequence' => 'integer',
            'priority' => 'integer',
            'valid_from' => 'date:Y-m-d',
            'valid_to' => 'date:Y-m-d',
            'is_active' => 'boolean',
            'verification_status' => VerificationStatus::class,
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(HisCatalogItem::class, 'catalog_item_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(HisCatalogVersion::class, 'catalog_version_id');
    }
}
