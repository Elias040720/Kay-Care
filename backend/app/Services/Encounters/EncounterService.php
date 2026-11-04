<?php

namespace App\Services\Encounters;

use App\Enums\EncounterStatus;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\IsoDateTime;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

class EncounterService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @return Collection<int, Encounter> */
    public function historyFor(Patient $patient): Collection
    {
        return $patient->encounters()
            ->with('patient')
            ->orderByDesc('occurred_at')
            ->limit((int) config('kaycare.encounters.history_limit', 200))
            ->get();
    }

    /**
     * Saving NEVER marks an encounter as reviewed: it is always created as draft.
     *
     * @param  array<string, mixed>  $data  validated EncounterWrite
     */
    public function create(array $data, User $user): Encounter
    {
        return DB::transaction(function () use ($data, $user) {
            $encounter = new Encounter(collect($data)->except('occurred_at')->all());
            $this->applyOccurredAt($encounter, $data['occurred_at']);
            $encounter->status = EncounterStatus::Draft;
            $encounter->responsible_user_id = $user->id;
            $encounter->created_by = $user->id;
            $encounter->updated_by = $user->id;
            $encounter->save();

            $this->audit->log(AuditLogger::ENCOUNTER_CREATED, $encounter, [
                'patient_id' => $encounter->patient_id,
                'type' => $encounter->type,
            ], $user);

            return $encounter->load('patient');
        });
    }

    /**
     * Applies a partial update keeping a snapshot of the previous state.
     *
     * @param  array<string, mixed>  $data  validated partial EncounterWrite
     */
    public function update(Encounter $encounter, array $data, User $user): Encounter
    {
        if (! $encounter->isDraft()) {
            throw new LogicException('Reviewed encounters are immutable.');
        }

        return DB::transaction(function () use ($encounter, $data, $user) {
            $encounter->fill(collect($data)->only(Encounter::EDITABLE)->except('occurred_at')->all());

            if (array_key_exists('occurred_at', $data)) {
                $this->applyOccurredAt($encounter, $data['occurred_at']);
            }

            // Decimal columns: ignore representation-only differences (5 vs 5.000).
            foreach (['weight_kg', 'height_cm', 'hb_g_dl'] as $field) {
                if ($encounter->isDirty($field)
                    && $encounter->getOriginal($field) !== null
                    && $encounter->getAttribute($field) !== null
                    && (float) $encounter->getOriginal($field) === (float) $encounter->getAttribute($field)) {
                    $encounter->setAttribute($field, $encounter->getOriginal($field));
                }
            }

            if (! $encounter->isDirty()) {
                return $encounter->load('patient');
            }

            $changed = array_keys($encounter->getDirty());
            $this->audit->snapshot($encounter, $changed, $user);
            $encounter->updated_by = $user->id;
            $encounter->save();

            // Only field names are audited; clinical text stays out of the log.
            $this->audit->log(AuditLogger::ENCOUNTER_UPDATED, $encounter, [
                'changed_fields' => $changed,
                'revision' => $encounter->revision,
            ], $user);

            return $encounter->load('patient');
        });
    }

    /**
     * Explicit professional review. Not exposed in API v1 (no endpoint in the
     * contract); available for a future contract extension and admin tooling.
     */
    public function markReviewed(Encounter $encounter, User $reviewer): Encounter
    {
        return DB::transaction(function () use ($encounter, $reviewer) {
            $this->audit->snapshot($encounter, ['status'], $reviewer);
            $encounter->status = EncounterStatus::Reviewed;
            $encounter->reviewed_by = $reviewer->id;
            $encounter->reviewed_at = now();
            $encounter->updated_by = $reviewer->id;
            $encounter->save();

            $this->audit->log(AuditLogger::ENCOUNTER_REVIEWED, $encounter, [], $reviewer);

            return $encounter;
        });
    }

    private function applyOccurredAt(Encounter $encounter, string $iso): void
    {
        $parsed = IsoDateTime::parse($iso);
        $newUtc = $parsed['utc']->format('Y-m-d H:i:s');

        // Same instant and offset: keep it clean (no spurious revision).
        if ($encounter->exists
            && $encounter->occurred_at?->format('Y-m-d H:i:s') === $newUtc
            && $encounter->occurred_at_offset === $parsed['offset']) {
            return;
        }

        $encounter->occurred_at = $parsed['utc'];
        $encounter->occurred_at_offset = $parsed['offset'];
    }
}
