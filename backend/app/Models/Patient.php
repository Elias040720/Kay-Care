<?php

namespace App\Models;

use App\Enums\Sex;
use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $chart_number
 * @property string|null $document_type
 * @property string|null $document_number
 * @property string $first_name
 * @property string $last_name
 * @property Carbon $birth_date
 * @property Sex $sex
 * @property string|null $locality
 * @property int $revision
 */
class Patient extends Model
{
    /** @use HasFactory<PatientFactory> */
    use HasFactory, HasUuids;

    /** Fields writable through the API (PatientWrite). */
    public const WRITABLE = [
        'chart_number',
        'first_name',
        'last_name',
        'birth_date',
        'sex',
        'document_type',
        'document_number',
        'locality',
    ];

    /** @var list<string> */
    protected $fillable = self::WRITABLE;

    protected $attributes = [
        'revision' => 1,
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date:Y-m-d',
            'sex' => Sex::class,
            'revision' => 'integer',
        ];
    }

    public function encounters(): HasMany
    {
        return $this->hasMany(Encounter::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Bounded search by chart number, document number or names.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.addcslashes(mb_strtolower($term), '\\%_').'%';
        $prefix = addcslashes(mb_strtoupper($term), '\\%_').'%';

        return $query->where(function (Builder $q) use ($like, $prefix, $term) {
            $q->where('chart_number', 'like', $prefix)
                ->orWhere('document_number', 'like', $prefix)
                ->orWhereRaw('LOWER(first_name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(last_name) LIKE ?', [$like]);

            // "Nombre Apellido" style searches.
            $parts = preg_split('/\s+/', $term) ?: [];
            if (count($parts) > 1) {
                $q->orWhere(function (Builder $q) use ($parts) {
                    foreach ($parts as $part) {
                        $p = '%'.addcslashes(mb_strtolower($part), '\\%_').'%';
                        $q->where(fn (Builder $w) => $w
                            ->whereRaw('LOWER(first_name) LIKE ?', [$p])
                            ->orWhereRaw('LOWER(last_name) LIKE ?', [$p]));
                    }
                });
            }
        });
    }
}
