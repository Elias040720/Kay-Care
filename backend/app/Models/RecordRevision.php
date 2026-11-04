<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Snapshot of a clinical record BEFORE an edit was applied.
 */
class RecordRevision extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'revisionable_type',
        'revisionable_id',
        'revision',
        'snapshot',
        'changed_fields',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'changed_fields' => 'array',
            'revision' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
