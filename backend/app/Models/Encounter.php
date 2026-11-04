<?php

namespace App\Models;

use App\Enums\EncounterStatus;
use Carbon\CarbonImmutable;
use Database\Factories\EncounterFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $patient_id
 * @property string $type
 * @property CarbonImmutable $occurred_at UTC
 * @property string $occurred_at_offset e.g. "-05:00"
 * @property string|null $clinical_note
 * @property string|null $weight_kg
 * @property string|null $height_cm
 * @property string|null $hb_g_dl
 * @property EncounterStatus $status
 * @property string $responsible_user_id
 * @property int $revision
 * @property-read Patient $patient
 */
class Encounter extends Model
{
    /** @use HasFactory<EncounterFactory> */
    use HasFactory, HasUuids;

    /** Clinical fields editable through PATCH (patient_id is immutable). */
    public const EDITABLE = [
        'type',
        'occurred_at',
        'clinical_note',
        'weight_kg',
        'height_cm',
        'hb_g_dl',
    ];

    /**
     * status, responsible_user_id and audit columns are never mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'patient_id',
        'type',
        'clinical_note',
        'weight_kg',
        'height_cm',
        'hb_g_dl',
    ];

    protected $attributes = [
        'status' => 'draft',
        'type' => 'CRED',
        'revision' => 1,
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'status' => EncounterStatus::class,
            'revision' => 'integer',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function hisLines(): HasMany
    {
        return $this->hasMany(HisLine::class)->orderBy('position');
    }

    /** Wall-clock time of the encounter in its original UTC offset. */
    public function localOccurredAt(): CarbonImmutable
    {
        return $this->occurred_at->setTimezone($this->occurred_at_offset);
    }

    public function isDraft(): bool
    {
        return $this->status === EncounterStatus::Draft;
    }
}
