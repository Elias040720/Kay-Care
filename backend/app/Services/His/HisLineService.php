<?php

namespace App\Services\His;

use App\Models\Encounter;
use App\Models\HisLine;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Replaces the set of activities CONFIRMED by the professional.
 */
class HisLineService
{
    public function __construct(
        private readonly HisValidationService $validator,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  list<array{catalog_item_id: string, diagnosis_type: ?string, lab: ?string}>  $lines
     * @return Collection<int, HisLine>
     *
     * @throws ValidationException
     */
    public function replace(Encounter $encounter, array $lines, User $user): Collection
    {
        $errors = $this->validator->lineErrors($encounter, $lines);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($encounter, $lines, $user) {
            // Lock the encounter row to serialize concurrent replacements.
            Encounter::query()->whereKey($encounter->getKey())->lockForUpdate()->first();

            $before = $encounter->hisLines()->with('item')->get()
                ->map(fn (HisLine $l) => $this->summary($l->item->code, $l->diagnosis_type, $l->lab))
                ->all();

            $encounter->hisLines()->delete();

            $now = now();
            foreach (array_values($lines) as $position => $line) {
                $model = new HisLine([
                    'catalog_item_id' => $line['catalog_item_id'],
                    'diagnosis_type' => $line['diagnosis_type'] ?? null,
                    'lab' => isset($line['lab']) && $line['lab'] !== '' ? $line['lab'] : null,
                    'position' => $position,
                ]);
                $model->encounter_id = $encounter->id;
                $model->confirmed_by = $user->id;
                $model->confirmed_at = $now;
                $model->save();
            }

            $encounter->forceFill(['updated_by' => $user->id])->touch();

            $fresh = $encounter->hisLines()->with('item.version')->get();

            $this->audit->log(AuditLogger::HIS_LINES_REPLACED, $encounter, [
                'before' => $before,
                'after' => $fresh->map(fn (HisLine $l) => $this->summary($l->item->code, $l->diagnosis_type, $l->lab))->all(),
            ], $user);

            $encounter->setRelation('hisLines', $fresh);

            return $fresh;
        });
    }

    /** @return array{code: string, diagnosis_type: ?string, lab: ?string} */
    private function summary(string $code, ?string $type, ?string $lab): array
    {
        return ['code' => $code, 'diagnosis_type' => $type, 'lab' => $lab];
    }
}
