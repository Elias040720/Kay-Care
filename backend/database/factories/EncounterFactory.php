<?php

namespace Database\Factories;

use App\Enums\EncounterStatus;
use App\Models\Encounter;
use App\Models\Patient;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test-only factory.
 *
 * @extends Factory<Encounter>
 */
class EncounterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'type' => 'CRED',
            'occurred_at' => CarbonImmutable::now()->subHour()->utc(),
            'occurred_at_offset' => '-05:00',
            'clinical_note' => 'Nota de prueba ficticia.',
            'weight_kg' => null,
            'height_cm' => null,
            'hb_g_dl' => null,
            'status' => EncounterStatus::Draft,
            'responsible_user_id' => fn (array $a) => Patient::query()->find($a['patient_id'])?->created_by,
            'created_by' => fn (array $a) => $a['responsible_user_id'],
            'updated_by' => fn (array $a) => $a['responsible_user_id'],
        ];
    }

    /** Local wall-clock time in Lima (UTC-05:00), e.g. "2026-03-10 09:00". */
    public function occurredAtLocal(string $localDateTime, string $offset = '-05:00'): static
    {
        return $this->state(fn () => [
            'occurred_at' => CarbonImmutable::parse($localDateTime, $offset)->utc(),
            'occurred_at_offset' => $offset,
        ]);
    }

    public function reviewed(): static
    {
        return $this->state(fn () => ['status' => EncounterStatus::Reviewed]);
    }
}
