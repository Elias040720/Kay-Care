<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Encounter;
use App\Models\RecordRevision;
use App\Models\User;
use App\Services\Encounters\EncounterService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EncounterTest extends TestCase
{
    use RefreshDatabase;

    private const CONTRACT_KEYS = [
        'id', 'patient_id', 'type', 'occurred_at', 'clinical_note',
        'weight_kg', 'height_cm', 'hb_g_dl', 'status',
    ];

    private const EXTRA_KEYS = ['age_at_encounter', 'revision', 'created_at', 'updated_at'];

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_creates_a_draft_encounter_with_contract_shape(): void
    {
        $user = $this->actingAsNurse();
        $patient = $this->patientBornOn('2026-10-25', $user);

        $response = $this->spa()->postJson('/api/v1/encounters', [
            'patient_id' => $patient->id,
            'type' => 'CRED',
            'occurred_at' => '2026-11-03T21:30:00-05:00',
            'clinical_note' => 'Nota ficticia de prueba.',
            'weight_kg' => 3.85,
            'height_cm' => 51.5,
            'hb_g_dl' => null,
        ]);

        $response->assertCreated();
        $data = $response->json('data');
        $this->assertExactKeys(array_merge(self::CONTRACT_KEYS, self::EXTRA_KEYS), $data);
        $this->assertSame('draft', $data['status']);
        $this->assertSame(1, $data['revision']);
        $this->assertSame('CRED', $data['type']);
        // Original offset preserved; stored as UTC.
        $this->assertSame('2026-11-03T21:30:00-05:00', $data['occurred_at']);
        $this->assertSame(3.85, $data['weight_kg']);
        $this->assertSame(51.5, $data['height_cm']);
        $this->assertNull($data['hb_g_dl']);
        // Age computed at the LOCAL encounter date (Nov 3), not today / not UTC date.
        $this->assertSame(9, $data['age_at_encounter']['days']);
        $this->assertSame('neonatal', $data['age_at_encounter']['group']);

        $encounter = Encounter::query()->findOrFail($data['id']);
        $this->assertSame('2026-11-04 02:30:00', $encounter->getRawOriginal('occurred_at'));
        $this->assertSame($user->id, $encounter->responsible_user_id);
        $this->assertDatabaseHas('audit_logs', ['event' => 'encounter.created', 'auditable_id' => $encounter->id]);
    }

    public function test_status_cannot_be_set_when_saving(): void
    {
        $user = $this->actingAsNurse();
        $patient = $this->patientBornOn('2026-06-01', $user);

        $this->spa()->postJson('/api/v1/encounters', [
            'patient_id' => $patient->id,
            'type' => 'CRED',
            'occurred_at' => '2026-11-01T09:00:00-05:00',
            'status' => 'reviewed',
        ])->assertStatus(422)->assertJsonValidationErrors('status');

        $this->assertDatabaseCount('encounters', 0);
    }

    public function test_validates_dates_ranges_and_types(): void
    {
        $user = $this->actingAsNurse();
        $patient = $this->patientBornOn('2026-06-01', $user);
        $base = ['patient_id' => $patient->id, 'type' => 'CRED'];

        $cases = [
            'no timezone' => [['occurred_at' => '2026-11-01T09:00:00'], 'occurred_at'],
            'future' => [['occurred_at' => now()->addDay()->toIso8601String()], 'occurred_at'],
            'before birth' => [['occurred_at' => '2026-05-31T09:00:00-05:00'], 'occurred_at'],
            'weight too high' => [['occurred_at' => '2026-11-01T09:00:00-05:00', 'weight_kg' => 400], 'weight_kg'],
            'weight not numeric' => [['occurred_at' => '2026-11-01T09:00:00-05:00', 'weight_kg' => 'diez'], 'weight_kg'],
            'height too low' => [['occurred_at' => '2026-11-01T09:00:00-05:00', 'height_cm' => 5], 'height_cm'],
            'hb out of range' => [['occurred_at' => '2026-11-01T09:00:00-05:00', 'hb_g_dl' => 40], 'hb_g_dl'],
            'hb too precise' => [['occurred_at' => '2026-11-01T09:00:00-05:00', 'hb_g_dl' => 11.25], 'hb_g_dl'],
            'type not CRED' => [['occurred_at' => '2026-11-01T09:00:00-05:00', 'type' => 'OTRO'], 'type'],
            'unknown patient' => [['occurred_at' => '2026-11-01T09:00:00-05:00', 'patient_id' => '9b2f4c1e-8d3a-4f6b-9c2d-1e5f7a8b9c0d'], 'patient_id'],
            'invalid patient id' => [['occurred_at' => '2026-11-01T09:00:00-05:00', 'patient_id' => 'abc'], 'patient_id'],
        ];

        foreach ($cases as $name => [$payload, $field]) {
            $this->spa()->postJson('/api/v1/encounters', array_merge($base, $payload))
                ->assertStatus(422)
                ->assertJsonValidationErrors([$field]);
        }

        $this->assertDatabaseCount('encounters', 0);
    }

    public function test_rejects_cred_for_patients_older_than_eleven_at_encounter_date(): void
    {
        $user = $this->actingAsNurse();
        $patient = $this->patientBornOn('2014-01-01', $user);

        $this->spa()->postJson('/api/v1/encounters', [
            'patient_id' => $patient->id, 'type' => 'CRED', 'occurred_at' => '2026-01-02T09:00:00-05:00',
        ])->assertStatus(422)->assertJsonValidationErrors('occurred_at');

        // A HISTORICAL encounter when the patient was still 11 is accepted.
        $this->spa()->postJson('/api/v1/encounters', [
            'patient_id' => $patient->id, 'type' => 'CRED', 'occurred_at' => '2025-12-31T09:00:00-05:00',
        ])->assertCreated()->assertJsonPath('data.age_at_encounter.years', 11);
    }

    public function test_patient_history_is_ordered_most_recent_first(): void
    {
        $user = $this->actingAsNurse();
        $patient = $this->patientBornOn('2025-01-01', $user);
        $other = $this->patientBornOn('2025-01-01', $user);
        $old = $this->encounterAt($patient, '2025-03-01 09:00', $user);
        $new = $this->encounterAt($patient, '2025-09-01 09:00', $user);
        $this->encounterAt($other, '2025-05-01 09:00', $user);

        $response = $this->spa()->getJson("/api/v1/patients/{$patient->id}/encounters")->assertOk();

        $this->assertExactKeys(['data'], $response->json());
        $this->assertSame([$new->id, $old->id], array_column($response->json('data'), 'id'));
        // Each encounter keeps the age it had on its own date.
        $this->assertSame(8, $response->json('data.0.age_at_encounter.months'));
        $this->assertSame(2, $response->json('data.1.age_at_encounter.months'));
    }

    public function test_show_returns_encounter(): void
    {
        $user = $this->actingAsNurse();
        $encounter = $this->encounterAt($this->patientBornOn('2025-01-01', $user), '2025-03-01 09:00', $user);

        $this->spa()->getJson("/api/v1/encounters/{$encounter->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $encounter->id)
            ->assertJsonPath('data.occurred_at', '2025-03-01T09:00:00-05:00');

        $this->spa()->getJson('/api/v1/encounters/9b2f4c1e-8d3a-4f6b-9c2d-1e5f7a8b9c0d')->assertStatus(404);
    }

    public function test_responsible_professional_can_update_with_traceability(): void
    {
        $user = $this->actingAsNurse();
        $encounter = $this->encounterAt($this->patientBornOn('2025-01-01', $user), '2025-03-01 09:00', $user, [
            'clinical_note' => 'Texto clínico original ficticio',
            'weight_kg' => '5.200',
        ]);

        $this->spa()->patchJson("/api/v1/encounters/{$encounter->id}", [
            'clinical_note' => 'Texto clínico corregido ficticio',
            'weight_kg' => 5.2, // same value: not a change
        ])
            ->assertOk()
            ->assertJsonPath('data.clinical_note', 'Texto clínico corregido ficticio')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.revision', 2);

        $revision = RecordRevision::query()->where('revisionable_id', $encounter->id)->firstOrFail();
        $this->assertSame(['clinical_note'], $revision->changed_fields);
        $this->assertSame('Texto clínico original ficticio', $revision->snapshot['clinical_note']);
        $this->assertSame($user->id, $revision->user_id);

        // The audit log records WHO/WHEN/WHICH fields, never the clinical text.
        $log = AuditLog::query()->where('event', 'encounter.updated')->firstOrFail();
        $this->assertSame(['clinical_note'], $log->metadata['changed_fields']);
        $this->assertStringNotContainsString('ficticio', json_encode($log->metadata));
    }

    public function test_other_nurse_cannot_modify_the_encounter(): void
    {
        $author = $this->nurse();
        $encounter = $this->encounterAt($this->patientBornOn('2025-01-01', $author), '2025-03-01 09:00', $author);
        $this->actingAsNurse(); // a different nurse

        $this->spa()->patchJson("/api/v1/encounters/{$encounter->id}", ['clinical_note' => 'Intento'])
            ->assertStatus(403)
            ->assertJsonStructure(['message']);

        // ...but can read it (single facility scope).
        $this->spa()->getJson("/api/v1/encounters/{$encounter->id}")->assertOk();
        $this->assertDatabaseCount('record_revisions', 0);
    }

    public function test_authorization_is_checked_before_validation(): void
    {
        $author = $this->nurse();
        $encounter = $this->encounterAt($this->patientBornOn('2025-01-01', $author), '2025-03-01 09:00', $author);
        $this->actingAsNurse();

        $this->spa()->patchJson("/api/v1/encounters/{$encounter->id}", ['weight_kg' => 'invalid'])
            ->assertStatus(403);
    }

    public function test_admin_can_modify_any_draft_encounter(): void
    {
        $author = $this->nurse();
        $encounter = $this->encounterAt($this->patientBornOn('2025-01-01', $author), '2025-03-01 09:00', $author);
        $this->actingAsNurse(User::factory()->admin()->create());

        $this->spa()->patchJson("/api/v1/encounters/{$encounter->id}", ['height_cm' => 60.5])
            ->assertOk()
            ->assertJsonPath('data.height_cm', 60.5);
    }

    public function test_reviewed_encounters_are_immutable(): void
    {
        $user = $this->actingAsNurse();
        $encounter = $this->encounterAt($this->patientBornOn('2025-01-01', $user), '2025-03-01 09:00', $user);
        app(EncounterService::class)->markReviewed($encounter, $user);

        $this->spa()->patchJson("/api/v1/encounters/{$encounter->id}", ['clinical_note' => 'Cambio'])
            ->assertStatus(403);
        $this->assertSame('reviewed', $encounter->fresh()->status->value);
        $this->assertDatabaseHas('audit_logs', ['event' => 'encounter.reviewed', 'auditable_id' => $encounter->id]);
    }

    public function test_encounter_cannot_be_reassigned_to_another_patient(): void
    {
        $user = $this->actingAsNurse();
        $encounter = $this->encounterAt($this->patientBornOn('2025-01-01', $user), '2025-03-01 09:00', $user);
        $other = $this->patientBornOn('2025-01-01', $user);

        $this->spa()->patchJson("/api/v1/encounters/{$encounter->id}", ['patient_id' => $other->id])
            ->assertStatus(422)->assertJsonValidationErrors('patient_id');

        // Sending the same patient_id is harmless.
        $this->spa()->patchJson("/api/v1/encounters/{$encounter->id}", ['patient_id' => $encounter->patient_id])
            ->assertOk();
    }

    public function test_update_of_date_is_revalidated_against_birth_date(): void
    {
        $user = $this->actingAsNurse();
        $encounter = $this->encounterAt($this->patientBornOn('2025-01-01', $user), '2025-03-01 09:00', $user);

        $this->spa()->patchJson("/api/v1/encounters/{$encounter->id}", ['occurred_at' => '2024-12-31T09:00:00-05:00'])
            ->assertStatus(422)->assertJsonValidationErrors('occurred_at');

        $this->spa()->patchJson("/api/v1/encounters/{$encounter->id}", ['occurred_at' => '2025-03-02T10:15:00-05:00'])
            ->assertOk()
            ->assertJsonPath('data.occurred_at', '2025-03-02T10:15:00-05:00');
    }

    public function test_optional_fields_can_be_cleared_with_null(): void
    {
        $user = $this->actingAsNurse();
        $encounter = $this->encounterAt($this->patientBornOn('2025-01-01', $user), '2025-03-01 09:00', $user, ['hb_g_dl' => '11.0']);

        $this->spa()->patchJson("/api/v1/encounters/{$encounter->id}", ['hb_g_dl' => null, 'clinical_note' => null])
            ->assertOk()
            ->assertJsonPath('data.hb_g_dl', null)
            ->assertJsonPath('data.clinical_note', null);
    }
}
