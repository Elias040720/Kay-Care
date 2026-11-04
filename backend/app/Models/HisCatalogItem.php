<?php

namespace App\Models;

use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $catalog_version_id
 * @property string $code
 * @property string $label
 * @property string $category
 * @property list<string> $allowed_diagnosis_types
 * @property bool $diagnosis_type_required
 * @property string $lab_mode none|control_number|numeric|enum|free
 * @property array{min?: int|float, max?: int|float, values?: list<string>}|null $lab_options
 * @property VerificationStatus $verification_status
 * @property string $source
 * @property bool $is_active
 * @property-read HisCatalogVersion $version
 */
class HisCatalogItem extends Model
{
    use HasUuids;

    public const LAB_NONE = 'none';

    public const LAB_CONTROL_NUMBER = 'control_number';

    public const LAB_NUMERIC = 'numeric';

    public const LAB_ENUM = 'enum';

    public const LAB_FREE = 'free';

    protected $fillable = [
        'catalog_version_id',
        'code',
        'label',
        'category',
        'allowed_diagnosis_types',
        'diagnosis_type_required',
        'lab_mode',
        'lab_options',
        'verification_status',
        'source',
        'source_reference',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'allowed_diagnosis_types' => 'array',
            'diagnosis_type_required' => 'boolean',
            'lab_options' => 'array',
            'is_active' => 'boolean',
            'verification_status' => VerificationStatus::class,
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(HisCatalogVersion::class, 'catalog_version_id');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(HisRule::class, 'catalog_item_id');
    }

    /** Verified only if both the item and its catalog version are verified. */
    public function effectiveVerification(): VerificationStatus
    {
        return $this->verification_status === VerificationStatus::Verified
            && $this->version?->verification_status === VerificationStatus::Verified
            ? VerificationStatus::Verified
            : VerificationStatus::Draft;
    }
}
