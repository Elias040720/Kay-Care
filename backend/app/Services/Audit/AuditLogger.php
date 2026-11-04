<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\RecordRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes the audit trail (events) and revision snapshots (previous state).
 *
 * Event metadata MUST NOT contain clinical free text (clinical notes). Only
 * field names, identifiers and codes are recorded. Full previous values live
 * in record_revisions, which is database-only and never written to logs.
 */
class AuditLogger
{
    /** Event names. */
    public const LOGIN = 'auth.login';

    public const LOGIN_FAILED = 'auth.login_failed';

    public const LOGOUT = 'auth.logout';

    public const PATIENT_CREATED = 'patient.created';

    public const PATIENT_UPDATED = 'patient.updated';

    public const ENCOUNTER_CREATED = 'encounter.created';

    public const ENCOUNTER_UPDATED = 'encounter.updated';

    public const ENCOUNTER_REVIEWED = 'encounter.reviewed';

    public const HIS_LINES_REPLACED = 'encounter.his_lines_replaced';

    public const DOCUMENT_GENERATED = 'document.generated';

    /** Fields whose values must never appear in audit metadata. */
    private const REDACTED_FIELDS = ['clinical_note', 'password', 'remember_token'];

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(string $event, ?Model $subject = null, array $metadata = [], ?User $user = null): AuditLog
    {
        $request = app()->bound('request') ? request() : null;

        return AuditLog::query()->create([
            'user_id' => $user?->getKey() ?? $request?->user()?->getKey(),
            'event' => $event,
            'auditable_type' => $subject ? class_basename($subject) : null,
            'auditable_id' => $subject?->getKey(),
            'metadata' => $this->redact($metadata),
            'ip_address' => $request?->ip(),
        ]);
    }

    /**
     * Stores the state BEFORE the pending changes and bumps the revision.
     * Must be called inside the same transaction that saves the model.
     *
     * @param  list<string>  $changedFields
     */
    public function snapshot(Model $model, array $changedFields, User $user): RecordRevision
    {
        $original = $model->getRawOriginal();

        $revision = RecordRevision::query()->create([
            'revisionable_type' => class_basename($model),
            'revisionable_id' => $model->getKey(),
            'revision' => (int) ($original['revision'] ?? 1),
            'snapshot' => $original,
            'changed_fields' => array_values($changedFields),
            'user_id' => $user->getKey(),
        ]);

        $model->setAttribute('revision', (int) ($original['revision'] ?? 1) + 1);

        return $revision;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function redact(array $metadata): array
    {
        foreach ($metadata as $key => $value) {
            if (in_array($key, self::REDACTED_FIELDS, true)) {
                $metadata[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $metadata[$key] = $this->redact($value);
            }
        }

        return $metadata;
    }
}
