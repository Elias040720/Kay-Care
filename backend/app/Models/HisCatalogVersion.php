<?php

namespace App\Models;

use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A versioned HIS catalog with provenance (source) and validity window.
 *
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string $source
 * @property VerificationStatus $verification_status
 */
class HisCatalogVersion extends Model
{
    use HasUuids;

    protected $fillable = [
        'code',
        'name',
        'source',
        'source_reference',
        'verification_status',
        'valid_from',
        'valid_to',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date:Y-m-d',
            'valid_to' => 'date:Y-m-d',
            'reviewed_at' => 'datetime',
            'is_active' => 'boolean',
            'verification_status' => VerificationStatus::class,
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(HisCatalogItem::class, 'catalog_version_id');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(HisRule::class, 'catalog_version_id');
    }

    /** Versions in force on a given local date (Y-m-d). */
    public function scopeEffectiveOn(Builder $query, string $date): Builder
    {
        return $query->where('is_active', true)
            ->whereDate('valid_from', '<=', $date)
            ->where(fn (Builder $q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date));
    }
}
